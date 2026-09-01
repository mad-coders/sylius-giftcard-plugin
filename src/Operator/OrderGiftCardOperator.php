<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardPurchaseCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Madcoders\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardOrigin;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface;
use Madcoders\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface as BaseOrderItemInterface;

/**
 * Handles the gift cards *bought on* an order. The cards *spent on* an order are somebody else's
 * job - see OrderGiftCardAmountModifier.
 *
 * One card is issued per purchased unit, not per order item: buying three gift cards produces three
 * separate codes, which is why the card hangs off the OrderItemUnit.
 */
final readonly class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    private LoggerInterface $logger;

    /**
     * The checker and the logger are appended rather than slotted in, so a host that redefined this
     * service with positional arguments keeps its existing four bound to the same parameters and
     * fails loudly on arity instead of silently binding the manager into the checker's slot.
     */
    public function __construct(
        private GiftCardFactoryInterface $giftCardFactory,
        private GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private GiftCardConfigurationProviderInterface $giftCardConfigurationProvider,
        private ObjectManager $giftCardManager,
        private GiftCardPurchaseCheckerInterface $giftCardPurchaseChecker,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function generate(OrderInterface $order): void
    {
        $channel = $order->getChannel();
        if (null === $channel) {
            return;
        }

        $units = $this->giftCardUnitsOf($order);

        // Checked here as well as at the cart and at checkout, because a cart outlives the setting:
        // a customer can fill it while the channel still sells gift cards and pay days after it
        // stops. This is the last line, and by the time it runs the customer has been charged - so
        // it is loud. Anyone reaching it means the checkout constraint was bypassed (an order
        // completed through a path that does not validate, or the mode changed between validation
        // and payment), and somebody has to reconcile the money by hand.
        if ([] !== $units && !$this->giftCardPurchaseChecker->canBeBoughtIn($channel)) {
            $this->logger->warning(
                'Refused to issue gift cards for a paid order: channel does not sell gift cards. The customer has been charged and no card was issued - this needs reconciling by hand.',
                [
                    'order_number' => $order->getNumber(),
                    'order_id' => $order->getId(),
                    'channel_code' => $channel->getCode(),
                    'gift_card_units' => \count($units),
                ],
            );

            return;
        }

        $configuration = $this->giftCardConfigurationProvider->getForChannel($channel);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();

        foreach ($units as $unit) {
            // Only ever issue a card for a unit that has none. Nothing guarantees a transition
            // fires exactly once, and a second run would hand out free money.
            if (null !== $unit->getGiftCard()) {
                continue;
            }

            $amount = $this->faceValueOf($unit);
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

            // The message the customer wrote on the line this unit belongs to. Two lines bought in
            // one order therefore keep their own messages, and a line bought in quantity three puts
            // the same message on all three cards.
            $orderItem = $unit->getOrderItem();
            if ($orderItem instanceof OrderItemInterface) {
                $giftCard->setCustomMessage($orderItem->getGiftCardMessage());
            }

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
     * What a purchased unit puts on the card it issues.
     *
     * The value of the goods, not the invoice line: what was actually paid for the unit, promotions
     * included, minus any tax charged on top of it.
     *
     * - **Promotions are included** because a card worth more than the customer paid for it turns
     *   any promotion on a gift card product into an arbitrage.
     * - **Tax is excluded** because tax is not part of what the card is worth. In a tax-inclusive
     *   shop Sylius records tax as a neutral adjustment and the unit total already excludes it; in a
     *   tax-exclusive shop the tax is added on top, and leaving it in would hand a customer who
     *   asked for a 50 card a 55 one. Both shops now issue the same card for the same choice.
     */
    private function faceValueOf(OrderItemUnitInterface $unit): int
    {
        return $unit->getTotal() - $unit->getAdjustmentsTotal(AdjustmentInterface::TAX_ADJUSTMENT);
    }

    /**
     * Every unit on the order that belongs to a gift card product.
     *
     * @return list<OrderItemUnitInterface>
     */
    private function giftCardUnitsOf(OrderInterface $order): array
    {
        $units = [];

        /** @var BaseOrderItemInterface $item */
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
