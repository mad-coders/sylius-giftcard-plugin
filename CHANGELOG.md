# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- A mailpit container in `compose.yml` (SMTP 1025, web interface 8025), with `MAILER_DSN` pointed at
  it. The plugin delivers a gift card's code by email, and until now `MAILER_DSN` was `null://null`,
  so there was no way for a contributor to see the one artefact a customer actually receives. Run
  `docker compose up -d mailpit` and read the mail at http://127.0.0.1:8025.

### Fixed

- The sale mode badge in the gift card configuration grid was invisible. It used Bootstrap's
  `bg-secondary`, which sets a background but no foreground colour, so "Sold in the shop" rendered as
  a blank grey pill. Now uses `text-bg-secondary`, as the rest of the plugin already did.
- The gift card configuration index rendered its own translation key as the page heading, breadcrumb
  and browser title: `madcoders_sylius_gift_card.ui.gift_card_configurations` was never defined.
- The gift card show page rendered `sylius.ui.yes` and `sylius.ui.no` verbatim. Sylius 2 does not
  ship those keys; the field now uses `sylius.ui.enabled` / `sylius.ui.disabled`, which it does, and
  which read better for a field labelled Enabled.

### Documentation

- Corrected the README, which still described redemption in the pre-tender terms the plugin
  abandoned: cards applied "against an order total" as "order adjustments" that "can never push a
  total below zero". Under ADR 0010 the total is precisely what a gift card does not touch. It now
  says the payment shrinks and the total does not, and links to the ADR.
- The README's admin bullet now mentions the sale mode, amount modes, mandatory expiry and the
  balance ledger, all of which shipped without it being updated.

### Security

- Redeeming a gift card is rate limited. A client that keeps submitting codes that do not work is
  refused after a configurable number of failures - ten per fifteen minutes by default - and the
  refusal is logged once per client per window at `warning` on the `security` channel, so a shop can
  alert on it. Only failures count. A successful redemption forgives the failures before it, but only
  once per window and only when a card was genuinely newly applied: applying a card does not debit it
  and can be repeated, so unlimited forgiveness would have sold unlimited guessing for the price of
  the cheapest card in the shop. The endpoint is an anonymous POST and a gift card code is money to
  whoever holds it, so unlimited attempts were a brute-force oracle. Removing a card is deliberately
  not limited: it resolves against the cart and never consults the repository. Closes #33.
- The limiter keys on the client **network** - IPv6 aggregated to its /64 - because a routed /64 comes
  free with any cheap VPS, and a second, looser window watches the whole shop for guessing spread
  across many addresses. The shop-wide window alerts at `error` rather than blocking by default;
  `shop_blocks: true` makes it enforce.
- The limiter **stands down, loudly, rather than lock a shop out**. A request carrying forwarding
  headers while `framework.trusted_proxies` is unset would otherwise put every customer behind a CDN
  in one bucket, so eleven wrong codes would stop redemption for everybody. That case logs a warning
  and is not limited. Configure trusted proxies - see `docs/INSTALLATION.md`.
- `symfony/lock` is suggested alongside `symfony/rate-limiter` and wired when present. Without it the
  counter is an unsynchronised read-modify-write, so concurrent attempts get roughly worker-count
  tries per round trip rather than the configured limit.
- `redemption_rate_limit.enabled: true` without `symfony/rate-limiter` installed now fails the
  container build instead of silently doing nothing, and an unparseable `interval` is rejected while
  the container is built rather than throwing on the first customer to type a code.
- A failed redemption now says the same thing whatever went wrong. "There is no gift card with this
  code", "this gift card cannot be used - it may be expired, disabled or already spent" and "this
  gift card cannot be used in this store" were three answers to the question *does this code exist?*,
  asked by anybody, as often as they liked. They are replaced by one message,
  `madcoders_sylius_gift_card.cart.not_usable`. The distinction has not been lost, it moved to where
  it is safe: *My gift cards* in the customer account shows the cards that are actually theirs, with
  balances, behind a login. The `cart.not_found`, `cart.not_redeemable` and `cart.channel_mismatch`
  translation keys are gone - a host that overrode them should override `cart.not_usable` and
  `cart.too_many_attempts` instead. See `docs/adr-log/0012-rate-limiting-gift-card-redemption.md`.

