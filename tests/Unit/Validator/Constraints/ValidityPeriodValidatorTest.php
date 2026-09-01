<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Calculator\GiftCardExpiryCalculator;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\ValidityPeriod;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\ValidityPeriodValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Refusing a validity period the shop cannot act on.
 *
 * The point is that the operator finds out. Before this, "1 yaer" saved cleanly and quietly issued
 * cards that never expired, and nothing anywhere said so.
 */
final class ValidityPeriodValidatorTest extends ConstraintValidatorTestCase
{
    /** @return iterable<string, array{string}> */
    public static function usablePeriods(): iterable
    {
        yield 'a year' => ['1 year'];
        yield 'months' => ['18 months'];
        yield 'days' => ['30 days'];
        yield 'a compound period' => ['1 year 6 months'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('usablePeriods')]
    public function testItAcceptsAPeriodTheShopCanAct0n(string $validityPeriod): void
    {
        $this->validator->validate($validityPeriod, new ValidityPeriod());

        $this->assertNoViolation();
    }

    /** @return iterable<string, array{string}> */
    public static function unusablePeriods(): iterable
    {
        yield 'not a period at all' => ['not a period'];
        yield 'a typo' => ['1 yaer'];
        yield 'moves nothing' => ['0 days'];
        yield 'moves backwards' => ['-1 year'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusablePeriods')]
    public function testItRefusesAPeriodThatCannotExpireACard(string $validityPeriod): void
    {
        $this->validator->validate($validityPeriod, new ValidityPeriod());

        $this->buildViolation('madcoders_sylius_gift_card.gift_card_configuration.validity_period.unparseable')->assertRaised();
    }

    public function testItLeavesABlankPeriodToNotBlank(): void
    {
        // Two constraints, two messages. "That is not a period" is the wrong answer to an empty
        // field, and raising both would show the operator two errors for one mistake.
        $this->validator->validate(null, new ValidityPeriod());
        $this->validator->validate('', new ValidityPeriod());
        $this->validator->validate('   ', new ValidityPeriod());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        // The real calculator, not a mock: what this constraint promises is that the form refuses
        // exactly the periods the calculator would refuse to honour, and a mock could not fail to
        // keep that promise.
        return new ValidityPeriodValidator(new GiftCardExpiryCalculator());
    }
}
