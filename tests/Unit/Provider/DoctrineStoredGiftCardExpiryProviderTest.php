<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\DoctrineStoredGiftCardExpiryProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reading the expiry date a card still has in the database.
 *
 * The answer decides whether a past date is refused or waved through, so the cases that matter are
 * the ones where there is nothing to read: a card being created has no stored date, and neither has
 * one the unit of work has never seen. Both have to come back null rather than throw, because the
 * constraint asks this question on every gift card write in the application.
 */
final class DoctrineStoredGiftCardExpiryProviderTest extends TestCase
{
    public function testItReadsTheDateTheUnitOfWorkLoadedRatherThanTheOneOnTheObject(): void
    {
        // The whole point. The form has already written the submitted date onto the entity by the
        // time validation runs, so reading the object would answer with the new value and the
        // constraint would never see a change at all.
        $storedExpiryDate = new \DateTimeImmutable('2027-01-01 09:00');

        $giftCard = new GiftCard();
        $giftCard->setExpiresAt(new \DateTimeImmutable('2020-01-01 09:00'));

        $provider = $this->providerFor($giftCard, UnitOfWork::STATE_MANAGED, ['expiresAt' => $storedExpiryDate]);

        self::assertSame($storedExpiryDate, $provider->getStoredExpiryDate($giftCard));
    }

    public function testItAnswersNullForACardBeingCreated(): void
    {
        $giftCard = new GiftCard();

        $provider = $this->providerFor($giftCard, UnitOfWork::STATE_NEW, []);

        self::assertNull($provider->getStoredExpiryDate($giftCard));
    }

    public function testItAnswersNullForACardTheUnitOfWorkIsNoLongerManaging(): void
    {
        $giftCard = new GiftCard();

        $provider = $this->providerFor($giftCard, UnitOfWork::STATE_DETACHED, ['expiresAt' => new \DateTimeImmutable()]);

        self::assertNull($provider->getStoredExpiryDate($giftCard));
    }

    public function testItAnswersNullWhenTheStoredRowHasNoDate(): void
    {
        // Rows written before the mandatory-expiry migration. Null means "nothing to compare
        // against", never "the card had no expiry" - see the interface.
        $giftCard = new GiftCard();

        $provider = $this->providerFor($giftCard, UnitOfWork::STATE_MANAGED, ['expiresAt' => null]);

        self::assertNull($provider->getStoredExpiryDate($giftCard));
    }

    public function testItAnswersNullWhenTheClassIsNotMappedToAnOrmManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createMock(ObjectManager::class));

        self::assertNull((new DoctrineStoredGiftCardExpiryProvider($registry))->getStoredExpiryDate(new GiftCard()));
    }

    public function testItAnswersNullWhenNoManagerKnowsTheClassAtAll(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        self::assertNull((new DoctrineStoredGiftCardExpiryProvider($registry))->getStoredExpiryDate(new GiftCard()));
    }

    /** @param array<string, mixed> $originalEntityData */
    private function providerFor(
        GiftCardInterface $giftCard,
        int $state,
        array $originalEntityData,
    ): DoctrineStoredGiftCardExpiryProvider {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getEntityState')->with($giftCard, UnitOfWork::STATE_NEW)->willReturn($state);
        $unitOfWork->method('getOriginalEntityData')->with($giftCard)->willReturn($originalEntityData);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with($giftCard::class)->willReturn($manager);

        return new DoctrineStoredGiftCardExpiryProvider($registry);
    }
}
