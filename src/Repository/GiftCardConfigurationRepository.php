<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Repository;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ChannelInterface;

class GiftCardConfigurationRepository extends EntityRepository implements GiftCardConfigurationRepositoryInterface
{
    public function findOneByChannel(ChannelInterface $channel): ?GiftCardConfigurationInterface
    {
        /** @var GiftCardConfigurationInterface|null $configuration */
        $configuration = $this->createQueryBuilder('o')
            ->andWhere('o.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $configuration;
    }
}