### Added

- A channel can now be set to issue gift cards **by an administrator only**, so a shop that hands
  cards out as goodwill or compensation is not forced to sell them as well. The setting lives on the
  channel's gift card configuration as a mode rather than a flag - "sellable to logged-in customers
  only" is the obvious next answer, and a boolean would need another column to say it. The mode is
  shown in the configuration list, so an operator running several channels can see which of them
  sell gift cards without opening each one.

  It is enforced at each of the three points where a customer can move from wanting a gift card to
  holding one: adding one to the cart is refused, **completing checkout with one in the cart is
  refused**, and paying such an order issues nothing. The checkout refusal is the one that matters,
  because it is the last point at which the customer has not yet been charged - a cart outlives the
  setting, so a check only at the cart would let every cart that predates the change through, for as
  long as the oldest unpaid order lives. It also covers raising the quantity of a gift card already
  in the cart, which Sylius does through a path that never runs the add-to-cart check at all.

  The guard at issue stays as a backstop and now **logs a warning** when it fires, naming the order
  and the channel. Anything reaching it has been charged and has no card, and that needs reconciling
  by hand rather than waiting for a complaint.

  One rough edge: the add-to-cart button still renders, and still renders enabled, so in admin-only
  mode the customer's first feedback is an error after clicking rather than a control that was never
  offered. The refusal is correct, but late.

  **Redeeming is untouched in either mode.** A card an administrator handed out is money the shop
  has already promised; a mode that refused to take it back would turn a goodwill gesture into a
  complaint. See `docs/adr-log/0013-gift-card-sale-mode.md`.

  Existing shops are unaffected: the mode defaults to sellable, in the model, in the column default
  and for a channel with no configuration at all. Closes #32.

- **Customers choose what a gift card is worth.** A channel's gift card configuration now decides how
  the amount is picked: the product's price as before, a list of preset amounts, any amount within a
  minimum and maximum, or presets plus a free amount. Presets and bounds are per channel and in that
  channel's currency. The chosen amount becomes the order line's price, so the order total, the taxes
  and the payment all reflect it, and the issued card is worth exactly that. Closes #34.
- **Customers can leave a short message with a gift card.** Up to 255 characters, shown on the form,
  enforced server side, stored on the cards issued for that line only, and shown with the code in the
  delivery email, on the card's page in the customer's account and in the admin. It is untrusted text
  and is rendered as text everywhere - with a test that proves it rather than assuming it. Closes #35.
- Two gift cards bought in one order keep their own amount and message even when they are the same
  product. Sylius merges cart lines whose variants match and discards the incoming one, so without
  this the second card silently inherited the first card's amount and message - and the customer was
  charged for two of whichever they picked first.
- Both fields sit on the gift card product page inside Sylius' own add-to-cart form, as plain HTML:
  the presets are radio buttons styled as cards, the free amount is a number input, the message is a
  textarea. Nothing needs JavaScript to decide what gets submitted.
- The gift card redeem field is now in the checkout, under the totals it changes, on the addressing,
  shipping and payment steps and on the summary page. It existed only on the cart before, so a
  customer already in checkout had to go back to find it - at exactly the moment they are looking at
  what they are about to pay. Applying or removing a card returns them to the step they were on.
  Not on the addressing step: applying is a post and redirect, so anything typed into that step's
  form and not yet submitted would be lost, and on that step it is a whole hand-typed address.
  Closes #30.

### Changed

