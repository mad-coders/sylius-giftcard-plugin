<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Operator;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Operates on the gift cards bought on an order.
 */
interface OrderGiftCardOperatorInterface
{
    /** Issues one card per purchased gift card unit that does not have one yet. */
    public function generate(OrderInterface $order): void;

    public function enable(OrderInterface $order): void;

    /** Called when the order the cards were bought on is cancelled. */
    public function disable(OrderInterface $order): void;

    /** @return list<GiftCardInterface> */
    public function giftCardsBoughtOn(OrderInterface $order): array;
}
