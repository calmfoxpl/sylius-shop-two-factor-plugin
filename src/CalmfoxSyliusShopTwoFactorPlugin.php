<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin;

use Calmfox\SyliusShopTwoFactorPlugin\DependencyInjection\Compiler\RegisterTwoFactorConditionPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CalmfoxSyliusShopTwoFactorPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterTwoFactorConditionPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
