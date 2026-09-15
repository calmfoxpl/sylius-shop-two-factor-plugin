<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Login;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** The passkey endpoints are called with fetch(); the CSRF token comes in a header. */
final readonly class JsonCsrfGuard
{
    public const TOKEN_ID = 'calmfox_shop_two_factor_passkey';

    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function check(Request $request): void
    {
        $token = new CsrfToken(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }
}
