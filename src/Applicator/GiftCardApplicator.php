<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Applicator;

use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderTrait;
use Madcoders\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * @see GiftCardApplicatorInterface
 */
final readonly class GiftCardApplicator implements GiftCardApplicatorInterface
{
    public function __construct(
        private GiftCardRepositoryInterface $giftCardRepository,
        private OrderProcessorInterface $orderProcessor,
    ) {
    }

    public function apply(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void
    {
        $order = $this->assertGiftCardAwareOrder($order);
        $giftCard = $this->resolve($giftCard);

        if (!$giftCard->isRedeemable()) {
            throw new GiftCardNotRedeemableException($giftCard, $this->describeWhyNotRedeemable($giftCard));
        }

        // Cards are channel-scoped: a card issued in one store or currency must not be spendable in
        // another, or its face value would silently change meaning.
        $orderChannel = $order->getChannel();
        $giftCardChannel = $giftCard->getChannel();

        if (null === $orderChannel || null === $giftCardChannel || $orderChannel->getCode() !== $giftCardChannel->getCode()) {
            throw new ChannelMismatchException($giftCardChannel, $orderChannel);
        }

        $order->addGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    public function remove(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void
    {
        $order = $this->assertGiftCardAwareOrder($order);

        $order->removeGiftCard($this->resolve($giftCard));

        $this->orderProcessor->process($order);
    }

    private function resolve(GiftCardInterface|string $giftCard): GiftCardInterface
    {
        if ($giftCard instanceof GiftCardInterface) {
            return $giftCard;
        }

        return $this->giftCardRepository->findOneByCode($giftCard)
            ?? throw new GiftCardNotFoundException($giftCard);
    }

    private function assertGiftCardAwareOrder(BaseOrderInterface $order): OrderInterface
    {
        if (!$order instanceof OrderInterface) {
            throw new \LogicException(sprintf(
                'The order must implement "%s" for gift cards to be applied to it. Apply "%s" to your Order entity - see docs/INSTALLATION.md.',
                OrderInterface::class,
                OrderTrait::class,
            ));
        }

        return $order;
    }

    private function describeWhyNotRedeemable(GiftCardInterface $giftCard): string
    {
        if (!$giftCard->isEnabled()) {
            return 'it is disabled';
        }

        if ($giftCard->isExpired()) {
            return 'it expired';
        }

        return 'it has no balance left';
    }
}
