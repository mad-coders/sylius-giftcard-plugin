<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\OrderProcessor;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardAmountMode;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Madcoders\SyliusGiftCardPlugin\OrderProcessor\GiftCardChosenAmountProcessor;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\Order;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItem;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Order\OrderItemUnit;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * Pricing a gift card line at what the customer chose.
 *
 * This processor is the plugin's authority on whether an amount may become a price - the shop form
 * only makes the refusal friendly - so the rejection paths matter more than the happy one.
 */
final class GiftCardChosenAmountProcessorTest extends TestCase
{
    private const int CHANNEL_PRICE = 5000;

    public function testAChosenPresetBecomesTheLinePrice(): void
    {
        $order = self::orderWithGiftCardItem(chosenAmount: 15000);

        $this->processorFor(self::presetsConfiguration())->process($order);

        self::assertSame(15000, $order->getItems()->first()->getUnitPrice());
    }

    public function testTheOrderTotalFollowsTheChosenAmount(): void
    {
        // Criterion 4 of the issue: the total, the taxes and the payment all have to see the chosen
        // amount, and they all read the item's price.
        $order = self::orderWithGiftCardItem(chosenAmount: 15000, quantity: 2);

        $this->processorFor(self::presetsConfiguration())->process($order);

        self::assertSame(30000, $order->getItemsTotal());
    }

    public function testAFreeAmountWithinTheChannelBoundsBecomesTheLinePrice(): void
    {
        $order = self::orderWithGiftCardItem(chosenAmount: 12345);

        $this->processorFor(self::rangeConfiguration())->process($order);

        self::assertSame(12345, $order->getItems()->first()->getUnitPrice());
    }

    public function testAnAmountThatIsNotAPresetIsRefusedEvenThoughNoFormWasInvolved(): void
    {
        // The forgery case. Nothing here goes through a form: the amount is written straight onto
        // the order item, exactly as a hand-crafted request that bypassed the shop would leave it.
        $order = self::orderWithGiftCardItem(chosenAmount: 1);

        $this->processorFor(self::presetsConfiguration())->process($order);

        $item = $order->getItems()->first();
        self::assertSame(self::CHANNEL_PRICE, $item->getUnitPrice(), 'the forged amount must not become the price');
        self::assertNull($item->getGiftCardAmount(), 'and it must not survive to be judged again');
    }

    public function testAFreeAmountOutsideTheChannelBoundsIsRefused(): void
    {
        $order = self::orderWithGiftCardItem(chosenAmount: 999999);

        $this->processorFor(self::rangeConfiguration())->process($order);

        self::assertSame(self::CHANNEL_PRICE, $order->getItems()->first()->getUnitPrice());
    }

    public function testAChannelThatSellsAtTheProductPriceRefusesEveryChosenAmount(): void
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Fixed);

        $order = self::orderWithGiftCardItem(chosenAmount: 15000);

        $this->processorFor($configuration)->process($order);

        self::assertSame(self::CHANNEL_PRICE, $order->getItems()->first()->getUnitPrice());
    }

    public function testAChannelWithNoGiftCardConfigurationRefusesEveryChosenAmount(): void
    {
        $order = self::orderWithGiftCardItem(chosenAmount: 15000);

        $this->processorFor(null)->process($order);

        self::assertSame(self::CHANNEL_PRICE, $order->getItems()->first()->getUnitPrice());
    }

    public function testAnOrdinaryProductCannotPriceItself(): void
    {
        // The flag is what makes a product a gift card. Without it, an amount on the line is just a
        // stray column, and honouring it would let any product be bought for any price.
        $order = self::orderWithGiftCardItem(chosenAmount: 1000, isGiftCard: false);

        $this->processorFor(self::rangeConfiguration())->process($order);

        self::assertSame(self::CHANNEL_PRICE, $order->getItems()->first()->getUnitPrice());
    }

    public function testALineWithNoChosenAmountKeepsTheChannelPrice(): void
    {
        $order = self::orderWithGiftCardItem(chosenAmount: null);

        $this->processorFor(self::rangeConfiguration())->process($order);

        $item = $order->getItems()->first();
        self::assertSame(self::CHANNEL_PRICE, $item->getUnitPrice());
        self::assertSame(self::CHANNEL_PRICE, $item->getOriginalUnitPrice());
    }

    public function testAnHonouredAmountLeavesNoStrikethroughPrice(): void
    {
        // A chosen amount is not a discount off the channel price, and showing the two side by side
        // would tell the customer they were getting one.
        $order = self::orderWithGiftCardItem(chosenAmount: 15000);

        $this->processorFor(self::presetsConfiguration())->process($order);

        self::assertNull($order->getItems()->first()->getOriginalUnitPrice());
    }

    public function testAnOrderThatIsNoLongerACartIsLeftAlone(): void
    {
        // Repricing a placed order would move money after the customer has paid.
        $order = self::orderWithGiftCardItem(chosenAmount: 15000);
        $order->setState(Order::STATE_NEW);

        $this->processorFor(self::presetsConfiguration())->process($order);

        self::assertSame(self::CHANNEL_PRICE, $order->getItems()->first()->getUnitPrice());
    }

    private function processorFor(?GiftCardConfigurationInterface $configuration): GiftCardChosenAmountProcessor
    {
        $provider = $this->createMock(GiftCardConfigurationProviderInterface::class);
        $provider->method('getForChannel')->willReturn($configuration);

        return new GiftCardChosenAmountProcessor($provider);
    }

    private static function presetsConfiguration(): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Presets);
        $configuration->setAmountPresets([5000, 15000]);

        return $configuration;
    }

    private static function rangeConfiguration(): GiftCardConfiguration
    {
        $configuration = new GiftCardConfiguration();
        $configuration->setAmountMode(GiftCardAmountMode::Range);
        $configuration->setMinimumAmount(1000);
        $configuration->setMaximumAmount(50000);

        return $configuration;
    }

    private static function orderWithGiftCardItem(
        ?int $chosenAmount,
        int $quantity = 1,
        bool $isGiftCard = true,
    ): Order {
        $currency = new Currency();
        $currency->setCode('USD');

        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setBaseCurrency($currency);

        $order = new Order();
        $order->setChannel($channel);

        $product = new Product();
        $product->setCode('PRODUCT');
        $product->setGiftCard($isGiftCard);

        // Sylius resolves an item's product through its variant, so the fixture has to build one.
        $variant = new ProductVariant();
        $variant->setCode('PRODUCT-VARIANT');
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);

        // Quantity is a consequence of the units in Sylius, not a setter.
        for ($i = 0; $i < $quantity; ++$i) {
            new OrderItemUnit($item);
        }

        // Where Sylius' own price recalculator, which runs immediately above this processor, leaves
        // the line.
        $item->setUnitPrice(self::CHANNEL_PRICE);
        $item->setOriginalUnitPrice(self::CHANNEL_PRICE);
        $item->setGiftCardAmount($chosenAmount);

        $order->addItem($item);

        return $order;
    }
}
