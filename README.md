<h1 align="center">Sylius Gift Card Plugin</h1>

<p align="center">Sell gift cards in your Sylius store, redeem them against order totals, and let
customers track what is left on them.</p>

<p align="center">
    <a href="https://github.com/mad-coders/sylius-giftcard-plugin/actions/workflows/ci.yaml"><img src="https://github.com/mad-coders/sylius-giftcard-plugin/actions/workflows/ci.yaml/badge.svg?branch=1.0" alt="CI"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-EUPL--1.2-blue.svg" alt="License"></a>
</p>

> **Status: in development.** The 1.0 line is being built phase by phase - see
> [`docs/PLAN.md`](docs/PLAN.md) for the roadmap and what has landed.

## What it does

- **Sell gift cards** as ordinary Sylius products. A card is generated per purchased unit when the
  order is paid, and emailed to the customer.
- **Redeem gift cards** against an order total. Cards are applied as order adjustments, so they
  stack, compose with promotions, and can never push a total below zero.
- **Track the balance.** A gift card knows both the customer who *bought* it and the customer who
  *uses* it, so the person actually spending the card sees its remaining balance and history in
  their account - even though someone else paid for it.
- **Administer cards** from the Sylius admin: create them manually, adjust balances, and configure
  code format and validity per channel.

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

A gift card is **an order adjustment, not a payment method**. When a card is applied to an order,
the plugin's order processor adds a negative adjustment of type `madcoders_gift_card`, capped at the
order's remaining total and tagged with the card's code. When the order is placed the balance is
moved off the card; when it is cancelled it is moved back - always by exactly the amount that was
actually charged.

This is the same approach as [Setono's gift card plugin](https://github.com/Setono/SyliusGiftCardPlugin),
and it means everything that already reads `Order::getTotal()` keeps working. The reasoning is in
[`docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md`](docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md).

## Development

```bash
make setup          # deps + docker (MySQL on 3307) + assets + database
make install-hooks  # pre-commit quality gate and commit template
make verify         # fast gate: composer validate + phpstan + ecs + unit tests
make test           # phpunit + behat
make help           # every available target
```

Contributor guide: [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md). Working on this with an AI agent?
Start from [`AGENTS.md`](AGENTS.md).

The primary branch is **`1.0`** - this repository has no `main` or `master`, following the Sylius
version-branch model.

## Credits

Functionally inspired by [Setono/SyliusGiftCardPlugin](https://github.com/Setono/SyliusGiftCardPlugin).
Built and maintained by [Madcoders](https://www.madcoders.co).

## License

[EUPL-1.2](LICENSE).
