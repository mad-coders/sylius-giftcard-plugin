# Project plan - madcoders/sylius-giftcard-plugin

Gift card plugin for **Sylius 2.x**. Functionally modelled on
[Setono/SyliusGiftCardPlugin](https://github.com/Setono/SyliusGiftCardPlugin) - in particular how
gift cards interact with order totals - with two deliberate differences:

1. **PDF generation is out of scope** for the initial release (no `knp_snappy`, no rendering
   pipeline, no PDF templates). Gift cards are delivered by email and shown in the customer
   account.
2. **A gift card is linked to two customers**: the *purchaser* (who bought it) and the *redeemer*
   (who used it). Setono only records a single `customer`. Tracking both lets the person actually
   spending the card see its remaining balance in their account, independently of who paid for it.

Repository conventions, tooling and CI are modelled on
[mad-coders/sylius-rma-plugin](https://github.com/mad-coders/sylius-rma-plugin).

## Target stack

| | |
|---|---|
| PHP | `^8.3` |
| Sylius | `^2.0` (CI matrix: `~2.0.0`, `~2.1.0`, `~2.2.0`) |
| Symfony | `^6.4 \|\| ^7.4` |
| Test application | `sylius/test-application` (the Sylius 2.x plugin convention) |
| Quality gates | PHPStan (level max), ECS, Rector, PHPUnit, Behat |
| Databases covered in CI | MySQL 8.4, MariaDB 11.4, PostgreSQL 16 |

## Branching

Trunkless, version-branch model (same as Sylius itself and the RMA plugin):

- **`1.0` is the primary branch** and the repository default. There is no `main`/`master`.
- Work happens on short-lived branches (`feat/...`, `fix/...`, `docs/...`) merged into `1.0` via
  pull requests.
- Future minor lines get their own branch (`1.1`, `1.2`, ...), created from `1.0` when the line is
  opened; fixes are merged into the oldest supported line and up-merged.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/) - see
  `docs/adr-log/0008-conventional-commits.md`.

## Domain model

```
GiftCard
├── code                unique, generated per channel configuration
├── channel             gift cards are channel-scoped
├── currencyCode
├── initialAmount       immutable once set (minor units)
├── amount              remaining balance (minor units)
├── enabled             a disabled card cannot be redeemed
├── expiresAt           nullable
├── origin              how the card came to exist: admin | order
├── purchaser           Customer who bought the card          <- both links are first-class
├── redeemer            Customer who redeemed the card        <-
├── orderItemUnit       the unit the card was bought as (null for admin-created cards)
├── appliedOrders       orders the card has been applied to (many-to-many)
└── transactions        the balance ledger (see below)

GiftCardTransaction      append-only ledger, one row per balance change
├── giftCard
├── order                nullable (null for manual admin adjustments)
├── type                 debit | credit
├── amount               always positive (minor units)
├── balanceAfter         the card balance after this transaction
└── createdAt

GiftCardConfiguration    per-channel settings
├── channel              one-to-one
├── enabled
├── codeLength / codePrefix
└── validityPeriod       e.g. "1 year"; used to compute expiresAt on creation
```

Extension points on Sylius models (traits the host application applies to its own entities):

| Sylius model | Added by the plugin |
|---|---|
| `Product` | `giftCard: bool` - marks the product as a gift card product |
| `OrderItemUnit` | `giftCard: ?GiftCard` - the card generated for this unit |
| `Order` | `giftCards: Collection<GiftCard>` - the cards applied to this order |

### Why a transaction ledger

The remaining balance is stored on `GiftCard::$amount` (as in Setono) so that redemption stays a
cheap read. The ledger exists so the *redeemer* can answer "where did my balance go?" in their
account, and so an admin can audit a card without reconstructing history from order adjustments.
It is append-only and never the source of truth for the balance.

## How totals work

Redemption follows the Setono approach, which is the idiomatic Sylius one: **a gift card is an
order adjustment, not a payment method.**

- Adjustment type: `AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT` (`madcoders_gift_card`).
- `OrderGiftCardProcessor` (an `OrderProcessorInterface`, registered last in the chain) removes its
  own previous adjustments, then for each applied card adds a **negative** adjustment capped at the
  order's remaining total, tagging it with the card's code as `originCode`. Cards therefore stack
  and can never take the order total below zero.
- `OrderGiftCardAmountModifier::decrement()` moves money off the card when the order is placed;
  `increment()` puts it back when the order is cancelled. Both walk the adjustments by `originCode`,
  so the amount actually charged is the amount actually returned.
- Every decrement/increment writes a `GiftCardTransaction`.

Because Sylius 2.x ships **two** state machine adapters, the transitions are wired for both:
a `winzou_state_machine` callback block *and* Symfony Workflow event listeners
(`workflow.sylius_order.completed.create` / `.cancel`) pointing at the same services. The business
logic lives in the service; the wiring is a thin adapter. See
`docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md`.

## Delivery phases

Each phase is a pull request into `1.0`, green on the quality gate before merge.

| # | Phase | Scope |
|---|---|---|
| 0 | **Bootstrap** | Repository, composer package, test application wiring, tooling (PHPStan/ECS/Rector/PHPUnit/Behat), Makefile, CI, git hooks, docs and ADR log, this plan. |
| 1 | **Model & persistence** | `GiftCard`, `GiftCardTransaction`, `GiftCardConfiguration`, the `Product`/`Order`/`OrderItemUnit` extension traits, Doctrine XML mapping, Sylius resource registration, repositories, first migration. |
| 2 | **Admin** | Grids, forms and menu entries for gift cards and per-channel configuration; manual card creation and balance adjustment; code generator. |
| 3 | **Redemption & totals** | `GiftCardApplicator`, `OrderGiftCardProcessor`, `OrderGiftCardAmountModifier`, adjustment wiring, cart/checkout form + controller, twig hooks showing the applied cards and the remaining total. |
| 4 | **Selling gift cards** | Gift card products, card generation on payment, state machine listeners for both adapters, purchaser association, notification email. |
| 5 | **Customer account** | "My gift cards" - cards bought and cards redeemed, remaining balance, transaction history. This is the phase that pays off the two-customer model. |
| 6 | **Fixtures, tests & docs** | Sylius fixtures for cards and configuration, Behat features covering each user-visible flow, unit tests for every service, installation and usage documentation. |

Deferred beyond 1.0: PDF gift cards, API Platform resources, bulk generation/import, partial
refunds back onto a card.

## Definition of done (per phase)

- `make verify` is green (composer validate + PHPStan + ECS + unit tests).
- New user-visible behaviour has a Behat feature; new services have unit tests.
- Load-bearing decisions are recorded as an ADR in `docs/adr-log/`.
- `CHANGELOG.md` updated under `[Unreleased]`.
