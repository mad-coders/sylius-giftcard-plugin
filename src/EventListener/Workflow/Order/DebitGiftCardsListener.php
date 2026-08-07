<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\Order;

use Madcoders\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifierInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Symfony Workflow adapter for the `sylius_order` "create" transition (the order being placed).
 *
 * Holds no logic beyond unwrapping the event and delegating.
 */
final readonly class DebitGiftCardsListener
{
    public function __construct(private OrderGiftCardAmountModifierInterface $orderGiftCardAmountModifier)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->orderGiftCardAmountModifier->debit($order);
    }
}
