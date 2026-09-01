<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\OrderGiftCardPurchaseAllowed;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\OrderGiftCardPurchaseAllowedValidator;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\Madcoders\SyliusGiftCardPlugin\Entity\Product\Product;

/**
 * Refusing to complete an order carrying a gift card in a channel that only issues cards from the
 * back office.
 *
 * This is the constraint that stops the customer *before* they are charged, so its negative cases
 * matter as much as its positive one: it runs on every checkout in the shop.
 */
final class OrderGiftCardPurchaseAllowedValidatorTest extends ConstraintValidatorTestCase
{
    private bool $sellable = true;

    public function testItRefusesAnOrderCarryingAGiftCardInAChannelThatIssuesCardsByAdministratorOnly(): void
    {
        $this->sellable = false;

        $this->validator->validate($this->createOrder(giftCardItems: 1), new OrderGiftCardPurchaseAllowed());

        $this->buildViolation('madcoders_sylius_gift_card.order.gift_card_not_sold_in_channel')->assertRaised();
    }

    public function testItRaisesOneViolationForAnOrderCarryingSeveralGiftCards(): void
    {
        // Repeating the same sentence per offending line tells the customer nothing new.
        $this->sellable = false;

        $this->validator->validate($this->createOrder(giftCardItems: 3), new OrderGiftCardPurchaseAllowed());

        $this->buildViolation('madcoders_sylius_gift_card.order.gift_card_not_sold_in_channel')->assertRaised();
    }

    public function testItAllowsAnOrderCarryingAGiftCardInAChannelThatSellsThem(): void
    {
        $this->sellable = true;

        $this->validator->validate($this->createOrder(giftCardItems: 1), new OrderGiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesAnOrdinaryOrderAloneEvenWhenGiftCardsAreNotSold(): void
    {
        // Every checkout in an admin-only channel runs this. Judging ordinary orders by the gift
        // card mode would stop the channel selling anything at all.
        $this->sellable = false;

        $this->validator->validate($this->createOrder(giftCardItems: 0, ordinaryItems: 2), new OrderGiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesAnOrderWithNoChannelAlone(): void
    {
        // Sylius' own constraints deal with an order that has no channel; there is no configuration
        // here to judge it against.
        $this->sellable = false;

        $this->validator->validate($this->createOrder(giftCardItems: 1, withChannel: false), new OrderGiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $checker = $this->createMock(GiftCardPurchaseCheckerInterface::class);
        $checker->method('canBeBoughtIn')->willReturnCallback(fn (ChannelInterface $channel): bool => $this->sellable);

        return new OrderGiftCardPurchaseAllowedValidator($checker);
    }

    private function createOrder(int $giftCardItems, int $ordinaryItems = 0, bool $withChannel = true): Order
    {
        $order = new Order();

        if ($withChannel) {
            $currency = new Currency();
            $currency->setCode('USD');

            $channel = new Channel();
            $channel->setCode('WEB');
            $channel->setBaseCurrency($currency);

            $order->setChannel($channel);
        }

        for ($i = 0; $i < $giftCardItems; ++$i) {
            $order->addItem($this->createItem(isGiftCard: true, suffix: (string) $i));
        }

        for ($i = 0; $i < $ordinaryItems; ++$i) {
            $order->addItem($this->createItem(isGiftCard: false, suffix: (string) $i));
        }

        return $order;
    }

    private function createItem(bool $isGiftCard, string $suffix): OrderItem
    {
        $product = new Product();
        $product->setCode('PRODUCT-' . ($isGiftCard ? 'GC' : 'ORD') . '-' . $suffix);
        $product->setGiftCard($isGiftCard);

        // Sylius resolves an item's product through its variant, so the fixture has to build the
        // variant rather than setting the product directly.
        $variant = new ProductVariant();
        $variant->setCode($product->getCode() . '-VARIANT');
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);

        return $item;
    }
}
