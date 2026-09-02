# My gift cards in the customer account

> **No screenshots on this page.** The documentation crawl ran as an administrator and never signed
> in as a customer, so no account page was captured. Everything below is read from the plugin's
> routes and templates.

The plugin adds **My gift cards** to the shop account menu. It is behind a login: a customer sees
only cards they are linked to.

## Why there are two lists

A gift card is usually bought *for* somebody, so it carries two customer links:

- the **purchaser**, who paid for it;
- the **redeemer**, the first customer to apply it to an order.

The account page shows a list for each, under the heading **My gift cards**.

### Gift cards I use

Listed first, because this is the list with a balance somebody is watching.

| Column | Shows |
|---|---|
| **Code** | The gift card code. |
| **Remaining balance** | What is left on the card. |
| **Expires at** | The expiry date, or **Never**. |
| (unlabelled) | A **Balance history** link to the card's own page. |

When there is nothing to show: "You are not using any gift cards yet."

### Gift cards I bought

| Column | Shows |
|---|---|
| **Code** | The gift card code. |
| **Initial amount** | What the card was worth when it was issued. |
| **Remaining balance** | What is left on it now. |

When there is nothing to show: "You have not bought any gift cards."

There is no **Balance history** link in this list. A purchaser sees the code and the two amounts,
not the ledger.

## A single card

Following **Balance history** opens the card's own page, which shows:

- the **code** as the heading;
- **Remaining balance**, over the initial amount;
- the **Custom message**, when the card has one, rendered as text;
- **Balance history**: a table of **Date**, **Type** (**Spent** or **Added**), **Amount** with its
  sign, and **Balance after**.

A card that has never moved shows "This gift card has not been used yet." instead of the table.

Unlike the admin's balance history, the customer's version has no **Order** column.

## Why this page matters

The redeem field on the cart gives the same refusal for every reason a card cannot be used, on
purpose. This page is where the missing detail lives: a customer whose card has expired, been
disabled or been spent out can see that here, because the page is behind a login and scoped to cards
that are actually theirs.

A customer can only open a card they are linked to. A gift card code is bearer-like, so these pages
refuse anything that is not theirs.
