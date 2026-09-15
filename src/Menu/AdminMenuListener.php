<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $event->getMenu()->getChild('configuration')
            ?->addChild('calmfox_shop_two_factor', ['route' => 'calmfox_shop_two_factor_admin_settings'])
            ->setLabel('calmfox_shop_two_factor.settings.title')
            ->setLabelAttribute('icon', 'tabler:user-shield')
        ;
    }
}
