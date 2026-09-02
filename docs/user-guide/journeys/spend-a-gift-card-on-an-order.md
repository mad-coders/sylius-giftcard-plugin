# Journey: spend a gift card on an order

> **Not verified against a running app, and no screenshots at all.** The development server stopped
> responding before the journey capture could run. The crawl that did succeed never reached the cart
> either, so there is no captured image of the redeem panel. Every step below is read from the
> plugin's routes, controller and templates.

**Who:** a customer holding a gift card.
**Goal:** apply a code to the basket and see what is left to pay.
**Before you start:** the card is enabled, has a balance, has not expired, and belongs to the
channel the customer is shopping in.

## 1. Add something to the basket

Open a product and select the add-to-cart button.

## 2. Open the basket

The **Gift cards** panel sits below the basket, outside the basket's own form.

The panel only renders when the basket has something in it.

## 3. Type the code

The box is labelled by its placeholder, **Enter your gift card code**. Leading and trailing spaces
are trimmed, so a code pasted with a stray space still works.

## 4. Select Apply

## What you should see afterwards

- The message "The gift card has been applied to your cart."
- The card listed above the box, showing its **code** and its **Remaining balance**, with a
  **Remove** button.
- Two new lines in the basket summary, below the order total:
  - **Gift cards:** the amount covered, as a negative figure.
  - **Left to pay:** what is actually charged.

**The order total does not change.** It is the value of the goods, and the goods still cost what
they cost. What shrinks is **Left to pay**.

## Applying more than one card

Repeat from step 3. Cards stack. Each is charged only what is still owed, so a card applied to an
order already covered in full is charged nothing and keeps its whole balance.

## Taking a card back off

Select **Remove** next to it. The basket returns to what it was, and applying the card again is
free: nothing is debited until the order is placed.

## If something goes wrong

| What you see | What it means |
|---|---|
| "Please enter a gift card code." | The box was empty. |
| "This gift card code cannot be used. Check it and try again." | Every other refusal: the code does not exist, or the card is expired, disabled, spent out, or belongs to another channel. |
| "Too many gift card codes have been tried from here. Please wait a few minutes before trying again." | Too many failed attempts from this network. Wait out the window; the default is 15 minutes. |

The second message is deliberately the same for every reason. A message that distinguished them
would tell anybody typing codes at random which ones are real. A customer who wants to know why
*their* card will not work signs in and opens
[My gift cards](../features/customer-account.md).

## When the balance actually moves

Not when the card is applied. The balance comes off when the **order is placed**, and goes back on
if the order is **cancelled**, by exactly the amount that was charged.

The first customer to redeem a card is recorded as its **Used by** and is not reassigned afterwards.

## Related

- [Redeeming a gift card](../features/redeeming-a-gift-card.md)
- [Apply a gift card during checkout](apply-a-gift-card-during-checkout.md)
