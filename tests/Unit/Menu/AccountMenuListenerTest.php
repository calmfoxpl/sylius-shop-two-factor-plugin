<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Menu;

use Calmfox\SyliusShopTwoFactorPlugin\Menu\AccountMenuListener;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AccountMenuListenerTest extends TestCase
{
    public function testLoginSecurityFollowsChangePassword(): void
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        foreach (['dashboard', 'personal_information', 'change_password', 'address_book'] as $name) {
            $menu->addChild($name);
        }

        (new AccountMenuListener())(new MenuBuilderEvent($factory, $menu));

        self::assertSame(['dashboard', 'personal_information', 'change_password', 'calmfox_two_factor', 'address_book'], array_keys($menu->getChildren()));
    }

    public function testWithoutChangePasswordItGoesLast(): void
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('dashboard');

        (new AccountMenuListener())(new MenuBuilderEvent($factory, $menu));

        self::assertSame(['dashboard', 'calmfox_two_factor'], array_keys($menu->getChildren()));
    }
}
