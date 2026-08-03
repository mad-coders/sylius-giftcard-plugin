<?php

declare(strict_types=1);

namespace Madcoders\SyliusGiftCardPlugin\Modifier;

use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

/**
 * Applies an order's gift card adjustments to the balances of the cards themselves.
 */
interface OrderGiftCardAmountModifierInterface
{
    /** Takes the charged amounts off the cards. Called when the order is placed. */
    public function debit(BaseOrderInterface $order): void;

    /** Puts the charged amounts back on the cards. Called when the order is cancelled. */
    public function credit(BaseOrderInterface $order): void;
}
