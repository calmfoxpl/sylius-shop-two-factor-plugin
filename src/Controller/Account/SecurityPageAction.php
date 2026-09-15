<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Account;

use Calmfox\SyliusShopTwoFactorPlugin\Email\EmailCodeThrottle;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Calmfox\SyliusShopTwoFactorPlugin\Totp\PendingTotpUser;
use Calmfox\SyliusShopTwoFactorPlugin\Totp\QrCodeRenderer;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * My account → Login security: the customer's passkeys, authenticator app and e-mail code,
 * each turned on and off on its own. A secret for a not yet paired app waits in the session.
 */
final readonly class SecurityPageAction
{
    public const TOTP_SESSION_KEY = 'calmfox_shop_two_factor.pending_totp_secret';

    public const EMAIL_PENDING_SESSION_KEY = 'calmfox_shop_two_factor.email_pending';

    /** @param array{passkey: bool, totp: bool, email: bool} $methods */
    public function __construct(
        private Security $security,
        private TwoFactorPolicy $policy,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private QrCodeRenderer $qrCodeRenderer,
        private EmailCodeThrottle $throttle,
        private Environment $twig,
        private array $methods,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof TwoFactorShopUserInterface) {
            throw new AccessDeniedException();
        }

        $session = $request->getSession();
        $qrCode = null;
        $secret = null;
        if ($this->methods['totp'] && !$user->isTotpAuthenticationEnabled()) {
            $secret = $session->get(self::TOTP_SESSION_KEY);
            if (!is_string($secret) || '' === $secret) {
                $secret = $this->totpAuthenticator->generateSecret();
                $session->set(self::TOTP_SESSION_KEY, $secret);
            }
            $qrCode = $this->qrCodeRenderer->dataUri($this->totpAuthenticator->getQRContent(new PendingTotpUser($user->getTotpAuthenticationUsername(), $secret)));
        }

        return new Response($this->twig->render('@CalmfoxSyliusShopTwoFactorPlugin/account/two_factor.html.twig', [
            'user' => $user,
            'policy' => $this->policy->current(),
            'mandatory' => $this->policy->mustSetUp($user),
            'methods' => $this->methods,
            'qrCode' => $qrCode,
            'secret' => null === $secret ? null : trim(chunk_split($secret, 4, ' ')),
            'emailPending' => true === $session->get(self::EMAIL_PENDING_SESSION_KEY),
            'emailResendIn' => $this->throttle->secondsLeft($request),
        ]));
    }
}