- **Every gift card now expires, and a gift card can no longer be paid for with a gift card.** The
  two go together: an expiry date a holder can renew for free by rolling one card into the next is
  not an expiry date. Closes #31 and #41. See
  `docs/adr-log/0015-every-gift-card-expires.md` and
  `docs/adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md`.

  **This changes behaviour for everyone on RC.2, including channels with no configuration at all.
  Read the upgrade notes below before running the migration.**

  What changed, in detail:

  - `GiftCardExpiryCalculatorInterface` is now the only thing that produces an expiry date, and its
    return type is **not nullable**. A channel with no configuration, a blank validity period, one
    that cannot be parsed and one that does not move the date forward (`0 days`, `-1 year`) all fall
    back to the plugin's documented default of **one year**. None of them can issue a card that never
    expires, and none can issue one that is already expired.
  - `GiftCardConfigurationInterface::calculateExpiryDate()` is **removed**. The configuration holds
    the period; turning it into a date belongs to the calculator. A host that called it should call
    `madcoders_sylius_gift_card.calculator.gift_card_expiry` instead.
  - `expires_at` is **NOT NULL**, and `GiftCard::expiresAt` carries a `NotNull` constraint, so the
    admin form refuses a cleared field with a message naming it rather than a driver-level error.
  - A channel's **validity period is required** and is refused if it cannot produce a future date.
    `1 yaer` used to save cleanly and quietly issue cards that never expired.
  - **"Never expires" is no longer expressible anywhere** - not in a fixture, not on a form, not in
    the database. The `expires_at: never` fixture option is gone, along with the `GIFT-NOEXPIRY` demo
    card (replaced by `GIFT-LONGLIFE`, twenty-five years out) and the
    `madcoders_sylius_gift_card.ui.never_expires` translation key. A shop that wants a card to
    outlive everybody says so with a long validity period, which is still a dated liability.
  - `GiftCardConfiguration` gains a **`tenderMode`** (`goods_only`, the default, or `anything`),
    answering whether a gift card may pay for another gift card. Enforced in three places: when a
    card is applied, at `sylius_checkout_complete`, and in the order processor, which caps what
    applied cards may cover at the order total **less its gift card lines**.
  - A **mixed basket still works**: a card pays for the shoes and not for the gift card next to
    them. A gift-card-only basket refuses redemption outright - including any shipping on it, because
    the postage is for goods this order does not have - with a message of its own explaining
    why - safe to be specific because the basket is judged before the code is looked up, so it
    reveals nothing about which codes exist.

  **Upgrading:**

  - Run `bin/console doctrine:migrations:migrate`. `Version20260902100000` gives every card without
    an expiry date one, measured from **that card's own creation date** plus its channel's validity
    period (a year where the channel has no usable one), then makes the column NOT NULL. A card
    created three years ago in a channel with a one-year period comes out **already expired** - money
    a holder can no longer spend - so tell them before you run it, not after. `docs/INSTALLATION.md`
    gives the two queries that count the affected cards before and after.
    `Version20260902110000` then adds the tender mode column.
  - **Every channel loses the ability to buy gift cards with gift cards, including channels you
    never configured.** Unlike the sale mode, this default deliberately does not preserve the old
    behaviour: the old behaviour was the hole. A shop that wants it back sets *What a gift card pays
    for* to *Anything, gift cards included* on that channel's configuration - and gives up
    enforceable expiry dates and a traceable purchaser chain in it.
  - Any channel whose validity period is blank or unparseable keeps working, on the default period,
    but cannot be re-saved from the admin until the period is corrected.
  - **Breaking for hosts that redefine these services positionally.** Each takes one appended
    argument: `GiftCardApplicator` and `OrderGiftCardProcessor` take
    `madcoders_sylius_gift_card.checker.gift_card_tender`; `GiftCardFactory`,
    `PrepareGiftCardOnCreateListener` and `GiftCardExampleFactory` take
    `madcoders_sylius_gift_card.calculator.gift_card_expiry`. `GiftCardType` takes the calculator,
    the configuration provider and `sylius.repository.channel`, which is what pre-fills the expiry
    field. An out-of-date definition fails on arity rather than binding the wrong service.
- **Breaking for hosts that redefine `madcoders_sylius_gift_card.operator.order_gift_card`
  positionally.** `OrderGiftCardOperator::__construct()` takes two more arguments: a
  `GiftCardPurchaseCheckerInterface` and an optional PSR-3 `LoggerInterface`. Both are **appended**,
  so the existing four keep their positions and an out-of-date definition fails on arity rather than
  binding the entity manager into the checker's slot. A host that redefined the service needs to add
  `madcoders_sylius_gift_card.checker.gift_card_purchase` as the fifth argument; the logger may be
  omitted, and is passed with `on-invalid="null"` so the plugin still works without MonologBundle.
