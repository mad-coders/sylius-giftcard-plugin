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
