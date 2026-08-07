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

A channel without a configuration still works - the defaults on the model apply (16 characters, no
prefix, one year).

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

## Redeeming a gift card

A customer enters a code in the gift card panel on the cart. A card can be applied when it is
enabled, not expired, has a balance left, and belongs to the order's channel.

An applied card becomes a **negative order adjustment**, not a payment. That means:

- gift cards compose with promotions, shipping and taxes, because they are applied to the final
  total after all of them;
- several cards **stack**;
- a card is only ever charged **what is still owed** - applying a 500 card to a 100 order takes 100
  from it and leaves 400;
- an order total never goes below zero.

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
