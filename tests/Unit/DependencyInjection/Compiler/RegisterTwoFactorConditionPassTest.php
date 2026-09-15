<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\DependencyInjection\Compiler;

use Calmfox\SyliusShopTwoFactorPlugin\DependencyInjection\Compiler\RegisterTwoFactorConditionPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterTwoFactorConditionPassTest extends TestCase
{
    public function testItAddsThePolicyConditionNextToTheExistingOnes(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('scheb_two_factor.condition_registry', new Definition(\stdClass::class, [new IteratorArgument([new Reference('scheb.existing')])]));

        (new RegisterTwoFactorConditionPass())->process($container);

        $argument = $container->getDefinition('scheb_two_factor.condition_registry')->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $argument);
        self::assertSame(['scheb.existing', 'calmfox_shop_two_factor.condition'], array_map(static fn (mixed $reference): string => $reference instanceof Reference ? (string) $reference : '', $argument->getValues()));
    }

    public function testItDoesNothingWithoutTheBundle(): void
    {
        $container = new ContainerBuilder();

        (new RegisterTwoFactorConditionPass())->process($container);

        self::assertFalse($container->hasDefinition('scheb_two_factor.condition_registry'));
    }
}
