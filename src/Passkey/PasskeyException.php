<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Passkey;

final class PasskeyException extends \RuntimeException
{
    public static function challengeGone(): self
    {
        return new self('calmfox_shop_two_factor.passkey.challenge_gone');
    }

    public static function rejected(?\Throwable $previous = null): self
    {
        return new self('calmfox_shop_two_factor.passkey.rejected', 0, $previous);
    }

    public static function alreadyPaired(): self
    {
        return new self('calmfox_shop_two_factor.passkey.already_paired');
    }
}
