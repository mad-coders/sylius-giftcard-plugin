# Installation

> This document tracks the installation contract as the plugin is built. Steps for features that
> have not landed yet are marked **(pending)**.

## 1. Require the package

```bash
composer require madcoders/sylius-giftcard-plugin
```

## 2. Register the bundle

```php
# config/bundles.php

return [
    // ...
    Madcoders\SyliusGiftCardPlugin\MadcodersSyliusGiftCardPlugin::class => ['all' => true],
];
```

## 3. Import the configuration

```yaml
# config/packages/madcoders_sylius_gift_card.yaml

imports:
    - { resource: "@MadcodersSyliusGiftCardPlugin/config/config.yaml" }
```

## 4. Import the routes

```yaml
# config/routes/madcoders_sylius_gift_card.yaml

madcoders_sylius_gift_card_admin:
    resource: "@MadcodersSyliusGiftCardPlugin/config/routes/admin.yaml"
    prefix: /admin

madcoders_sylius_gift_card_shop:
    resource: "@MadcodersSyliusGiftCardPlugin/config/routes/shop.yaml"
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

## 5. Declare the gift card entities

The plugin's models are Doctrine **mapped superclasses**, so your application supplies the concrete
entities. Create three classes:

```php
# src/Entity/GiftCard/GiftCard.php

namespace App\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCard as BaseGiftCard;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__gift_card')]
class GiftCard extends BaseGiftCard
{
}
```

```php
# src/Entity/GiftCard/GiftCardTransaction.php

namespace App\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardTransaction as BaseGiftCardTransaction;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__gift_card_transaction')]
class GiftCardTransaction extends BaseGiftCardTransaction
{
}
```

```php
# src/Entity/GiftCard/GiftCardConfiguration.php

namespace App\Entity\GiftCard;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\GiftCardConfiguration as BaseGiftCardConfiguration;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_gift_card__configuration')]
class GiftCardConfiguration extends BaseGiftCardConfiguration
{
}
```

Then point the resources at them:

```yaml
# config/packages/madcoders_sylius_gift_card.yaml

sylius_resource:
    resources:
        madcoders_sylius_gift_card.gift_card:
            classes:
                model: App\Entity\GiftCard\GiftCard
        madcoders_sylius_gift_card.gift_card_transaction:
            classes:
                model: App\Entity\GiftCard\GiftCardTransaction
        madcoders_sylius_gift_card.gift_card_configuration:
            classes:
                model: App\Entity\GiftCard\GiftCardConfiguration
```

## 6. Extend your Sylius entities

The plugin adds state to four Sylius models: `Order`, `OrderItem`, `OrderItemUnit` and `Product`.

> **These classes already exist in your application.** Sylius Standard ships all three under
> `src/Entity/`, and they usually already carry other plugins' interfaces and traits - a stock
> Sylius 2.2 has Mollie's traits on `Order` and `Product`, and `Product` has a `createTranslation()`
> the ORM needs. **Add to those classes; do not replace them.** Overwriting `Product` with just the
> gift card trait breaks product translations outright.

Each entity needs the same three additions: import the plugin's interface and trait, add the
interface to `implements`, and `use` the trait inside the class.

**Order** - the gift cards being spent on the order. This one needs a fourth addition: the trait
cannot initialise its own collection, because `Order` already has a constructor.

```diff
  namespace App\Entity\Order;

  use Doctrine\ORM\Mapping as ORM;
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface as GiftCardOrderInterface;
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderTrait as GiftCardOrderTrait;
  use Sylius\Component\Core\Model\Order as BaseOrder;

  #[ORM\Entity]
  #[ORM\Table(name: 'sylius_order')]
- class Order extends BaseOrder
+ class Order extends BaseOrder implements GiftCardOrderInterface
  {
+     use GiftCardOrderTrait;
+
+     public function __construct()
+     {
+         parent::__construct();
+
+         $this->initializeGiftCards();
+     }
  }
