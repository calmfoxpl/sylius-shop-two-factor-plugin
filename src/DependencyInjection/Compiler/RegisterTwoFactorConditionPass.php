<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds the policy condition to scheb/2fa's condition registry instead of taking over the single
 * `two_factor_condition` option, so other packages (e.g. 2FA for administrators) can add theirs.
 */
final class RegisterTwoFactorConditionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('scheb_two_factor.condition_registry')) {
            return;
        }

        $registry = $container->getDefinition('scheb_two_factor.condition_registry');
        $conditions = $registry->getArgument(0);
        $values = $conditions instanceof IteratorArgument ? $conditions->getValues() : [];
        $values[] = new Reference('calmfox_shop_two_factor.condition');
        $registry->setArgument(0, new IteratorArgument($values));
    }
}
