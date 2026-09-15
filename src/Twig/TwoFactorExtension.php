<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Twig;

use Calmfox\SyliusShopTwoFactorPlugin\Controller\Account\ManageAction;
use Calmfox\SyliusShopTwoFactorPlugin\Controller\Admin\ManageCustomerAction;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwoFactorExtension extends AbstractExtension
{
    public function __construct(private readonly TwoFactorPolicy $policy)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calmfox_shop_two_factor_policy', fn (): string => $this->policy->current()),
            new TwigFunction('calmfox_shop_two_factor_csrf_id', ManageAction::csrfTokenId(...)),
            new TwigFunction('calmfox_shop_two_factor_customer_csrf_id', ManageCustomerAction::csrfTokenId(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            // j***@example.com — enough to recognise the mailbox, not enough to read it off a screen
            new TwigFilter('calmfox_mask_email', static function (string $email): string {
                [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

                return mb_substr($local, 0, 1) . '***@' . $domain;
            }),
        ];
    }
}
