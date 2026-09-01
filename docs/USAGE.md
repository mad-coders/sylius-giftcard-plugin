# Usage

How the plugin behaves once it is installed (see `docs/INSTALLATION.md` for getting it there).

## Configuring a channel

Gift cards are channel-scoped: a card issued in one channel cannot be spent in another, so its face
value cannot silently change currency or store.

Each channel gets a **gift card configuration** under *Marketing > Gift card configuration*:

| Setting | Meaning |
|---|---|
| Code prefix | Prepended to every generated code, e.g. `GIFT-`. Optional. |
| Code length | Number of random characters after the prefix. |
| Validity period | How long a new card stays valid, as a relative date expression such as `1 year` or `6 months`. Leave empty for cards that never expire. |
| Gift card sales | Whether customers may buy gift cards in this channel, or only an administrator may issue them. |

A channel without a configuration still works - the defaults on the model apply (16 characters, no
prefix, one year, gift cards sold in the shop).

Generated codes avoid the characters people misread off a card or an email (`0`/`O`, `1`/`I`/`L`,
`5`/`S`) and come from a cryptographically secure source, because a guessable gift card code is a
way to spend other people's money.

## Selling gift cards

Mark a product as a gift card by ticking **This product is a gift card** on the product form.

When an order containing that product is **paid**:

- one card is issued **per purchased unit** - buying three gift cards gives three separate codes;
- each card's face value is **what was actually paid for that unit**, adjustments included, not the
  product's list price. A discounted gift card issues a card worth the discounted price, so a
  promotion cannot be turned into free money;
- the buyer is recorded as the card's **purchaser**;
- the codes are emailed to the buyer.

Issuing waits for payment, so an unpaid order never hands out spendable codes. If the order is
later **cancelled**, the cards it issued are disabled - not deleted, so their history survives and
an admin can reinstate them.

### Not selling them

Set **Gift card sales** to *Issued by an administrator only* for a channel that hands cards out as
goodwill or compensation and never sells them. Adding a gift card product to the cart is refused,
completing checkout with one already in the cart is refused, and an order that somehow reaches
payment anyway issues nothing and logs a warning naming the order.

The customer is stopped before they are charged. The add-to-cart button is still shown, though, so
their first feedback is an error after clicking rather than a control that was never offered.

The product keeps its gift card flag, so switching the mode back resumes selling. **Redeeming is
unaffected in either mode** - a card an administrator issued is spendable in the shop exactly as a
bought one is. See `docs/adr-log/0013-gift-card-sale-mode.md`.

## Redeeming a gift card

A customer enters a code in the gift card panel on the cart. A card can be applied when it is
enabled, not expired, has a balance left, and belongs to the order's channel.

**Every refusal says the same thing** - "This gift card code cannot be used." - whether the code does
not exist, the card is expired, disabled, spent, or belongs to another channel. The panel is an
anonymous POST and a code is money to whoever holds it, so a message that distinguished those cases
would tell anybody typing codes at random which ones are real. A customer who wants to know why their
own card will not work finds it in *My gift cards*, which is behind a login and shows only cards that
are theirs.

Repeated failures from the same client are refused after a threshold, with a message of their own.
See `docs/INSTALLATION.md` for the settings and `docs/adr-log/0012-rate-limiting-gift-card-redemption.md`
for the reasoning.

**A gift card is money, not a discount.** The order stays worth what the goods are worth; the card
comes off what the customer has to pay. So a 100 order paid with a 40 card is still a 100 order, and
the customer pays 60.

That distinction is not cosmetic:

- **tax** is owed on the value of the goods sold, and the tax on the card was already settled when
  it was bought - discounting the order would reduce the taxable base a second time;
- **reporting and refunds** see an order worth what was actually sold;
- **promotions** calculate against the real total, so paying with a card cannot switch off a
  "spend over X" promotion.

Beyond that:

- several cards **stack**;
- a card is only ever charged **what is still owed** - applying a 500 card to a 100 order takes 100
  from it and leaves 400;
- the amount to pay never goes below zero.

Read the split with `Order::getAmountToPay()` and `Order::getGiftCardTotal()`. **`getTotal()` is
the value of the goods, not what the customer pays** - a host application showing "you pay" must use
`getAmountToPay()`.

The balance moves off the card when the order is placed, and back onto it if the order is
cancelled - by exactly the amount that was charged, including when the card was only partly used.

The first customer to redeem a card is recorded as its **redeemer**, and is not reassigned
afterwards. Passing the code on to somebody else does not take the card away from the person who
started spending it.

## Who sees what

A gift card is linked to two customers, because a gift card is usually bought *for* somebody:

- the **purchaser**, who paid for it;
- the **redeemer**, who is spending it.

In *My gift cards* in the customer account, each of them sees a different list. "Cards I use" is
the one with a balance worth watching. A customer can only see cards they are linked to - a gift
card code is bearer-like, so the account pages refuse anything that is not theirs.

## Administering gift cards

Under *Marketing > Gift cards* an admin can:

- **browse and filter** cards by code, channel and enabled state, seeing the remaining balance,
  expiry and both customer links at a glance;
- **create a card by hand** - only a channel and an amount are needed; the code, currency and expiry
  are filled in from the channel's configuration. Enter a code explicitly to import pre-printed
  cards;
- **inspect a card**, including its full balance history;
- **adjust a balance** for a goodwill top-up or to claw back a card issued in error.

The initial amount cannot be changed after creation: a card's face value must not move under orders
that already reference it. Use the balance adjustment for corrections.

Every balance change - redemption, cancellation, or a manual adjustment - is recorded in the card's
**balance history**, so "where did my balance go?" is answerable without reconstructing it from
order adjustments.

## Emails

Buying gift cards sends `madcoders_gift_cards_purchased` to the buyer, listing every code bought on
the order. Override the subject or template by redefining that code under `sylius_mailer.emails` in
your application.

Mail failures do not fail the payment: an order that is paid stays paid, and the codes remain
visible in the customer's account.

## Demo data

The plugin ships a `madcoders_gift_card` fixtures suite with a full card, a small one, a partly
spent one, an expired one and a disabled one - enough to try every path by hand:

```bash
bin/console sylius:fixtures:load madcoders_gift_card
```

It is deliberately a separate suite rather than an addition to Sylius' `default` one: installing a
plugin should not change what `sylius:fixtures:load` does to your shop.

## Translations

English and Polish ship with the plugin. Add a locale by copying
`translations/messages.en.yaml` and `translations/flashes.en.yaml` into your application's
`translations/` and translating the values - the flash messages must stay in the `flashes` domain,
which is where Sylius looks for them.
