<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\OrderPayment;

use Madcoders\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Symfony Workflow adapter for the `sylius_order_payment` "pay" transition: the point a purchased
 * gift card becomes real.
 *
 * Generation waits for payment rather than for the order being placed, so an unpaid order never
 * hands out spendable codes.
 */
final readonly class IssueGiftCardsListener
{
    public function __construct(private OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->orderGiftCardOperator->generate($order);
        $this->orderGiftCardOperator->enable($order);
    }
}
