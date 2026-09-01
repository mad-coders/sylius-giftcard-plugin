<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Prices a gift card line at the amount the customer chose for it.
 *
 * Sylius takes every order item's unit price from channel pricing on every recalculation
 * (`OrderPricesRecalculator`, priority 50), so a customer-chosen price cannot simply be written once
 * and left alone - the next cart change would wipe it. This processor runs immediately below that
 * one and puts the chosen amount back, before promotions (20), taxes (10) and the payment (0) are
 * calculated, so all three are based on what the customer actually agreed to pay.
 *
 * It is also the *authority* on whether an amount is allowed. The shop form refuses a bad amount
 * with a friendly message, but a form is not a security boundary: an amount that reached the order
 * item some other way is judged here, on every single processing run, against the same
 * `isAllowedAmount()` the form used. An amount the channel does not offer is discarded and the line
 * falls back to the product's channel price, so a forged value can never reach a payment or a card.
 *
 * See docs/adr-log/0014-customer-chosen-gift-card-amount.md.
 */
final readonly class GiftCardChosenAmountProcessor implements OrderProcessorInterface
{
    public function __construct(
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface || !$order->canBeProcessed()) {
            return;
        }

        $channel = $order->getChannel();
        if (null === $channel) {
            return;
        }

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItemInterface) {
                continue;
            }

            $chosenAmount = $item->getGiftCardAmount();
            if (null === $chosenAmount) {
                continue;
            }

            $product = $item->getProduct();

            // A line that is no longer a gift card - the product's flag was turned off after it went
            // into a cart - keeps no say over its own price.
            $allowed = $product instanceof ProductInterface &&
                $product->isGiftCard() &&
                null !== $configuration &&
                $configuration->isAllowedAmount($chosenAmount);

            if (!$allowed) {
                // Cleared, not just ignored: leaving it in place would mean re-judging the same
                // rejected value on every run, and would show the customer a choice that is not
                // being honoured.
                $item->setGiftCardAmount(null);

                continue;
            }

            $item->setUnitPrice($chosenAmount);

            // Otherwise the shop shows the channel price struck through next to the chosen one, as
            // though the customer were getting a discount they are not.
            $item->setOriginalUnitPrice(null);
        }
    }
}
