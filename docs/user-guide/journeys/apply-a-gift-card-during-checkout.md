# Journey: apply a gift card during checkout

> **Not verified against a running app, and no screenshots at all.** The development server stopped
> responding before the journey capture could run, and the crawl that did succeed never reached any
> checkout step. Every step below is read from the plugin's twig hook configuration, controller and
> templates.

**Who:** a customer already part-way through checkout.
**Goal:** apply a gift card without going back to the basket.

## Where the panel is

In the checkout sidebar, directly under the totals, on these steps:

| Step | Panel? |
|---|---|
| Addressing | **No** |
| Shipping | Yes |
| Payment | Yes |
| Summary | Yes |

It is missing from the addressing step on purpose. Applying a card is a form post followed by a
redirect, so the step's form is re-rendered from what has been saved and anything typed but not yet
submitted is gone. On shipping or payment that costs a radio button. On addressing it would cost a
whole address, typed out by hand, with no explanation.

If you are on the addressing step, continue to shipping first.

## 1. Scroll to the sidebar

The panel is headed **Gift cards** and sits below the totals table.

## 2. Type the code and select Apply

The box has the placeholder **Enter your gift card code**, the same one as on the basket page. It is
the same panel, rendered in a narrow column.

## What you should see afterwards

- The message "The gift card has been applied to your cart." rendered by the panel itself, not by
  the page.
- The card listed above the box, by **code**, with a **Remove** button. The compact sidebar version
  does not show the remaining balance; the basket page does.
- **Gift cards:** and **Left to pay:** lines under the order total in the sidebar.
- You stay on the step you were on. You are not sent back to the basket.

## Why the panel renders its own messages

The addressing, shipping and payment steps do not render flash messages at all. Before the panel
rendered its own, a refusal on one of those steps was silent, and the unread message later surfaced
on whichever page did render flashes, attached to the wrong action.

## If something goes wrong

The messages are the same as on the basket page. See
[Spend a gift card on an order](spend-a-gift-card-on-an-order.md#if-something-goes-wrong).

One extra case: if your session expires while a checkout page is open, applying a card sends you to
the basket rather than to a checkout step that no longer has a saved cart behind it.

## Related

- [Redeeming a gift card](../features/redeeming-a-gift-card.md)
