# 0016 - A gift card does not buy a gift card

**Status:** accepted. **Completes** [0015](0015-every-gift-card-expires.md), which does not hold
without it.

## Context

`OrderGiftCardProcessor` settled applied cards against `Order::getTotal()` with no exclusion for
gift card product lines, so a gift card could pay for a gift card. That was true before
[0014](0014-customer-chosen-gift-card-amount.md) and 0014 named it without fixing it, because it is
a decision about tender rather than about amounts.

0014 also made it sharp. Once a channel allows a freely chosen amount, a holder can name **exactly**
their remaining balance:

1. `GIFT-A` has 412.37 left and expires in seven days.
2. The customer buys a new gift card for exactly 412.37 and pays with `GIFT-A`.
3. Cash paid: **0.00**. They now hold a fresh code with a fresh expiry.
4. Repeat forever.

Two things break:

- **The expiry becomes unenforceable.** The size of the shop's liability never changes, but its
  duration becomes unbounded. That defeats 0015 entirely: shipping a mandatory expiry alongside a
  free renewal mechanism is shipping a guarantee that does not hold.
- **The audit trail is laundered.** Each hop mints a new card with a new `purchaser`/`redeemer`
  association, so the link back to whoever originally bought the card is gone after one round.

It costs the attacker nothing and needs no unusual access - a card, and a shop in `range` or
`presets_and_range` mode.

## Decision

**A gift card settles everything on an order except the gift cards on it. The rule is per channel,
it defaults to on, and one service answers it for everybody.**

1. `GiftCardConfiguration` carries a `GiftCardTenderMode` - `goods_only` (default) or `anything`.
   An enum rather than a boolean, for the same reason as [0013](0013-gift-card-sale-mode.md):
   "goods and shipping but not gift cards" is a policy, and "goods only, shipping paid in cash" is a
   plausible next answer a boolean could not express without another column.
2. `GiftCardTenderCheckerInterface` is the single decision point. It answers two questions that are
   really one: **how much** of an order gift cards may settle, and **whether** they may be applied at
   all. Three copies of this rule would drift, which is exactly how the hole survived being written
   down in 0014 without being closed.
3. **The settleable total is `Order::getTotal()` less the gift card lines' own totals.** Deliberately
   arithmetic on Sylius' own numbers rather than an attribution of shipping, tax and order
   promotions between "gift card" and "goods". An item total already carries the promotions and
   taxes that landed on that line, so subtracting it removes the gift card's value and its own tax
   and nothing else. Everything that is not a line - shipping, order-level adjustments - stays
   settleable, which is right: a gift card is emailed, so the postage is for the goods.
4. **A basket of nothing but gift cards settles nothing at all, postage included.** The reasoning in
   point 3 is that the shipping is for the goods; on an order with no goods it has nothing left to
   stand on. Without this carve-out the rule contradicts itself across two screens - the cart refuses
   redemption outright, and the checkout, two clicks later, lets a card cover the shipping charge on
   the same basket. No money is at stake either way (a $10 postage cover is not a rollover), but a
   rule that is refused in one place and honoured in the next is not a rule anybody can reason about,
   and it made three paragraphs of our own documentation false as written.
5. **Enforced at three points**, again following 0013:
   - **At the point the card is applied.** `GiftCardApplicator` throws
     `GiftCardsNotAcceptedOnOrderException` before it looks the code up (see below), and the cart
     controller turns that into a flash message of its own.
   - **At checkout.** `GiftCardRedemptionAllowed`, a class constraint on `OrderInterface` in the
     `sylius_checkout_complete` group, alongside Sylius' own `OrderProductEligibility`. This catches
     the cart that was assembled before the rule bit: a customer who applied a card to shoes and a
     gift card, then removed the shoes, or an operator who changed the channel's mode in between.
   - **In the order processor.** The coverage is capped at the settleable total on every processing
     run. This is the one that protects the money, the way the issue-time guard does in 0013: it
     runs on server state no request can skip.
6. **Capping the coverage never shrinks the payment.** The processor keeps the order total and the
   settleable total apart and settles the payment against the first. Conflating them would reduce
   what the shop is paid by the amount the cards were *not* allowed to cover - handing over a gift
   card nobody paid for, which is a far worse bug than the one being fixed.
