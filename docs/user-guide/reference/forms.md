# Forms reference

Every field the plugin adds, with the label and help text as they appear on screen.

Forms marked **captured** were recorded by the documentation crawl. The rest are read from the
plugin's form definitions; their fields and messages are accurate, but the rendered page was never
photographed.

---

## New gift card

**Captured.** `/admin/gift-cards/new`, reached with **Create** on **Marketing > Gift cards**.

![The New Gift card form](../assets/admin-gift-cards-new.jpg)

| Field | Type | Required | Help text |
|---|---|---|---|
| **Code** | Text | No | "Leave empty to generate one from the channel's configuration." |
| **Channel** | Select | Yes | - |
| **Expires at** | Date and time | No | "Leave empty to use the channel's configured validity period." |
| **Custom message** | Textarea | No | - |
| **Enabled** | Checkbox | No | Ticked by default. |
| **Initial amount** | Money | Yes | No currency symbol: the currency comes from the channel. |

Buttons: **Create**, **Back**.

Validation messages:

| Message | Field |
|---|---|
| "Please enter the amount the gift card is worth." | **Initial amount**, left empty. |
| "The amount must be greater than zero." | **Initial amount**, zero or negative. |
| "A gift card with this code already exists." | **Code**, already in use. |
| "Please enter a gift card code." | **Code**, when a code is required. |

## Edit gift card

Same form, with two differences once the card exists:

- **Code** is present but **disabled**, with the help text "A code cannot be changed once the card
  is issued - the customer already has it, and orders paid with the card are linked to it."
- **Initial amount** is **not on the form**. Use [Adjust balance](#adjust-balance) instead.

## Adjust balance

`/admin/gift-cards/{id}/adjust-balance`, reached with **Adjust balance** on a card's page. Not
captured by the crawl.

| Field | Type | Required | Notes |
|---|---|---|---|
| **Direction** | Radio buttons | Yes | **Add to balance** (preselected) or **Take from balance**. |
| **Amount** | Money | Yes | Always positive. No currency symbol. |

Buttons: **Adjust balance**, **Cancel**.

Validation message: "The amount must be greater than zero."

Success flash: "The gift card balance has been adjusted."

---

## Gift card configuration

`/admin/gift-card-configurations/new` and the edit form behind the pencil icon. The list page was
captured; the form itself was not.

| Field | Type | Required | Help text |
|---|---|---|---|
| **Channel** | Select | Yes | - |
| **Code prefix** | Text | No | "Prepended to every generated code, for example \"GIFT-\"." |
| **Code length** | Integer | Yes | "Number of random characters after the prefix. Minimum 12 - a shorter code is guessable, and a gift card code is money." |
| **Validity period** | Text | No | "How long a new card stays valid, for example \"1 year\". Leave empty for cards that never expire." |
| **Gift card sales** | Select | Yes | "Whether customers can buy gift cards in this channel. Cards already issued can always be redeemed, whichever you choose." |
| **How the amount is chosen** | Select | Yes | "Whether a customer buying a gift card in this channel pays the product's price or picks the amount themselves." |
| **Preset amounts** | Text | No | "The amounts offered as ready-made choices, separated by commas, in this channel's currency - for example \"25, 50, 100\". Used by the preset modes." |
| **Smallest amount** | Money | No | "The least a customer may type in. Used by the free-amount modes. Preset amounts are offered whether or not they fall inside it." |
| **Largest amount** | Money | No | "The most a customer may type in. Used by the free-amount modes. Preset amounts are offered whether or not they fall inside it." |
| **Enabled** | Checkbox | No | - |

**Gift card sales** choices: **Sold in the shop**, **Issued by an administrator only**.

**How the amount is chosen** choices: **The product's price**, **A list of preset amounts**, **Any
amount within a range**, **Preset amounts, or any amount within a range**.

"Required" above is the form's own required marker. The two bounds and the presets become required
in practice depending on the amount mode, enforced by the cross-field messages below.

Validation messages:

| Message | When |
|---|---|
| "The code length must be at least 12 characters - a shorter code is guessable." | **Code length** below 12. |
| "Enter the preset amounts separated by commas, for example \"25, 50, 100\"." | **Preset amounts** could not be parsed. |
| "This mode offers preset amounts, so at least one has to be set." | A preset mode with no presets. |
| "This mode lets the customer type an amount, so both the smallest and the largest have to be set." | A free-amount mode missing a bound. |
| "The largest amount cannot be smaller than the smallest one." | Inverted bounds. |

The money boxes show no currency symbol. The channel is chosen on this same form, so there is no
symbol that is guaranteed to be right.

---

## Product form: the gift card flag

Added to the general section of the Sylius product form. Not captured by the crawl.

| Field | Type | Required | Help text |
|---|---|---|---|
| **This product is a gift card** | Checkbox | No | "Buying it issues a gift card per unit, worth what was paid for that unit." |

---

## Product page: the customer's choices

Added to the add-to-cart form of a gift card product, between the variant table and the quantity.
Not captured by the crawl.

### Amount

Rendered only when the channel's **How the amount is chosen** is not **The product's price**.

| Sub-control | Type | Label | When |
|---|---|---|---|
| Preset | Radio buttons | **Choose an amount** | Preset modes. One radio per preset, labelled with the formatted money value. |
| Custom | Money box | **Choose an amount** in a pure range mode, **Other amount** when presets are also offered | Free-amount modes. |

In a free-amount mode the bounds are shown under the box as "Type anything between *minimum* and
*maximum*." The `min`, `max` and `step` attributes on the input are advisory; the server-side check
is what binds.

Validation messages:

| Message | When |
|---|---|
| "Please choose how much this gift card should be worth." | Nothing chosen. |
| "Please choose one of the available amounts." | An amount that is not one of the presets, in a presets-only mode. |
| "The amount must be between *minimum* and *maximum*." | Outside the bounds. |

### Message

| Field | Type | Required | Help text |
|---|---|---|---|
| **Message** | Textarea, 3 rows | No | "Optional. Up to 255 characters, shown with the code when it is delivered." |

Validation message: "Your message is too long - please keep it to 255 characters or fewer."

The `maxlength` attribute is advisory; the length constraint is what binds.

---

## Shop: the redeem panel

On the cart page and in the checkout sidebar on the shipping, payment and summary steps. Not
captured by the crawl.

| Control | Type | Notes |
|---|---|---|
| Gift card code | Text | Placeholder **Enter your gift card code**. It has no visible label. |
| **Apply** | Submit | Applies the code to the cart. |
| **Remove** | Submit | One per applied card. |

Both forms are plain POSTs with a CSRF token and work without JavaScript.

Messages, rendered by the panel itself:

| Message | When |
|---|---|
| "The gift card has been applied to your cart." | Accepted. |
| "The gift card has been removed from your cart." | Removed. |
| "Please enter a gift card code." | The box was empty. |
| "This gift card code cannot be used. Check it and try again." | Every other refusal. |
| "Too many gift card codes have been tried from here. Please wait a few minutes before trying again." | Rate limited. |

---

## Grid filters

| Grid | Filters |
|---|---|
| **Gift cards** | **Code** (text), **Channel** (entity), **Enabled** (boolean) |
| **Gift card configuration** | **Channel** (entity) |
