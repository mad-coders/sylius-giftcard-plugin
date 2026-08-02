# 0004 - Redemption as an order adjustment

**Status:** accepted

## Context

A gift card reduces what a customer pays. There are two plausible ways to express that in Sylius:

1. **A payment method** - the card pays part of the order, the rest goes to another payment.
2. **An order adjustment** - the card reduces the order total, exactly like a promotion.

Option 1 fights Sylius: payments are per-order and single-amount, the checkout payment step
assumes one method, and every downstream consumer of `Order::getTotal()` (shipping thresholds,
tax, refunds, exports) would need to learn about a second money source.

Option 2 is what `Setono/SyliusGiftCardPlugin` does, and it composes with everything that already
reads order totals and adjustments.

## Decision

A gift card applied to an order produces a **negative order adjustment** of type
`madcoders_gift_card` (`AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT`), tagged with the card's
code in `originCode`.

- `OrderGiftCardProcessor implements OrderProcessorInterface`, registered **last** in the
  `sylius.order_processor` chain, so it sees the final total after items, shipping, taxes and
  promotions.
- On every run it first **removes its own previous adjustments**, then re-applies them. Processing
  is idempotent; re-processing a cart never double-discounts.
- Each card's adjustment is **capped at the order's remaining total**, so several cards stack and
  the total can never go negative.
- `OrderGiftCardAmountModifier` moves money off the card (`decrement`) when the order is placed and
  back on (`increment`) when it is cancelled, driven by the adjustments' `originCode` - so the
  amount returned is exactly the amount charged.

### State machine wiring

Sylius 2.x supports two state machine adapters (`winzou_state_machine` and Symfony Workflow) and
the host application chooses. The plugin therefore ships **both** wirings, pointing at the same
services:

- `config/state_machine/winzou/*.yaml` - callback blocks on `sylius_order`.
- `config/services/listeners.xml` - invokable listeners tagged
  `kernel.event_listener` on `workflow.sylius_order.completed.create` and `.cancel`.

The listener classes contain no logic beyond unwrapping the event and delegating.

## Consequences

- Gift cards behave like promotions for anything reading the order: totals, summaries, exports.
- A gift card can never be "over-redeemed" on an order, because the adjustment is capped.
- The plugin must run after every other order processor; its priority is part of its contract.
- Adjustments are the source of truth for what to decrement, not the cart's UI state.

## Rules

1. Never mutate `GiftCard::$amount` outside `OrderGiftCardAmountModifier` (and the admin's explicit
   manual adjustment action).
2. Never compute a discount without going through the order processor - anything that changes what
   a card contributes belongs in `OrderGiftCardProcessor`.
3. Any new order transition that needs gift card behaviour must be wired for **both** adapters.
