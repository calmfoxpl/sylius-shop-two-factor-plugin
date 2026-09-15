<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Totp;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * Stands in for the customer while they are setting up the authenticator app, so the
 * secret can be shown and verified without touching the entity until the code is correct.
 */
final readonly class PendingTotpUser implements TwoFactorInterface
{
    public function __construct(private string $username, private string $secret)
    {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
