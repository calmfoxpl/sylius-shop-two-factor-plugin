<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Model;

use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyTwoFactorProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class TwoFactorShopUserTraitTest extends TestCase
{
    private const PASSKEY = ['id' => 'a', 'name' => 'Phone', 'publicKey' => 'pem', 'signCount' => 0, 'createdAt' => '2026-01-01T00:00:00+00:00', 'lastUsedAt' => null];

    public function testTheMostConvenientMethodIsPreferred(): void
    {
        $user = $this->user();
        self::assertNull($user->getPreferredTwoFactorProvider());

        $user->setEmailAuthEnabled(true);
        self::assertSame('email', $user->getPreferredTwoFactorProvider());

        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        self::assertSame('totp', $user->getPreferredTwoFactorProvider());

        $user->setPasskeyCredentials([self::PASSKEY]);
        self::assertSame(PasskeyTwoFactorProvider::ALIAS, $user->getPreferredTwoFactorProvider());
    }

    public function testCodesAreSentToTheCustomersAddress(): void
    {
        self::assertSame('customer@example.com', $this->user()->getEmailAuthRecipient());
    }

    public function testTurningEmailCodesOffForgetsAPendingCode(): void
    {
        $user = $this->user();
        $user->setEmailAuthEnabled(true);
        $user->setEmailAuthCode('123456');

        $user->setEmailAuthEnabled(false);

        self::assertNull($user->getEmailAuthCode());
    }

    public function testClearingRemovesEveryMethod(): void
    {
        $user = $this->user();
        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setPasskeyCredentials([self::PASSKEY]);
        $user->setEmailAuthEnabled(true);

        $user->clearTwoFactorAuthentication();

        self::assertFalse($user->hasTwoFactorAuthentication());
        self::assertSame([], $user->getPasskeyCredentials());
    }

    private function user(): ShopUser
    {
        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        $user = new ShopUser();
        $user->setCustomer($customer);

        return $user;
    }
}
