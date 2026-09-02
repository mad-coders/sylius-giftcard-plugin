<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Checker;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTenderMode;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

/**
 * @see GiftCardTenderCheckerInterface
 */
final readonly class GiftCardTenderChecker implements GiftCardTenderCheckerInterface
{
    public function __construct(
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
    ) {
    }

    public function settleableTotalOf(BaseOrderInterface $order): int
    {
        $total = $order->getTotal();

        if ($this->allowsGiftCardsToPayForGiftCards($order)) {
            return max(0, $total);
        }

        $giftCardLinesTotal = 0;
        $hasGiftCardLines = false;

        foreach ($this->giftCardLinesOf($order) as $item) {
            $giftCardLinesTotal += $item->getTotal();
            $hasGiftCardLines = true;
        }

        if (!$hasGiftCardLines) {
            return max(0, $total);
        }

        // Nothing but gift cards on the order: nothing here a gift card may pay for, including the
        // cost of delivering them. Everything below leaves shipping settleable on the reasoning
        // that the postage is for the goods - and on this order there are no goods, so that
        // reasoning has nothing left to stand on.
        //
        // Without this the rule contradicts itself between two screens: the cart refuses redemption
        // outright, and the checkout, two clicks later, lets a card cover the $10 postage on the
        // same basket. No money is at stake either way; a rule that is refused in one place and
        // honoured in the next is the problem.
        if (!$this->hasNonGiftCardLines($order)) {
            return 0;
        }

        // Order total less what the gift card lines are worth. Deliberately arithmetic on Sylius'
        // own numbers rather than an attribution of shipping, tax and order promotions between
        // "gift card" and "goods": an item total already carries the promotions and taxes that
        // landed on that line, so subtracting it removes the gift card's own value and its own tax
        // and nothing else. Everything that is not a line - shipping, order-level adjustments -
        // stays settleable, which is right: a gift card is emailed, so the postage is for the goods.
        return max(0, $total - $giftCardLinesTotal);
    }

    public function allowsRedemptionOn(BaseOrderInterface $order): bool
    {
        // Presence, not value. An order carrying no gift card products has nothing for this rule to
        // say about it, so it is never refused here. Asking instead whether the gift card lines are
        // *worth* anything would let a line priced at zero switch the rule off for the whole order -
        // harmless today, and exactly the kind of edge that becomes an exploit after the next
        // pricing feature.
        if (!$this->hasGiftCardLines($order)) {
            return true;
        }

        return $this->settleableTotalOf($order) > 0;
    }

    private function allowsGiftCardsToPayForGiftCards(BaseOrderInterface $order): bool
    {
        $channel = $order instanceof OrderInterface ? $order->getChannel() : null;

        $configuration = null === $channel ? null : $this->giftCardConfigurationProvider->getForChannel($channel);

        // Anything we cannot read a rule from - no channel, no configuration - gets the safe rule,
        // not the old one. Unlike the sale mode in ADR 0013, "keep behaving as before" is not the
        // conservative choice here: before was a hole that let a card renew itself forever, and
        // leaving unconfigured channels in it would mean the fix protected only the shops that had
        // already read the release notes.
        return ($configuration?->getTenderMode() ?? GiftCardTenderMode::GoodsOnly)
            ->allowsGiftCardsToPayForGiftCards();
    }

    private function hasGiftCardLines(BaseOrderInterface $order): bool
    {
        foreach ($this->giftCardLinesOf($order) as $item) {
            return true;
        }

        return false;
    }

    /** Whether anything on this order is not a gift card - that is, whether there are goods. */
    private function hasNonGiftCardLines(BaseOrderInterface $order): bool
    {
        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItemInterface) {
                continue;
            }

            $product = $item->getProduct();

            if (!$product instanceof GiftCardProductInterface || !$product->isGiftCard()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The lines on this order that sell a gift card product.
     *
     * @return iterable<OrderItemInterface>
     */
    private function giftCardLinesOf(BaseOrderInterface $order): iterable
    {
        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItemInterface) {
                continue;
            }

            $product = $item->getProduct();

            // A host application that has not applied ProductTrait sells no gift cards.
            if (!$product instanceof GiftCardProductInterface || !$product->isGiftCard()) {
                continue;
            }

            yield $item;
        }
    }
}
