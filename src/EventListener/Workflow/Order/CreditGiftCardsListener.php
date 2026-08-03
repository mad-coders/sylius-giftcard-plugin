<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\Order;

use Madcoders\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifierInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Symfony Workflow adapter for the `sylius_order` "cancel" transition.
 *
 * @see DebitGiftCardsListener for why both adapters are wired.
 */
final readonly class CreditGiftCardsListener
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

        $this->orderGiftCardAmountModifier->credit($order);
    }
}
