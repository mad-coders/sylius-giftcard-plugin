# Journey: apply a gift card during checkout

> **Not captured.** This walkthrough has no screenshots. The journey runs stopped at the basket,
> which is where the same panel could be photographed with far less setup. The steps below are read
> from the plugin's twig hook configuration, controller and templates. The reason there are no
> pictures is itself part of how the panel works, and is explained under
> [Why the shipping step is expensive to reach](#why-the-shipping-step-is-expensive-to-reach).

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

## Why the addressing step has no panel

This is a decision, not an oversight.

Applying a card is a form post followed by a redirect. The step you were on is then re-rendered from
what has been **saved**, and anything typed but not yet submitted is gone. On the shipping or payment
step that costs a customer a radio button, which they will notice and can reselect in a second. On
the addressing step it would cost them a whole delivery address, typed out by hand, with nothing on
screen explaining where it went.

So the panel is left off that one step. A customer on addressing continues to shipping first, having
lost nothing.

## Why the shipping step is expensive to reach

That same decision is why this walkthrough was never photographed. As a guest, the first step where
the panel appears is shipping, and shipping is only reachable once the entire address form is filled
and accepted: name, street, city, postcode, country, and whatever else the channel demands. Every one
of those fields would have to be scripted, kept in step with the demo data's countries and zones, and
maintained, to arrive at a panel that is byte for byte the panel already photographed on the basket
page.

The screenshots on [Spend a gift card on an order](spend-a-gift-card-on-an-order.md) are of the same
component. The checkout sidebar renders it in a narrow column, which is the only difference described
below.

## 1. Scroll to the sidebar

The panel is headed **Gift cards** and sits below the totals table.

## 2. Type the code and select Apply

The box has the placeholder **Enter your gift card code**, the same one as on the basket page.

## What you should see afterwards

- The message "The gift card has been applied to your cart." rendered by the panel itself, not by
  the page.
- The card listed above the box, by **code**, with a **Remove** button. The compact sidebar version
  does not show the remaining balance; the basket page does.
- **Gift cards:** and **Left to pay:** lines under the order total in the sidebar.
- You stay on the step you were on. You are not sent back to the basket.

The order total does not move, exactly as on the basket page. A gift card is money, not a discount.

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
- [Spend a gift card on an order](spend-a-gift-card-on-an-order.md)
