<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Factory;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculatorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Creates gift cards with the state a card can never be without: a channel, a currency, an initial
 * amount and an expiry date.
 *
 * Codes are *not* assigned here - that needs the generator, which needs to check the repository for
 * collisions, and keeping it out of the factory lets a caller create a card with a code they were
 * given (an imported batch, a fixture) without fighting a generated one.
 */
final readonly class GiftCardFactory implements GiftCardFactoryInterface
{
    /** @param FactoryInterface<GiftCardInterface> $decoratedFactory */
    public function __construct(
        private FactoryInterface $decoratedFactory,
        private GiftCardExpiryCalculatorInterface $giftCardExpiryCalculator,
    ) {
    }

    public function createNew(): GiftCardInterface
    {
        return $this->decoratedFactory->createNew();
    }

    /**
     * @param int $initialAmount in minor units
     */
    public function createForChannel(
        ChannelInterface $channel,
        int $initialAmount,
        GiftCardOrigin $origin = GiftCardOrigin::Admin,
        ?GiftCardConfigurationInterface $configuration = null,
    ): GiftCardInterface {
        $giftCard = $this->createNew();

        $giftCard->setChannel($channel);
        $giftCard->setCurrencyCode($channel->getBaseCurrency()?->getCode());
        $giftCard->setInitialAmount($initialAmount);
        $giftCard->setOrigin($origin);
        $giftCard->setExpiresAt($this->giftCardExpiryCalculator->calculate($configuration));

        return $giftCard;
    }
}
