# 0004 - Redemption as an order adjustment

**Status:** superseded in part by [0010](0010-gift-card-as-tender.md), which replaces the
discount mechanism below with tender against the payment. The state machine wiring and the use of a
coded adjustment as the per-card record still stand.

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
- Its adjustment type is added to Sylius' `OrderAdjustmentsClearer` list by a compiler pass, rather
  than the processor removing its own adjustments. The clearer runs at priority **60** - before
  promotions (20) and taxes (10) - so a stale gift card discount from the previous run can never
  distort those calculations. Removing them in our own processor (at -10) would be too late:
  everything in between would already have computed against a discounted total. Processing is
  idempotent as a result.
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
