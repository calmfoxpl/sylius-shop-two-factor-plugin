<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/** My account → Login security, right after "Change password". */
final class AccountMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $menu
            ->addChild('calmfox_two_factor', ['route' => 'calmfox_shop_two_factor_account'])
            ->setLabel('calmfox_shop_two_factor.account.menu')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;

        $order = array_keys($menu->getChildren());
        $order = array_values(array_diff($order, ['calmfox_two_factor']));
        $position = array_search('change_password', $order, true);
        array_splice($order, false === $position ? count($order) : $position + 1, 0, ['calmfox_two_factor']);
        $menu->reorderChildren($order);
    }
}
