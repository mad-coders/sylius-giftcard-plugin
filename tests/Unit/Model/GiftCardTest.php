<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Model;

use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;

/**
 * Covers the invariants listed in ai/coding-rules.md - the rules the rest of the plugin is allowed
 * to assume hold.
 */
final class GiftCardTest extends TestCase
{
    public function testItStartsWithItsFullValueAvailable(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);

        self::assertSame(5000, $giftCard->getInitialAmount());
        self::assertSame(5000, $giftCard->getAmount());
        self::assertSame(0, $giftCard->getSpentAmount());
    }

    public function testItRefusesToChangeItsInitialAmount(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);

        $this->expectException(InvalidGiftCardAmountException::class);

        $giftCard->setInitialAmount(9000);
    }

    /** @dataProvider nonPositiveAmounts */
    public function testItRefusesANonPositiveInitialAmount(int $initialAmount): void
    {
        $this->expectException(InvalidGiftCardAmountException::class);

        (new GiftCard())->setInitialAmount($initialAmount);
    }

    public function testDebitingReducesTheBalanceAndRaisesTheSpentAmount(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $giftCard->debit(1500);

        self::assertSame(3500, $giftCard->getAmount());
        self::assertSame(1500, $giftCard->getSpentAmount());
        self::assertSame(5000, $giftCard->getInitialAmount(), 'the initial amount is untouched by spending');
    }

    public function testItCanBeSpentDownToExactlyZero(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $giftCard->debit(5000);

        self::assertSame(0, $giftCard->getAmount());
        self::assertFalse($giftCard->isRedeemable(), 'an empty card has nothing left to redeem');
    }

    public function testItRefusesToBeOverdrawn(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->debit(4000);

        $this->expectException(InvalidGiftCardAmountException::class);

        $giftCard->debit(1001);
    }

    public function testCreditingRestoresTheBalance(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->debit(2000);

        $giftCard->credit(2000);

        self::assertSame(5000, $giftCard->getAmount());
        self::assertSame(0, $giftCard->getSpentAmount());
    }

    public function testItRefusesToBeCreditedAboveItsInitialAmount(): void
    {
        // A cancelled order can only ever give back what it took; anything more means a bug
        // upstream, and silently inflating the card would turn it into free money.
        $giftCard = $this->createGiftCard(5000);
        $giftCard->debit(1000);

        $this->expectException(InvalidGiftCardAmountException::class);

        $giftCard->credit(1001);
    }

    /** @dataProvider nonPositiveAmounts */
    public function testItRefusesANonPositiveDebit(int $amount): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->expectException(InvalidGiftCardAmountException::class);

        $giftCard->debit($amount);
    }

    /** @dataProvider nonPositiveAmounts */
    public function testItRefusesANonPositiveCredit(int $amount): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->debit(1000);

        $this->expectException(InvalidGiftCardAmountException::class);

        $giftCard->credit($amount);
    }

    public function testItCannotBeCreditedBeforeItHasAnInitialAmount(): void
    {
        $this->expectException(InvalidGiftCardAmountException::class);

        (new GiftCard())->credit(100);
    }

    public function testAnUnexpiringCardIsNeverExpired(): void
    {
        $giftCard = $this->createGiftCard(5000);

        self::assertFalse($giftCard->isExpired());
        self::assertTrue($giftCard->isRedeemable());
    }

    public function testItExpiresOnceItsExpiryDateHasPassed(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->setExpiresAt(new \DateTime('2026-01-01 12:00:00'));

        self::assertFalse($giftCard->isExpired(new \DateTime('2025-12-31 23:59:59')));
        self::assertTrue($giftCard->isExpired(new \DateTime('2026-01-01 12:00:01')));
    }

    public function testAnExpiredCardIsNotRedeemable(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->setExpiresAt(new \DateTime('2026-01-01 12:00:00'));

        self::assertFalse($giftCard->isRedeemable(new \DateTime('2026-01-02 00:00:00')));
    }

    public function testADisabledCardIsNotRedeemable(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $giftCard->disable();

        self::assertFalse($giftCard->isRedeemable());
    }

    public function testItKeepsTheFirstCustomerWhoRedeemsIt(): void
    {
        // The redeemer link is what lets that customer track the remaining balance in their
        // account, so handing the code on to somebody else must not take the card away from them.
        $giftCard = $this->createGiftCard(5000);
        $firstRedeemer = new Customer();
        $secondRedeemer = new Customer();

        $giftCard->assignRedeemer($firstRedeemer);
        $giftCard->assignRedeemer($secondRedeemer);

        self::assertSame($firstRedeemer, $giftCard->getRedeemer());
    }

    public function testThePurchaserAndTheRedeemerAreIndependent(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $purchaser = new Customer();
        $redeemer = new Customer();

        $giftCard->setPurchaser($purchaser);
        $giftCard->assignRedeemer($redeemer);

        self::assertSame($purchaser, $giftCard->getPurchaser());
        self::assertSame($redeemer, $giftCard->getRedeemer());
    }

    public function testItDefaultsToAdminOrigin(): void
    {
        self::assertSame(GiftCardOrigin::Admin, (new GiftCard())->getOrigin());
    }

    /** @return iterable<string, array{int}> */
    public static function nonPositiveAmounts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    private function createGiftCard(int $initialAmount): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-TEST');
        $giftCard->setInitialAmount($initialAmount);

        return $giftCard;
    }
}
