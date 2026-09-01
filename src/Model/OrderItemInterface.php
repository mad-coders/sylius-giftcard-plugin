<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\OrderItemInterface as BaseOrderItemInterface;

/**
 * Applied to the host application's OrderItem together with {@see OrderItemTrait}.
 *
 * What the customer asked for when they put a gift card in their basket. The choice belongs to the
 * *line*, not the unit: one trip through the product form produces one amount and one message, and
 * every card that line issues carries them.
 *
 * The amount is stored rather than derived from the item's unit price because Sylius rewrites that
 * price from channel pricing on every order recalculation. It is the record of what the customer
 * chose; the price is a consequence of it - see
 * docs/adr-log/0014-customer-chosen-gift-card-amount.md.
 */
interface OrderItemInterface extends BaseOrderItemInterface
{
    /**
     * The face value the customer chose for this line, in minor units, or null when the channel
     * sells gift cards at the product's price.
     */
    public function getGiftCardAmount(): ?int;

    public function setGiftCardAmount(?int $giftCardAmount): void;

    /** The customer's message to the recipient. Untrusted text: escape it wherever it is rendered. */
    public function getGiftCardMessage(): ?string;

    public function setGiftCardMessage(?string $giftCardMessage): void;
}