```

If your `Order` already has a constructor, add the `initializeGiftCards()` call to it rather than
declaring a second one.

**OrderItem** - the amount and the message the customer chose for this line.

```diff
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderItemInterface as GiftCardOrderItemInterface;
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderItemTrait as GiftCardOrderItemTrait;

- class OrderItem extends BaseOrderItem
+ class OrderItem extends BaseOrderItem implements GiftCardOrderItemInterface
  {
+     use GiftCardOrderItemTrait;
  }
```

**OrderItemUnit** - the gift card generated for a purchased unit.

```diff
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface as GiftCardOrderItemUnitInterface;
+ use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitTrait as GiftCardOrderItemUnitTrait;

- class OrderItemUnit extends BaseOrderItemUnit
+ class OrderItemUnit extends BaseOrderItemUnit implements GiftCardOrderItemUnitInterface
  {
+     use GiftCardOrderItemUnitTrait;
  }
```

**Product** - marks a product as a gift card product.

```diff
+ use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
+ use Madcoders\SyliusGiftCardPlugin\Model\ProductTrait as GiftCardProductTrait;

- class Product extends BaseProduct
+ class Product extends BaseProduct implements GiftCardProductInterface
  {
+     use GiftCardProductTrait;
+
      // whatever your Product already has - keep it, `createTranslation()` especially
  }
```

The traits carry their own Doctrine mapping, so there is nothing else to map.

If your application does **not** already override these Sylius models, register the overrides too -
a stock Sylius Standard already does this for you:

```yaml
# config/packages/madcoders_sylius_gift_card.yaml

sylius_order:
    resources:
        order:
            classes:
                model: App\Entity\Order\Order
        order_item:
            classes:
                model: App\Entity\Order\OrderItem
        order_item_unit:
            classes:
                model: App\Entity\Order\OrderItemUnit

sylius_product:
    resources:
        product:
            classes:
                model: App\Entity\Product\Product
