<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Email;

use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

/** Sends the login code through Sylius' mailer, in the shop's mail layout and the customer's channel and locale. */
final readonly class AuthCodeMailer implements AuthCodeMailerInterface
{
    public const EMAIL_CODE = 'calmfox_shop_two_factor_code';

    public function __construct(
        private SenderInterface $emailSender,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $code = $user->getEmailAuthCode();
        if (null === $code) {
            return;
        }

        $this->emailSender->send(self::EMAIL_CODE, [$user->getEmailAuthRecipient()], [
            'user' => $user,
            'code' => $code,
            'channel' => $this->channelContext->getChannel(),
            'localeCode' => $this->localeContext->getLocaleCode(),
        ]);
    }
}
