<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Controller\Admin;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A customer's 2FA from their page in the panel (e.g. after they lost their phone):
 *
 *  - reset: removes all methods and makes them turn one on again, whatever the policy,
 *  - disable: removes all methods; the policy decides what happens next.
 */
final readonly class ManageCustomerAction
{
    public const RESET = 'reset';

    public const DISABLE = 'disable';

    /** @param RepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        private RepositoryInterface $customerRepository,
        private ObjectManager $userManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, int|string $id, string $operation): Response
    {
        if (!in_array($operation, [self::RESET, self::DISABLE], true)) {
            throw new NotFoundHttpException();
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId($operation, $id), (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $customer = $this->customerRepository->find($id);
        $user = $customer instanceof CustomerInterface ? $customer->getUser() : null;
        if (!$user instanceof TwoFactorShopUserInterface) {
            throw new NotFoundHttpException();
        }

        $user->clearTwoFactorAuthentication();
        $user->setTwoFactorSetupRequired(self::RESET === $operation);
        $this->userManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', [
                'message' => 'calmfox_shop_two_factor.manage.' . $operation . '_done',
                'parameters' => ['%email%' => $customer->getEmail()],
            ]);
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_customer_show', ['id' => $customer->getId()]));
    }

    public static function csrfTokenId(string $operation, int|string $id): string
    {
        return 'calmfox_shop_two_factor_customer_' . $operation . $id;
    }
}
