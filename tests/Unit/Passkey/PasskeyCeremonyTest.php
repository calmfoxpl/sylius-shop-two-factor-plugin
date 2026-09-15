<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Passkey;

use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyCeremony;
use Calmfox\SyliusShopTwoFactorPlugin\Passkey\PasskeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\Support\SoftwareAuthenticator;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class PasskeyCeremonyTest extends TestCase
{
    private const ORIGIN = 'https://shop.example.com';

    private PasskeyCeremony $ceremony;

    private Request $request;

    private ShopUser $shopUser;

    protected function setUp(): void
    {
        $this->ceremony = new PasskeyCeremony('Test shop');
        $this->request = Request::create(self::ORIGIN . '/account/two-factor');
        $this->request->setSession(new Session(new MockArraySessionStorage()));

        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        $this->shopUser = new ShopUser();
        $this->shopUser->setCustomer($customer);
        (new \ReflectionProperty($this->shopUser, 'id'))->setValue($this->shopUser, 42);
    }

    public function testItPairsAPasskeyAndLogsInWithIt(): void
    {
        $authenticator = new SoftwareAuthenticator('shop.example.com');

        $options = $this->ceremony->registrationOptions($this->shopUser, $this->request);
        self::assertSame('shop.example.com', self::path($options, 'rp', 'id'));
        self::assertSame('required', self::path($options, 'authenticatorSelection', 'userVerification'));
        self::assertSame('none', $options['attestation'] ?? null);

        $this->ceremony->register($this->shopUser, $this->request, $authenticator->register($options, self::ORIGIN), 'Laptop');

        $credentials = $this->shopUser->getPasskeyCredentials();
        self::assertCount(1, $credentials);
        self::assertSame('Laptop', $credentials[0]['name']);
        self::assertSame(SoftwareAuthenticator::b64u($authenticator->credentialId), $credentials[0]['id']);
        self::assertStringContainsString('PUBLIC KEY', $credentials[0]['publicKey'], 'only the public key is stored');
        self::assertNull($credentials[0]['lastUsedAt']);

        $loginOptions = $this->ceremony->loginOptions($this->shopUser, $this->request);
        self::assertIsArray($loginOptions['allowCredentials'] ?? null);
        self::assertCount(1, $loginOptions['allowCredentials']);

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $authenticator->login($loginOptions, self::ORIGIN, userHandle: '42'));

        self::assertNotNull($this->shopUser->getPasskeyCredentials()[0]['lastUsedAt']);
    }

    /** @return iterable<string, array{string}> */
    public static function foreignOrigins(): iterable
    {
        yield 'look-alike domain' => ['https://evilshop.example.com'];
        yield 'another domain' => ['https://attacker.example'];
        yield 'plain http' => ['http://shop.example.com'];
    }

    #[DataProvider('foreignOrigins')]
    public function testItRejectsAPairingSignedForAnotherOrigin(string $origin): void
    {
        $options = $this->ceremony->registrationOptions($this->shopUser, $this->request);

        $this->expectException(PasskeyException::class);

        $this->ceremony->register($this->shopUser, $this->request, (new SoftwareAuthenticator('shop.example.com'))->register($options, $origin), 'Laptop');
    }

    public function testASubdomainOfTheRelyingPartyIsAccepted(): void
    {
        $options = $this->ceremony->registrationOptions($this->shopUser, $this->request);

        $this->ceremony->register($this->shopUser, $this->request, (new SoftwareAuthenticator('shop.example.com'))->register($options, 'https://admin.shop.example.com'), 'Laptop');

        self::assertTrue($this->shopUser->hasPasskeys());
    }

    public function testItRequiresUserVerification(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);

        $this->expectException(PasskeyException::class);

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $authenticator->login($options, self::ORIGIN, SoftwareAuthenticator::USER_PRESENT));
    }

    public function testAKeyOfAnotherAccountDoesNotLogIn(): void
    {
        $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);

        $this->expectException(PasskeyException::class);

        $this->ceremony->verifyLogin($this->shopUser, $this->request, (new SoftwareAuthenticator('shop.example.com'))->login($options, self::ORIGIN));
    }

    public function testAUserHandleOfAnotherAccountIsRejected(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);

        $this->expectException(PasskeyException::class);

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $authenticator->login($options, self::ORIGIN, userHandle: '7'));
    }

    public function testAForgedSignatureIsRejected(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);
        $response = $authenticator->login($options, self::ORIGIN);
        \assert(is_array($response['response']));
        $response['response']['signature'] = SoftwareAuthenticator::b64u(random_bytes(70));

        $this->expectException(PasskeyException::class);

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $response);
    }

    public function testAChallengeCanBeUsedOnlyOnce(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);
        $response = $authenticator->login($options, self::ORIGIN);
        $this->ceremony->verifyLogin($this->shopUser, $this->request, $response);

        $this->expectExceptionObject(PasskeyException::challengeGone());

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $response);
    }

    public function testAFailedAttemptBurnsTheChallenge(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->loginOptions($this->shopUser, $this->request);

        try {
            $this->ceremony->verifyLogin($this->shopUser, $this->request, $authenticator->login($options, 'https://attacker.example'));
            self::fail('The foreign origin should have been rejected.');
        } catch (PasskeyException) {
        }

        $this->expectExceptionObject(PasskeyException::challengeGone());

        $this->ceremony->verifyLogin($this->shopUser, $this->request, $authenticator->login($options, self::ORIGIN));
    }

    public function testTheSameDeviceCannotBePairedTwice(): void
    {
        $authenticator = $this->paired();
        $options = $this->ceremony->registrationOptions($this->shopUser, $this->request);
        self::assertIsArray($options['excludeCredentials'] ?? null);
        self::assertCount(1, $options['excludeCredentials'], 'the device is told which key it already has');

        $this->expectExceptionObject(PasskeyException::alreadyPaired());

        $this->ceremony->register($this->shopUser, $this->request, $authenticator->register($options, self::ORIGIN), 'Again');
    }

    public function testPlainHttpIsAcceptedOnLocalhostOnly(): void
    {
        $request = Request::create('http://localhost/account/two-factor');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $options = $this->ceremony->registrationOptions($this->shopUser, $request);

        $this->ceremony->register($this->shopUser, $request, (new SoftwareAuthenticator('localhost'))->register($options, 'http://localhost'), 'Dev');

        self::assertTrue($this->shopUser->hasPasskeys());
    }

    public function testAConfiguredRelyingPartyIdWinsOverTheRequestHost(): void
    {
        $ceremony = new PasskeyCeremony('Test shop', 'example.com');

        $options = $ceremony->registrationOptions($this->shopUser, $this->request);

        self::assertSame('example.com', self::path($options, 'rp', 'id'));
    }

    private function paired(): SoftwareAuthenticator
    {
        $authenticator = new SoftwareAuthenticator('shop.example.com');
        $options = $this->ceremony->registrationOptions($this->shopUser, $this->request);
        $this->ceremony->register($this->shopUser, $this->request, $authenticator->register($options, self::ORIGIN), 'Laptop');

        return $authenticator;
    }

    /** @param array<string, mixed> $options */
    private static function path(array $options, string $section, string $key): mixed
    {
        $values = $options[$section] ?? null;
        self::assertIsArray($values);

        return $values[$key] ?? null;
    }
}
