<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Applicator;

use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardsNotAcceptedOnOrderException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * Attaches and detaches gift cards to an order, reprocessing it so the totals follow immediately.
 */
interface GiftCardApplicatorInterface
{
    /**
     * @return bool whether the card was *newly* attached. False means it was already on the order and
     *              nothing changed - applying the same card twice is a silent no-op, and callers that
     *              treat a successful call as "a card was redeemed just now" have to be able to tell
     *              the two apart. The rate limiter does: see GiftCardRedemptionLimiterInterface::clear().
     *
     * @throws GiftCardsNotAcceptedOnOrderException when the order itself takes no gift cards, whatever
     *                                              code is offered. Thrown before the code is resolved,
     *                                              so it reveals nothing about which codes exist.
     * @throws GiftCardNotFoundException
     * @throws GiftCardNotRedeemableException
     * @throws ChannelMismatchException
     */
    public function apply(BaseOrderInterface $order, GiftCardInterface|string $giftCard): bool;

    /**
     * @throws GiftCardNotFoundException
     */
    public function remove(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void;
}