- **A card's face value now excludes tax charged on top of the price.** It is what was paid for the
  unit, promotions included, minus any non-neutral tax adjustment. A tax-exclusive shop previously
  issued a 55 card to a customer who paid 50 plus tax - the mis-issue ADR 0010 named and did not fix
  - so the same choice was worth different amounts depending on how the shop prices.

  Tax-inclusive shops are unaffected: they record included tax as a *neutral* adjustment, which
  Sylius does not count in `getAdjustmentsTotal()`, so there is nothing to subtract and the gross
  price stands. That is correct - the customer asked for a 50 card and paid 50.

  **Upgrading a tax-exclusive shop:** cards issued from now on are worth the pre-tax amount, so a
  customer who previously received a 55 card for a 50 product now receives a 50 one. This includes
  **orders already placed and awaiting payment**, whose cards have not been issued yet - the customer
  may be holding an order confirmation quoting the larger figure. Cards already issued are not
  touched. Either settle outstanding gift card orders before upgrading, or expect to top up the
  affected cards by hand from the admin.
- **Host applications must apply `OrderItemInterface` and `OrderItemTrait` to their `OrderItem`**, and
  register the override, exactly as they already do for `Order`, `OrderItemUnit` and `Product`. That
  is where the chosen amount and the message live. See `docs/INSTALLATION.md` step 6, and run the new
  migration.
- Gift card messages are rendered by the panel itself, under plugin-owned flash types, rather than
  left to the page. Only the cart and the checkout summary step render flashes at all, so a refusal
  on the shipping or payment step was silent - and the unread message then surfaced on whichever
  later page did render flashes, attached to the wrong action.
- The apply and remove endpoints accept an optional `_return_to` field naming where to send the
  customer afterwards. It is resolved through a whitelist of keys, never a submitted URL or the
  referer, so a forged value can only send them to their own cart. Anything already posting to these
  endpoints without the field keeps redirecting to the cart.

### Added

- Behat coverage for a gift card that stops being redeemable *after* the customer applied it -
  expiring, or disabled by an administrator, mid-checkout. The processor re-checks every card on each
  pass and drops the ones that are no longer redeemable, so the payment goes back to the full amount
  and the card keeps its balance. That was untested, and it is the one path where the plugin could
  have handed over goods for money nobody paid.

### Fixed

- `make serve-test` (and `make serve`) served the application with PHP's default 128M memory limit
  and without `variables_order=EGPCS`. The first meant the shop 500s with a blank page on any request
  that warms a cold container; the second meant `APP_ENV` never reached Symfony, so `make serve-test`
  quietly booted `dev` against the dev database. Both are why the `@javascript` Behat suite could not
  be run locally by following the documented workflow.
- The gift card configuration form silently ignored a code length below the minimum. `setCodeLength()`
  raises anything shorter to 12 as a backstop, so by the time the field was validated it held the
  raised value and the constraint passed - an operator who asked for 4-character codes was given
  12-character ones and told nothing, walking away believing their channel issues short codes. The
  submitted value is now checked before the model rounds it up, and the form says so.

### Added

- Behat coverage for the per-channel gift card configuration screens, which had none: creating a
  configuration through the admin, and refusing a code length below the minimum. That minimum is a
  security control - a guessable gift card code is money anybody can spend - and the form is the only
  place it is enforced against a human.

### Fixed

- `make backend`, `make db-reset`, `make fixtures` and `make serve` failed on a clean checkout:
  building the full Sylius container exceeds PHP's default 128M CLI limit, and only `lint-container`
  lifted it. Every console call now does.
- Naming a customer that does not exist as a gift card fixture's `purchaser` or `redeemer` silently
  linked nobody, because Sylius' `LazyOption` yields null for an unknown email. It now fails with the
  address it could not find - the previous behaviour produced demo data that claimed to show the
  two-customer model while showing nothing.

### Added

