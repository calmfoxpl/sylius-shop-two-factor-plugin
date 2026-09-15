<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Account;

use Calmfox\SyliusShopTwoFactorPlugin\Controller\Login\JsonCsrfGuard;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyCeremony;
use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PasskeyRegisterAction
{
    public function __construct(
        private Security $security,
        private PasskeyCeremony $ceremony,
        private JsonCsrfGuard $csrfGuard,
        private ObjectManager $userManager,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->csrfGuard->check($request);

        $user = $this->security->getUser();
        if (!$user instanceof TwoFactorShopUserInterface) {
            throw new AccessDeniedException();
        }

        $payload = $request->toArray();
        $name = $payload['name'] ?? null;

        try {
            if (!is_array($payload['credential'] ?? null)) {
                throw PasskeyException::rejected();
            }
            /** @var array<string, mixed> $credential */
            $credential = $payload['credential'];
            $this->ceremony->register($user, $request, $credential, is_string($name) ? $name : '');
        } catch (PasskeyException $exception) {
            return new JsonResponse(
                ['error' => $this->translator->trans($exception->getMessage(), [], 'validators')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setTwoFactorSetupRequired(false);
        $this->userManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'calmfox_shop_two_factor.account.passkey_added');
        }

        return new JsonResponse(['redirect' => $this->urlGenerator->generate('calmfox_shop_two_factor_account')]);
    }
}
