# 0013 - Selling gift cards is a per-channel mode, enforced twice

**Status:** accepted.

## Context

Every product marked as a gift card product was buyable, in every channel, always. A shop that
issues cards as goodwill or compensation and never sells them had no way to say so - the only
lever was to stop marking products as gift cards, which loses the flag rather than the policy and
does not survive a change of mind.

Three things made this more than a checkbox:

- **A gift card is tender** ([0010](0010-gift-card-as-tender.md)). Cards an administrator hands out
  are money the shop has already promised. Gating redemption alongside selling would strand that
  money and turn a goodwill gesture into a complaint.
- **A cart outlives a setting.** Selling is not a single moment: a customer fills a cart, and pays
  minutes or days later. A check only at the cart lets every cart that predates the change through,
  for as long as the oldest unpaid order lives.
- **"Sellable or not" is not obviously the last word.** "Sellable to logged-in customers only" is
  the next thing a shop asks for, and a boolean cannot say it without a second column and a second
  migration.

## Decision

**A channel's gift card configuration carries a `GiftCardSaleMode`, and the mode is enforced both
where a card enters a cart and where a card is issued.**

- `GiftCardSaleMode` is a backed enum (`sellable`, `admin_only`) on `GiftCardConfiguration`, not a
  boolean. A third mode is a new case and a new label, not a schema change.
- The mode defaults to `Sellable`, in the model and as the column default, and a channel with **no
  configuration at all** is sellable too. Upgrading changes nothing for an existing shop.
- `GiftCardPurchaseChecker` is the single service that answers "may the shop sell gift cards in this
  channel?". Both enforcement points ask it, so the rule cannot drift into two versions.
- **At the cart:** `GiftCardPurchaseAllowed`, a class constraint on Sylius' `AddToCartCommand` in
  the `sylius` validation group, alongside Sylius' own availability checks. The customer is refused
  where they are, as a form error, rather than surprised later in checkout.
- **At issue:** `OrderGiftCardOperator::generate()` returns without issuing. This is the one that
  actually protects the money - a cart filled while the channel still sold gift cards must not hand
  out a card once it has stopped.
- **Redeeming is not gated anywhere.** The redeem panel renders and a card is spendable in either
  mode.

## Consequences

- A shop can run a channel that only ever issues cards from the back office, and the gift card
  product flag survives the mode being switched back and forth.
- Two enforcement points means two places to keep in step. They share one checker, and both are
  covered - the cart by unit tests on the validator, the issue path by Behat.
- The constraint is evaluated on **every** add-to-cart in the shop, gift card or not. It returns
  early for anything that is not a gift card product, and a test asserts an ordinary product is
  untouched in admin-only mode - an over-eager violation here would stop the shop selling anything.
- The mode is on the configuration, so it is per channel. A shop wanting one policy everywhere sets
  it once per channel; there is no global switch, and adding one later would be a second way to say
  the same thing.

## Rules

1. Anything that lets a customer *obtain* a gift card asks `GiftCardPurchaseCheckerInterface`. A new
   purchase surface is not done until it does.
2. Nothing on the *redemption* path ever consults the sale mode. A card that exists is spendable in
   its channel; see [0010](0010-gift-card-as-tender.md).
3. An unknown or absent configuration means sellable. A new mode may narrow who can buy, but the
   default must stay the behaviour a shop had before the mode existed.
4. Both enforcement points stay. Removing the check at issue in favour of the cart one reopens the
   stale-cart hole, and removing the cart one refuses the customer at the wrong moment.
