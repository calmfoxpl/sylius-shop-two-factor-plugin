<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusShopTwoFactorPlugin\Unit\Email;

use Calmfox\SyliusShopTwoFactorPlugin\Email\EmailCodeThrottle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class EmailCodeThrottleTest extends TestCase
{
    public function testTheFirstCodeCanBeSentAtOnce(): void
    {
        self::assertSame(0, (new EmailCodeThrottle(60))->secondsLeft($this->request()));
    }

    public function testTheNextCodeWaitsForTheInterval(): void
    {
        $throttle = new EmailCodeThrottle(60);
        $request = $this->request();

        $throttle->markSent($request);

        self::assertGreaterThan(55, $throttle->secondsLeft($request));
        self::assertLessThanOrEqual(60, $throttle->secondsLeft($request));
    }

    public function testAZeroIntervalNeverWaits(): void
    {
        $throttle = new EmailCodeThrottle(0);
        $request = $this->request();

        $throttle->markSent($request);

        self::assertSame(0, $throttle->secondsLeft($request));
    }

    private function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
