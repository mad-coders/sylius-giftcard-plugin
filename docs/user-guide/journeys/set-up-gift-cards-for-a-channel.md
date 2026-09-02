# Journey: set up gift cards for a channel

> **Not verified against a running app.** The development server stopped responding before the
> journey capture could run, so this walkthrough has no journey screenshots and no recorded step
> results. It is written from the plugin's routes, form definitions and templates. The one
> screenshot below is a page the crawl did capture; every step after it is unverified.

**Who:** an operator setting the shop up.
**Goal:** decide how codes are generated, how long cards last, whether customers may buy them, and
how they pick an amount.
**Before you start:** the plugin is installed and the channel exists.

## 1. Open the configuration list

Go to **Marketing > Gift card configuration** in the admin sidebar.

![The Gift card configuration list with one row for the Fashion Web Store channel](../assets/admin-gift-card-configurations.jpg)

Captured page. The heading rendering as `madcoders_sylius_gift_card.ui.gift_card_configurations` is
a missing translation, not something you have misconfigured.

If your channel already has a row, select the pencil in its **Actions** column instead of the next
step. A channel can only have one configuration.

## 2. Select Create

## 3. Pick the channel

**Channel** is required. The configuration applies to that channel only.

## 4. Decide the code format

- **Code prefix**: optional, for example `GIFT-`.
- **Code length**: the number of random characters after the prefix. Minimum 12. Anything shorter is
  refused with "The code length must be at least 12 characters - a shorter code is guessable."

## 5. Decide how long cards last

**Validity period** takes a relative expression such as `1 year` or `6 months`. Leave it empty for
cards that never expire.

## 6. Decide whether customers may buy gift cards

**Gift card sales**:

- **Sold in the shop** - customers can buy gift card products in this channel.
- **Issued by an administrator only** - they cannot. Cards you issue by hand are still spendable.

## 7. Decide how the amount is chosen

**How the amount is chosen**, then fill in what that mode needs:

| Mode | Also fill in |
|---|---|
| **The product's price** | Nothing. |
| **A list of preset amounts** | **Preset amounts**, comma separated, for example `25, 50, 100`. |
| **Any amount within a range** | **Smallest amount** and **Largest amount**. |
| **Preset amounts, or any amount within a range** | All three. |

The form refuses a mode without the values it needs, naming the field. Money is entered in major
units in the channel's currency, with no currency symbol on the box.

## 8. Tick Enabled, then Create

## What you should see afterwards

A row for the channel in the list, with your prefix, length, validity period and sale mode.

**The Gift card sales cell renders as a blank grey pill.** The value is set; the badge just has no
readable text colour. To check it, reopen the configuration for editing. See
[Accessibility notes](../reference/accessibility.md).

## If something goes wrong

| What you see | What it means |
|---|---|
| "The code length must be at least 12 characters - a shorter code is guessable." | Raise **Code length** to 12 or more. |
| "This mode offers preset amounts, so at least one has to be set." | Fill in **Preset amounts**. |
| "This mode lets the customer type an amount, so both the smallest and the largest have to be set." | Fill in both bounds. |
| "The largest amount cannot be smaller than the smallest one." | Swap the two bounds. |

## Related

- [Gift card configuration](../features/gift-card-configuration.md)
- [Selling gift cards](../features/selling-gift-cards.md)
