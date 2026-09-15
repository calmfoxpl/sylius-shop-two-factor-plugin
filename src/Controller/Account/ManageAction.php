<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Account;

use Calmfox\SyliusShopTwoFactorPlugin\Email\EmailCodeThrottle;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Totp\PendingTotpUser;
use Doctrine\Persistence\ObjectManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Turning methods on and off in My account. Turning one on is proven by using it (a code from
 * the app, a code from the mailbox); turning one off asks for the current password, so an
 * unattended logged-in browser is not enough to weaken the account.
 */
final readonly class ManageAction
{
    private const OPERATIONS = [
        'totp' => ['enable', 'remove'],
        'email' => ['start', 'confirm', 'remove'],
        'passkey' => ['remove'],
    ];

    /** @param array{passkey: bool, totp: bool, email: bool} $methods */
    public function __construct(
        private Security $security,
        private ObjectManager $userManager,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private CodeGeneratorInterface $emailCodeGenerator,
        private EmailCodeThrottle $throttle,
        private UserPasswordHasherInterface $passwordHasher,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private array $methods,
    ) {
    }

    public function __invoke(Request $request, string $method, string $operation): Response
    {
        if (!in_array($operation, self::OPERATIONS[$method] ?? [], true) || !($this->methods[$method] ?? false)) {
            throw new NotFoundHttpException();
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId($method, $operation), $request->request->getString('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof TwoFactorShopUserInterface) {
            throw new AccessDeniedException();
        }

        if ('remove' === $operation && !$this->passwordHasher->isPasswordValid($user, $request->request->getString('current_password'))) {
            return $this->back($request, 'error', 'calmfox_shop_two_factor.account.wrong_password');
        }

        return match ($method . '/' . $operation) {
            'totp/enable' => $this->enableTotp($request, $user),
            'totp/remove' => $this->done($request, $user, static fn () => $user->setTotpSecret(null), 'calmfox_shop_two_factor.account.totp_removed'),
            'email/start' => $this->startEmail($request, $user),
            'email/confirm' => $this->confirmEmail($request, $user),
            'email/remove' => $this->done($request, $user, static fn () => $user->setEmailAuthEnabled(false), 'calmfox_shop_two_factor.account.email_removed'),
            'passkey/remove' => $this->removePasskey($request, $user),
            default => throw new NotFoundHttpException(),
        };
    }

    public static function csrfTokenId(string $method, string $operation): string
    {
        return sprintf('calmfox_shop_two_factor_%s_%s', $method, $operation);
    }

    private function enableTotp(Request $request, TwoFactorShopUserInterface $user): Response
    {
        $secret = $request->getSession()->get(SecurityPageAction::TOTP_SESSION_KEY);
        $code = preg_replace('/\s+/', '', $request->request->getString('code'));

        if (!is_string($secret) || !$this->totpAuthenticator->checkCode(new PendingTotpUser($user->getTotpAuthenticationUsername(), $secret), (string) $code)) {
            return $this->back($request, 'error', 'calmfox_shop_two_factor.account.wrong_code');
        }

        $request->getSession()->remove(SecurityPageAction::TOTP_SESSION_KEY);

        return $this->done($request, $user, static fn () => $user->setTotpSecret($secret), 'calmfox_shop_two_factor.account.totp_enabled');
    }

    private function startEmail(Request $request, TwoFactorShopUserInterface $user): Response
    {
        $secondsLeft = $this->throttle->secondsLeft($request);
        if ($secondsLeft > 0) {
            return $this->back($request, 'info', ['message' => 'calmfox_shop_two_factor.email.wait', 'parameters' => ['%seconds%' => $secondsLeft]]);
        }

        // the code proves the customer can read this mailbox before it becomes a way into the account
        $this->emailCodeGenerator->generateAndSend($user);
        $this->throttle->markSent($request);
        $request->getSession()->set(SecurityPageAction::EMAIL_PENDING_SESSION_KEY, true);

        return $this->back($request, 'success', ['message' => 'calmfox_shop_two_factor.account.email_code_sent', 'parameters' => ['%email%' => $user->getEmailAuthRecipient()]]);
    }

    private function confirmEmail(Request $request, TwoFactorShopUserInterface $user): Response
    {
        $expected = $user->getEmailAuthCode();
        $code = preg_replace('/\s+/', '', $request->request->getString('code'));

        if (null === $expected || !hash_equals($expected, (string) $code)) {
            return $this->back($request, 'error', 'calmfox_shop_two_factor.account.wrong_code');
        }

        $request->getSession()->remove(SecurityPageAction::EMAIL_PENDING_SESSION_KEY);

        return $this->done($request, $user, static function () use ($user): void {
            $user->setEmailAuthEnabled(true);
            $user->clearEmailAuthCode();
        }, 'calmfox_shop_two_factor.account.email_enabled');
    }

    private function removePasskey(Request $request, TwoFactorShopUserInterface $user): Response
    {
        $id = $request->request->getString('id');

        return $this->done($request, $user, static fn () => $user->setPasskeyCredentials(array_values(array_filter(
            $user->getPasskeyCredentials(),
            static fn (array $credential): bool => $credential['id'] !== $id,
        ))), 'calmfox_shop_two_factor.account.passkey_removed');
    }

    private function done(Request $request, TwoFactorShopUserInterface $user, \Closure $change, string $message): Response
    {
        $change();
        if ($user->hasTwoFactorAuthentication()) {
            $user->setTwoFactorSetupRequired(false);
        }
        $this->userManager->flush();

        return $this->back($request, 'success', $message);
    }

    /** @param string|array{message: string, parameters: array<string, mixed>} $message */
    private function back(Request $request, string $type, string|array $message): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('calmfox_shop_two_factor_account'));
    }
}