- `madcoders_gift_card_product`, a fixture that marks products as gift card products - by code, or
  simply by count for a demo whose catalogue is generated. Without it there was no way to show a
  gift card being *sold*, which is half the plugin.
- The demo suite now covers every state a card can be in: spendable, partly spent, spent out,
  expired, disabled, expiring soon, never expiring, and all three shapes of the two-customer model
  (bought-not-used, bought-by-one-used-by-another, bought-and-used-by-the-same-person). It creates
  two known customers for those, since Sylius' own fixtures generate random addresses.
- `expires_at: never` in the gift card fixtures, for a card that outlives the channel's configured
  validity period. A null previously meant "let the configuration decide", so this was impossible to
  express.

### Fixed

- **The plugin's migrations never ran in a host application.** They were registered under the
  `DoctrineMigrations` namespace, which a Sylius application already maps to its own `migrations/`
  directory - and the application's configuration beats anything the plugin prepends, so the path
  was silently discarded. `doctrine:migrations:migrate` created no gift card tables at all. They now
  register under `Madcoders\SyliusGiftCardPlugin\Migrations`, as every other Sylius plugin does.
  The test application was unaffected, which is why nothing caught it until the plugin was installed
  into a real Sylius.
- **`docs/INSTALLATION.md` told you to create three files that already exist.** Sylius Standard ships
  `src/Entity/Order/Order.php`, `src/Entity/Order/OrderItemUnit.php` and
  `src/Entity/Product/Product.php`, and they already carry other plugins' interfaces and traits.
  Following the guide literally replaced them, which stripped those traits and `Product`'s
  `createTranslation()` - breaking product translations, and with them the fixtures and the shop.
  Step 6 is now written as a modification of the existing classes.

### Added

- An `installation` CI job that installs the plugin into a fresh Sylius Standard by following
  `docs/INSTALLATION.md`, on Sylius 2.0 and 2.2, and asserts the result boots: migrations produce the
  schema the models map to, the documented routes and services resolve, the promised tables and
  columns exist, and the fixtures put real gift cards in the database. The files come from the guide
  itself, so a stale or wrong snippet fails CI. Runnable locally with `make install-test`. Both bugs
  above were found by writing it.

### Fixed

- **Validation messages no longer reach the user as raw translation keys.** Symfony resolves every
  constraint violation in the `validators` catalogue, never in `messages`, so nine keys that lived in
  `translations/messages.*.yaml` rendered as, for example,
  `madcoders_sylius_gift_card.gift_card.amount.positive` where a sentence belonged. They have moved
  to `translations/validators.en.yaml` and `validators.pl.yaml`: the gift card `code`,
  `initial_amount` and `amount` messages, and the gift card configuration's `code_length`,
  `amount_presets` and `bounds` ones. A host that overrode any of them in `messages` has to move its
  override to `validators` too. Closes #37.
- The gift card configuration form's own error messages are translated from `validators` as well,
  rather than from the translator's default domain. Those are added as a `FormError` by hand, which
  nothing translates on the way out, so the form has to do it - and doing it in `messages` is what
  split the plugin's validation messages across two catalogues and hid the bug above: the one message
  the plugin translated itself was the one message that worked, and the only one a test asserted.
  There is now one rule, recorded in `ai/coding-rules.md`: a validation message lives in `validators`,
  whoever raises it.
- The admin balance-adjustment form renders with the admin form theme. It was falling back to Twig's
  default theme, so a rejected adjustment showed its reason as an unstyled bare list in the middle of
  the admin panel.

### Documentation

- Recorded the removal of the winzou state machine wiring as
  `docs/adr-log/0011-symfony-workflow-only.md`, and corrected the five documents that still described
  it as supported. Two of those were instructions rather than description - `ai/coding-rules.md` and
  `AGENTS.md` both carried a standing rule to wire every order transition for both adapters, which
  would have had the next change reintroduce it. `docs/INSTALLATION.md` told hosts the winzou
  callbacks were prepended for them, which was simply false, and it now also documents the two
  decorated Sylius payment services.
