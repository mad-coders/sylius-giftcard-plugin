# Selling gift cards

> **No screenshots on this page.** The documentation crawl covered the admin and eight ordinary
> shop products; the demo data it ran against had no gift card product, so no gift card product
> page, cart or checkout screen was captured. Everything below is read from the plugin's forms,
> templates and order processors.

A gift card is sold as an ordinary Sylius product. There is no separate catalogue for them.

## Marking a product as a gift card

Open the product in **Catalog > Products** and tick **This product is a gift card** in the general
section of the product form. The help text reads "Buying it issues a gift card per unit, worth what
was paid for that unit."

The flag is a property of the product, not of the channel. Whether customers can actually buy it is
decided per channel by **Gift card sales** on the
[gift card configuration](gift-card-configuration.md#whether-the-channel-sells-gift-cards).

## What the customer chooses on the product page

Two extra fields appear on the add-to-cart form of a gift card product, between the variant table
and the quantity.

### The amount

Shown only when the channel's **How the amount is chosen** is something other than **The product's
price**. What renders depends on the mode:

| Channel mode | The customer sees |
|---|---|
| **The product's price** | Nothing. The card is worth the product's channel price. |
| **A list of preset amounts** | **Choose an amount**, with one radio button per preset, labelled with the formatted money value. |
| **Any amount within a range** | **Choose an amount**, a money box, and "Type anything between *minimum* and *maximum*." under it. |
| **Preset amounts, or any amount within a range** | The preset radios, plus an **Other amount** radio and a box. |

The controls are plain radio buttons and a number input. Nothing on the page needs JavaScript to
decide what gets submitted.

If the amount is not one the channel offers, the form says so:

- "Please choose how much this gift card should be worth."
- "Please choose one of the available amounts."
- "The amount must be between *minimum* and *maximum*."

Those messages are the friendly half of the refusal. The binding half runs server-side on every
order recalculation: an amount the channel does not offer loses its claim on the price and the line
falls back to the channel price. The customer's request is not erased from the line, so restoring a
preset you removed restores their price without them doing anything.

**This last part is silent.** If you edit a live channel's presets or bounds while a customer has a
gift card in their cart, the amount they are about to pay changes and nothing tells them. It needs
an operator editing a channel while a specific cart is open, and it is a known gap recorded in
[ADR 0014](../../adr-log/0014-customer-chosen-gift-card-amount.md).

### The message

**Message** is offered on every gift card product, whatever the amount mode, because a fixed-price
card is still a present. It is optional and capped at 255 characters, with the help text "Optional.
Up to 255 characters, shown with the code when it is delivered."

Over the limit the form says "Your message is too long - please keep it to 255 characters or fewer."

The message is stored on the cards issued for that order line, and shown with the code in the
delivery email, on the card's page in the customer's account, and on the card page in the admin. It
is customer-supplied text and is rendered as text everywhere it appears, never as markup.

### How lines merge in the cart

Two gift cards of the same product stay two separate lines when their amount or their message
differs, so each card carries what was asked for it. Two identical ones merge into a quantity of
two, like any other product.

Changing the quantity from the cart page does not clear the amount or the message. The cart page's
quantity form is built without the product, so it never carries those fields.

## What happens when the order is paid

Issuing waits for payment. An unpaid order never hands out spendable codes.

When an order containing gift card products reaches **paid**:

- one card is issued **per purchased unit**. Buying three gift cards gives three separate codes.
- each card's face value is **what was actually paid for that unit, less any tax charged on top of
  the price**. Promotions stay in, so a discounted gift card issues a card worth the discounted
  price. Tax added on top comes out, because it is not part of what the card is worth. A
  tax-inclusive shop is unaffected: included tax is a neutral adjustment, so there is nothing to
  subtract and the gross price stands.
- the buyer is recorded as the card's **purchaser** ("Bought by").
- the codes, and any message the buyer wrote, are emailed to the buyer.

The card's code, initial amount, expiry and message all appear in that email. Mail failures do not
fail the payment: an order that is paid stays paid, and the codes remain visible in the customer's
account.

## What happens when the order is cancelled

Cards issued by a cancelled order are **disabled**, not deleted. Their history survives, and an
administrator can reinstate them from the card's edit form.

## Not selling gift cards in a channel

Set **Gift card sales** to **Issued by an administrator only** on that channel's configuration. See
[Gift card configuration](gift-card-configuration.md#whether-the-channel-sells-gift-cards) for what
that does and where the refusal appears.

## The emails

Buying gift cards sends the email registered as `madcoders_gift_cards_purchased`, listing every code
bought on the order. Its default subject is "Your gift cards". A host application overrides the
subject or the template by redefining that code under `sylius_mailer.emails`.
