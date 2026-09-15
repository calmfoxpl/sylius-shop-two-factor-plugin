<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\EventListener;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * A customer who must use 2FA (policy "required", or reset in the panel) sees only the login
 * security page in their account until they turn a method on. Browsing, cart and checkout are
 * left alone — a customer is not stopped from buying.
 */
final readonly class EnforceTwoFactorSetupListener
{
    private const ACCOUNT_ROUTE_PREFIX = 'sylius_shop_account_';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private FirewallMap $firewallMap,
        private UrlGeneratorInterface $urlGenerator,
        private TwoFactorPolicy $policy,
        private string $firewallName,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $route = is_string($route) ? $route : '';
        if (!str_starts_with($route, self::ACCOUNT_ROUTE_PREFIX)) {
            return;
        }

        if ($this->firewallMap->getFirewallConfig($request)?->getName() !== $this->firewallName) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || $token instanceof TwoFactorTokenInterface) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof TwoFactorShopUserInterface || !$this->policy->mustSetUp($user)) {
            return;
        }

        if ($request->isXmlHttpRequest() || 'html' !== $request->getPreferredFormat()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('calmfox_shop_two_factor_account')));
    }
}
