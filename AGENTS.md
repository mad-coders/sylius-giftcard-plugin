# AGENTS.md

Entry point for AI agents and contributors working on **madcoders/sylius-giftcard-plugin**, a
Sylius 2.x plugin that lets a shop sell gift cards and lets customers redeem them against order
totals while tracking the remaining balance.

## Read first

- `docs/PLAN.md` - what we are building, the domain model, and the delivery phases.
- `ai/coding-rules.md` - conventions to follow; the quality gates that must stay green.
- This file - orientation, commands, and the testing policy.

## Deeper context (read on demand)

Pull these in **only when a change needs deeper understanding** of the area you are touching - not
as upfront reading for every task:

- `docs/adr-log/` - the **architectural decisions** and their rationale. Open the relevant ADR
  before changing the area it governs; index in `docs/adr-log/README.md`.
- `docs/INSTALLATION.md` - what a host application has to do to install the plugin. Anything that
  changes the installation contract has to change this file too.

## Golden rule

**Run the relevant gate after each change.** Make one logical change, run the matching target,
confirm green, then continue. `make verify` is fast and needs no database - there is no excuse for
skipping it.

## Layout

Sylius 2.x plugin layout (configuration and templates live at the repository root, not under
`src/Resources/`):

```
config/          services (XML), doctrine mapping (XML), routes, grids, state machine, twig hooks
src/             plugin source (Madcoders\SyliusGiftCardPlugin\, PSR-4)
src/Migrations/  Doctrine migrations
templates/       Twig templates (@MadcodersSyliusGiftCardPlugin/...)
translations/    translation catalogues
features/        Behat feature files
tests/Behat/     Behat contexts and page objects
tests/Unit/      PHPUnit unit tests (no kernel, no database)
tests/Functional/, tests/Integration/   PHPUnit tests that boot the kernel
tests/TestApplication/   configuration for the sylius/test-application test app
```

The test application is **not** vendored into `tests/Application/` as in Sylius 1.x - it comes from
the `sylius/test-application` package and is configured through the `SYLIUS_TEST_APP_*` environment
variables in `tests/TestApplication/.env`.

## Looking things up (Sylius docs & code examples)

Don't guess at Sylius/Symfony/Doctrine APIs from memory - they shift between versions and this
project spans Sylius `~2.0` to `~2.2` on Symfony `^6.4 || ^7.4`. When you need framework behaviour,
configuration, or a usage example, use the MCP tools:

- **context7** (`mcp__context7__*`) - version-correct documentation for Sylius, Symfony and
  Doctrine. Resolve the library id, then query the docs. Prefer this over training memory for
  anything version-sensitive (resource config, grids, state machine, mailer, forms, twig hooks).
- **grep-app** (`mcp__grep-app__*`) - search real-world code across public repositories for concrete
  usage patterns, e.g. how other Sylius 2.x plugins wire a resource, a grid, or a workflow listener.

Two Sylius 2.x specifics that are easy to get wrong from memory:

1. **Two state machine adapters.** `winzou_state_machine` and Symfony Workflow are both supported
   and the host application picks. Anything hooking an order transition must be wired for both -
   see `docs/adr-log/0004-gift-card-redemption-as-order-adjustment.md`.
2. **Twig hooks, not template events.** The 1.x `sylius.ui` template event system is replaced by
   `sylius_twig_hooks`. Configure hooks in `config/twig_hooks/`.

## Testing policy

Tests are part of "done", not an afterthought.

- **Every new user-visible behaviour gets a Behat feature.** A new flow, page, admin action or
  transition is specified in `features/*.feature` with contexts and pages under `tests/Behat/`.
  Prefer the non-JS suite; reserve `@javascript` for behaviour that genuinely needs a browser.
- **Every new service gets unit tests** in `tests/Unit/`, mirroring the `src/` namespace.
- **Prefer testing behaviour over implementation.** Assert on observable outcomes and public
  contracts - what the service returns or does for given inputs, including edge cases and error
  paths - not private internals or call sequences. Mock only the collaborators at the boundary. A
  test should survive a refactor that preserves behaviour.
- Money is always in **minor units** (integers). A test that uses floats for money is wrong.
- A change is not finished until `make verify` is green and any new or affected Behat scenarios
  pass.

## Commands

Drive the toolchain through the **Make targets** (`make help` lists them all). Do not call the
underlying binaries directly - the targets pin the right `APP_ENV`, config files and flags.

```
make install            # composer install
make setup              # one-shot local setup: deps + docker + assets + database
make app                # docker + assets + fresh schema + fixtures
make serve              # dev application on http://127.0.0.1:8080
```

Quality gates:

```
make verify             # fast gate: composer validate + phpstan + ecs + unit tests (no database)
make phpstan            # static analysis (level max, no baseline)
make ecs                # code style check   (make fix to auto-apply)
make rector             # report pending modernisations (make rector-fix to apply)
make phpunit            # full PHPUnit suite (needs a database)
make behat              # non-JavaScript Behat suite
make test               # phpunit + behat
make ci                 # everything CI runs
```

The `@javascript` Behat suite needs headless Chrome and a running test-env server:

```
make docker-up-all      # MySQL + Chrome
make serve-test         # test application on 8080 (separate terminal, keep running)
make behat-js
```

Install the git hooks once per clone (pre-commit runs the fast gate, and sets the commit template):

```
make install-hooks
```

## Branching and commits

Trunkless: **`1.0` is the primary branch**, there is no `main`/`master`. Work on short-lived
`feat/...`, `fix/...`, `docs/...` branches and merge through pull requests. Commits follow
Conventional Commits. See `docs/adr-log/0008-conventional-commits.md`.

Record notable changes in `CHANGELOG.md` under `[Unreleased]` as part of the change.
