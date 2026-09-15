<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Functional;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\Support\SoftwareAuthenticator;

final class LoginTest extends FunctionalTestCase
{
    public function testWithoutMethodsNoSecondFactorIsAsked(): void
    {
        $this->logIn($this->createCustomer());

        $this->client->request('GET', '/en_US/account/dashboard');

        self::assertResponseIsSuccessful();
    }

    public function testTheAppCodeIsAskedAfterThePassword(): void
    {
        $user = $this->createCustomer();
        $user->setTotpSecret(self::TOTP_SECRET);
        $this->entityManager()->flush();
        $this->logIn($user);

        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseRedirects('/en_US/2fa');

        $this->client->followRedirect();
        $this->client->submitForm('Log in', ['_auth_code' => '000000']);
        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseRedirects('/en_US/2fa', null, 'a wrong code keeps the account locked');

        $this->client->request('GET', '/en_US/2fa');
        $this->client->submitForm('Log in', ['_auth_code' => self::totp(self::TOTP_SECRET)]);
        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testTheEmailCodeIsSentAndAccepted(): void
    {
        $user = $this->createCustomer();
        $user->setTotpSecret(self::TOTP_SECRET);
        $user->setEmailAuthEnabled(true);
        $this->entityManager()->flush();
        $this->logIn($user);

        $crawler = $this->client->request('GET', '/en_US/2fa');
        self::assertCount(1, $crawler->filter('a[href*="preferProvider=email"]'), 'the customer can switch to the e-mail code');

        $crawler = $this->client->request('GET', '/en_US/2fa?preferProvider=email');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('c***@example.com', $crawler->text(), 'the address is shown masked');
        $code = $this->codeFromLastEmail();

        $this->client->submitForm('Log in', ['_auth_code' => $code]);
        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testAPasskeyIsOfferedFirstAndLogsIn(): void
    {
        $user = $this->createCustomer();
        $authenticator = new SoftwareAuthenticator('localhost');
        $this->client->loginUser($user, 'shop');
        $crawler = $this->client->request('GET', '/en_US/account/two-factor');
        $csrf = (string) $crawler->filter('[data-calmfox-passkey-setup]')->attr('data-csrf');
        $registration = $this->postJson('/en_US/account/two-factor/passkey/options', $csrf);
        /** @var array<string, mixed> $options */
        $options = $registration['options'];
        $this->postJson('/en_US/account/two-factor/passkey', $csrf, ['credential' => $authenticator->register($options, 'http://localhost'), 'name' => 'Phone']);
        $this->logOut();

        $this->logIn($user);
        $crawler = $this->client->request('GET', '/en_US/2fa');
        self::assertCount(1, $crawler->filter('[data-calmfox-passkey-login]'));
        self::assertSelectorTextContains('[data-calmfox-passkey-login] [data-start]', 'Use a passkey');

        $login = $this->postJson('/en_US/2fa/passkey/options', (string) $crawler->filter('[data-calmfox-passkey-login]')->attr('data-csrf'));
        /** @var array<string, mixed> $loginOptions */
        $loginOptions = $login['options'];
        $form = $crawler->filter('[data-calmfox-passkey-login] form')->form();
        $form['_auth_code'] = json_encode($authenticator->login($loginOptions, 'http://localhost'), \JSON_THROW_ON_ERROR);
        $this->client->submit($form);

        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testTurnedOffPolicyDoesNotAskForTheSecondFactor(): void
    {
        $this->setPolicy(TwoFactorPolicy::DISABLED);
        $user = $this->createCustomer();
        $user->setTotpSecret(self::TOTP_SECRET);
        $this->entityManager()->flush();
        $this->logIn($user);

        $this->client->request('GET', '/en_US/account/dashboard');

        self::assertResponseIsSuccessful();
    }

    public function testARequiredPolicyLocksTheAccountAreaButNotShopping(): void
    {
        $this->setPolicy(TwoFactorPolicy::REQUIRED);
        $this->logIn($this->createCustomer());

        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseRedirects('/en_US/account/two-factor');

        $this->client->request('GET', '/en_US/account/two-factor');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/en_US/cart/');
        self::assertResponseIsSuccessful();
    }
}
