# Journey: correct a gift card's balance

> **Not verified against a running app.** The development server stopped responding before the
> journey capture could run, so this walkthrough has no journey screenshots and no recorded step
> results. It is written from the plugin's routes, form definitions and templates. The card page
> below is a captured page; **the Adjust balance page itself was never captured** - the crawl ran
> out of budget before reaching it - so its fields are read from the form definition.

**Who:** an administrator handling a customer query.
**Goal:** add goodwill to a partly spent card, or claw back a card issued in error, with the change
recorded in the card's history.
**Before you start:** you have the card's code or can find it in the list.

## 1. Find the card

**Marketing > Gift cards**, then filter by **Code** if the list is long. Select **details** in the
row's **Actions** column.

## 2. Read the card before you change it

![A partly spent gift card showing $35.00 left of $100.00 and one Spent entry in the balance history](../assets/admin-gift-cards-4.jpg)

Captured page. The **Balance history** explains every movement so far: date, whether it was
**Spent** or **Added**, how much, what the balance was afterwards, and the order that caused it.

This is what to check before adjusting anything. A balance that looks wrong is often a redemption
the customer has forgotten about, and the history says which order it went to.

The **Enabled** row currently shows the raw key `sylius.ui.yes`; that is a missing translation, not
a state you need to fix.

## 3. Select Adjust balance

The button is in the card's header, top right.

## 4. Choose a direction

**Direction** is a pair of radio buttons:

- **Add to balance** - a goodwill top-up. Preselected.
- **Take from balance** - claw back.

## 5. Enter an Amount

Always a positive number, in the card's currency. The **Direction** is what decides the sign, so a
stray minus sign cannot turn a top-up into a deduction.

## 6. Select Adjust balance

**Cancel** returns to the card without changing anything.

## What you should see afterwards

- The flash "The gift card balance has been adjusted."
- **Remaining balance** moved by the amount you entered.
- A new row at the bottom of **Balance history**, typed **Added** or **Spent**, showing the amount
  with its sign and the new balance. Its **Order** column is `-`, because a manual adjustment has no
  order behind it.

## If something goes wrong

| What you see | What it means |
|---|---|
| "The amount must be greater than zero." | **Amount** was zero or negative. Use **Take from balance** to reduce a card. |

## Why not just edit the initial amount

You cannot: **Initial amount** is not on the edit form once a card exists. A card's face value must
not move under orders that already reference it. An adjustment leaves the face value alone and
records the correction where anybody can see it.

## Related

- [Managing gift cards in the admin](../features/managing-gift-cards.md#adjusting-a-balance)
- [Forms reference](../reference/forms.md#adjust-balance)
