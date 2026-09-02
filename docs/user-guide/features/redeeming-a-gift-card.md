# Redeeming a gift card

> **No shop screenshots on this page.** The documentation crawl reached the homepage, a taxon page,
> eight product pages, the login page and the contact page. It never reached the cart or any
> checkout step, so the redeem panel was not captured. Everything below is read from the plugin's
> routes, controller and templates.

## What redeeming does

Applying a gift card does **not** reduce the order. It reduces what the customer pays.

- **Order total** stays at the value of the goods.
- **Gift cards** shows what the applied cards cover, as a negative figure.
- **Left to pay** is what the customer is actually charged.

A 100 order paid with a 40 card is still a 100 order, and the customer pays 60. See
[ADR 0010](../../adr-log/0010-gift-card-as-tender.md) for why this matters to tax, refunds,
reporting and promotions.

Consequences worth knowing:

- Several cards **stack**.
- A card is only ever charged **what is still owed**. Applying a 500 card to a 100 order takes 100
  from it and leaves 400 on the card.
- Left to pay never goes below zero.
- A fully covered order keeps its payment, at zero, rather than losing it.

## Where a customer can redeem

There are two places, and they are the same panel:

**On the cart page.** A **Gift cards** panel below the cart, outside the cart's own form.

**In the checkout sidebar**, directly under the totals, on the **shipping**, **payment** and
**summary** steps.

The panel is deliberately absent from the **addressing** step. Applying a card is a form post
followed by a redirect, so the step's form is re-rendered from what has been saved. On the shipping
or payment step that costs a customer a radio button; on the addressing step it would cost them a
whole address, typed out by hand, with no explanation.

Applying or removing a card sends the customer back to the page they were on, so redeeming from the
middle of checkout does not lose their place.

## The panel

| Control | Behaviour |
|---|---|
| The code box, placeholder **Enter your gift card code** | Where the customer types the code. Leading and trailing spaces are trimmed. |
| **Apply** | Applies the code to the cart. |
| **Remove**, one per applied card | Takes that card back off the cart. |

Each applied card is listed by code. On the cart page each also shows **Remaining balance**; the
checkout sidebar renders the compact version, which shows the code only.

Both forms are plain HTML posts and work without JavaScript.

The panel renders its own success and error messages, rather than relying on the page to render
flashes. The addressing, shipping and payment steps do not render flashes at all, so without this a
refusal there would be silent and the unread message would surface later, attached to the wrong
action.

| Message | When |
|---|---|
| "The gift card has been applied to your cart." | The code was accepted. |
| "The gift card has been removed from your cart." | A card was removed. |
| "Please enter a gift card code." | The box was empty. |
| "This gift card code cannot be used. Check it and try again." | Every other refusal. |
| "Too many gift card codes have been tried from here. Please wait a few minutes before trying again." | The rate limit was hit. |

## Why every refusal says the same thing

A card can be applied when it is enabled, not expired, has a balance left, and belongs to the
order's channel.

When it cannot, the customer always sees the same sentence, whether the code does not exist, the
card is expired, disabled, spent, or belongs to another channel. The redeem field is an anonymous
POST and a gift card code is money to whoever holds it. A message that distinguished those cases
would tell anybody typing codes at random which ones are real.

A customer who wants to know why *their own* card will not work finds out in
[My gift cards](customer-account.md), which is behind a login and shows only cards that are theirs.

Removing a card is resolved against the cart's own cards and never consults the card repository, so
removing a code that is not on the cart is silently indistinguishable from removing one that is.

## Rate limiting

Repeated failed attempts from the same client are refused after a threshold. Only **failures**
count; a customer entering codes they actually hold never spends an allowance, and a successful
redemption forgives the failures before it, once per window.

The defaults are 10 failures per 15 minutes per client, plus a much looser shop-wide window at 200,
which alerts rather than blocking. Whether the limiter runs at all, its thresholds and its window
are host configuration. See [`docs/INSTALLATION.md`](../../INSTALLATION.md) for the settings, and
[ADR 0012](../../adr-log/0012-rate-limiting-gift-card-redemption.md) for the reasoning.

Three things an operator should know:

- The limiter needs `symfony/rate-limiter`. A host that has not installed it redeems as before,
  unthrottled.
- Behind a CDN or proxy with `framework.trusted_proxies` unset, the limiter refuses to arm and logs
  a warning. That is deliberate: the alternative is one shared allowance for every customer behind
  that edge, which is a silent shop-wide lockout.
- Customers sharing an address - an office, a school, mobile carrier-grade NAT - share one
  allowance. The first correct code clears whatever their colleagues got wrong.

Removing a card is never rate limited.

## What happens to the balance

The balance moves off the card when the order is **placed**, and back onto it if the order is
**cancelled**, by exactly the amount that was charged, including when the card was only partly used.
Applying a card to a cart does not debit it.

The first customer to redeem a card is recorded as its **redeemer** ("Used by") and is not
reassigned afterwards. Passing the code on does not take the card away from the person who started
spending it.

## What you see on the order in the admin

An order paid partly with gift cards carries extra lines in its summary:

- **Gift cards:** the total covered, as a negative figure.
- One line per card, indented, showing the **code** and what that card covered.
- **Left to pay:** what was actually charged.

The order total stays at the full value of the goods. Without those lines you could not tell why a
payment is smaller than the total, or which card covered the difference. The per-card codes are the
handle for tracing a balance back to the order that spent it.

## For developers

`Order::getTotal()` is the value of the goods and is never touched by gift cards. Anything showing a
customer what they pay must use `Order::getAmountToPay()`; `Order::getGiftCardTotal()` is the
covered part. Gift card adjustments are **neutral**, so Sylius' `getAdjustmentsTotal()` deliberately
excludes them.
