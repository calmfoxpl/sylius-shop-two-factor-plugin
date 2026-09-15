<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\DependencyInjection;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('calmfox_sylius_shop_two_factor');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('default_policy')
                    ->info('Policy for customers until someone sets it in the panel (Configuration → Two-factor authentication for customers).')
                    ->values(TwoFactorPolicy::ALL)
                    ->defaultValue(TwoFactorPolicy::OPTIONAL)
                ->end()
                ->arrayNode('methods')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('passkey')->defaultTrue()->end()
                        ->booleanNode('totp')->defaultTrue()->end()
                        ->booleanNode('email')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('passkeys')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('rp_name')
                            ->info('Name shown by the device when creating the passkey.')
                            ->defaultValue('Sylius')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('rp_id')
                            ->info('Bare domain passkeys are bound to (e.g. shop.example.com). Null = host of the request. Changing it invalidates all paired passkeys.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('email_code_resend_interval')
                    ->info('Seconds a customer has to wait before asking for another e-mail code.')
                    ->defaultValue(60)
                    ->min(0)
                ->end()
                ->scalarNode('firewall')
                    ->info('Name of the shop firewall.')
                    ->defaultValue('shop')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