7. **The refusal is checked before the code is resolved.** It carries a specific message, and any
   specific answer that arrives only for codes that exist is an oracle telling an anonymous caller
   which codes are real - the exact leak the single "this code cannot be used" message in
   [0012](0012-rate-limiting-gift-card-redemption.md) exists to avoid. Asked about the basket first,
   it answers identically for every code. It is not counted as a failed attempt by the rate limiter
   either: nothing was guessed.

### Criterion 5: the mixed basket allows redemption against the non-gift-card portion

An order with a 180 pair of shoes and a 25 gift card **can** be paid with a card, and that card can
cover the shoes but not the gift card.

Refusing the whole basket was the alternative, and it is simpler. It was rejected because:

- **It closes nothing extra.** The attack in #41 is getting gift card value for gift card value.
  Capping the coverage stops that completely: rolling 412.37 over now requires 412.37 of real goods,
  which is not free and is not repeatable.
- **It punishes a customer who did nothing wrong.** A basket that is 88% ordinary goods would be
  refused entirely because of one gift card in it, and the only remedy is to place two orders.
- **The arithmetic is not ambiguous.** The objection to a partial rule is usually that attributing
  shipping and tax between the two halves is guesswork. It is not, here: subtracting the gift card
  *line totals* from the order total is exact, uses Sylius' own numbers, and never over-credits,
  because a line total already includes that line's tax.

The gift-card-only basket is the same rule, not an exception to it: its settleable total is zero -
shipping included, see decision 4 - and rather than let a card "apply" and cover nothing, the
applicator and the checkout constraint refuse it and say why.

### What a shop that wants this loses by turning it on

Setting a channel to `anything` restores the old behaviour, and a shop may genuinely want it - a
corporate reseller buying gift cards in bulk with a company card, say. What it gives up:

- **Its expiry dates stop meaning anything.** Any holder can roll a card forward indefinitely for
  no cash, so the channel's liabilities have a stated duration and an unbounded real one. Everything
  0015 exists for is off in that channel.
- **The purchaser link is only as good as the last hop.** After one rollover the card's `purchaser`
  is the previous holder, not the person who paid money into the shop. Tracing a card back to a
  real payment is no longer possible from the card alone.
- **Balance can leave the shop's control without a cash sale.** Not new money - the total liability
  is unchanged - but it moves between codes and customers with no payment record tying the hops
  together, which is exactly the shape anti-money-laundering rules on stored value are written
  about.

## Consequences

- **This is a behaviour change for anyone on RC.2, including channels with no configuration at
  all.** Unlike the sale mode in 0013, the default deliberately does *not* preserve the previous
  behaviour: the previous behaviour was the hole, and defaulting unconfigured channels into it would
  mean the fix protected only the shops that had already read the release notes.
- A customer whose basket mixes goods and gift cards sees their card cover less than its balance.
  Nothing on the cart currently explains *why* the coverage stopped where it did - the amount to pay
  is correct and visible, but the reason is not. That is a real gap and a narrow one; it needs a
  line in the redeem panel and deserves its own ticket rather than being smuggled in here.
- The refusal at checkout can only be cleared by the customer removing the gift card from their
  basket or removing the card from the order. The message says both.
- `GiftCardTenderCheckerInterface` is asked on every order processing run and every checkout, gift
  card or not. Both return early for a basket with no gift card lines, and unit tests pin that an
  ordinary order is untouched - an over-eager answer here would stop every card in the shop working.

## Rules

1. Anything that decides how much a gift card may cover asks `GiftCardTenderCheckerInterface`.
   Asking the question a second way is a bug waiting to disagree with the first.
2. The settleable total and the order total stay separate in the processor. A card covering less
   must never mean the customer pays less.
3. The order refusal is decided from the basket, before any code is resolved. Moving it after the
   lookup turns it into an oracle for which codes exist.
4. An unconfigured channel gets the safe rule. "Behave as before" is not the conservative default
   when before was the hole.
5. A shop may turn this off per channel, and the admin help text says what it costs. It is never
   turned off by default, and never globally.
