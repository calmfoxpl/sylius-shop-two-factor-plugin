<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Twig;

use Calmfox\SyliusShopTwoFactorPlugin\Policy\TwoFactorPolicy;
use Calmfox\SyliusShopTwoFactorPlugin\Twig\TwoFactorExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwoFactorExtensionTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function addresses(): iterable
    {
        yield 'regular' => ['jane.doe@example.com', 'j***@example.com'];
        yield 'one letter' => ['j@example.com', 'j***@example.com'];
        yield 'no at sign' => ['not-an-address', 'n***@'];
    }

    #[DataProvider('addresses')]
    public function testItMasksAnEmailAddress(string $address, string $masked): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ address|calmfox_mask_email }}']));
        $twig->addExtension(new TwoFactorExtension(new TwoFactorPolicy($this->createMock(EntityManagerInterface::class), TwoFactorPolicy::OPTIONAL)));

        self::assertSame($masked, $twig->render('t', ['address' => $address]));
    }
}
