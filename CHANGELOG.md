# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
