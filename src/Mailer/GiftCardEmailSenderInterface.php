<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Mailer;

use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardEmailSenderInterface
{
    /**
     * Emails the codes of the gift cards bought on this order to whoever placed it.
     */
    public function sendGiftCardsFromOrder(OrderInterface $order): void;
}
