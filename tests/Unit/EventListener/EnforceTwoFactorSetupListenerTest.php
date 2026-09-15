<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\EventListener;

use Calmfox\SyliusShopTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusShopTwoFactorPlugin\EventListener\EnforceTwoFactorSetupListener;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class EnforceTwoFactorSetupListenerTest extends TestCase
{
    public function testTheAccountAreaSendsToTheSecurityPage(): void
    {
        $event = $this->event('sylius_shop_account_dashboard');

        $this->listener()($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/two-factor', $response->getTargetUrl());
    }

    /** @return iterable<string, array{string}> */
    public static function shoppingRoutes(): iterable
    {
        yield 'homepage' => ['sylius_shop_homepage'];
        yield 'cart' => ['sylius_shop_cart_summary'];
        yield 'checkout' => ['sylius_shop_checkout_address'];
        yield 'the security page itself' => ['calmfox_shop_two_factor_account'];
    }

    #[DataProvider('shoppingRoutes')]
    public function testShoppingIsNeverBlocked(string $route): void
    {
        $event = $this->event($route);

        $this->listener()($event);

        self::assertNull($event->getResponse());
    }

    private function listener(): EnforceTwoFactorSetupListener
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new ShopUser(), 'shop'));

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(new FirewallConfig('shop', 'security.user_checker'));

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/account/two-factor');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new TwoFactorSettings(TwoFactorPolicy::REQUIRED));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new EnforceTwoFactorSetupListener($tokenStorage, $firewallMap, $router, new TwoFactorPolicy($entityManager, TwoFactorPolicy::OPTIONAL), 'shop');
    }

    private function event(string $route): RequestEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
