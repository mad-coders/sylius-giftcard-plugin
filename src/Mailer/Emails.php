<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Mailer;

/**
 * Email codes the plugin registers with Sylius' mailer. A host application overrides an email's
 * subject or template by redefining the code under `sylius_mailer.emails`.
 */
final class Emails
{
    public const string GIFT_CARDS_PURCHASED = 'madcoders_gift_cards_purchased';

    private function __construct()
    {
    }
}
