<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Functional;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

abstract class FunctionalTestCase extends WebTestCase
{
    protected const PASSWORD = 'kmv53p!L8za7KgjZh_AF';

    protected const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->ensureChannel();
        $this->setPolicy(TwoFactorPolicy::OPTIONAL);
    }

    protected function setPolicy(string $policy): void
    {
        $this->service('calmfox_shop_two_factor.policy', TwoFactorPolicy::class)->change($policy);
    }

    protected function createCustomer(): ShopUser
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = $this->service('sylius.factory.customer', FactoryInterface::class);
        /** @var FactoryInterface<ShopUser> $userFactory */
        $userFactory = $this->service('sylius.factory.shop_user', FactoryInterface::class);

        $email = sprintf('customer.%s@example.com', bin2hex(random_bytes(4)));
        $customer = $customerFactory->createNew();
        $customer->setEmail($email);
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');

        $user = $userFactory->createNew();
        $user->setCustomer($customer);
        $user->setUsername($email);
        $user->setPlainPassword(self::PASSWORD);
        $user->setEnabled(true);
        $user->setVerifiedAt(new \DateTime());

        $this->entityManager()->persist($customer);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    /** Logs in through the shop's login form, so two-factor authentication kicks in. */
    protected function logIn(ShopUser $user): void
    {
        $this->client->request('GET', '/en_US/login');
        $this->client->submitForm('Login', ['_username' => (string) $user->getEmail(), '_password' => self::PASSWORD]);
    }

    protected function logOut(): void
    {
        $this->client->request('GET', '/en_US/logout');
        $this->client->getCookieJar()->clear();
    }

    protected function reload(ShopUser $user): ShopUser
    {
        $this->entityManager()->clear();
        /** @var UserRepositoryInterface<ShopUser> $repository */
        $repository = $this->service('sylius.repository.shop_user', UserRepositoryInterface::class);
        $fresh = $repository->find($user->getId());
        self::assertInstanceOf(ShopUser::class, $fresh);

        return $fresh;
    }

    /** @param non-empty-string $secret */
    protected static function totp(string $secret): string
    {
        if (time() % 30 > 27) {
            sleep(3);
        }

        return TOTP::createFromSecret($secret)->now();
    }

    protected function lastEmail(): Email
    {
        $messages = self::getMailerMessages();
        self::assertNotEmpty($messages, 'No e-mail was sent.');
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    protected function codeFromLastEmail(): string
    {
        self::assertSame(1, preg_match('/(?<!\d)(\d{6})(?!\d)/', strip_tags((string) $this->lastEmail()->getHtmlBody()), $matches));

        return $matches[1];
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function postJson(string $path, string $csrf, array $body = []): array
    {
        $this->client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($body, \JSON_THROW_ON_ERROR));
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }

    /** The shop needs a channel for localhost, with a locale and a currency. */
    private function ensureChannel(): void
    {
        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = $this->service('sylius.repository.channel', RepositoryInterface::class);
        if (null !== $channels->findOneBy(['code' => 'WEB'])) {
            return;
        }

        /** @var FactoryInterface<LocaleInterface> $localeFactory */
        $localeFactory = $this->service('sylius.factory.locale', FactoryInterface::class);
        $locale = $localeFactory->createNew();
        $locale->setCode('en_US');

        /** @var FactoryInterface<CurrencyInterface> $currencyFactory */
        $currencyFactory = $this->service('sylius.factory.currency', FactoryInterface::class);
        $currency = $currencyFactory->createNew();
        $currency->setCode('USD');

        /** @var FactoryInterface<CountryInterface> $countryFactory */
        $countryFactory = $this->service('sylius.factory.country', FactoryInterface::class);
        $country = $countryFactory->createNew();
        $country->setCode('US');

        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $this->service('sylius.factory.channel', FactoryInterface::class);
        $channel = $channelFactory->createNew();
        $channel->setCode('WEB');
        $channel->setName('Web');
        $channel->setHostname('localhost');
        $channel->setEnabled(true);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);
        $channel->addCountry($country);

        foreach ([$locale, $currency, $country, $channel] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();
    }
}
