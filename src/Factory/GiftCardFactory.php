<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Factory;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Creates gift cards with the state a card can never be without: a channel, a currency and an
 * initial amount.
 *
 * Codes are *not* assigned here - that needs the generator, which needs to check the repository for
 * collisions, and keeping it out of the factory lets a caller create a card with a code they were
 * given (an imported batch, a fixture) without fighting a generated one.
 *
 * @implements FactoryInterface<GiftCardInterface>
 */
final readonly class GiftCardFactory implements FactoryInterface
{
    /** @param FactoryInterface<GiftCardInterface> $decoratedFactory */
    public function __construct(private FactoryInterface $decoratedFactory)
    {
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
        $giftCard->setExpiresAt($configuration?->calculateExpiryDate());

        return $giftCard;
    }
}
