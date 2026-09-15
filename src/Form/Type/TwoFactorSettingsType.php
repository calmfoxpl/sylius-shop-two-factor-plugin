<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Form\Type;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<array{policy: string}> */
final class TwoFactorSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('policy', ChoiceType::class, [
            'label' => 'calmfox_shop_two_factor.settings.policy',
            'expanded' => true,
            'choices' => array_combine(
                array_map(static fn (string $policy): string => 'calmfox_shop_two_factor.settings.policy_' . $policy, TwoFactorPolicy::ALL),
                TwoFactorPolicy::ALL,
            ),
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'calmfox_shop_two_factor_settings';
    }
}
