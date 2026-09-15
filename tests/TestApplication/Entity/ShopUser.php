<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUser as BaseShopUser;

/** The test application's customer account, set up the way the README tells applications to. */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_shop_user')]
class ShopUser extends BaseShopUser implements TwoFactorShopUserInterface
{
    use TwoFactorShopUserTrait;
}
