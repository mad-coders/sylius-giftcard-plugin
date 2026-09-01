<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface as GiftCardOrderItemInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Buying a gift card for an amount the customer chose, and with a message on it.
 *
 * The choice is written onto the order item and the order is then put through Sylius' real order
 * processor chain - which is where the plugin's own processor lives. So these scenarios exercise the
 * thing that actually decides the price, rather than asserting a price the test itself wrote.
 *
 * That also makes the forged-amount step honest: it writes an amount the channel never offered
 * straight onto the item, exactly as a request that never went near the shop form would leave it.
 */
final readonly class GiftCardPurchaseContext implements Context
{
    /** @param FactoryInterface<OrderItemInterface> $orderItemFactory */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private OrderProcessorInterface $orderProcessor,
        private ObjectManager $orderManager,
    ) {
    }

    /**
     * @Given the customer bought a :product for :amount
     */
    public function theCustomerBoughtAGiftCardFor(ProductInterface $product, string $amount): void
    {
        $this->buy($product, self::toMinorUnits($amount));
    }

    /**
     * @Given the customer bought :quantity :product products for :amount each
     */
    public function theCustomerBoughtSeveralGiftCardsFor(int $quantity, ProductInterface $product, string $amount): void
    {
        $this->buy($product, self::toMinorUnits($amount), quantity: $quantity);
    }

    /**
     * @Given the customer bought a :product for :amount saying :message
     */
    public function theCustomerBoughtAGiftCardForSaying(ProductInterface $product, string $amount, string $message): void
    {
        $this->buy($product, self::toMinorUnits($amount), message: $message);
    }

    /**
     * @Given the customer bought a :product saying :message
     */
    public function theCustomerBoughtAGiftCardSaying(ProductInterface $product, string $message): void
    {
        $this->buy($product, null, message: $message);
    }

    /**
     * The forgery. Nothing here goes near the shop form: the amount is written onto the order item
     * exactly as a hand-crafted request that skipped the form would leave it, and the order is then
     * processed normally.
     *
     * @Given the customer submitted an amount of :amount for a :product without using the shop form
     */
    public function theCustomerSubmittedAnAmountWithoutUsingTheShopForm(string $amount, ProductInterface $product): void
    {
        $this->buy($product, self::toMinorUnits($amount));
    }

    private function buy(ProductInterface $product, ?int $chosenAmount, int $quantity = 1, ?string $message = null): void
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        $variant = $product->getVariants()->first();
        Assert::isInstanceOf($variant, ProductVariantInterface::class);

        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $this->orderItemQuantityModifier->modify($item, $quantity);

        Assert::isInstanceOf(
            $item,
            GiftCardOrderItemInterface::class,
            'The application\'s OrderItem must carry the plugin\'s trait for a customer to choose an amount.',
        );

        $item->setGiftCardAmount($chosenAmount);
        $item->setGiftCardMessage($message);

        $order->addItem($item);

        // The order is still a cart at this point, so the whole chain runs - including the plugin's
        // processor, which is what turns the chosen amount into the line's price.
        $this->orderProcessor->process($order);

        $this->orderManager->flush();
    }

    private static function toMinorUnits(string $amount): int
    {
        $normalised = preg_replace('/[^0-9.]/', '', $amount) ?? '';

        return (int) round(((float) $normalised) * 100);
    }
}
