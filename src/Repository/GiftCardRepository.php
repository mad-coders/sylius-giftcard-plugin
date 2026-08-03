<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Repository;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

class GiftCardRepository extends EntityRepository implements GiftCardRepositoryInterface
{
    public function findOneByCode(string $code): ?GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $giftCard;
    }

    public function findOneByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->andWhere('o.channel = :channel')
            ->setParameter('code', $code)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $giftCard;
    }

    public function findByPurchaser(CustomerInterface $customer): array
    {
        /** @var array<array-key, GiftCardInterface> $giftCards */
        $giftCards = $this->createQueryBuilder('o')
            ->andWhere('o.purchaser = :customer')
            ->setParameter('customer', $customer)
            ->addOrderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $giftCards;
    }

    public function findByRedeemer(CustomerInterface $customer): array
    {
        /** @var array<array-key, GiftCardInterface> $giftCards */
        $giftCards = $this->createQueryBuilder('o')
            ->andWhere('o.redeemer = :customer')
            ->setParameter('customer', $customer)
            ->addOrderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $giftCards;
    }

    public function codeExists(string $code): bool
    {
        // Used by the code generator on every attempt, so it selects the id rather than hydrating
        // an entity we would immediately discard.
        $result = $this->createQueryBuilder('o')
            ->select('1')
            ->andWhere('o.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $result;
    }
}
