# 0014 - The customer chooses the amount; the order processor is what makes it a price

**Status:** accepted

## Context

Until now a gift card's face value came from the product's channel price, so a shop that wanted to
sell cards at several values had to create a product per value. Issue #34 asks for the amount to be
chosen per purchase - from presets, freely within a range, or both.

Sylius prices order items from channel pricing, and it does so *repeatedly*.
`OrderPricesRecalculator` (order processor priority 50) rewrites every mutable item's `unitPrice`
from `ProductVariantPricesCalculator` on **every** order processing run, which happens on every cart
change and again through checkout. So "set the price when the item is added" is not a design; it is
a value that survives until the customer changes their shipping address.

Whatever carries the amount also has to interact correctly with three things that measure the order:

- **promotions** (priority 20), which discount against the item price;
- **taxes** (priority 10), which compute against the item total;
- **the payment** (priority 0), which is sized from the order total.

And it has to be safe. The amount arrives from a browser. Criterion 8 of the issue exists precisely
because a form is not a security boundary: a request that never went near the form must not be able
to buy a 500 card for 1 cent, or to mint a card for an amount the shop never offered.

## Decision

**The chosen amount is stored on the order item; a dedicated order processor turns it into the
item's price, and is the authority on whether it may.**

1. `OrderItemTrait` adds `giftCardAmount` (minor units) and `giftCardMessage` to the host's
   `OrderItem`. The choice belongs to the *line*: one trip through the product form produces one
   amount and one message, and every card that line issues carries them.
2. **`OrderItemTrait` overrides `equals()` so two gift cards with different choices never merge.**
   Sylius merges a new cart line into an existing one when `equals()` says they match, and its
   answer for a core order item is "the variants match" - `OrderModifier` then bumps the existing
   line's quantity and discards the incoming item whole. Without the override, adding a 25 card
   saying "For Ann" and then a 100 card saying "For Bob" leaves one line of two 25 cards both
   saying "For Ann": the customer is charged the wrong total and gets cards they never asked for.
   Two gift cards that *are* alike still merge into a quantity of two. For any other product both
   fields are null on both items, so the override reduces to Sylius' own answer.
3. `GiftCardChosenAmountProcessor` runs at **priority 45** - immediately below Sylius' price
   recalculator (50) and above promotions (20), taxes (10) and the payment (0). It writes the chosen
   amount onto `unitPrice`, so the price survives recalculation and everything downstream measures
   what the customer agreed to pay. `OrderProcessorPriorityTest` reads both ends of that window from
   the real XML - ours and Sylius' - so a Sylius upgrade that renumbers its chain fails CI.
4. **The same processor re-validates on every run.** It asks
   `GiftCardConfigurationInterface::isAllowedAmount()` before honouring anything, and an amount the
   channel does not offer loses its claim on the price and the line falls back to the channel price.
   The shop form asks the identical question through a `ChosenGiftCardAmount` constraint, but only to
   produce a friendly message; the processor is what binds, because it runs on server state no
   request can skip. The refused amount is **not erased from the line** - refusing the price and
   destroying the record of the request are different decisions, and only the first is wanted.
5. `GiftCardConfiguration` gains an `amountMode` (`fixed`, `presets`, `range`, `presets_and_range`),
   a list of presets and a minimum/maximum - all per channel and in the channel's currency, because
   a gift card is already channel-scoped and so is its money. `isAllowedAmount()` is the single
   decision point all of the above share.
6. **The issued card's face value is what was paid for the unit, less tax charged on top.** Promotions stay in, so
   a discounted gift card cannot be resold for more than it cost; tax comes out, because tax is not
   part of what the card is worth. Before this change a tax-exclusive shop issued a 55 card to a
   customer who paid 50 plus tax - the mis-issue [0010](0010-gift-card-as-tender.md) already named
   and did not fix.

A channel left in `fixed` mode behaves exactly as before, which is what every existing installation
gets on upgrade.

## Alternatives rejected

