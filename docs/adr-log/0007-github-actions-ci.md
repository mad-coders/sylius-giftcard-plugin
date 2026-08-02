# 0007 - CI on GitHub Actions with a Sylius/Symfony/database matrix

**Status:** accepted

## Context

The plugin claims support for Sylius `^2.0` on Symfony `^6.4 || ^7.4`. That is a wide surface: the
Sylius 2.x line changed the admin templates, the state machine abstraction and the test application
layout between minors. A single build proves very little.

Building a Sylius test application from scratch is also non-trivial - which is why SyliusLabs ships
`BuildTestAppAction` for exactly this.

## Decision

Two jobs on GitHub Actions (`.github/workflows/ci.yaml`):

1. **`static`** - one PHP version, no database. Mirrors `make verify`: composer validate, PHPStan,
   ECS, Rector dry-run, unit tests. Fails fast and cheaply on nearly every regression.
2. **`tests`** - a matrix over Sylius (`~2.0.0`, `~2.1.0`, `~2.2.0`), Symfony (`^6.4`, `^7.4`) and
   database (MySQL 8.4, plus MariaDB 11.4 and PostgreSQL 16 on the newest Sylius). Builds a real
   test application with `SyliusLabs/BuildTestAppAction@v4`, then runs the container lint, the
   non-unit PHPUnit suites and Behat.

Sylius `~2.0.0` on Symfony `^7.4` is excluded - that combination is not supported upstream.

Behat is retried once with `--rerun` before being reported as failed, to absorb genuinely flaky
browser scenarios without hiding real failures.

## Consequences

- A pull request gets feedback from the `static` job in a couple of minutes.
- Cross-version breakage is caught before release, not by users.
- The matrix is the definition of "supported"; adding a supported version means adding a matrix
  entry.

## Rules

1. Keep the `static` job aligned with `make verify` - if the local gate changes, change the job.
2. Adding or dropping a supported Sylius/Symfony/database version means editing the matrix in the
   same commit as the `composer.json` constraint.
3. Do not mark a Behat failure as flaky by deleting the scenario; fix it or tag it.
