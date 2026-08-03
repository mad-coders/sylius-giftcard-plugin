<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns the gift cards applied to an order into negative order adjustments.
 *
 * Registered last in the `sylius.order_processor` chain, so it sees the final total after items,
 * shipping, promotions and taxes. Its own adjustments from the previous run are wiped beforehand by
 * Sylius' OrderAdjustmentsClearer - see RegisterGiftCardAdjustmentClearingPass - which is what makes
 * repeated processing idempotent.
 *
 * See docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md.
 */
final readonly class OrderGiftCardProcessor implements OrderProcessorInterface
{
    /** @param AdjustmentFactoryInterface<\Sylius\Component\Order\Model\AdjustmentInterface> $adjustmentFactory */
    public function __construct(
        private AdjustmentFactoryInterface $adjustmentFactory,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        // A host application that has not applied OrderTrait to its Order simply has no gift cards
        // to process; that is a valid (if unusual) installation, not an error.
        if (!$order instanceof OrderInterface) {
            return;
        }

        if (!$order->canBeProcessed() || $order->isEmpty() || !$order->hasGiftCards()) {
            return;
        }

        $label = $this->translator->trans('madcoders_sylius_gift_card.ui.gift_card');

        foreach ($order->getGiftCards() as $giftCard) {
            if (!$giftCard->isRedeemable()) {
                continue;
            }

            // getTotal() is recalculated as each adjustment is added, so this is the total still
            // left to pay after the cards already handled in this loop. Reading it each time is
            // what lets several cards stack without any of them overshooting.
            $remainingTotal = $order->getTotal();
            if ($remainingTotal <= 0) {
                break;
            }

            $amount = min($giftCard->getAmount(), $remainingTotal);

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $label,
                -$amount,
            );

            // The code is how the amount modifier finds its way back to the card when the order is
            // placed or cancelled, so what gets charged is exactly what gets deducted.
            $adjustment->setOriginCode($giftCard->getCode());

            $order->addAdjustment($adjustment);
        }
    }
}
