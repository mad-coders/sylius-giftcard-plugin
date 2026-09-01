<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

/**
 * What a gift card may be spent on in a channel.
 *
 * An enum rather than a boolean for the same reason as {@see GiftCardSaleMode}: "what counts as
 * settleable" is a policy, and "goods and shipping but not gift cards" or "goods only, shipping
 * paid in cash" are plausible next answers that a boolean could not express without another column.
 *
 * This gates *what a card settles*, never whether it may be applied to a channel at all - a card is
 * always spendable in the channel that issued it, see docs/adr-log/0010-gift-card-as-tender.md.
 */
enum GiftCardTenderMode: string
{
    /**
     * A gift card pays for everything on the order except other gift cards. The default, and the
     * only mode under which a mandatory expiry date means anything - see
     * docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md.
     */
    case GoodsOnly = 'goods_only';

    /** A gift card pays for anything on the order, gift cards included. */
    case Anything = 'anything';

    /** Whether a gift card may settle the gift card products on an order. */
    public function allowsGiftCardsToPayForGiftCards(): bool
    {
        return self::Anything === $this;
    }
}
