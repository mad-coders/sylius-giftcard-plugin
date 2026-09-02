<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\StoredGiftCardExpiryProviderInterface;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardExpiryNotInThePast;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardExpiryNotInThePastValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Refusing an expiry date that makes a spendable balance unspendable.
 *
 * Every case here is about the *difference* between the date submitted and the date the card
 * already had, which is why the constraint needs the stored value at all. Judging the submitted
 * date alone would refuse a card the #31 migration dated into the past, and an administrator could
 * then not so much as disable it.
 */
final class GiftCardExpiryNotInThePastValidatorTest extends ConstraintValidatorTestCase
{
    private const string MESSAGE = 'madcoders_sylius_gift_card.gift_card.expires_at.not_in_the_past';

    /** What the card is holding in the database when the form is submitted. */
    private ?\DateTimeInterface $storedExpiryDate = null;

    public function testItRefusesACardIssuedAlreadyExpired(): void
    {
        // Nothing stored: this card is being created, and it is dead the moment it exists.
        $this->storedExpiryDate = null;

        $this->validator->validate($this->giftCardExpiring('-1 day'), new GiftCardExpiryNotInThePast());

        $this->buildViolation(self::MESSAGE)->atPath('property.path.expiresAt')->assertRaised();
    }

    public function testItRefusesALiveCardsDateBeingMovedIntoThePast(): void
    {
        $this->storedExpiryDate = new \DateTimeImmutable('+6 months');

        $this->validator->validate($this->giftCardExpiring('-1 day'), new GiftCardExpiryNotInThePast());

        $this->buildViolation(self::MESSAGE)->atPath('property.path.expiresAt')->assertRaised();
    }

    public function testItAllowsACardThatHadAlreadyExpiredToBeSavedAgain(): void
    {
        // The #31 migration's cards, and the reason this constraint asks about the stored date at
        // all. Today has taken the balance already, so the write takes nothing.
        $this->storedExpiryDate = new \DateTimeImmutable('-2 years');

        $this->validator->validate($this->giftCardExpiring('-2 years'), new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    public function testItAllowsAnAlreadyExpiredCardsDateToBeMovedAtAll(): void
    {
        // Further back, even. There is no balance left to lose, so there is nothing to protect and
        // no reason to make the administrator fight the form.
        $this->storedExpiryDate = new \DateTimeImmutable('-1 month');

        $this->validator->validate($this->giftCardExpiring('-5 years'), new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    /** @return iterable<string, array{string}> */
    public static function futureDates(): iterable
    {
        yield 'far out' => ['+10 years'];
        yield 'next year' => ['+1 year'];
        yield 'in three days' => ['+3 days'];
        yield 'in a minute' => ['+1 minute'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('futureDates')]
    public function testItAllowsAnExpiryBroughtForwardToADateStillInTheFuture(string $expiresAt): void
    {
        // The rule is about the past, not about all reductions. A shop that decides a card should
        // run out sooner is entitled to say so.
        $this->storedExpiryDate = new \DateTimeImmutable('+10 years');

        $this->validator->validate($this->giftCardExpiring($expiresAt), new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    public function testItDoesNotRefuseAStoredCardWhosePreviousDateCannotBeSeen(): void
    {
        // An importer or a Messenger handler that clears its entity manager every few thousand rows
        // hands the validator a detached card: it is in the database, but nothing can say what date
        // it holds. Judging that as a creation would refuse a legacy card being edited only to
        // disable it - the exact case this rule promises to leave alone, failing on the batch jobs
        // least able to explain themselves.
        $this->storedExpiryDate = null;

        $this->validator->validate($this->storedCard($this->giftCardExpiring('-1 day')), new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    public function testItStillRefusesAStoredCardWhosePreviousDateWasLive(): void
    {
        // The mirror image, and the reason the fallback above is a tiebreak rather than an escape
        // hatch: when the previous date *can* be seen and it was live, an identity changes nothing.
        $this->storedExpiryDate = new \DateTimeImmutable('+6 months');

        $this->validator->validate($this->storedCard($this->giftCardExpiring('-1 day')), new GiftCardExpiryNotInThePast());

        $this->buildViolation(self::MESSAGE)->atPath('property.path.expiresAt')->assertRaised();
    }

    public function testItLeavesAMissingDateToNotBlank(): void
    {
        // Two constraints, two messages. "Not in the past" is the wrong answer to an empty field,
        // and raising both would show one mistake twice.
        $this->storedExpiryDate = new \DateTimeImmutable('+1 year');

        $giftCard = new GiftCard();
        $giftCard->setExpiresAt(null);

        $this->validator->validate($giftCard, new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    public function testItIgnoresAnythingThatIsNotAGiftCard(): void
    {
        $this->validator->validate(null, new GiftCardExpiryNotInThePast());
        $this->validator->validate('2020-01-01', new GiftCardExpiryNotInThePast());

        $this->assertNoViolation();
    }

    private function giftCardExpiring(string $expiresAt): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setExpiresAt(new \DateTimeImmutable($expiresAt));

        return $giftCard;
    }

    /**
     * The same card, as one the database has already given an id.
     *
     * Reflection because a gift card's id is Doctrine's to assign and the model rightly offers no
     * setter. "Has an identity" is the only thing available to tell a card that exists from one
     * being issued when the stored date cannot be read, so a test of that branch has to be able to
     * say which it is looking at.
     */
    private function storedCard(GiftCardInterface $giftCard): GiftCardInterface
    {
        (new \ReflectionProperty(GiftCard::class, 'id'))->setValue($giftCard, 42);

        return $giftCard;
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        // Each test says what the database holds by assigning a date, so nothing has to be
        // re-programmed per case. Reading it out of Doctrine's identity map is the provider's
        // business and is covered where that is wired, not here.
        $storedGiftCardExpiryProvider = $this->createMock(StoredGiftCardExpiryProviderInterface::class);
        $storedGiftCardExpiryProvider
            ->method('getStoredExpiryDate')
            ->willReturnCallback(fn (GiftCardInterface $giftCard): ?\DateTimeInterface => $this->storedExpiryDate)
        ;

        return new GiftCardExpiryNotInThePastValidator($storedGiftCardExpiryProvider);
    }
}
