# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
