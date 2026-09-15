<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Functional;

use Tests\Calmfox\SyliusShopTwoFactorPlugin\Support\SoftwareAuthenticator;

final class AccountMethodsTest extends FunctionalTestCase
{
    public function testTheAccountOffersAllMethods(): void
    {
        $this->logIn($this->createCustomer());

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');

        self::assertResponseIsSuccessful();
        foreach (['passkey', 'totp', 'email'] as $method) {
            self::assertCount(1, $crawler->filter('.calmfox-shop-two-factor-method--' . $method), $method);
        }

        $dashboard = $this->client->request('GET', '/en_US/account/dashboard');
        self::assertCount(1, $dashboard->filter('a[href$="/en_US/account/two-factor"]'), 'the account menu links to the page');
    }

    public function testTheAuthenticatorAppIsTurnedOnOnlyWithACorrectCode(): void
    {
        $user = $this->createCustomer();
        $this->logIn($user);

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $secret = str_replace(' ', '', $crawler->filter('.calmfox-shop-two-factor-method--totp code')->text());
        self::assertNotSame('', $secret);

        $this->client->submit($crawler->filter('form[action$="/totp/enable"]')->form(['code' => '000000']));
        self::assertNull($this->reload($user)->getTotpSecret());

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/totp/enable"]')->form(['code' => self::totp($secret)]));

        self::assertSame($secret, $this->reload($user)->getTotpSecret());
    }

    public function testTheEmailCodeIsTurnedOnAfterProvingTheMailbox(): void
    {
        $user = $this->createCustomer();
        $this->logIn($user);

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/email/start"]')->form());
        self::assertEmailCount(1);
        $code = $this->codeFromLastEmail();
        self::assertFalse($this->reload($user)->isEmailAuthEnabled(), 'not on before the code is confirmed');

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/email/start"]')->form());
        self::assertEmailCount(0, null, 'a second code right away is throttled');

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/email/confirm"]')->form(['code' => $code]));

        $enabled = $this->reload($user);
        self::assertTrue($enabled->isEmailAuthEnabled());
        self::assertNull($enabled->getEmailAuthCode());
    }

    public function testAPasskeyIsAddedWithAVerifiedSignature(): void
    {
        $user = $this->createCustomer();
        $this->logIn($user);

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $csrf = (string) $crawler->filter('[data-calmfox-passkey-setup]')->attr('data-csrf');
        $response = $this->postJson('/en_US/account/two-factor/passkey/options', $csrf);
        self::assertIsArray($response['options'] ?? null);
        /** @var array<string, mixed> $options */
        $options = $response['options'];

        $result = $this->postJson('/en_US/account/two-factor/passkey', $csrf, ['credential' => (new SoftwareAuthenticator('localhost'))->register($options, 'http://localhost'), 'name' => 'Phone']);

        self::assertResponseIsSuccessful();
        self::assertSame('/en_US/account/two-factor', $result['redirect'] ?? null);
        self::assertTrue($this->reload($user)->hasPasskeys());
    }

    public function testTurningAMethodOffAsksForTheCurrentPassword(): void
    {
        $user = $this->createCustomer();
        $user->setTotpSecret(self::TOTP_SECRET);
        $this->entityManager()->flush();
        $this->client->loginUser($user, 'shop');

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/totp/remove"]')->form(['current_password' => 'wrong']));
        self::assertNotNull($this->reload($user)->getTotpSecret());

        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $this->client->submit($crawler->filter('form[action$="/totp/remove"]')->form(['current_password' => self::PASSWORD]));
        self::assertNull($this->reload($user)->getTotpSecret());
    }

    public function testChangesRequireTheCsrfToken(): void
    {
        $this->client->loginUser($this->createCustomer(), 'shop');

        $this->client->request('POST', '/en_US/account/two-factor/email/start', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertEmailCount(0);
    }
}
