# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
