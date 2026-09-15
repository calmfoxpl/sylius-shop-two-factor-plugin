<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Model;

use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

/**
 * A shop customer's second factors: passkeys, an authenticator app (TOTP) and a code sent by
 * e-mail. The customer turns on any of them in their account; at login the most convenient one
 * is offered first and they can switch to another.
 */
interface TwoFactorShopUserInterface extends ShopUserInterface, TotpTwoFactorInterface, EmailTwoFactorInterface, PreferredProviderInterface
{
    public function getTotpSecret(): ?string;

    public function setTotpSecret(?string $totpSecret): void;

    /**
     * @return list<array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}>
     */
    public function getPasskeyCredentials(): array;

    /**
     * @param array<int, array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}> $passkeyCredentials
     */
    public function setPasskeyCredentials(array $passkeyCredentials): void;

    public function hasPasskeys(): bool;

    public function setEmailAuthEnabled(bool $emailAuthEnabled): void;

    public function clearEmailAuthCode(): void;

    /** Set when the shop staff reset the customer's 2FA: they must turn a method on again, whatever the policy. */
    public function isTwoFactorSetupRequired(): bool;

    public function setTwoFactorSetupRequired(bool $twoFactorSetupRequired): void;

    /** True once the customer has any second factor. */
    public function hasTwoFactorAuthentication(): bool;

    /** Removes every method (passkeys, app, e-mail code). */
    public function clearTwoFactorAuthentication(): void;
}
