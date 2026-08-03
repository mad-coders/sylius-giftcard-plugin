<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Provider;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class GiftCardConfigurationProvider implements GiftCardConfigurationProviderInterface
{
    public function __construct(
        private GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
    ) {
    }

    public function getForChannel(ChannelInterface $channel): ?GiftCardConfigurationInterface
    {
        return $this->giftCardConfigurationRepository->findOneByChannel($channel);
    }
}
