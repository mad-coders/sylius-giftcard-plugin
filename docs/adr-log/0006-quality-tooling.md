# 0006 - Quality tooling: PHPStan, ECS, Rector, PHPUnit, Behat via Make

**Status:** accepted

## Context

The Sylius ecosystem has historically used a wide toolchain (Psalm, PhpSpec, PHPStan, ECS, PHPUnit,
Behat). Running all of it is slow and produces overlapping findings; contributors then disagree on
which tool's opinion wins.

## Decision

Five tools, each with a distinct job, all driven through **Make targets**:

| Tool | Job | Target |
|---|---|---|
| PHPStan (level max) | static correctness | `make phpstan` |
| ECS (sylius-labs standard) | code style | `make ecs` / `make fix` |
| Rector | PHP 8.3 + Sylius modernisation | `make rector` / `make rector-fix` |
| PHPUnit | unit, functional, integration tests | `make phpunit` |
| Behat | user-visible behaviour | `make behat` |

Psalm and PhpSpec are **not** used - PHPStan and PHPUnit cover the same ground.

`make verify` is the fast gate (composer validate + PHPStan + ECS + unit tests, no database) and is
what the pre-commit hook and the `static` CI job run. `make ci` is the full pipeline.

The Make targets pin the right `APP_ENV`, config file and flags. Contributors call the targets, not
the underlying binaries.

## Consequences

- One place (the Makefile) defines how the project is checked; CI and local runs cannot drift.
- PHPStan runs at `level: max` with **no baseline** - new findings must be fixed, not recorded.
- The fast gate needs no database, so the hook stays usable.

## Rules

1. Drive the toolchain through `make`; do not call `vendor/bin/*` directly in docs, CI or hooks.
2. Do not introduce a PHPStan baseline. If a finding is genuinely wrong, add a narrowly scoped
   `ignoreErrors` entry with a comment explaining why.
3. Do not add a sixth tool without an ADR superseding this one.
