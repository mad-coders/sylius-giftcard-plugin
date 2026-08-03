<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\OrderProcessor\OrderGiftCardProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;

/**
 * The order-total behaviour, which is the part of the plugin most likely to cost real money if it
 * is wrong. Uses real Sylius order objects rather than mocks, because the thing under test is how
 * the adjustments interact with Sylius' own total recalculation.
 */
final class OrderGiftCardProcessorTest extends TestCase
{
    public function testItDoesNothingToAnOrderWithoutGiftCards(): void
    {
        $order = $this->createOrder(10_000);

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $order->getTotal());
        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testItDiscountsTheOrderByTheGiftCardBalance(): void
    {
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        self::assertSame(7_000, $order->getTotal());
    }

    public function testTheAdjustmentCarriesTheGiftCardCodeSoItCanBeTracedBack(): void
    {
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);
        self::assertCount(1, $adjustments);

        $adjustment = $adjustments->first();
        self::assertNotFalse($adjustment);
        self::assertSame('GIFT-A', $adjustment->getOriginCode());
        self::assertSame(-3_000, $adjustment->getAmount());
    }

    public function testSeveralGiftCardsStack(): void
    {
        $order = $this->createOrder(10_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));
        $order->addGiftCard($this->createGiftCard('GIFT-B', 2_500));

        $this->createProcessor()->process($order);

        self::assertSame(4_500, $order->getTotal());
        self::assertCount(2, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testAGiftCardWorthMoreThanTheOrderOnlyTakesWhatIsOwed(): void
    {
        // Otherwise the shop would hand back the difference as a negative total, and the card would
        // lose more balance than the customer actually spent.
        $order = $this->createOrder(4_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 10_000));

        $this->createProcessor()->process($order);

        self::assertSame(0, $order->getTotal());

        $adjustment = $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->first();
        self::assertNotFalse($adjustment);
        self::assertSame(-4_000, $adjustment->getAmount());
    }

    public function testOnceTheOrderIsCoveredNoFurtherCardIsCharged(): void
    {
        $order = $this->createOrder(3_000);
        $order->addGiftCard($this->createGiftCard('GIFT-A', 5_000));
        $order->addGiftCard($this->createGiftCard('GIFT-B', 5_000));

        $this->createProcessor()->process($order);

        self::assertSame(0, $order->getTotal());
        self::assertCount(
            1,
            $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT),
            'the second card should be left untouched rather than charged for nothing',
        );
    }

    public function testItSkipsCardsThatCannotBeRedeemed(): void
    {
        $order = $this->createOrder(10_000);

        $disabled = $this->createGiftCard('GIFT-DISABLED', 3_000);
        $disabled->disable();

        $expired = $this->createGiftCard('GIFT-EXPIRED', 3_000);
        $expired->setExpiresAt(new \DateTime('2000-01-01'));

        $order->addGiftCard($disabled);
        $order->addGiftCard($expired);

        $this->createProcessor()->process($order);

        self::assertSame(10_000, $order->getTotal());
        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    public function testItLeavesAnEmptyOrderAlone(): void
    {
        $order = new Order();
        $order->addGiftCard($this->createGiftCard('GIFT-A', 3_000));

        $this->createProcessor()->process($order);

        self::assertCount(0, $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT));
    }

    private function createProcessor(): OrderGiftCardProcessor
    {
        return new OrderGiftCardProcessor($this->createAdjustmentFactory(), new IdentityTranslator());
    }

    private function createAdjustmentFactory(): AdjustmentFactoryInterface
    {
        $factory = $this->createMock(AdjustmentFactoryInterface::class);
        $factory
            ->method('createWithData')
            ->willReturnCallback(static function (string $type, string $label, int $amount): Adjustment {
                $adjustment = new Adjustment();
                $adjustment->setType($type);
                $adjustment->setLabel($label);
                $adjustment->setAmount($amount);

                return $adjustment;
            })
        ;

        return $factory;
    }

    private function createOrder(int $unitPrice): Order
    {
        $order = new Order();

        $item = new OrderItem();
        $item->setUnitPrice($unitPrice);

        $unit = new OrderItemUnit($item);
        self::assertNotNull($unit->getOrderItem());

        $order->addItem($item);

        self::assertSame($unitPrice, $order->getTotal(), 'the fixture order should cost what the test expects');

        return $order;
    }

    private function createGiftCard(string $code, int $amount): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);

        return $giftCard;
    }
}
