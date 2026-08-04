<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Modifier;

use Madcoders\SyliusGiftCardPlugin\Exception\InvalidGiftCardAmountException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionType;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifier;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The single write path for a gift card balance. Its whole job is that a balance never moves
 * without a matching ledger entry, so that is what these assert.
 */
final class GiftCardBalanceModifierTest extends TestCase
{
    public function testDebitingMovesTheBalanceAndWritesALedgerEntry(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->createModifier()->debit($giftCard, 1500);

        self::assertSame(3500, $giftCard->getAmount());
        self::assertCount(1, $giftCard->getTransactions());

        $transaction = $giftCard->getTransactions()->first();
        self::assertNotFalse($transaction);
        self::assertSame(GiftCardTransactionType::Debit, $transaction->getType());
        self::assertSame(1500, $transaction->getAmount());
        self::assertSame(3500, $transaction->getBalanceAfter());
        self::assertNull($transaction->getOrder(), 'a manual adjustment has no originating order');
    }

    public function testCreditingMovesTheBalanceAndWritesALedgerEntry(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $this->createModifier()->debit($giftCard, 2000);

        $this->createModifier()->credit($giftCard, 500);

        self::assertSame(3500, $giftCard->getAmount());

        $transaction = $giftCard->getTransactions()->last();
        self::assertNotFalse($transaction);
        self::assertSame(GiftCardTransactionType::Credit, $transaction->getType());
        self::assertSame(500, $transaction->getAmount());
        self::assertSame(3500, $transaction->getBalanceAfter());
    }

    public function testARefusedDebitLeavesNoLedgerEntryBehind(): void
    {
        // The ledger has to stay a faithful account of the balance: recording a movement that was
        // rejected would make the history disagree with the card.
        $giftCard = $this->createGiftCard(5000);

        try {
            $this->createModifier()->debit($giftCard, 6000);
            self::fail('Overdrawing the card should have been refused.');
        } catch (InvalidGiftCardAmountException) {
            // expected
        }

        self::assertSame(5000, $giftCard->getAmount());
        self::assertCount(0, $giftCard->getTransactions());
    }

    public function testARefusedCreditLeavesNoLedgerEntryBehind(): void
    {
        $giftCard = $this->createGiftCard(5000);

        try {
            $this->createModifier()->credit($giftCard, 100);
            self::fail('Crediting above the initial amount should have been refused.');
        } catch (InvalidGiftCardAmountException) {
            // expected
        }

        self::assertSame(5000, $giftCard->getAmount());
        self::assertCount(0, $giftCard->getTransactions());
    }

    public function testTheLedgerAccumulatesInOrder(): void
    {
        $giftCard = $this->createGiftCard(5000);
        $modifier = $this->createModifier();

        $modifier->debit($giftCard, 1000);
        $modifier->debit($giftCard, 2000);
        $modifier->credit($giftCard, 500);

        self::assertCount(3, $giftCard->getTransactions());
        self::assertSame(2500, $giftCard->getAmount());

        $balancesAfter = $giftCard->getTransactions()
            ->map(static fn (GiftCardTransactionInterface $t): int => $t->getBalanceAfter())
            ->toArray()
        ;

        self::assertSame([4000, 2000, 2500], array_values($balancesAfter));
    }

    private function createModifier(): GiftCardBalanceModifier
    {
        /** @var FactoryInterface<GiftCardTransactionInterface> $factory */
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): GiftCardTransaction => new GiftCardTransaction());

        return new GiftCardBalanceModifier($factory);
    }

    private function createGiftCard(int $initialAmount): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('GIFT-TEST');
        $giftCard->setInitialAmount($initialAmount);

        return $giftCard;
    }
}
