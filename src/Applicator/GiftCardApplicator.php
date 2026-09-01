<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Applicator;

use Madcoders\SyliusGiftCardPlugin\Checker\GiftCardTenderCheckerInterface;
use Madcoders\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardNotRedeemableException;
use Madcoders\SyliusGiftCardPlugin\Exception\GiftCardsNotAcceptedOnOrderException;
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
        private GiftCardTenderCheckerInterface $giftCardTenderChecker,
    ) {
    }

    public function apply(BaseOrderInterface $order, GiftCardInterface|string $giftCard): bool
    {
        $order = $this->assertGiftCardAwareOrder($order);

        // Asked about the basket BEFORE the code is looked up, and deliberately so. This refusal
        // has to say something specific - "a gift card cannot pay for a gift card" - and any
        // specific answer that arrives only for real codes is an oracle telling an anonymous
        // caller which codes exist. Checked first, it says the same thing for every code, real or
        // invented.
        if (!$this->giftCardTenderChecker->allowsRedemptionOn($order)) {
            throw new GiftCardsNotAcceptedOnOrderException();
        }

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

        // Asked before adding, because addGiftCard() early-returns on a card the order already has.
        // Without this the caller cannot tell a redemption from a re-submission of the same code, and
        // a re-submission is free and endlessly repeatable - applying does not debit the card.
        $newlyApplied = !$order->hasGiftCard($giftCard);

        $order->addGiftCard($giftCard);

        $this->orderProcessor->process($order);

        return $newlyApplied;
    }

    public function remove(BaseOrderInterface $order, GiftCardInterface|string $giftCard): void
    {
        $order = $this->assertGiftCardAwareOrder($order);

        // Resolved from the order's own cards rather than the repository. Removing something that
        // was never applied is meaningless, and looking the code up globally would turn this into
        // an oracle: "no such card" for an unknown code versus "removed" for any real one in the
        // shop tells an anonymous caller which codes exist. Gift card codes are bearer-like, so
        // that distinction is worth money.
        $giftCard = $this->resolveApplied($order, $giftCard);

        $order->removeGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    private function resolveApplied(OrderInterface $order, GiftCardInterface|string $giftCard): GiftCardInterface
    {
        $code = $giftCard instanceof GiftCardInterface ? $giftCard->getCode() : $giftCard;

        foreach ($order->getGiftCards() as $appliedGiftCard) {
            if ($appliedGiftCard->getCode() === $code) {
                return $appliedGiftCard;
            }
        }

        throw new GiftCardNotFoundException((string) $code);
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
