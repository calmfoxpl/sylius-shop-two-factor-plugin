<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Model;

use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyTwoFactorProvider;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

/**
 * Use in your ShopUser entity together with TwoFactorShopUserInterface. Adds the TOTP secret,
 * the list of passkeys (public keys only), the e-mail code switch and the current code, and the
 * "turn a method on again" flag.
 */
trait TwoFactorShopUserTrait
{
    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    protected ?string $totpSecret = null;

    /** @var list<array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}>|null */
    #[ORM\Column(name: 'passkey_credentials', type: 'json', nullable: true)]
    protected ?array $passkeyCredentials = null;

    #[ORM\Column(name: 'email_auth_enabled', type: 'boolean', options: ['default' => false])]
    protected bool $emailAuthEnabled = false;

    #[ORM\Column(name: 'email_auth_code', type: 'string', length: 16, nullable: true)]
    protected ?string $emailAuthCode = null;

    #[ORM\Column(name: 'two_factor_setup_required', type: 'boolean', options: ['default' => false])]
    protected bool $twoFactorSetupRequired = false;

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return (string) $this->getEmail();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return null === $this->totpSecret
            ? null
            : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getPasskeyCredentials(): array
    {
        return $this->passkeyCredentials ?? [];
    }

    /** @param array<int, array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}> $passkeyCredentials */
    public function setPasskeyCredentials(array $passkeyCredentials): void
    {
        $this->passkeyCredentials = [] === $passkeyCredentials ? null : array_values($passkeyCredentials);
    }

    public function hasPasskeys(): bool
    {
        return [] !== $this->getPasskeyCredentials();
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->emailAuthEnabled;
    }

    public function setEmailAuthEnabled(bool $emailAuthEnabled): void
    {
        $this->emailAuthEnabled = $emailAuthEnabled;
        if (!$emailAuthEnabled) {
            $this->emailAuthCode = null;
        }
    }

    public function getEmailAuthRecipient(): string
    {
        return (string) $this->getEmail();
    }

    public function getEmailAuthCode(): ?string
    {
        return $this->emailAuthCode;
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->emailAuthCode = $authCode;
    }

    public function clearEmailAuthCode(): void
    {
        $this->emailAuthCode = null;
    }

    public function isTwoFactorSetupRequired(): bool
    {
        return $this->twoFactorSetupRequired;
    }

    public function setTwoFactorSetupRequired(bool $twoFactorSetupRequired): void
    {
        $this->twoFactorSetupRequired = $twoFactorSetupRequired;
    }

    public function hasTwoFactorAuthentication(): bool
    {
        return $this->isTotpAuthenticationEnabled() || $this->hasPasskeys() || $this->emailAuthEnabled;
    }

    public function clearTwoFactorAuthentication(): void
    {
        $this->totpSecret = null;
        $this->passkeyCredentials = null;
        $this->emailAuthEnabled = false;
        $this->emailAuthCode = null;
    }

    /** One gesture beats retyping digits, and an app beats waiting for an e-mail. */
    public function getPreferredTwoFactorProvider(): ?string
    {
        return match (true) {
            $this->hasPasskeys() => PasskeyTwoFactorProvider::ALIAS,
            $this->isTotpAuthenticationEnabled() => 'totp',
            $this->emailAuthEnabled => 'email',
            default => null,
        };
    }
}
