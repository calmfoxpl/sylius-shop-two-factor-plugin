<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Email;

use Symfony\Component\HttpFoundation\Request;

/** One e-mail code per interval per session, so the button cannot be used to flood a mailbox. */
final readonly class EmailCodeThrottle
{
    private const SESSION_KEY = 'calmfox_shop_two_factor.email_code_sent_at';

    public function __construct(private int $interval)
    {
    }

    public function secondsLeft(Request $request): int
    {
        $sentAt = $request->getSession()->get(self::SESSION_KEY, 0);
        $sentAt = is_int($sentAt) ? $sentAt : 0;

        return max(0, $sentAt + $this->interval - time());
    }

    public function markSent(Request $request): void
    {
        $request->getSession()->set(self::SESSION_KEY, time());
    }
}