- Replaced the remaining pre-tender language ("reduce the order total", "gift card discount",
  "redeem them against order totals") in the README, `composer.json`, `docs/PLAN.md` and
  `docs/INSTALLATION.md`. ADR 0004 is marked "do not implement from it" with its superseded sections
  and its withdrawn rule 3 struck through.

## [1.0.0-RC.2] - 2026-08-08

Second release candidate. A source review after RC.1 found four ways an order settled with a gift
card could lose money or get stuck, all of them silent. Anyone running RC.1 should upgrade.

The worst of them: an order settled with a gift card never reached `paid`. Since `paid` is the
transition that issues purchased gift cards and emails their codes, a customer who bought a card and
paid for part of it with another one was charged, received nothing, and their order could never be
fulfilled.

The gift card split is now shown on every surface Sylius renders an order total on, not just the
cart. A host application showing an amount due must still read `Order::getAmountToPay()` rather than
`getTotal()` - see `docs/adr-log/0010-gift-card-as-tender.md`, whose rules 4 and 5 now spell out
where that applies and which Sylius services compare against the total.

Known limitations are unchanged from RC.1: no PDF gift cards, no API Platform resources, no bulk
generation or import, no partial refunds back onto a card, and a card's face value comes from the
product's price rather than being chosen by the customer.

### Added

- The gift card split - what the cards cover, and what is actually owed - is now shown on every
  surface Sylius renders an order total on: the checkout sidebar, the checkout summary page, the
  account order page and the admin order view. Previously only the cart showed it, so a customer
  part-paying with a card was told at checkout to expect a charge much larger than the one that
  would reach their card. The admin order view also names each card by code.

- `GiftCardInterface::refund()` and `GiftCardBalanceModifierInterface::refund()`, for giving back
  money an order took off a card. They differ from `credit()` only in not being capped at the card's
  face value; that cap exists to catch a mistyped admin top-up and has no business refusing a refund.
  A host application that reimplements `GiftCardInterface` or the modifier must implement them.

### Changed

- A gift card's code can no longer be edited once the card has been issued; the admin form shows it
  read-only. The code is bearer money the customer is already holding, and it is the only link
  between an order and the card that paid for it - renaming an issued card invalidated the code in
  the customer's hand and silently stranded every refund for orders that used it.

### Fixed

- Cancelling an order no longer fails outright when an administrator topped the gift card up in the
  meantime. The refund would take the card above its face value, the model refused it, and the
  exception escaped the workflow listener as a 500 - leaving the order uncancelled and the customer
  out of pocket. Refunds now go through `refund()` rather than `credit()`.

- A failed or cancelled payment no longer asks the customer for the gift card money a second time.
  Sylius replaces such a payment with one for `Order::getTotal()`, which under the tender model is
  the full value of the goods, while the cards were already debited when the order was placed.
  `sylius.order_processing.order_payment_processor.after_checkout` is now decorated to size the
  replacement from `Order::getAmountToPay()`, less anything already captured.

- An order settled with a gift card now reaches the `paid` payment state. Sylius' resolver compares
  completed payments against `Order::getTotal()`, which the tender model deliberately leaves at the
  full value of the goods, so a part-paid order stuck at `partially_paid` and a fully covered one at
  `awaiting_payment`. Neither could be fulfilled, and because `paid` is what issues purchased gift
  cards and emails their codes, a customer who bought a card and paid for it partly with another one
  was charged and received nothing. `sylius.state_resolver.order_payment` is now decorated to compare
  against `Order::getAmountToPay()` instead; everything else is left to Sylius.

## [1.0.0-RC.1] - 2026-08-07

First release candidate. Gift cards can be sold, redeemed, administered and tracked by the customers
they belong to, across Sylius 2.0, 2.1 and 2.2 on Symfony 6.4 and 7.4, with MySQL, MariaDB and
PostgreSQL.

A gift card is treated as **money against the amount to pay**, not a discount on the order total.
A host application showing an amount due must read `Order::getAmountToPay()`, not `getTotal()` -
see `docs/adr-log/0010-gift-card-as-tender.md`.

A source review before this candidate found and fixed two defects that would have cost real money -
customers charged the full total while their card was debited, and every product save silently
clearing its gift card flag - plus an anonymous code-enumeration hole. Those are listed under Fixed
below, with the tests that catch them.

