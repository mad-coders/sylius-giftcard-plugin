<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\StoredGiftCardExpiryProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

final class GiftCardExpiryNotInThePastValidator extends ConstraintValidator
{
    public function __construct(private readonly StoredGiftCardExpiryProviderInterface $storedGiftCardExpiryProvider)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($constraint, GiftCardExpiryNotInThePast::class);

        if (!$value instanceof GiftCardInterface) {
            return;
        }

        $expiresAt = $value->getExpiresAt();

        // A missing date is NotNull's business. Answering "not in the past" to an empty field says
        // the wrong thing about the wrong problem.
        if (null === $expiresAt) {
            return;
        }

        $now = new \DateTimeImmutable();

        // The whole rule is about the past. A date still in the future is allowed however far it has
        // been brought forward - a shop that decides a card should run out sooner is entitled to say
        // so, and the balance stays spendable until it does.
        if ($expiresAt >= $now) {
            return;
        }

        $storedExpiryDate = $this->storedGiftCardExpiryProvider->getStoredExpiryDate($value);

        // The card was already expired before this write, so nothing spendable is being destroyed:
        // there is no balance to lose that today has not taken already. This is what keeps a card
        // the #31 migration dated into the past editable, and what lets a legacy card be disabled or
        // have its message corrected without its own stored date being thrown back at the
        // administrator.
        if (null !== $storedExpiryDate && $storedExpiryDate < $now) {
            return;
        }

        // No previous date to compare against, on a card that has one in the database somewhere.
        // That is not a card being issued in the past; it is a card whose previous date could not be
        // *seen* - detached because an importer clears its manager every few thousand rows, or
        // mapped to a manager this cannot read. Refusing on that would lock exactly the legacy cards
        // this rule promises stay editable, and it would do it to the batch jobs least able to
        // report why. A guess in the safe direction: only a card with no identity is judged as a
        // creation.
        if (null === $storedExpiryDate && null !== $value->getId()) {
            return;
        }

        // Named on the field, not on the form, so the administrator is told which date is the
        // problem rather than being handed a sentence at the top of a form with eight fields.
        $this->context->buildViolation($constraint->message)
            ->atPath('expiresAt')
            ->addViolation()
        ;
    }
}
