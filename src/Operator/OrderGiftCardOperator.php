<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

/**
 * Handles the gift cards *bought on* an order. The cards *spent on* an order are somebody else's
 * job - see OrderGiftCardAmountModifier.
 *
 * One card is issued per purchased unit, not per order item: buying three gift cards produces three
 * separate codes, which is why the card hangs off the OrderItemUnit.
 */
final readonly class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    public function __construct(
        private GiftCardFactoryInterface $giftCardFactory,
        private GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private ObjectManager $giftCardManager,
    ) {
    }

    public function generate(OrderInterface $order): void
    {
        $channel = $order->getChannel();
        if (null === $channel) {
            return;
        }

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();

        foreach ($this->giftCardUnitsOf($order) as $unit) {
            // Only ever issue a card for a unit that has none. Nothing guarantees a transition
            // fires exactly once, and a second run would hand out free money.
            if (null !== $unit->getGiftCard()) {
                continue;
            }

            // The face value is what was actually paid for the unit, adjustments included - not the
            // product's list price. Issuing a card worth more than the customer paid for it would
            // turn any promotion on a gift card product into an arbitrage.
            $amount = $unit->getTotal();
            if ($amount <= 0) {
                continue;
            }

            $giftCard = $this->giftCardFactory->createForChannel(
                $channel,
                $amount,
                GiftCardOrigin::Order,
                $configuration,
            );

            $giftCard->setCode($this->giftCardCodeGenerator->generate($configuration));
            $giftCard->setPurchaser($customer);
            $giftCard->setOrderItemUnit($unit);

            // The unit holds the inverse side of the association, so Doctrine will not persist the
            // card by reachability - it has to be told. Flushing is left to whatever is driving the
            // transition, so this stays inside the caller's unit of work.
            $this->giftCardManager->persist($giftCard);
        }
    }

    public function enable(OrderInterface $order): void
    {
        foreach ($this->giftCardsBoughtOn($order) as $giftCard) {
            $giftCard->enable();
        }
    }

    public function disable(OrderInterface $order): void
    {
        foreach ($this->giftCardsBoughtOn($order) as $giftCard) {
            $giftCard->disable();
        }
    }

    /**
     * The cards bought on this order.
     *
     * @return list<GiftCardInterface>
     */
    public function giftCardsBoughtOn(OrderInterface $order): array
    {
        $giftCards = [];

        foreach ($this->giftCardUnitsOf($order) as $unit) {
            $giftCard = $unit->getGiftCard();

            if (null !== $giftCard) {
                $giftCards[] = $giftCard;
            }
        }

        return $giftCards;
    }

    /**
     * Every unit on the order that belongs to a gift card product.
     *
     * @return list<OrderItemUnitInterface>
     */
    private function giftCardUnitsOf(OrderInterface $order): array
    {
        $units = [];

        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            // A host application that has not applied ProductTrait simply sells no gift cards.
            if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
                continue;
            }

            foreach ($item->getUnits() as $unit) {
                if ($unit instanceof OrderItemUnitInterface) {
                    $units[] = $unit;
                }
            }
        }

        return $units;
    }
}
