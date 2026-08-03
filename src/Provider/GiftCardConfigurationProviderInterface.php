<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Provider;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Core\Model\ChannelInterface;

interface GiftCardConfigurationProviderInterface
{
    /**
     * The gift card configuration for the channel, or null when the channel has none. A channel
     * without a configuration still works - the defaults on the configuration model apply.
     */
    public function getForChannel(ChannelInterface $channel): ?GiftCardConfigurationInterface;
}
