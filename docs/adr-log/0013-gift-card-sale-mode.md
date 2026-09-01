# 0013 - Selling gift cards is a per-channel mode, enforced three times

**Status:** accepted.

## Context

Every product marked as a gift card product was buyable, in every channel, always. A shop that
issues cards as goodwill or compensation and never sells them had no way to say so - the only
lever was to stop marking products as gift cards, which loses the flag rather than the policy and
does not survive a change of mind.

Four things made this more than a checkbox:

- **A gift card is tender** ([0010](0010-gift-card-as-tender.md)). Cards an administrator hands out
  are money the shop has already promised. Gating redemption alongside selling would strand that
  money and turn a goodwill gesture into a complaint.
- **A cart outlives a setting.** Selling is not a single moment: a customer fills a cart, and pays
  minutes or days later. A check only at the cart lets every cart that predates the change through,
  for as long as the oldest unpaid order lives.
- **Adding is not the only way in.** Sylius' cart summary changes quantities through `CartType`,
  which never builds an `AddToCartCommand`. A guard on adding alone lets a customer take one gift
  card already in the cart to ten.
- **"Sellable or not" is not obviously the last word.** "Sellable to logged-in customers only" is
  the next thing a shop asks for, and a boolean cannot say it without a second column and a second
  migration.

## Decision

**A channel's gift card configuration carries a `GiftCardSaleMode`, enforced at each of the three
points where a customer can move from wanting a gift card to holding one.**

- `GiftCardSaleMode` is a backed enum (`sellable`, `admin_only`) on `GiftCardConfiguration`, not a
  boolean. A third mode is a new case and a new label, not a schema change.
- The mode defaults to `Sellable`, in the model and as the column default, and a channel with **no
  configuration at all** is sellable too. Upgrading changes nothing for an existing shop.
- `GiftCardPurchaseChecker` is the single service that answers "may the shop sell gift cards in this
  channel?". All three enforcement points ask it, so the rule cannot drift into three versions.
- **At the cart:** `GiftCardPurchaseAllowed`, a class constraint on `AddToCartCommandInterface` in
  the `sylius` validation group, alongside Sylius' own availability checks. The customer is refused
  where they are, as a form error.
- **At checkout:** `OrderGiftCardPurchaseAllowed`, a class constraint on `OrderInterface` in the
  `sylius_checkout_complete` group - the group carrying Sylius' own `OrderProductEligibility`. This
  is the one that matters, because it is the last point at which the customer has **not yet been
  charged**. It also covers the quantity path that never builds an `AddToCartCommand`.
- **At issue:** `OrderGiftCardOperator::generate()` returns without issuing, and **logs a warning**.
  Reaching it means the checkout constraint was bypassed - an order completed through a path that
  does not validate, or the mode changed between validation and payment. By then the customer has
  been charged, so the refusal must be discoverable rather than silent.
- **Redeeming is not gated anywhere.** The redeem panel renders and a card is spendable in either
  mode.

### Why the issue-time guard is not enough on its own

The first version of this decision had only the cart and issue guards, and framed the issue guard as
"the one that protects the money". That was only true of the shop's side of the till. A customer
whose cart predated the mode change would complete checkout, be charged in full, and receive
nothing - no card, no email (`giftCardsBoughtOn()` is empty, so the mailer sends nothing), and no
record anywhere. The design took the charge and dropped the goods, and the only way anyone would
find out was a complaint.

The checkout constraint closes that window before the money moves. The issue guard stays as the last
line, but it is now loud rather than silent, because anything reaching it needs reconciling by hand.

## Consequences

- A shop can run a channel that only ever issues cards from the back office, and the gift card
  product flag survives the mode being switched back and forth.
- Three enforcement points means three places to keep in step. They share one checker, they are each
  covered by unit tests, and a functional test boots the container and asserts both constraints are
  registered **exactly once** and actually raise their violation.
- Both constraints are evaluated on **every** add-to-cart and **every** checkout in the shop, gift
  card or not. Each returns early for an order or item that is not a gift card, and a test asserts
  ordinary products are untouched in admin-only mode - an over-eager violation in either would stop
  the shop selling anything at all.
- The mode is on the configuration, so it is per channel. A shop wanting one policy everywhere sets
  it once per channel; there is no global switch, and adding one later would be a second way to say
  the same thing.
- **The add-to-cart button still renders, and still renders enabled.** Sylius disables it from
  `form.vars.valid`, which is true until the first live re-render, so in admin-only mode the
  customer's first feedback is an error *after* clicking rather than a control that was never
  offered. This is a real rough edge, not a non-issue: the plugin drives its whole shop UI through
  twig hooks and `sylius_shop.product.show.content.info.summary.add_to_cart` is a hookable region it
  could replace or suppress without touching a Sylius template. It was left alone to keep this
  change to the money path; the honest statement is that the refusal is correct but late, and the
  fix has a known home.
- `config/validation/*.xml` is registered by **nothing explicit**. FrameworkBundle scans
  `<bundle path>/config/validation`, and the bundle path is the repository root only because
  `MadcodersSyliusGiftCardPlugin::getPath()` says so. Prepending to `framework` from `load()` looks
  like it registers them and does not (`MergeExtensionConfigurationPass` runs every `prepend()`
  before it loads any extension, and loads FrameworkBundle first); doing it from `prepend()` really
  does register them, **a second time**, raising two identical violations per add-to-cart. Both
  mistakes are caught by the functional wiring test.

## Rules

1. Anything that lets a customer *obtain* a gift card asks `GiftCardPurchaseCheckerInterface`. A new
   purchase surface is not done until it does.
2. Nothing on the *redemption* path ever consults the sale mode. A card that exists is spendable in
   its channel; see [0010](0010-gift-card-as-tender.md).
3. An unknown or absent configuration means sellable. A new mode may narrow who can buy, but the
   default must stay the behaviour a shop had before the mode existed.
4. **A refusal after the customer has been charged is a bug, not a policy.** Any new guard on the
   purchase path belongs at or before `sylius_checkout_complete`. The issue-time guard is a
   backstop, and every time it fires it logs.
5. All three enforcement points stay. Dropping the issue guard reopens the stale-cart hole, dropping
   the checkout guard takes the customer's money, and dropping the cart guard refuses them late.
6. Do not register `config/validation` from the extension. See the last consequence above.