- **Mark the item `immutable`.** `OrderItem::isImmutable()` is persisted, and
  `OrderPricesRecalculator` is currently its only reader, so setting it would make a written price
  stick with no new column and no new processor. Rejected: the flag's name promises far more than
  "do not reprice", so a future Sylius is free to widen it - and gift card lines would silently stop
  being editable. It also leaves the stored price as the *only* record of the choice, so nothing
  distinguishes "the customer asked for 50" from "the channel charges 50", which is exactly the
  distinction the re-validation in point 4 depends on.
- **Decorate `OrderPricesRecalculator`.** Works, but takes ownership of Sylius' whole recalculation
  to change one kind of line, and collides with any other plugin that wants the same seam. A
  separate processor is additive and composes.
- **Decorate `ProductVariantPriceCalculator`.** It is handed a variant and a context, never the
  order item, so it cannot see whose choice it is pricing. A per-request stash to smuggle the amount
  in would be worse than the problem.
- **A product variant per amount, created on demand.** Turns a customer's typing into catalogue
  data, pollutes reporting and inventory, and has no answer for a free amount.
- **Validate only in the form.** The friendly half of the refusal, and not enough on its own -
  see criterion 8.
- **Keep tax in the face value** (today's behaviour). Rejected as above: the same choice would be
  worth different amounts in a tax-inclusive and a tax-exclusive shop.

## Consequences

- Host applications must apply `OrderItemInterface` + `OrderItemTrait` to their `OrderItem`. This is
  an installation-contract change; `docs/INSTALLATION.md` and the installation test cover it.
- A tax-exclusive shop now issues cards for the pre-tax amount. That is a **behaviour change** for
  existing installations, and a deliberate one.
- A promotion on a gift card product still reduces the face value, unchanged.
- An amount that was valid when it entered the cart but is not after an operator edits the channel's
  presets stops being charged, and the line reverts to the channel price. Refusing the cart outright
  would break a cart the customer had done nothing wrong with; honouring the amount would sell at a
  price the shop no longer offers. The request itself is kept, so putting the preset back restores
  the customer's price without them doing anything.

  **This is still a silent change to what the customer is about to pay.** Nothing tells them the
  total moved, and if it happens between the summary and the pay button they will not notice. That
  is a real gap, and a narrow one: it needs an operator to edit a live channel while a specific cart
  is open. Telling the customer needs a flash, and an order processor is the wrong place to raise
  one - it runs on every recalculation, including from the CLI and the API, where there is no session
  and no page to show it on. Doing it properly means noticing the *transition* somewhere that has a
  request, and that deserves its own ticket rather than a half-measure here.
- **A gift card can still be bought with another gift card, and `range` mode makes that sharper.** A
  holder with 412.37 left and a week to run can buy a new card for exactly 412.37, pay nothing, and
  receive a fresh code with a fresh expiry and themselves as purchaser - repeatable indefinitely. The
  liability does not grow, but its duration becomes unbounded, which defeats the point of the
  mandatory expiry work in #31, and the link between a card and whoever originally bought it is
  laundered. Refusing it is a decision about tender, not about amounts, so it does not belong in this
  ADR; it needs its own ticket. Until then a channel that cares can use `presets` mode, where an
  exact-balance card cannot be requested.

## Rules

1. `isAllowedAmount()` is the only place that decides whether an amount may be charged. Anything
   asking the question a second way is a bug waiting to disagree with the first.
2. The chosen amount is never trusted because it is stored. It is re-checked on every processing
   run, and anything that bypasses the processor must check it itself.
3. The processor's priority window - below Sylius' price recalculator, above promotions, taxes and
   the payment - is load-bearing. `OrderProcessorPriorityTest` guards both ends; do not move it
   without moving that test's reasoning too.
4. A card's face value is what was paid for the unit, less tax charged on top of the price. Nothing
   else. In a tax-inclusive shop the subtraction is a no-op because included tax is a *neutral*
   adjustment and `getAdjustmentsTotal()` skips neutral ones - do not "fix" that by summing the
   adjustments directly or by using `getTaxTotal()`, both of which would under-issue every card in
   every tax-inclusive shop by the full tax rate.
5. Two gift card lines merge only when the customer asked for the same thing. `equals()` on the
   order item is what decides that, and anything that resolves cart lines another way has to ask the
   same question.
6. The customer's message is untrusted text. It reaches an administrator's screen and an email, and
   is rendered as text in both - never with `raw`, never marked safe.
