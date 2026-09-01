<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Factory\OrderItemUnitFactory;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifier;
use Sylius\Component\Order\Modifier\OrderModifier;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItem;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItemUnit;

/**
 * Whether two gift cards added to the same cart become one line or two.
 *
 * Driven through Sylius' real `OrderModifier`, which is what the add-to-cart listener calls. That
 * matters: `OrderModifier` asks `equals()` and, on a match, bumps the existing line's quantity and
 * throws the incoming item away whole. Asserting against `Order::addItem()` instead would prove
 * nothing, because that method guards on identity and can never merge.
 */
final class OrderItemMergeTest extends TestCase
{
    public function testTwoGiftCardsOfDifferentAmountsStayTwoLines(): void
    {
        // The bug this exists to stop: one variant, two amounts. Merging them charges the customer
        // twice the cheaper card and issues two of it, or twice the dearer one - depending only on
        // which they clicked first.
        $order = self::order();
        $variant = self::variant();

        self::modifier()->addToOrder($order, self::item($variant, amount: 2500, message: 'For Ann'));
        self::modifier()->addToOrder($order, self::item($variant, amount: 10000, message: 'For Bob'));

        self::assertCount(2, $order->getItems());
        self::assertSame([2500, 10000], self::amountsOn($order));
        self::assertSame(['For Ann', 'For Bob'], self::messagesOn($order));
    }

    public function testTwoGiftCardsOfTheSameAmountWithDifferentMessagesStayTwoLines(): void
    {
        $order = self::order();
        $variant = self::variant();

        self::modifier()->addToOrder($order, self::item($variant, amount: 5000, message: 'For Ann'));
        self::modifier()->addToOrder($order, self::item($variant, amount: 5000, message: 'For Bob'));

        self::assertCount(2, $order->getItems());
        self::assertSame(['For Ann', 'For Bob'], self::messagesOn($order));
    }

    public function testTwoIdenticalGiftCardsStillMergeIntoAQuantityOfTwo(): void
    {
        // Buying two identical cards is one line of two, exactly as buying two of anything else is.
        // Splitting them would be its own bug - two lines a customer cannot tell apart.
        $order = self::order();
        $variant = self::variant();

        self::modifier()->addToOrder($order, self::item($variant, amount: 5000, message: 'For Ann'));
        self::modifier()->addToOrder($order, self::item($variant, amount: 5000, message: 'For Ann'));

        self::assertCount(1, $order->getItems());
        self::assertSame(2, $order->getItems()->first()->getQuantity());
    }

    public function testTwoGiftCardsWithNoChoiceAtAllStillMerge(): void
    {
        // A channel that sells gift cards at the product's price makes no choice to keep apart, so
        // the lines have to behave exactly as they did before this feature existed.
        $order = self::order();
        $variant = self::variant();

        self::modifier()->addToOrder($order, self::item($variant, amount: null, message: null));
        self::modifier()->addToOrder($order, self::item($variant, amount: null, message: null));

        self::assertCount(1, $order->getItems());
        self::assertSame(2, $order->getItems()->first()->getQuantity());
    }

    public function testOrdinaryProductsAreUnaffected(): void
    {
        // Nothing outside a gift card carries an amount or a message, so this reduces to Sylius'
        // own answer and every other product in the shop merges as it always did.
        $order = self::order();
        $variant = self::variant();

        $first = new OrderItem();
        $first->setVariant($variant);
        new OrderItemUnit($first);

        $second = new OrderItem();
        $second->setVariant($variant);
        new OrderItemUnit($second);

        self::modifier()->addToOrder($order, $first);
        self::modifier()->addToOrder($order, $second);

        self::assertCount(1, $order->getItems());
        self::assertSame(2, $order->getItems()->first()->getQuantity());
    }

    public function testDifferentVariantsNeverMergeWhateverTheyCarry(): void
    {
        $order = self::order();

        self::modifier()->addToOrder($order, self::item(self::variant('ONE'), amount: 5000, message: 'x'));
        self::modifier()->addToOrder($order, self::item(self::variant('TWO'), amount: 5000, message: 'x'));

        self::assertCount(2, $order->getItems());
    }

    private static function modifier(): OrderModifier
    {
        // A no-op processor: this test is about which line the item lands on, and the pricing that
        // follows has its own test.
        $processor = new class() implements OrderProcessorInterface {
            public function process(\Sylius\Component\Order\Model\OrderInterface $order): void
            {
            }
        };

        return new OrderModifier($processor, new OrderItemQuantityModifier(new OrderItemUnitFactory(OrderItemUnit::class)));
    }

    private static function order(): Order
    {
        return new Order();
    }

    private static function variant(string $code = 'GIFT-CARD-VARIANT'): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);

        return $variant;
    }

    private static function item(ProductVariant $variant, ?int $amount, ?string $message): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setGiftCardAmount($amount);
        $item->setGiftCardMessage($message);
        new OrderItemUnit($item);

        return $item;
    }

    /** @return list<int|null> */
    private static function amountsOn(Order $order): array
    {
        $amounts = [];

        foreach ($order->getItems() as $item) {
            $amounts[] = $item->getGiftCardAmount();
        }

        return $amounts;
    }

    /** @return list<string|null> */
    private static function messagesOn(Order $order): array
    {
        $messages = [];

        foreach ($order->getItems() as $item) {
            $messages[] = $item->getGiftCardMessage();
        }

        return $messages;
    }
}
