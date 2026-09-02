<h1 align="center">Sylius Gift Card Plugin</h1>

<p align="center">Sell gift cards in your Sylius store, spend them against what a customer owes, and let
customers track what is left on them.</p>

<p align="center">
    <a href="https://github.com/mad-coders/sylius-giftcard-plugin/actions/workflows/ci.yaml"><img src="https://github.com/mad-coders/sylius-giftcard-plugin/actions/workflows/ci.yaml/badge.svg?branch=1.0" alt="CI"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-EUPL--1.2-blue.svg" alt="License"></a>
</p>

> **Status: 1.0.0-RC.1.** Feature-complete and green across the supported Sylius, Symfony and
> database matrix. Being a release candidate it wants real-world use before a stable tag - see
> [`CHANGELOG.md`](CHANGELOG.md) for what is in it and what is deliberately left out.

Requires `"minimum-stability": "RC"` (or `composer require madcoders/sylius-giftcard-plugin:^1.0@RC`)
until 1.0.0 is tagged.

## What it does

- **Sell gift cards** as ordinary Sylius products. A card is generated per purchased unit when the
  order is paid, and emailed to the customer.
- **Redeem gift cards** against what the customer owes, from the cart or from the checkout. A card
  is money, not a discount: the order total stays at the full value of the goods and the *payment*
  is what shrinks. Cards stack, and together they can never cover more than is owed. See
  [ADR 0010](docs/adr-log/0010-gift-card-as-tender.md).
- **Track the balance.** A gift card knows both the customer who *bought* it and the customer who
  *uses* it, so the person actually spending the card sees its remaining balance and history in
  their account - even though someone else paid for it.
- **Administer cards** from the Sylius admin: create them manually, adjust balances, and configure
  code format, validity, whether cards are sold at all, and how a customer chooses an amount - all
  per channel. Every gift card has an expiry date, and every balance change leaves a ledger entry.
- **Treat codes as money.** The redeem field is rate limited per client, refusals say the same thing
  whether or not the code exists, and no code ever reaches a log, a flash or an exception message.
  Needs `symfony/rate-limiter`; see [`docs/INSTALLATION.md`](docs/INSTALLATION.md).

Not in 1.0: PDF gift cards, API Platform resources, bulk generation. See
[`docs/adr-log/0009-no-pdf-in-1-0.md`](docs/adr-log/0009-no-pdf-in-1-0.md).

## Requirements

| | |
|---|---|
| PHP | `^8.3` |
| Sylius | `^2.0` (tested against `~2.0`, `~2.1`, `~2.2`) |
| Symfony | `^6.4 \|\| ^7.4` |

## Installation

```bash
composer require madcoders/sylius-giftcard-plugin
```

Then follow [`docs/INSTALLATION.md`](docs/INSTALLATION.md) - the plugin needs its bundle registered,
its configuration and routes imported, and its entity extensions applied to your `Product`, `Order`
and `OrderItemUnit`.

## How redemption works

**A gift card is money, not a discount.** The order stays worth what the goods are worth; the card
comes off what the customer has to pay. A 100 order paid with a 40 card is still a 100 order, and
the customer pays 60.

That matters beyond bookkeeping: tax is owed on the value of the goods sold and was already settled
on the card when it was bought, refunds and reporting see an order worth what was actually sold, and
a card cannot switch off a "spend over X" promotion by moving the total it tests.

Read the split with `Order::getAmountToPay()` and `Order::getGiftCardTotal()`. **`getTotal()` is the
value of the goods, not what the customer pays.** The reasoning is in
[`docs/adr-log/0010-gift-card-as-tender.md`](docs/adr-log/0010-gift-card-as-tender.md).

## Development

```bash
make setup          # deps + docker (MySQL on 3307) + assets + database
make install-hooks  # pre-commit quality gate and commit template
make app            # fresh schema + demo data, ready to look at
make serve          # http://127.0.0.1:8080
make verify         # fast gate: composer validate + phpstan + ecs + unit tests
make test           # phpunit + behat
make install-test   # install into a throwaway Sylius by following docs/INSTALLATION.md
make help           # every available target
```

### The demo data

`make app` loads a gift card in every state the plugin can produce, so the admin grid, the account
pages and the cart panel all have something to show:

| Card | What it demonstrates |
|---|---|
| `GIFT-FULL0001`, `GIFT-SMALL001` | Spendable; two of them on one cart proves cards stack |
| `GIFT-LARGE001` | Worth more than the cart - covers it all and keeps the change |
| `GIFT-USED0001` | Partly spent, with the ledger entry explaining the balance |
| `GIFT-EMPTY001` | Spent out: still listed, no longer redeemable |
| `GIFT-EXPIRED1`, `GIFT-DISABLED` | The two ways a card stops being redeemable |
| `GIFT-EXPSOON1`, `GIFT-LONGLIFE` | Expiring in a week, and in twenty-five years - there is no card without an expiry, because there is no way to make one |
| `GIFT-GIFTED01` | Bought by one customer, not yet used by anybody |
| `GIFT-SHARED01` | **The two-customer model**: bought by one, spent by another |
| `GIFT-SELFUSE1` | Bought and used by the same person |

`giftcard.buyer@example.com` and `giftcard.holder@example.com` (password `sylius`) are the two
customers those last three cards link, and the first two products in the catalogue are marked as
gift card products so the "buying one issues a card" flow can be walked through.

Usage guide: [`docs/USAGE.md`](docs/USAGE.md). Contributor guide: [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md). Working on this with an AI agent?
Start from [`AGENTS.md`](AGENTS.md).

The primary branch is **`1.0`** - this repository has no `main` or `master`, following the Sylius
version-branch model.

## Credits

Functionally inspired by [Setono/SyliusGiftCardPlugin](https://github.com/Setono/SyliusGiftCardPlugin).
Built and maintained by [Madcoders](https://www.madcoders.co).

## License

[EUPL-1.2](LICENSE).
