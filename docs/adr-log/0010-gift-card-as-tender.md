# 0010 - A gift card is tender, not a discount

**Status:** accepted. **Supersedes the redemption mechanism in**
[0004](0004-gift-card-redemption-as-order-adjustment.md) (its state machine and adjustment-record
decisions still stand).

## Context

0004 modelled a redeemed gift card as a negative order adjustment, following
`Setono/SyliusGiftCardPlugin`. That made a $100 order paid with a $40 card into a $60 order.

It is the wrong model, and the review made the consequences concrete:

- **Tax.** A gift card is not a price reduction. Tax is owed on the value of the goods sold, and the
  tax on the card itself was already dealt with when the card was bought. Discounting the order
  reduces the taxable base for a second time.
- **Reporting and refunds.** An order for $100 of goods showed as a $60 order. Revenue is
  understated and a refund returns $60 for goods worth $100.
- **Promotions.** A "spend over $X" promotion could be switched off by paying with a card, because
  the card moved the total the promotion tests.

The plugin also independently mis-issued cards bought in a tax-exclusive shop, because the face
value was taken from a unit total that included tax - the same confusion from the other direction.

## Decision

**A gift card is money against the amount to pay, not a discount on the price.**

- `Order::getTotal()` is **not** touched by gift cards. The order stays worth what the goods are
  worth.
- Each applied card contributes a **neutral** adjustment of type `madcoders_gift_card`, tagged with
  the card's code. Neutral adjustments do not move the order total; this is a *record* of which card
  covers how much, and it remains what the balance modifier reads when the order is placed or
  cancelled.
- `OrderGiftCardProcessor` runs **below** Sylius' payment processor (priority `-10`) and takes the
  covered amount off the **payment**. That processor has already sized the payment from the order
  total by then, which is precisely why the gift card processor must follow it.
- `Order::getAmountToPay()` and `Order::getGiftCardTotal()` expose the split. They are needed
  because Sylius deliberately excludes neutral adjustments from `getAdjustmentsTotal()`.

The processor still runs after taxes (priority 10), so what a card settles against is the real
amount owed.

## Consequences

- The tax base is the full sale value, which is what tax is actually owed on.
- Reporting, refunds and promotions all see an order worth what was sold.
- The cart and checkout show two numbers - the order total, and what is left to pay - because the
  customer genuinely owes the first and pays the second.
- A fully covered order keeps its payment, at zero, rather than having it removed. Sylius removes
  payments only when the order *total* is zero, which no longer happens.
- Host applications reading `Order::getTotal()` to mean "what the customer pays" are wrong under
  this model and must use `getAmountToPay()`.

## Rules

1. Never reduce `Order::getTotal()` for a gift card. If a change makes a card behave like a
   discount, it is wrong.
2. Gift card adjustments are always **neutral**. They record coverage; they never move a total.
3. The order processor must stay **below** Sylius' payment processor and **above** nothing that
   sizes the payment. `OrderProcessorPriorityTest` enforces both ends against Sylius' own
   configuration.
4. Anything showing the customer what they pay uses `getAmountToPay()`, never `getTotal()`. That
   means every surface Sylius renders an order total on, not just the cart: the checkout sidebar,
   the checkout summary, the account order page and the admin order view all carry the split. A new
   display surface is not done until it does too.
5. Sylius' own payment state resolver and after-checkout payment processor both compare against
   `getTotal()`, so both are decorated. Anything else in Sylius that sizes or judges a payment from
   the order total needs the same treatment - a gift card order will otherwise look unpaid, or be
   charged for money it has already handed over.
