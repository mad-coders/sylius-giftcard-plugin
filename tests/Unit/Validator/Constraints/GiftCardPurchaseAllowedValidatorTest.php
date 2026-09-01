<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardPurchaseAllowed;
use Madcoders\SyliusGiftCardPlugin\Validator\Constraints\GiftCardPurchaseAllowedValidator;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommand;
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
 * Refusing a gift card product at the cart in a channel that only issues cards from the back
 * office. The negative cases matter most: this constraint sits on every add-to-cart in the shop,
 * so an over-eager violation would stop the shop selling anything at all.
 */
final class GiftCardPurchaseAllowedValidatorTest extends ConstraintValidatorTestCase
{
    private bool $sellable = true;

    public function testItRefusesAGiftCardProductInAChannelThatIssuesCardsByAdministratorOnly(): void
    {
        $this->sellable = false;

        $this->validator->validate($this->createCommand(isGiftCard: true), new GiftCardPurchaseAllowed());

        $this->buildViolation('madcoders_sylius_gift_card.cart_item.gift_card_not_sold_in_channel')
            ->atPath('property.path.cartItem')
            ->assertRaised()
        ;
    }

    public function testItAllowsAGiftCardProductInAChannelThatSellsThem(): void
    {
        $this->sellable = true;

        $this->validator->validate($this->createCommand(isGiftCard: true), new GiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesOrdinaryProductsAloneEvenWhenGiftCardsAreNotSold(): void
    {
        // The constraint applies to every add-to-cart in the shop. If it judged ordinary products
        // by the gift card mode, an admin-only channel would sell nothing at all.
        $this->sellable = false;

        $this->validator->validate($this->createCommand(isGiftCard: false), new GiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    public function testItLeavesACartWithNoChannelAlone(): void
    {
        // A cart before the channel context has resolved has no configuration to be judged against.
        $this->sellable = false;

        $this->validator->validate($this->createCommand(isGiftCard: true, withChannel: false), new GiftCardPurchaseAllowed());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $checker = $this->createMock(GiftCardPurchaseCheckerInterface::class);
        $checker->method('canBeBoughtIn')->willReturnCallback(fn (ChannelInterface $channel): bool => $this->sellable);

        return new GiftCardPurchaseAllowedValidator($checker);
    }

    private function createCommand(bool $isGiftCard, bool $withChannel = true): AddToCartCommand
    {
        $product = new Product();
        $product->setCode('PRODUCT-' . ($isGiftCard ? 'GC' : 'ORD'));
        $product->setGiftCard($isGiftCard);

        // Sylius resolves an item's product through its variant, so the fixture has to build the
        // variant rather than setting the product directly.
        $variant = new ProductVariant();
        $variant->setCode($product->getCode() . '-VARIANT');
        $product->addVariant($variant);

        $cartItem = new OrderItem();
        $cartItem->setVariant($variant);

        $cart = new Order();

        if ($withChannel) {
            $currency = new Currency();
            $currency->setCode('USD');

            $channel = new Channel();
            $channel->setCode('WEB');
            $channel->setBaseCurrency($currency);

            $cart->setChannel($channel);
        }

        return new AddToCartCommand($cart, $cartItem);
    }
}
