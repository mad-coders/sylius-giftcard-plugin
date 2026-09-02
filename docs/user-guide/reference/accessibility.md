# Accessibility notes

What the documentation crawl measured on the plugin's admin screens, and what it found.

Scope: the crawl signed in as an administrator and visited eight of the plugin's pages. It never
reached the cart, the checkout, the customer account, a gift card product page or the **Adjust
balance** page, so none of those are covered here.

## Two rendering faults on the plugin's own screens

### The Gift card sales badge has no readable text

**Where:** the **Gift card sales** column of the **Gift card configuration** list.

The badge sets a background colour without a matching text colour, so the label - "Sold in the shop"
or "Issued by an administrator only" - renders as light text on a light grey pill and cannot be
read. The text is in the page, so a screen reader announces it; a sighted user sees a blank pill.

![The Gift card configuration list, with the Gift card sales cell rendering as a blank grey pill](../assets/admin-gift-card-configurations.jpg)

Workaround: open the configuration for editing to read the value.

### Untranslated keys shown to the user

Two strings render as raw translation keys rather than text:

| Where | Renders as | Should read |
|---|---|---|
| The **Gift card configuration** list heading, breadcrumb and page title | `madcoders_sylius_gift_card.ui.gift_card_configurations` | "Gift card configuration" |
| The **Enabled** row on a card's page | `sylius.ui.yes` | "Yes" |

Both are visible in the captured screenshots. They are missing translations, not settings.

## What the crawl measured

| Page | Images missing alt | Buttons without an accessible name | `<h1>` elements | `<main>` landmark |
|---|---|---|---|---|
| `/admin/gift-cards/` | 0 | 15 | 14 | no |
| `/admin/gift-card-configurations/` | 0 | 4 | 3 | no |
| `/admin/gift-cards/new` | 0 | 3 | 2 | no |
| `/admin/gift-cards/{id}` (five cards) | 0 | 3 | 2 | no |

### These numbers are Sylius' admin, not the plugin's

The same crawl measured Sylius' own pages in the same run:

| Page | Buttons without a name | `<h1>` elements | `<main>` landmark |
|---|---|---|---|
| `/admin/products/` | 23 | 13 | no |
| `/admin/taxons/new` | 15 | 14 | no |
| `/admin/promotions/` | 8 | 7 | no |
| `/admin/` (dashboard) | 5 | 2 | no |

The plugin's grids sit inside the same admin chrome and score the same way. No page in the whole
admin declares a `<main>` landmark, and every page has more than one `<h1>`. Fixing those is a
Sylius concern, not something this plugin controls.

The plugin's own contribution to the unnamed-button count is one icon-only delete button per grid
row: 12 rows plus 3 chrome buttons on the **Gift cards** list, 1 row plus 3 on the **Gift card
configuration** list. Those are Sylius' standard grid action buttons, rendered by Sylius' own
templates.

### Language attribute

Every admin page reports `lang="en_US"`. That is not a valid BCP 47 language tag - it should be
`en-US`. It is set by Sylius across the whole admin, not by this plugin. The shop front page reports
`lang="en"`.

## What the plugin does well

Read from the templates rather than measured:

- **The redeem panel needs no JavaScript.** The code box, **Apply** and **Remove** are a plain form
  post. So is the amount chooser on a gift card product page: radio buttons and a number input.
- **The amount presets are marked as a radio group** (`role="radiogroup"`), so the choices are
  announced as one set rather than as unrelated buttons.
- **Refusals are shown next to the thing that was refused.** The redeem panel renders its own
  messages, because three of the four checkout steps do not render flash messages at all and a
  refusal there used to be silent.
- **Customer-supplied text is never rendered as markup.** The gift card message reaches an
  administrator's screen, the customer's account and an email, and is escaped in all three.

## Known rough edges

- **The gift card code box has no visible label.** It is identified only by its placeholder,
  **Enter your gift card code**, which disappears as soon as the customer starts typing.
- **The Gift cards grid scrolls horizontally at 1440px.** **Enabled**, **Creation date** and
  **Actions** sit past the right edge of the visible table, so the link into a card is not reachable
  without scrolling sideways.
- **In admin-only mode the add-to-cart button on a gift card product still renders enabled.** The
  customer's first feedback is an error after clicking, rather than a control that was never
  offered. This is recorded as a known limitation in
  [ADR 0013](../../adr-log/0013-gift-card-sale-mode.md).
- **On the New gift card form, the required Initial amount field is rendered last**, below the
  **Enabled** checkbox, rather than next to the other card details.
