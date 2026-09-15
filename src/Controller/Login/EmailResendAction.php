<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Login;

use Calmfox\SyliusShopTwoFactorPlugin\Email\EmailCodeThrottle;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** "Send me a new code" on the login code form: a fresh code replaces the previous one. */
final readonly class EmailResendAction
{
    public const CSRF_TOKEN_ID = 'calmfox_shop_two_factor_email_resend';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CodeGeneratorInterface $codeGenerator,
        private EmailCodeThrottle $throttle,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$token instanceof TwoFactorTokenInterface || !$user instanceof TwoFactorShopUserInterface || !$user->isEmailAuthEnabled()) {
            throw new AccessDeniedException();
        }

        $session = $request->getSession();
        $secondsLeft = $this->throttle->secondsLeft($request);
        if (0 === $secondsLeft) {
            $this->codeGenerator->generateAndSend($user);
            $this->throttle->markSent($request);
        }

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(0 === $secondsLeft ? 'success' : 'info', 0 === $secondsLeft
                ? 'calmfox_shop_two_factor.email.code_resent'
                : ['message' => 'calmfox_shop_two_factor.email.wait', 'parameters' => ['%seconds%' => $secondsLeft]]);
        }

        return new RedirectResponse($this->urlGenerator->generate('calmfox_shop_two_factor_login', ['preferProvider' => 'email']));
    }
}
