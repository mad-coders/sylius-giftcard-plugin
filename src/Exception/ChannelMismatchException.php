<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Thrown when a gift card issued for one channel is applied to an order in another. Gift cards are
 * channel-scoped so that a card bought in one currency or store cannot be spent in a different one.
 */
final class ChannelMismatchException extends GiftCardException
{
    public function __construct(
        private readonly ?ChannelInterface $giftCardChannel,
        private readonly ?ChannelInterface $orderChannel,
    ) {
        parent::__construct(sprintf(
            'The gift card belongs to channel "%s" but the order is in channel "%s".',
            $giftCardChannel?->getCode() ?? 'none',
            $orderChannel?->getCode() ?? 'none',
        ));
    }

    public function getGiftCardChannel(): ?ChannelInterface
    {
        return $this->giftCardChannel;
    }

    public function getOrderChannel(): ?ChannelInterface
    {
        return $this->orderChannel;
    }
}
