# 0009 - No PDF gift cards in 1.0

**Status:** accepted

## Context

`Setono/SyliusGiftCardPlugin` renders gift cards as PDFs through `knp_snappy` / wkhtmltopdf: a
configuration entity with an uploaded background image, a template content provider, rendering
options, a filesystem for generated files, and an email attachment pipeline.

That is a large, operationally awkward surface - wkhtmltopdf is an unmaintained binary that has to
be installed on every environment including CI - and it is orthogonal to the thing this plugin
exists to get right: balances and order totals.

## Decision

**1.0 ships no PDF generation.** No `knp_snappy` dependency, no rendering pipeline, no PDF
templates, no gift card image/background configuration.

Gift cards are delivered as an email containing the code and amount, and are visible in the
customer account.

## Consequences

- No binary dependency; CI and local setup stay to PHP, a database and (for the JS suite) Chrome.
- The `GiftCardConfiguration` entity holds only code/validity settings, not presentation.
- Adding PDFs later is additive: a renderer service plus an email attachment. Nothing decided here
  blocks it.

## Rules

1. Do not add `knplabs/knp-snappy*` or any headless-browser PDF dependency to `composer.json` on the
   1.0 line.
2. Gift card delivery stays template-driven through Sylius' mailer; presentation concerns belong in
   Twig templates the host application can override.
3. Revisit with a superseding ADR if PDFs are scheduled - do not reintroduce them piecemeal.
