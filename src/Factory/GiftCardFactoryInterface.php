<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Factory;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<GiftCardInterface>
 */
interface GiftCardFactoryInterface extends FactoryInterface
{
    /**
     * @param int $initialAmount in minor units
     */
    public function createForChannel(
        ChannelInterface $channel,
        int $initialAmount,
        GiftCardOrigin $origin = GiftCardOrigin::Admin,
        ?GiftCardConfigurationInterface $configuration = null,
    ): GiftCardInterface;
}
