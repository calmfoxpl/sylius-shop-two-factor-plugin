<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Functional;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class PanelTest extends FunctionalTestCase
{
    public function testThePolicyIsSetInConfiguration(): void
    {
        $this->logInAsAdmin();

        $crawler = $this->client->request('GET', '/admin/customer-two-factor/settings');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="calmfox_shop_two_factor_settings"]')->form();
        $form['calmfox_shop_two_factor_settings[policy]'] = TwoFactorPolicy::REQUIRED;
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/customer-two-factor/settings');
        self::assertSame(TwoFactorPolicy::REQUIRED, $this->service('calmfox_shop_two_factor.policy', TwoFactorPolicy::class)->current());
    }

    public function testResetFromTheCustomerPageRequiresTurningAMethodOnAgain(): void
    {
        $user = $this->customerWithApp();
        $this->logInAsAdmin();

        $crawler = $this->client->request('GET', '/admin/customers/' . $this->customerId($user));
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action$="/two-factor/reset"]')->form());

        $reset = $this->reload($user);
        self::assertFalse($reset->hasTwoFactorAuthentication());
        self::assertTrue($reset->isTwoFactorSetupRequired());

        $this->client->getCookieJar()->clear();
        $this->logIn($reset);
        $this->client->request('GET', '/en_US/account/dashboard');
        self::assertResponseRedirects('/en_US/account/two-factor', null, 'even with an optional policy');
    }

    public function testTurningOffFromTheCustomerPageLeavesTheRestToThePolicy(): void
    {
        $user = $this->customerWithApp();
        $this->logInAsAdmin();

        $crawler = $this->client->request('GET', '/admin/customers/' . $this->customerId($user));
        $this->client->submit($crawler->filter('form[action$="/two-factor/disable"]')->form());

        $disabled = $this->reload($user);
        self::assertFalse($disabled->hasTwoFactorAuthentication());
        self::assertFalse($disabled->isTwoFactorSetupRequired());
    }

    public function testManagingACustomerRequiresTheCsrfToken(): void
    {
        $user = $this->customerWithApp();
        $this->logInAsAdmin();

        $this->client->request('POST', '/admin/customers/' . $this->customerId($user) . '/two-factor/reset', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->reload($user)->hasTwoFactorAuthentication());
    }

    public function testTheConsoleCommandResetsAndDisables(): void
    {
        $user = $this->customerWithApp();
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find('calmfox:shop:2fa:reset'));

        $tester->execute(['email' => $user->getEmail(), '--disable' => true]);
        self::assertFalse($this->reload($user)->isTwoFactorSetupRequired());

        $tester->execute(['email' => $user->getEmail()]);
        self::assertTrue($this->reload($user)->isTwoFactorSetupRequired());
    }

    private function customerWithApp(): ShopUser
    {
        $user = $this->createCustomer();
        $user->setTotpSecret(self::TOTP_SECRET);
        $this->entityManager()->flush();

        return $user;
    }

    private function customerId(ShopUser $user): string
    {
        $id = $user->getCustomer()?->getId();
        self::assertIsInt($id);

        return (string) $id;
    }

    private function logInAsAdmin(): void
    {
        /** @var FactoryInterface<AdminUserInterface> $factory */
        $factory = $this->service('sylius.factory.admin_user', FactoryInterface::class);
        $admin = $factory->createNew();
        $email = sprintf('admin.%s@example.com', bin2hex(random_bytes(4)));
        $admin->setEmail($email);
        $admin->setUsername($email);
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        $admin->setPlainPassword(self::PASSWORD);
        $this->entityManager()->persist($admin);
        $this->entityManager()->flush();

        $this->client->loginUser($admin, 'admin');
    }
}
