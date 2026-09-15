<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Policy;

use Calmfox\SyliusShopTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Which shop customers have to use two-factor authentication:
 *
 *  - required: every customer; without a method their account area shows only the security page
 *    (shopping and checkout keep working),
 *  - optional: whoever turns on a method, plus customers whose 2FA was reset in the panel,
 *  - disabled: nobody; methods stay on the accounts but are not asked for.
 */
final class TwoFactorPolicy
{
    public const REQUIRED = 'required';

    public const OPTIONAL = 'optional';

    public const DISABLED = 'disabled';

    public const ALL = [self::REQUIRED, self::OPTIONAL, self::DISABLED];

    private ?string $current = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $defaultPolicy,
    ) {
    }

    public function current(): string
    {
        if (null !== $this->current) {
            return $this->current;
        }

        try {
            $settings = $this->settings();
        } catch (\Throwable) {
            // table not migrated yet: fall back to the configured default instead of breaking the login
            return $this->defaultPolicy;
        }

        return $this->current = $settings?->getPolicy() ?? $this->defaultPolicy;
    }

    public function change(string $policy): void
    {
        if (!in_array($policy, self::ALL, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown two-factor policy "%s".', $policy));
        }

        $settings = $this->settings();
        if (null === $settings) {
            $settings = new TwoFactorSettings($policy);
            $this->entityManager->persist($settings);
        } else {
            $settings->setPolicy($policy);
        }

        $this->entityManager->flush();
        $this->current = $policy;
    }

    public function isActive(): bool
    {
        return self::DISABLED !== $this->current();
    }

    /** Must this customer pair a method before using the panel? */
    public function mustSetUp(TwoFactorShopUserInterface $shopUser): bool
    {
        if (!$this->isActive() || $shopUser->hasTwoFactorAuthentication()) {
            return false;
        }

        return self::REQUIRED === $this->current() || $shopUser->isTwoFactorSetupRequired();
    }

    private function settings(): ?TwoFactorSettings
    {
        return $this->entityManager->getRepository(TwoFactorSettings::class)->findOneBy([], ['id' => 'ASC']);
    }
}
