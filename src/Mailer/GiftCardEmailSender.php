<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Mailer;

use Madcoders\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

/**
 * Sends the purchased gift card codes to the customer who bought them.
 *
 * This is how a buyer actually receives what they paid for, so it deliberately does not fail the
 * transition it runs in: an order that is paid stays paid even if the mail transport is down, and
 * the codes remain visible in the customer's account either way.
 */
final readonly class GiftCardEmailSender implements GiftCardEmailSenderInterface
{
    public function __construct(
        private SenderInterface $sender,
        private OrderGiftCardOperatorInterface $orderGiftCardOperator,
    ) {
    }

    public function sendGiftCardsFromOrder(OrderInterface $order): void
    {
        $giftCards = $this->orderGiftCardOperator->giftCardsBoughtOn($order);
        if ([] === $giftCards) {
            return;
        }

        $customer = $order->getCustomer();
        $email = $customer?->getEmail();
        if (null === $email || '' === $email) {
            return;
        }

        // One email listing every card bought on the order, rather than one per card: a customer
        // buying five gift cards wants one message, not five.
        $this->sender->send(
            Emails::GIFT_CARDS_PURCHASED,
            [$email],
            [
                'order' => $order,
                'giftCards' => $giftCards,
                'customer' => $customer,
                'channel' => $order->getChannel(),
                'localeCode' => $order->getLocaleCode(),
            ],
        );
    }
}
