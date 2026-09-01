<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Checker;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardSaleMode;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The single answer to "may the shop sell gift cards here?".
 *
 * It exists as its own service because the answer is needed in two places that are nowhere near
 * each other - the cart, and the payment transition that issues a card - and a mode change between
 * them must not let an order through. Two copies of this rule would drift.
 */
final readonly class GiftCardPurchaseChecker implements GiftCardPurchaseCheckerInterface
{
    public function __construct(
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
    ) {
    }

    public function canBeBoughtIn(ChannelInterface $channel): bool
    {
        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        // A channel with no configuration sells gift cards, which is what it did before there was
        // a mode at all. Refusing here would silently stop sales in every unconfigured channel on
        // upgrade.
        return GiftCardSaleMode::Sellable === ($configuration?->getSaleMode() ?? GiftCardSaleMode::Sellable);
    }
}
