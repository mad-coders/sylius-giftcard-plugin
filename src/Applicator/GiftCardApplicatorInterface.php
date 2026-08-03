<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Applicator;

use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * Attaches and detaches gift cards to an order, reprocessing it so the totals follow immediately.
 */
interface GiftCardApplicatorInterface
{
    /**
     * @throws GiftCardNotFoundException
     * @throws GiftCardNotRedeemableException
     * @throws ChannelMismatchException
     */
    public function apply(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void;

    /**
     * @throws GiftCardNotFoundException
     */
    public function remove(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void;
}
