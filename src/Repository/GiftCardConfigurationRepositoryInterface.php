<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Repository;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface GiftCardConfigurationRepositoryInterface extends RepositoryInterface
{
    public function findOneByChannel(ChannelInterface $channel): ?GiftCardConfigurationInterface;
}
