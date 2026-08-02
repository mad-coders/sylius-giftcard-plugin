# 0008 - Conventional Commits and the trunkless `1.0` branch model

**Status:** accepted

## Context

The plugin follows the Sylius release model: long-lived version branches, no single trunk. The RMA
plugin does the same (its default branch is `1.3`). A `main`/`master` branch in that model is
either a duplicate of the newest line or a source of confusion about where a fix belongs.

Commit history also needs to be readable enough to assemble a changelog per line.

## Decision

**Branching.** Trunkless. `1.0` is the primary branch and the repository default; there is no
`main` or `master`. Work happens on short-lived `feat/...`, `fix/...`, `docs/...`, `ci/...`
branches merged into `1.0` through pull requests. New minor lines branch off as `1.1`, `1.2`, ...
when opened; fixes land on the oldest supported line and are up-merged.

**Commits.** [Conventional Commits 1.0.0](https://www.conventionalcommits.org/):
`type(scope): subject`, imperative, no trailing period. Types: `feat`, `fix`, `docs`, `refactor`,
`test`, `build`, `ci`, `chore`, `perf`, `style`, `revert`. Breaking changes take a `!` and/or a
`BREAKING CHANGE:` footer. `.gitmessage` is installed as the commit template by
`make install-hooks`.

Notable changes are recorded in `CHANGELOG.md` under `[Unreleased]` as part of the change, not
afterwards.

## Consequences

- `git log --oneline` per branch is close to a changelog already.
- There is exactly one answer to "which branch does this fix go on".
- Pull requests are the only way into a version branch; direct pushes are not used.

## Rules

1. Never create `main` or `master` on this repository.
2. Never push directly to a version branch; open a pull request.
3. One logical change per commit, with the scope naming the area (`gift-card`, `checkout`, `admin`,
   `account`, `deps`).
