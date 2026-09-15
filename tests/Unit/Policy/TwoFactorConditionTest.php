<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Policy;

use Calmfox\SyliusShopTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorCondition;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class TwoFactorConditionTest extends TestCase
{
    public function testTurnedOffPolicySkipsTheSecondFactorForCustomers(): void
    {
        self::assertFalse($this->condition(TwoFactorPolicy::DISABLED)->shouldPerformTwoFactorAuthentication($this->context(new ShopUser())));
        self::assertTrue($this->condition(TwoFactorPolicy::OPTIONAL)->shouldPerformTwoFactorAuthentication($this->context(new ShopUser())));
    }

    public function testAdministratorsAreNotAffected(): void
    {
        self::assertTrue($this->condition(TwoFactorPolicy::DISABLED)->shouldPerformTwoFactorAuthentication($this->context(new AdminUser())));
    }

    private function condition(string $policy): TwoFactorCondition
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new TwoFactorSettings($policy));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new TwoFactorCondition(new TwoFactorPolicy($entityManager, TwoFactorPolicy::OPTIONAL));
    }

    private function context(UserInterface $user): AuthenticationContextInterface
    {
        $context = $this->createMock(AuthenticationContextInterface::class);
        $context->method('getUser')->willReturn($user);

        return $context;
    }
}
