<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardPurchaseCheckerInterface
{
    /**
     * Whether a customer may buy a gift card product in this channel.
     *
     * Says nothing about redeeming: a card is spendable in its channel whatever the answer here,
     * because a card issued by an administrator would otherwise be worthless.
     */
    public function canBeBoughtIn(ChannelInterface $channel): bool;
}