```

A complete worked example lives in `tests/TestApplication/` in this repository, and
`tests/Installation/` applies exactly these steps to a clean Sylius on every CI run.

## 7. Run the migrations

```bash
bin/console doctrine:migrations:migrate
```

This creates `madcoders_gift_card__gift_card`, `madcoders_gift_card__gift_card_transaction`,
`madcoders_gift_card__configuration` and the `madcoders_gift_card__order_gift_cards` join table, and
adds the `gift_card` column to `sylius_product`. The migrations are written against the Schema API,
so they run on MySQL, MariaDB and PostgreSQL alike.

There is more than one migration, and a plugin upgrade can add another - always run
`doctrine:migrations:migrate` after updating the package, not only on first install.

> **Upgrading from RC.2: deploy the code before you migrate, and stop the shop while you do.**
> MySQL and MariaDB do not run DDL inside a transaction. The migration reads the cards with no expiry
> date, writes a date to each, and only then makes the column `NOT NULL` - so if any instance still
> running the old code issues a card in between, the `ALTER` fails on the new null while the `UPDATE`s
> stay committed, and the migration is recorded as failed with the schema half changed. Roll the new
> code out first (it never writes a card without a date), or take maintenance mode for the migration.
> PostgreSQL is transactional here and rolls the whole thing back, so this is a MySQL and MariaDB
> instruction specifically.
>
> `Version20260902100000` gives every gift card that has no expiry date one,
> measured from that card's own creation date plus its channel's validity period, and then makes the
> column NOT NULL. A card created three years ago in a channel with a one-year period therefore comes
> out **already expired**, which is money its holder can no longer spend. Tell the holders before you
> run it, not after.
>
> Count them **before** upgrading, while the nulls are still there:
>
> ```sql
> SELECT COUNT(*) FROM madcoders_gift_card__gift_card WHERE expires_at IS NULL;
> ```
>
> and afterwards, to see which of those are now dead:
>
> ```sql
> SELECT code, created_at, expires_at
> FROM madcoders_gift_card__gift_card
> WHERE expires_at < NOW() AND amount > 0
> ORDER BY expires_at;
> ```
>
> Anything that comes back is a card with money on it that can no longer be spent. Re-dating those by
> hand, or topping up replacements from the admin, is a decision only you can make. Re-dating them
> works: the admin form refuses an expiry date **in the past**, not one on a card that is already
> expired, so a card this migration killed can be given a new date and brought back. What you cannot
> do is push a live card's date backwards - see
> [ADR 0018](adr-log/0018-an-expiry-date-cannot-be-moved-into-the-past.md).

## 8. Configure a channel

Gift cards are channel-scoped. Give each channel a configuration under
*Marketing > Gift card configuration* in the admin - code prefix, code length, validity period,
whether the channel sells gift cards or only issues them from the back office, and what a gift card
may be spent on. A channel without one still works; the model defaults apply, which include selling
gift cards.

Two of those defaults are worth knowing before you go live:

- **Validity period: `1 year`, and it is required.** Every gift card expires - there is no way to
  issue one that does not, in any channel, by any route. A channel with no configuration at all, or
  one whose period is blank or cannot be parsed, issues cards valid for one year. Set the period to
  whatever your jurisdiction and your accounting need; a shop that wants cards to effectively outlive
  everybody sets a long period (`25 years`) rather than none, so the liability stays dated. The form
  refuses a period it cannot act on, so `1 yaer` comes back as an error instead of silently issuing
  cards that never expire. See
  [ADR 0015](adr-log/0015-every-gift-card-expires.md).
- **An expiry date cannot be moved into the past.** A card cannot be issued already expired, and a
  live card's date cannot be edited backwards - either would leave a balance on your books that
  nobody can spend, with nothing in the card's history to say why. Bringing a date forward to one
  still in the future is untouched. To take a card out of circulation deliberately, adjust its
  balance to zero: that stops it being redeemable *and* writes a ledger entry, which backdating never
  did. See [ADR 0018](adr-log/0018-an-expiry-date-cannot-be-moved-into-the-past.md).
- **What a gift card pays for: everything except gift cards.** A gift card cannot be used to buy
  another gift card. Without this, a holder can buy a new card for exactly their remaining balance,
  pay nothing, and receive a fresh code with a fresh expiry - forever - which makes the expiry date
  above unenforceable. An order containing both goods and a gift card still redeems: the card covers
  the goods and the gift card is payable in cash. A basket of nothing but gift cards refuses
  redemption and says why, and that includes any shipping charged on it: with no goods on the order
  there is nothing a gift card may pay for, postage included. Set it to *Anything, gift cards included* per channel if you genuinely
  want the old behaviour, and read what that costs in
  [ADR 0016](adr-log/0016-a-gift-card-does-not-buy-a-gift-card.md).

## What the plugin registers for you

These need no configuration on your side, but are worth knowing about:

- **An order processor** at priority `-10`, which runs after every Sylius processor - including the
  payment processor - and settles the applied gift cards against the payment. It records what each
  card covered as a *neutral* adjustment and leaves `Order::getTotal()` alone: a gift card is money
  against the amount to pay, not a discount on the price. See
  `docs/adr-log/0010-gift-card-as-tender.md`.
- **The `madcoders_gift_card` adjustment type** added to Sylius' `OrderAdjustmentsClearer`, so a
  previous run's coverage can never survive into the next one and compound.
- **Two decorations on Sylius services that size or judge a payment from the order total**, which
  under the tender model is larger than what the customer owes:
  `sylius.state_resolver.order_payment` (otherwise an order settled with a card never reaches
  `paid`) and `sylius.order_processing.order_payment_processor.after_checkout` (otherwise a retried
  payment asks for the gift card money a second time).
- **Symfony Workflow listeners** on the order and payment transitions, as plain service tags. There
  is no `winzou_state_machine` wiring: Sylius 2.x does not install that bundle, so it would be dead
  configuration. See `docs/adr-log/0011-symfony-workflow-only.md`.
- **An email**, `madcoders_gift_cards_purchased`. Override its subject or template by redefining
  that code under `sylius_mailer.emails`.
- **Admin and account menu entries**, added through Sylius' menu events.

## Rate limiting the redeem field

A gift card code is a bearer instrument, and the redeem field is an anonymous POST: without a limiter
it accepts unlimited guesses. The plugin throttles **failed** attempts per client network - ten per
fifteen minutes by default, on out of the box - logs the refusal at `warning` on the `security`
channel, and never counts a successful one. See
`docs/adr-log/0012-rate-limiting-gift-card-redemption.md` for why the client network and not the
session or the customer.

**The limiter needs `symfony/rate-limiter`, which Sylius does not install.** Without it the plugin
boots and redeems cards exactly as it otherwise would - unthrottled. Install it, together with
`symfony/lock`, which makes the counter atomic:

```bash
composer require symfony/rate-limiter symfony/lock
```

`symfony/lock` is optional in the sense that the limiter works without it, but without a lock,
counting an attempt is a read-modify-write with nothing serialising it: concurrent posts all read the
same count and all store one more, so the real allowance per round trip becomes the number of PHP
workers rather than the number you configured.

Tune it, or turn it off, under the plugin's own configuration key:

```yaml
# config/packages/madcoders_sylius_gift_card.yaml

