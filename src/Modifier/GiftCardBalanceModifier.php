<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Modifier;

use Madcoders\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransactionType;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @see GiftCardBalanceModifierInterface
 */
final readonly class GiftCardBalanceModifier implements GiftCardBalanceModifierInterface
{
    /** @param FactoryInterface<GiftCardTransactionInterface> $giftCardTransactionFactory */
    public function __construct(private FactoryInterface $giftCardTransactionFactory)
    {
    }

    public function debit(GiftCardInterface $giftCard, int $amount, ?BaseOrderInterface $order = null): void
    {
        // debit() enforces the invariants and throws if they would break, so the ledger entry is
        // only written once the balance has actually moved.
        $giftCard->debit($amount);

        $this->recordTransaction($giftCard, GiftCardTransactionType::Debit, $amount, $order);
    }

    public function credit(GiftCardInterface $giftCard, int $amount, ?BaseOrderInterface $order = null): void
    {
        $giftCard->credit($amount);

        $this->recordTransaction($giftCard, GiftCardTransactionType::Credit, $amount, $order);
    }

    private function recordTransaction(
        GiftCardInterface $giftCard,
        GiftCardTransactionType $type,
        int $amount,
        ?BaseOrderInterface $order,
    ): void {
        $transaction = $this->giftCardTransactionFactory->createNew();
        $transaction->setType($type);
        $transaction->setAmount($amount);
        $transaction->setOrder($order);
        $transaction->setBalanceAfter($giftCard->getAmount());

        $giftCard->addTransaction($transaction);
    }
}
