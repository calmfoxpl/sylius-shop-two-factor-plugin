<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CalmfoxSyliusShopTwoFactorExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{default_policy: string, methods: array{passkey: bool, totp: bool, email: bool}, passkeys: array{rp_name: string, rp_id: string|null}, email_code_resend_interval: int, firewall: string} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('calmfox_sylius_shop_two_factor.default_policy', $config['default_policy']);
        $container->setParameter('calmfox_sylius_shop_two_factor.methods', $config['methods']);
        $container->setParameter('calmfox_sylius_shop_two_factor.passkeys.rp_name', $config['passkeys']['rp_name']);
        $container->setParameter('calmfox_sylius_shop_two_factor.passkeys.rp_id', $config['passkeys']['rp_id']);
        $container->setParameter('calmfox_sylius_shop_two_factor.email_code_resend_interval', $config['email_code_resend_interval']);
        $container->setParameter('calmfox_sylius_shop_two_factor.firewall', $config['firewall']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        if (!$config['methods']['passkey']) {
            $container->removeDefinition('calmfox_shop_two_factor.passkey.provider');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'CalmfoxSyliusShopTwoFactorPlugin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'Calmfox\SyliusShopTwoFactorPlugin\Entity',
                        'alias' => 'CalmfoxSyliusShopTwoFactorPlugin',
                    ],
                ],
            ],
        ]);
    }
}
