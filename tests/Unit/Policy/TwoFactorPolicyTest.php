<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Policy;

use Calmfox\SyliusShopTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Calmfox\SyliusShopTwoFactorPlugin\TestApplication\Entity\ShopUser;

final class TwoFactorPolicyTest extends TestCase
{
    public function testItFallsBackToTheConfiguredDefault(): void
    {
        self::assertSame(TwoFactorPolicy::OPTIONAL, $this->policy(null, TwoFactorPolicy::OPTIONAL)->current());
    }

    public function testThePanelSettingWins(): void
    {
        self::assertSame(TwoFactorPolicy::DISABLED, $this->policy(new TwoFactorSettings(TwoFactorPolicy::DISABLED))->current());
    }

    public function testAMissingTableDoesNotBreakTheLogin(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willThrowException(new \RuntimeException('Table not found'));

        self::assertSame(TwoFactorPolicy::REQUIRED, (new TwoFactorPolicy($entityManager, TwoFactorPolicy::REQUIRED))->current());
    }

    public function testChangingThePolicyStoresIt(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(static fn (TwoFactorSettings $settings): bool => TwoFactorPolicy::DISABLED === $settings->getPolicy()));
        $entityManager->expects(self::once())->method('flush');

        $policy = new TwoFactorPolicy($entityManager, TwoFactorPolicy::REQUIRED);
        $policy->change(TwoFactorPolicy::DISABLED);

        self::assertSame(TwoFactorPolicy::DISABLED, $policy->current());
    }

    public function testAnUnknownPolicyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->policy(null)->change('sometimes');
    }

    /** @return iterable<string, array{string, bool, bool, bool}> */
    public static function setupCases(): iterable
    {
        // policy, has a method, reset by another administrator, must set up
        yield 'required, nothing paired' => [TwoFactorPolicy::REQUIRED, false, false, true];
        yield 'required, paired' => [TwoFactorPolicy::REQUIRED, true, false, false];
        yield 'optional, nothing paired' => [TwoFactorPolicy::OPTIONAL, false, false, false];
        yield 'optional, reset' => [TwoFactorPolicy::OPTIONAL, false, true, true];
        yield 'disabled, reset' => [TwoFactorPolicy::DISABLED, false, true, false];
        yield 'disabled, nothing paired' => [TwoFactorPolicy::DISABLED, false, false, false];
    }

    #[DataProvider('setupCases')]
    public function testWhoMustSetUpASecondFactor(string $policy, bool $paired, bool $reset, bool $expected): void
    {
        $adminUser = new ShopUser();
        $adminUser->setTotpSecret($paired ? 'JBSWY3DPEHPK3PXP' : null);
        $adminUser->setTwoFactorSetupRequired($reset);

        self::assertSame($expected, $this->policy(new TwoFactorSettings($policy))->mustSetUp($adminUser));
    }

    private function policy(?TwoFactorSettings $settings, string $default = TwoFactorPolicy::REQUIRED): TwoFactorPolicy
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($settings);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new TwoFactorPolicy($entityManager, $default);
    }
}
