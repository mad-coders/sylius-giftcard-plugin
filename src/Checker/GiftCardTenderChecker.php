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

        // Order total less what the gift card lines are worth. Deliberately arithmetic on Sylius'
        // own numbers rather than an attribution of shipping, tax and order promotions between
        // "gift card" and "goods": an item total already carries the promotions and taxes that
        // landed on that line, so subtracting it removes the gift card's own value and its own tax
        // and nothing else. Everything that is not a line - shipping, order-level adjustments -
        // stays settleable, which is right: a gift card is emailed, so the postage is for the goods.
        $giftCardLinesTotal = 0;

        foreach ($this->giftCardLinesOf($order) as $item) {
            $giftCardLinesTotal += $item->getTotal();
        }

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
