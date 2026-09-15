<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Passkey;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Doctrine\Persistence\ObjectManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Passkey as a scheb/2fa provider for shop customers. The signed WebAuthn response travels in
 * the regular auth code field as JSON. The alias differs from other passkey providers (e.g. the
 * one for administrators), so both can be installed.
 */
final readonly class PasskeyTwoFactorProvider implements TwoFactorProviderInterface
{
    public const ALIAS = 'shop_passkey';

    public function __construct(
        private PasskeyCeremony $ceremony,
        private RequestStack $requestStack,
        private ObjectManager $userManager,
        private TwoFactorFormRendererInterface $formRenderer,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof TwoFactorShopUserInterface && $user->hasPasskeys();
    }

    public function prepareAuthentication(object $user): void
    {
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (!$user instanceof TwoFactorShopUserInterface || null === $request) {
            return false;
        }

        try {
            $decoded = json_decode($authenticationCode, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (!is_array($decoded)) {
            return false;
        }
        /** @var array<string, mixed> $response */
        $response = $decoded;

        try {
            $this->ceremony->verifyLogin($user, $request, $response);
        } catch (PasskeyException) {
            return false;
        }

        $this->userManager->flush();

        return true;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
