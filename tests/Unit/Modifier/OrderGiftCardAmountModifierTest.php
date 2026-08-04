<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Modifier;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionType;
use Madcoders\SyliusGiftCardPlugin\Modifier\GiftCardBalanceModifier;
use Madcoders\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Resource\Factory\FactoryInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * Balance movement when an order is placed and cancelled. Getting this wrong either charges a
 * customer twice or gives away balance, so the edge cases matter more than the happy path.
 */
final class OrderGiftCardAmountModifierTest extends TestCase
{
    public function testPlacingAnOrderTakesTheChargedAmountOffTheCard(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $this->createModifier()->debit($order);

        self::assertSame(3_000, $giftCard->getAmount());
    }

    public function testEveryDebitIsRecordedInTheLedger(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $this->createModifier()->debit($order);

        self::assertCount(1, $giftCard->getTransactions());

        $transaction = $giftCard->getTransactions()->first();
        self::assertNotFalse($transaction);
        self::assertSame(GiftCardTransactionType::Debit, $transaction->getType());
        self::assertSame(2_000, $transaction->getAmount(), 'the ledger records a positive amount; the direction is the type');
        self::assertSame(3_000, $transaction->getBalanceAfter());
        self::assertSame($order, $transaction->getOrder());
    }

    public function testCancellingAnOrderPutsTheChargedAmountBack(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $modifier = $this->createModifier();
        $modifier->debit($order);
        $modifier->credit($order);

        self::assertSame(5_000, $giftCard->getAmount());
        self::assertCount(2, $giftCard->getTransactions());
    }

    public function testItOnlyGivesBackWhatWasActuallyCharged(): void
    {
        // The card was worth more than the order, so only part of it was spent. Cancelling must
        // restore that part - not the card's full value.
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 1_200], [$giftCard]);

        $modifier = $this->createModifier();
        $modifier->debit($order);
        $modifier->credit($order);

        self::assertSame(5_000, $giftCard->getAmount());
    }

    public function testCancellingTwiceDoesNotInflateTheCard(): void
    {
        // Nothing guarantees a transition fires exactly once, and a card that gains balance every
        // time somebody cancels an order is free money.
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $modifier = $this->createModifier();
        $modifier->debit($order);
        $modifier->credit($order);
        $modifier->credit($order);

        self::assertSame(5_000, $giftCard->getAmount());
    }

    public function testItDebitsOnlyWhatIsLeftIfTheCardWasSpentElsewhereMeanwhile(): void
    {
        // The adjustment was capped against the balance when the cart was processed, but another
        // order may have drained the card before this one was placed. Placing the order must not
        // blow up.
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $giftCard->debit(4_500);

        $this->createModifier()->debit($order);

        self::assertSame(0, $giftCard->getAmount());
    }

    public function testSeveralAdjustmentsForTheSameCardAreSummed(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder([], [$giftCard]);
        $this->addGiftCardAdjustment($order, 'GIFT-A', 1_000);
        $this->addGiftCardAdjustment($order, 'GIFT-A', 500);

        $this->createModifier()->debit($order);

        self::assertSame(3_500, $giftCard->getAmount());
    }

    public function testTheFirstRedeemerIsRecordedOnTheCard(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $customer = new Customer();
        $order->setCustomer($customer);

        $this->createModifier()->debit($order);

        self::assertSame($customer, $giftCard->getRedeemer());
    }

    public function testAGuestOrderLeavesTheCardWithoutARedeemer(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-A' => 2_000], [$giftCard]);

        $this->createModifier()->debit($order);

        self::assertNull($giftCard->getRedeemer());
        self::assertSame(3_000, $giftCard->getAmount(), 'the balance still moves for a guest');
    }

    public function testAnAdjustmentForAnUnknownCardIsIgnored(): void
    {
        $giftCard = $this->createGiftCard('GIFT-A', 5_000);
        $order = $this->createOrder(['GIFT-UNKNOWN' => 2_000], [$giftCard]);

        $this->createModifier()->debit($order);

        self::assertSame(5_000, $giftCard->getAmount());
    }

    private function createModifier(): OrderGiftCardAmountModifier
    {
        // The real balance modifier rather than a mock: the behaviour under test is the interplay
        // between the adjustments and what actually lands on the card and in its ledger, which a
        // mocked collaborator would assert away.
        /** @var FactoryInterface<GiftCardTransactionInterface> $factory */
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): GiftCardTransaction => new GiftCardTransaction());

        return new OrderGiftCardAmountModifier(new GiftCardBalanceModifier($factory));
    }

    /**
     * @param array<string, int>          $chargedAmountsByCode
     * @param list<GiftCardInterface>     $giftCards
     */
    private function createOrder(array $chargedAmountsByCode, array $giftCards): Order
    {
        $order = new Order();

        $item = new OrderItem();
        $item->setUnitPrice(100_000);
        $order->addItem($item);

        foreach ($giftCards as $giftCard) {
            $order->addGiftCard($giftCard);
        }

        foreach ($chargedAmountsByCode as $code => $amount) {
            $this->addGiftCardAdjustment($order, $code, $amount);
        }

        return $order;
    }

    private function addGiftCardAdjustment(Order $order, string $code, int $amount): void
    {
        $adjustment = new Adjustment();
        $adjustment->setType(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);
        $adjustment->setLabel('Gift card');
        $adjustment->setAmount(-$amount);
        $adjustment->setOriginCode($code);

        $order->addAdjustment($adjustment);
    }

    private function createGiftCard(string $code, int $amount): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);

        return $giftCard;
    }
}
