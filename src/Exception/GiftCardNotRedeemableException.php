<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Exception;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * Thrown when a gift card exists but cannot be applied to an order: it is disabled, expired, or has
 * nothing left on it.
 */
final class GiftCardNotRedeemableException extends GiftCardException
{
    public function __construct(private readonly GiftCardInterface $giftCard, string $reason)
    {
        parent::__construct(sprintf('The gift card "%s" cannot be redeemed: %s.', (string) $giftCard->getCode(), $reason));
    }

    public function getGiftCard(): GiftCardInterface
    {
        return $this->giftCard;
    }
}
