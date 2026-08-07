<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\EventListener\Workflow\OrderPayment;

use Madcoders\SyliusGiftCardPlugin\Mailer\GiftCardEmailSenderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Emails the purchased gift card codes once the order is paid.
 *
 * Registered at a lower priority than the issuing listener, so the cards exist by the time this
 * runs.
 */
final readonly class SendGiftCardsListener
{
    public function __construct(private GiftCardEmailSenderInterface $giftCardEmailSender)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->giftCardEmailSender->sendGiftCardsFromOrder($order);
    }
}