Known limitations, deferred beyond 1.0: no PDF gift cards, no API Platform resources, no bulk
generation or import, no partial refunds back onto a card, and a card's face value comes from the
product's price rather than being chosen by the customer. A card drained between being applied and
the order being placed also leaves its coverage on that order rather than the order being re-priced.

### Fixed

- **Cancelling an order could inflate a gift card.** The credit was taken from the order's
  adjustment - what the order *intended* to take - rather than from the ledger's record of what it
  actually took. When a debit had been clamped because the card was spent elsewhere in between, the
  cancellation handed back more than was ever charged. It now credits the outstanding debit recorded
  against that order, netting off credits already given, so a repeated cancellation is a no-op.
- The admin balance adjustment now checks authorization itself rather than relying only on the
  firewall, since it moves money. The required role is the
  `madcoders_sylius_gift_card.admin_role` parameter.
- The admin gift card form validates its amount, and a duplicate code is reported as a form error.
  Previously a blank amount, a zero amount or a code already in use produced a 500.
- Gift card codes are configured with a minimum length of 12 characters, enforced in the model as
  well as the form. A shorter code space is guessable, and the misconfiguration was not visible
  until codes were already issued.
- Exception messages mask gift card codes. They reach application logs and error trackers, which
  retain them for months, and a code is bearer money.
- Added interface-named aliases for the repositories and the order processor, so host code can
  autowire the plugin's contracts.

### Changed

- **A gift card is now tender rather than a discount.** It no longer reduces the order total; the
  order stays worth what the goods are worth and the card comes off what the customer has to pay.
  This keeps the tax base at the full sale value (tax on the card was settled when it was bought),
  keeps reporting and refunds honest, and stops a card switching off a "spend over X" promotion.
  `Order::getAmountToPay()` and `Order::getGiftCardTotal()` expose the split - **a host application
  showing "you pay" must use `getAmountToPay()`, not `getTotal()`**. See
  `docs/adr-log/0010-gift-card-as-tender.md`.
- **Removed the winzou state machine wiring.** Sylius 2.x does not install winzou/state-machine - it
  is only a composer `suggest` - the default adapter is Symfony Workflow, and CI never exercised the
  winzou path. It was untested surface carried for no supported configuration.

### Added

- A checkout Behat suite covering the path nothing exercised before: a gift card applied to a real
  order that is then placed. It asserts the **payment amount**, which is what catches a gift card
  processor running in the wrong place, plus the balance moving, the ledger entry, the redeemer
  being recorded, several cards on one order, and the balance coming back on cancellation.
- A fully covered order now has a documented outcome: Sylius removes the payment entirely rather
  than sending the customer to a gateway for zero.

### Fixed

- **Customers were charged the full order total while their gift card was still debited.** The
  order processor ran at priority -10, below Sylius' payment processor at 0 - and that processor
  sets the payment amount from `Order::getTotal()`. The discount was therefore applied *after* the
  amount had been captured. It now runs at priority 5, between taxes and payment, with a test
  asserting the ordering against Sylius' own configuration so an upgrade cannot silently undo it.
- **Editing any product silently cleared its gift card flag.** The flag is a mapped checkbox, and
  Sylius' product form renders only what a hookable emits, so it was never displayed - and an
  absent checkbox submits as false. There was also no way to mark a product as a gift card from the
  admin at all. Both are fixed by rendering the field on the create and update forms.
- **An unauthenticated visitor could enumerate valid gift card codes.** Removing a card resolved
  the code against the repository before checking the cart, so an unknown code and a real one
  belonging to somebody else produced different responses. Removal now only ever looks at the cards
  on the caller's own cart.
- Removing a gift card required a `GET` with no CSRF token, so a third-party page could strip a
  shopper's discount. It is now a `POST` with a token.
- The plugin no longer injects demo fixtures into the host's `default` suite, and no longer
  hardcodes a channel code - which broke `sylius:fixtures:load` for any shop whose channel was not
  named `FASHION_WEB`. The demo data moved to an opt-in `madcoders_gift_card` suite.

### Added

