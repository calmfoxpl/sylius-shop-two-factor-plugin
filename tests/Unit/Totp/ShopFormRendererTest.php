<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Totp;

use Calmfox\SyliusShopTwoFactorPlugin\Totp\ShopFormRenderer;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ShopFormRendererTest extends TestCase
{
    public function testCustomersGetTheShopTemplate(): void
    {
        $inner = $this->createMock(TwoFactorFormRendererInterface::class);
        $inner->expects(self::never())->method('renderForm');

        self::assertSame('shop form', $this->renderer($inner, new ShopUser())->renderForm(new Request(), [])->getContent());
    }

    public function testOtherUsersGetTheDecoratedRenderer(): void
    {
        $inner = $this->createMock(TwoFactorFormRendererInterface::class);
        $inner->expects(self::once())->method('renderForm')->willReturn(new Response('admin form'));

        self::assertSame('admin form', $this->renderer($inner, new AdminUser())->renderForm(new Request(), [])->getContent());
    }

    public function testWithoutADecoratedRendererItAlwaysRendersTheShopTemplate(): void
    {
        self::assertSame('shop form', $this->renderer(null, new AdminUser())->renderForm(new Request(), [])->getContent());
    }

    private function renderer(?TwoFactorFormRendererInterface $inner, UserInterface $user): ShopFormRenderer
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        return new ShopFormRenderer($inner, $tokenStorage, new Environment(new ArrayLoader(['shop.html.twig' => 'shop form'])), 'shop.html.twig');
    }
}
