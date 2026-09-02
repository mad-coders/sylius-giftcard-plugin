# Contributing

## Getting set up

You need PHP 8.3+, **Node 20+**, Composer 2, Docker (for MySQL and headless Chrome) and the Symfony
CLI. Node 20 is a hard requirement of Sylius' admin assets - on an older Node, `make frontend` fails
with `The engine "node" is incompatible with this module`, and every Behat scenario then dies with a
500 from the missing Webpack entrypoints file.

```bash
git clone git@github.com:mad-coders/sylius-giftcard-plugin.git
cd sylius-giftcard-plugin
make setup          # deps + MySQL container (host port 3307) + assets + database
make app            # fresh schema + demo data
make install-hooks  # pre-commit gate + commit message template
```

`make help` lists every target. Drive the toolchain through Make - the targets pin the right
`APP_ENV`, config files and flags.

If port 3307 is taken, or you want to point at a database you already run, override `DATABASE_URL`
in `tests/TestApplication/.env.local` (git-ignored).

### Reading the mail the plugin sends

A gift card's code reaches the customer by email, so it is worth being able to read one. The
committed `MAILER_DSN` is `null://null` - mail is discarded, and CI needs no mail server. To see it,
start mailpit and point the mailer at it in the same git-ignored override:

```bash
docker compose up -d mailpit
echo 'MAILER_DSN=smtp://127.0.0.1:1025' >> tests/TestApplication/.env.local
```

Mail then arrives at http://127.0.0.1:8025.

## Working on a change

1. Branch off `1.0` - `feat/...`, `fix/...`, `docs/...`, `ci/...`. **The primary branch is `1.0`;
   this repository has no `main` or `master`.**
2. Make one logical change at a time.
3. Run the gate that matches what you touched, then the full fast gate:

   ```bash
   make verify   # composer validate + phpstan + ecs + unit tests, no database needed
   ```

4. Add tests. New user-visible behaviour gets a Behat feature; new services get unit tests. See the
   testing policy in `AGENTS.md`.
5. Record notable changes in `CHANGELOG.md` under `[Unreleased]`, in the same commit.
6. If you made a load-bearing decision, add an ADR in `docs/adr-log/`.
7. Open a pull request against `1.0`.

## Running the tests

```bash
make phpunit        # full PHPUnit suite (needs a database)
make phpunit-unit   # unit tests only, no database
make behat          # non-JavaScript Behat suite
```

The `@javascript` suite needs headless Chrome and a running test-env server:

```bash
make docker-up-all  # MySQL + Chrome
make serve-test     # test application on http://127.0.0.1:8080 - keep this running
make behat-js       # in another terminal
```

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org/): `type(scope): subject`, imperative,
no trailing period.

```
feat(checkout): apply a gift card to the order total
fix(gift-card): reject expired gift cards on redemption
docs: document the installation steps
```

`make install-hooks` sets `.gitmessage` as your commit template, which lists the allowed types.

## Code style and static analysis

- ECS with the sylius-labs standard: `make ecs`, auto-fix with `make fix`.
- PHPStan at `level: max` with **no baseline**: `make phpstan`. Fix findings rather than recording
  them. If a finding is genuinely wrong, add a narrowly scoped `ignoreErrors` entry with a comment.
- Rector keeps the code current with PHP 8.3 and Sylius: `make rector` reports, `make rector-fix`
  applies.

The conventions themselves - naming, money handling, model rules, gift card invariants - are in
`ai/coding-rules.md`.

## A note on PHP versions

The plugin requires PHP `^8.3`. If your default `php` is older, put a newer one first on your PATH
before running the Make targets, e.g. on macOS with Homebrew:

```bash
export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"
```

The same applies to Node - `nvm use 22` (or any 20+) before `make frontend`.