- Project bootstrap: composer package, Sylius 2.x test application wiring, plugin bundle and
  dependency injection extension.
- Toolchain driven through Make: PHPStan (level max, no baseline), ECS, Rector, PHPUnit and Behat.
- GitHub Actions CI: a fast static/unit job plus a Sylius/Symfony/database matrix built with
  `SyliusLabs/BuildTestAppAction`.
- Conventional Commits template and a pre-commit hook running the fast quality gate.
- Project plan (`docs/PLAN.md`), architectural decision log (`docs/adr-log/`), agent and contributor
  guides.
- Gift card domain model: `GiftCard` (balance, expiry, channel, origin), `GiftCardTransaction`
  (append-only balance ledger) and per-channel `GiftCardConfiguration`.
- A gift card records both the customer who **bought** it and the customer who **redeems** it, so
  the person spending the card can track its remaining balance even when somebody else paid.
- Extension traits for the Sylius `Order`, `OrderItemUnit` and `Product` models, carrying their own
  Doctrine mapping.
- Doctrine XML mapping, Sylius resource registration, repositories and the first migration (written
  against the Schema API, so it runs on MySQL, MariaDB and PostgreSQL).
- Gift card redemption: applying a card to an order produces a negative `madcoders_gift_card`
  adjustment, capped at the order's remaining total, so cards stack and a total never goes below
  zero. The plugin's adjustment type is registered with Sylius' adjustment clearer, so promotions
  and taxes are never computed against an already-discounted total.
- Balances move when the order is placed and are restored when it is cancelled, driven by the
  adjustments actually charged and wired for **both** Sylius 2.x state machine adapters (winzou and
  Symfony Workflow). Every movement writes a ledger entry, and the first redemption records the
  redeeming customer on the card.
- Gift card code generator (unambiguous alphabet, cryptographically random, collision-checked) and
  per-channel configuration provider.
- Shop cart UI: a gift card panel on the cart page to apply a code, see the applied cards and their
  remaining balance, and remove one again, plus a summary line showing what the cards take off the
  total. Works without JavaScript.
- Sylius fixtures for gift cards and per-channel configuration, wired into the default suite, so
  `bin/console sylius:fixtures:load` gives a shop with full, partly-spent, expired and disabled
  cards to try.
- Behat coverage of the whole redemption flow: applying, stacking, the remaining-total cap,
  removal, and refusal of expired, disabled and unknown codes.
- Admin panel: grids, create/edit forms and menu entries for gift cards and per-channel
  configuration. Leaving the code blank on create generates one from the channel's configuration.
- A gift card show page carrying the balance, both customer links and the full balance history, plus
  a manual balance adjustment action that goes through the same write path as redemption - so a
  correction is recorded in the ledger like any other change.
- `GiftCardBalanceModifier`: the single place a balance may change, always writing the matching
  ledger entry.
- Selling gift cards: a product can be marked as a gift card in the admin, and paying for an order
  issues one card per purchased unit, carrying what was actually paid for that unit and linked to
  the customer who bought it. Cancelling the order takes the cards out of circulation.
- Issuing is wired for both Sylius 2.x state machine adapters, on the order payment `pay`
  transition.
- A notification email listing the codes bought on an order, sent to the buyer when the order is
  paid. Mail failures do not fail the payment.
- Polish translations alongside English, for both the `messages` and `flashes` domains.
- `docs/USAGE.md` describing how the plugin behaves once installed, and an expanded
  `docs/INSTALLATION.md` covering what the plugin registers and how to override it.
- "My gift cards" in the customer account: the cards you use (with their remaining balance) listed
  separately from the cards you bought, plus a balance history page per card. A customer can only
  see cards they are linked to.

[Unreleased]: https://github.com/mad-coders/sylius-giftcard-plugin/compare/v1.0.0-RC.2...1.0
[1.0.0-RC.2]: https://github.com/mad-coders/sylius-giftcard-plugin/compare/v1.0.0-RC.1...v1.0.0-RC.2
[1.0.0-RC.1]: https://github.com/mad-coders/sylius-giftcard-plugin/releases/tag/v1.0.0-RC.1
