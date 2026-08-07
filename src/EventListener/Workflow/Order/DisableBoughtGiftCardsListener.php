<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\Order;

use Madcoders\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Symfony Workflow adapter for the `sylius_order` "cancel" transition.
 *
 * Cancelling the order a gift card was bought on takes the card out of circulation - the shop was
 * not paid for it. The card is disabled rather than deleted so its history survives, and so an
 * admin can put it back if the cancellation was a mistake.
 */
final readonly class DisableBoughtGiftCardsListener
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

        $this->orderGiftCardOperator->disable($order);
    }
}
