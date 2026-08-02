# Coding rules

Conventions for `madcoders/sylius-giftcard-plugin`. These are the rules the quality gates and code
review enforce. Read `AGENTS.md` first for orientation and commands.

## Naming

| Thing | Convention | Example |
|---|---|---|
| PHP namespace | `Madcoders\SyliusGiftCardPlugin\` | `Madcoders\SyliusGiftCardPlugin\Model\GiftCard` |
| Test namespace | `Tests\Madcoders\SyliusGiftCardPlugin\` | `Tests\Madcoders\SyliusGiftCardPlugin\Unit\...` |
| Bundle | `MadcodersSyliusGiftCardPlugin` | |
| DI alias / config root | `madcoders_sylius_gift_card` | |
| Service id | `madcoders_sylius_gift_card.<concern>.<name>` | `madcoders_sylius_gift_card.applicator.gift_card` |
| Resource | `madcoders_sylius_gift_card.<resource>` | `madcoders_sylius_gift_card.gift_card` |
| Table | `madcoders_gift_card__<name>` | `madcoders_gift_card__gift_card` |
| Route | `madcoders_sylius_gift_card_<section>_<action>` | `madcoders_sylius_gift_card_shop_account_index` |
| Translation key | `madcoders_sylius_gift_card.<domain>.<key>` | `madcoders_sylius_gift_card.ui.remaining_balance` |
| Template namespace | `@MadcodersSyliusGiftCardPlugin/...` | |

## PHP

- `declare(strict_types=1);` in every file.
- Classes are `final` unless they are a model intended for host extension, or an abstract base.
- Constructor property promotion; `readonly` where the collaborator never changes.
- Type everything. PHPStan runs at `level: max` with **no baseline** - if it complains, fix the
  code, don't record the complaint.
- Depend on interfaces, never on concrete plugin classes, across service boundaries.
- Throw domain exceptions from `src/Exception/`, not bare `\RuntimeException`, for anything a caller
  might reasonably want to catch (unknown code, expired card, channel mismatch, insufficient
  balance).

## Money

- **All money is integer minor units.** No floats, ever - not in models, services, tests or
  fixtures.
- Amounts carry a `currencyCode`; a gift card can only be applied to an order in its own channel
  and currency.
- Never round in the plugin. If a calculation needs rounding, it belongs in Sylius' own money
  handling.

## Models and persistence

See `docs/adr-log/0002-doctrine-xml-mapped-superclasses.md`.

- Doctrine mapping in **XML** under `config/doctrine/`, as mapped superclasses. No attributes, no
  annotations on model classes.
- Extensions to Sylius models ship as interface + trait pairs the host application applies.
- Every schema change gets a migration in `src/Migrations/`. Never `doctrine:schema:update`.
- Associations target interfaces, resolved through Sylius resource metadata.

## Services

See `docs/adr-log/0003-service-wiring.md`.

- Declared explicitly in XML under `config/services/`, one file per concern, with explicit ids and
  arguments.
- Business logic lives in the service. Framework wiring - state machine callbacks, event listeners,
  controllers - stays thin and delegates.
- Anything hooking an order transition is wired for **both** state machine adapters (winzou and
  Symfony Workflow).

## Gift card invariants

These are the rules the domain must never violate. If a change makes one of them conditional, that
needs an ADR.

1. `initialAmount` is set once and never changes.
2. `amount` (remaining balance) is never negative and never exceeds `initialAmount`.
3. `amount` is only changed by `OrderGiftCardAmountModifier` or an explicit admin adjustment, and
   **every** change writes a `GiftCardTransaction` in the same unit of work.
4. A card is only redeemable when it is enabled, not expired, has `amount > 0`, and its channel
   matches the order's channel.
5. `redeemer` is assigned once, on first successful redemption, and is never reassigned.
6. The adjustment a card contributes to an order is capped at the order's remaining total; an order
   total never goes below zero because of gift cards.

## Presentation

- Twig hooks (`sylius_twig_hooks`) under `config/twig_hooks/` - the 1.x `sylius.ui` template event
  system does not exist in Sylius 2.x.
- Templates live in `templates/`, addressed as `@MadcodersSyliusGiftCardPlugin/...`, structured
  `admin/` and `shop/` to mirror Sylius.
- **No hardcoded user-facing strings.** Everything goes through a translation key in
  `translations/messages.en.yaml`; other locales are added as translations land.
- Grids and routes in YAML under `config/grids/` and `config/routes/`.

## Tests

- Unit tests mirror the `src/` namespace under `tests/Unit/`, and must not boot the kernel or touch
  a database - they run in the fast gate.
- Behat features describe behaviour in the shop/admin user's language, one feature file per flow.
- Test behaviour, not implementation: assert observable outcomes and public contracts, including
  edge and error paths. Mock only boundary collaborators. A test should survive a behaviour-
  preserving refactor.

## Commits and changelog

- Conventional Commits; scope names the area (`gift-card`, `checkout`, `admin`, `account`, `deps`).
- One logical change per commit.
- Notable changes go into `CHANGELOG.md` under `[Unreleased]` in the same commit.
