<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Account;

use Calmfox\SyliusShopTwoFactorPlugin\Controller\Login\JsonCsrfGuard;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyCeremony;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class PasskeyRegistrationOptionsAction
{
    public function __construct(
        private Security $security,
        private PasskeyCeremony $ceremony,
        private JsonCsrfGuard $csrfGuard,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->csrfGuard->check($request);

        $user = $this->security->getUser();
        if (!$user instanceof TwoFactorShopUserInterface) {
            throw new AccessDeniedException();
        }

        return new JsonResponse(['options' => $this->ceremony->registrationOptions($user, $request)]);
    }
}
