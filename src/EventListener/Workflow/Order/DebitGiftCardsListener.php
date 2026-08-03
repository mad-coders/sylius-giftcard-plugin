<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\Order;

use Madcoders\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifierInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Symfony Workflow adapter for the `sylius_order` "create" transition (the order being placed).
 *
 * Sylius 2.x supports two state machine adapters and the host application chooses, so the same
 * modifier is also reachable from a winzou callback - see config/state_machine/winzou/. This class
 * holds no logic beyond unwrapping the event.
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
