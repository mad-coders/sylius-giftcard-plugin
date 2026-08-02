# 0005 - Purchaser + redeemer links and a balance ledger

**Status:** accepted

## Context

`Setono/SyliusGiftCardPlugin` links a gift card to a single `customer` - the one who bought it. In
practice a gift card is usually bought *for someone else*: the buyer and the spender are different
people, and it is the spender who needs to see "how much is left on this card".

With a single link, the spender has no relationship to the card at all; their account cannot list
it, and the remaining balance is only visible by re-entering the code at checkout.

Separately, the remaining balance is a single mutable integer. When a customer asks "why is there
only 20 left?", answering means reconstructing history from order adjustments across orders.

## Decision

1. A gift card carries **two customer links**:
   - `purchaser` - set when the order the card was bought on is completed.
   - `redeemer` - set the first time the card is successfully redeemed, from the redeeming order's
     customer. It is not reassigned on later redemptions.

   Either may be null: an admin-created card has no purchaser until it is used as a gift; an unused
   card has no redeemer.

2. Every balance change writes an append-only **`GiftCardTransaction`** row (`debit` on redemption,
   `credit` on cancellation or manual top-up) carrying the amount, the order it relates to, and the
   resulting balance.

   The ledger is **not** the source of truth for the balance - `GiftCard::$amount` is, so
   redemption stays a single cheap read. The ledger explains the balance; it does not define it.

## Consequences

- The customer account can show two lists: cards I bought, and cards I use - the latter with the
  live remaining balance and its history.
- Admins can audit a card without reading order adjustments.
- The ledger grows monotonically; it is never updated or deleted, only appended to.
- A card redeemed by a guest checkout has a null `redeemer` until a customer account is attached.

## Rules

1. `redeemer` is assigned **once**, on first successful redemption; later redemptions by anyone do
   not overwrite it.
2. Every write to `GiftCard::$amount` is accompanied by a `GiftCardTransaction` in the same unit of
   work. There is no code path that changes the balance silently.
3. `balanceAfter` on a transaction always equals the card balance after that transaction. If the
   two ever disagree, the card's `amount` wins and the discrepancy is a bug in whatever wrote the
   ledger row.
