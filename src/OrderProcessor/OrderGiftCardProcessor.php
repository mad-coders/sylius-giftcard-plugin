<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Settles an order's gift cards against what the customer has to pay.
 *
 * A gift card is money, not a discount. The order is still worth what the goods are worth - a card
 * changes who pays for them, not the price. So this deliberately does NOT reduce
 * Order::getTotal(); it records what each card covers and takes that off the payment instead.
 *
 * The distinction matters beyond bookkeeping:
 *
 * - the tax base stays the full sale value, which is what tax is actually owed on - tax was already
 *   dealt with when the card was bought;
 * - reporting and refunds see an order worth what was sold, not an artificially discounted one;
 * - promotions calculate against the real total, so a card cannot change whether a "spend over X"
 *   promotion applies.
 *
 * See docs/adr-log/0010-gift-card-as-tender.md, which supersedes the original adjustment design.
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
        // to settle; that is a valid (if unusual) installation, not an error.
        if (!$order instanceof OrderInterface) {
            return;
        }

        if (!$order->canBeProcessed() || $order->isEmpty()) {
            return;
        }

        $label = $this->translator->trans('madcoders_sylius_gift_card.ui.gift_card');

        // The full price of the goods. This runs after taxes, so it is the real amount owed.
        $amountToPay = $order->getTotal();
        $covered = 0;

        foreach ($order->getGiftCards() as $giftCard) {
            if (!$giftCard->isRedeemable()) {
                continue;
            }

            $remainingToPay = $amountToPay - $covered;
            if ($remainingToPay <= 0) {
                break;
            }

            $amount = min($giftCard->getAmount(), $remainingToPay);

            // Neutral, so it does not move the order total. It is the record of which card covered
            // how much, which is what tells the balance modifier what to debit when the order is
            // placed, and what to give back if it is cancelled.
            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $label,
                -$amount,
                true,
            );

            $adjustment->setOriginCode($giftCard->getCode());

            $order->addAdjustment($adjustment);

            $covered += $amount;
        }

        $this->settlePayment($order, $amountToPay - $covered);
    }

    /**
     * Takes what the gift cards cover off the payment.
     *
     * Sylius' payment processor has already set the payment to the order total by the time this
     * runs - which is exactly why this processor sits below it in the chain.
     */
    private function settlePayment(OrderInterface $order, int $amountLeftToPay): void
    {
        // Only cart-state payments: process() is guarded by canBeProcessed(), which is true only
        // while the ORDER is still a cart, and Sylius' checkout payment processor targets payments
        // in the cart state. A payment that has advanced past that belongs to a placed order, which
        // this processor never touches.
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        if (null === $payment) {
            return;
        }

        $payment->setAmount(max(0, $amountLeftToPay));
    }
}
