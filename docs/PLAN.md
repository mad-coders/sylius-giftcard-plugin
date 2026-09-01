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
├── expiresAt           required; every card expires (ADR 0015)
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
├── validityPeriod       required, e.g. "1 year"; the only input to the expiry calculator
├── saleMode             whether the shop may sell gift cards here (ADR 0013)
├── amountMode / amountPresets / minimumAmount / maximumAmount   (ADR 0014)
└── tenderMode           whether a gift card may pay for a gift card (ADR 0016)
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
- `OrderGiftCardProcessor` is an `OrderProcessorInterface` registered at priority **-10**, below
  every Sylius processor *including the payment processor*, so it sees the final total after items,
  shipping, promotions and taxes - and can then settle the cards against the payment Sylius has
  just sized. For each applied card it adds a **neutral** adjustment capped at what is still owed,
  tagged with the card's code as `originCode`, and takes the covered amount off the payment.
  `Order::getTotal()` is deliberately left alone: a gift card is money against the amount to pay,
  not a discount on the price. See `docs/adr-log/0010-gift-card-as-tender.md`.
- The adjustment type is registered with Sylius' own `OrderAdjustmentsClearer` (priority 60) by a
  compiler pass, rather than the processor removing its own adjustments. This is load-bearing: the
  clearer runs *before* promotions (20) and taxes (10), so a previous run's coverage can never
  survive into the next one and compound, and reprocessing a cart stays idempotent.
- `OrderGiftCardAmountModifier::debit()` moves money off the cards when the order is placed;
  `credit()` puts it back when the order is cancelled. Both work from the order's *adjustments*, so
  the amount returned is exactly the amount charged - including when a card was only partly used
  because the order was cheaper than its balance.
- Every debit and credit writes a `GiftCardTransaction`, and the first debit records the redeeming
  customer on the card.

Order transitions are wired through **Symfony Workflow event listeners only**
(`workflow.sylius_order.completed.create` / `.cancel`). Sylius 2.x does not install
`winzou/state-machine`, so a winzou callback block would be dead configuration CI could never
exercise. The business logic lives in the service; the listener is a thin adapter. See
`docs/adr-log/0011-symfony-workflow-only.md`.

Two Sylius services compare a payment against `Order::getTotal()`, which the tender model leaves at
the full value of the goods, so both are decorated: `sylius.state_resolver.order_payment` and
`sylius.order_processing.order_payment_processor.after_checkout`.

## Delivery phases

Each phase is a pull request into `1.0`, green on the quality gate before merge.

Phases 2 and 3 were swapped during delivery: redemption is the functional core of the plugin and
depends on nothing in the admin phase, so it shipped first.

| # | Phase | Scope |
|---|---|---|
| 0 | **Bootstrap** | Repository, composer package, test application wiring, tooling (PHPStan/ECS/Rector/PHPUnit/Behat), Makefile, CI, git hooks, docs and ADR log, this plan. |
| 1 | **Model & persistence** | `GiftCard`, `GiftCardTransaction`, `GiftCardConfiguration`, the `Product`/`Order`/`OrderItemUnit` extension traits, Doctrine XML mapping, Sylius resource registration, repositories, first migration. |
| 3 | **Redemption & totals** | `GiftCardApplicator`, `OrderGiftCardProcessor`, `OrderGiftCardAmountModifier`, adjustment wiring, code generator, per-channel configuration provider, state machine wiring for both adapters. |
| 3b | **Cart UI, fixtures & Behat** | Shop-side apply/remove panel on the cart, gift card and configuration fixtures in the default suite, Behat coverage of the redemption flow. |
| 2 | **Admin** | Grids, forms and menu entries for gift cards and per-channel configuration; manual card creation, a show page with the balance ledger, and balance adjustment. |
| 4 | **Selling gift cards** | Gift card products, card generation on payment, state machine listeners for both adapters, purchaser association. The notification email moved to phase 6 - it needs the account view's templates. |
| 5 | **Customer account** | "My gift cards" - cards bought and cards redeemed, remaining balance, transaction history. This is the phase that pays off the two-customer model. |
| 6 | **Docs & polish** | The gift card notification email (deferred from phase 4 - it needed the account templates), usage documentation, Polish translations. |

All six phases are delivered.

Deferred beyond 1.0: PDF gift cards, API Platform resources, bulk generation/import, partial
refunds back onto a card, and letting a customer choose the amount when buying a gift card (the
face value currently comes from the product's price).

## Definition of done (per phase)

- `make verify` is green (composer validate + PHPStan + ECS + unit tests).
- New user-visible behaviour has a Behat feature; new services have unit tests.
- Load-bearing decisions are recorded as an ADR in `docs/adr-log/`.
- `CHANGELOG.md` updated under `[Unreleased]`.