madcoders_sylius_gift_card:
    redemption_rate_limit:
        # enabled is true by default - see the note below before setting it here
        limit: 10              # failed attempts allowed per client per window
        interval: '15 minutes' # any relative date format - '1 hour', '30 minutes'
        shop_limit: 200        # failed attempts allowed across the whole shop per window; 0 to ignore
        shop_blocks: false     # whether reaching shop_limit refuses redemption for everybody
```

Writing `enabled: true` **without `symfony/rate-limiter` installed is a container build failure**, not
a no-op: a shop owner who turns the limiter on should not end up unprotected and told otherwise. The
default degrades quietly, an explicit request does not - which is why the snippet above leaves the key
alone. Use `enabled: false` to accept unlimited attempts deliberately.

`shop_limit` is a second, much looser window over the whole shop, for guessing spread thinly across
many addresses. It **alerts** by default rather than blocking, because refusing every redemption in
the shop is a kill switch that anybody with a botnet could pull on purpose. Turn on `shop_blocks` only
if you would rather stop guessing than keep gift card payments working under attack.

**If you run behind a load balancer or CDN, configure Symfony's `framework.trusted_proxies`.** Without
it every request appears to come from your own edge, so limiting on the client would lock out the
whole shop at once. The limiter detects this - forwarding headers present, no trusted proxies
configured - and **stands down rather than lock your shop out**, logging a warning. Redemption is then
completely unthrottled, so this is a configuration step and not an optional one:

```yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-port']
```

**If you run more than one web node**, point the limiter's cache pool at shared storage. Its state
lives in `madcoders_sylius_gift_card.cache.rate_limiter`, which defaults to `cache.app`; a limiter
whose state is per-node is a limiter divided by the number of nodes:

```yaml
framework:
    cache:
        pools:
            madcoders_sylius_gift_card.cache.rate_limiter:
                adapter: cache.adapter.redis
```

## Authorization

Admin actions that move money check the role in the
`madcoders_sylius_gift_card.admin_role` parameter, which defaults to
`ROLE_ADMINISTRATION_ACCESS`. Override it if your application has a finer permission model:

```yaml
parameters:
    madcoders_sylius_gift_card.admin_role: 'ROLE_GIFT_CARD_MANAGER'
```

## Overriding

Every service has an interface-named alias, so decorating or replacing one is standard Symfony. The
models, repositories, factories and forms are Sylius resources, so they are overridden through
`sylius_resource` configuration in the usual way. Templates are overridden by placing a file at the
same path under your application's `templates/bundles/MadcodersSyliusGiftCardPlugin/`.

See `docs/USAGE.md` for how the plugin behaves once installed.
