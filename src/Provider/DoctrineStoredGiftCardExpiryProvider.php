<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * Reads the stored expiry date out of Doctrine's identity map.
 *
 * The unit of work keeps the row as it was loaded until the change set is computed, so during
 * validation - which happens long before the flush - it still holds the date the database has. That
 * is the only place the previous value survives: the form has already written the submitted date
 * onto the entity by then, and re-reading the row would just hand back the modified object from the
 * identity map.
 *
 * Resolved through the registry rather than one injected manager, so a host that maps its own gift
 * card class to a different manager is answered about *its* class.
 */
final readonly class DoctrineStoredGiftCardExpiryProvider implements StoredGiftCardExpiryProviderInterface
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function getStoredExpiryDate(GiftCardInterface $giftCard): ?\DateTimeInterface
    {
        $manager = $this->managerRegistry->getManagerForClass($giftCard::class);

        // Not an ORM manager, so there is no identity map to ask. Answering null leaves the caller
        // with "nothing to compare against", which is the safe reading: a validator that cannot see
        // the previous date judges the submitted one on its own.
        if (!$manager instanceof EntityManagerInterface) {
            return null;
        }

        $unitOfWork = $manager->getUnitOfWork();

        // STATE_NEW is the assumption for an entity the unit of work has never seen, which is what
        // a card being created is. Passing it keeps Doctrine from going to the database to work the
        // state out - and from throwing on an entity with no identifier.
        if (UnitOfWork::STATE_MANAGED !== $unitOfWork->getEntityState($giftCard, UnitOfWork::STATE_NEW)) {
            return null;
        }

        $storedExpiryDate = $unitOfWork->getOriginalEntityData($giftCard)['expiresAt'] ?? null;

        return $storedExpiryDate instanceof \DateTimeInterface ? $storedExpiryDate : null;
    }
}
