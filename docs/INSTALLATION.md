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

The plugin adds state to three Sylius models. Apply the interface and the trait to your own
entities - the traits carry their own Doctrine mapping, so there is nothing else to map.

**Order** - the gift cards being spent on the order. Note the constructor call: the trait cannot
initialise its own collection because `Order` already has a constructor.

```php
# src/Entity/Order/Order.php

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\OrderInterface as GiftCardOrderInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderTrait as GiftCardOrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements GiftCardOrderInterface
{
    use GiftCardOrderTrait;

    public function __construct()
    {
        parent::__construct();

        $this->initializeGiftCards();
    }
}
```

**OrderItemUnit** - the gift card generated for a purchased unit.

```php
# src/Entity/Order/OrderItemUnit.php

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitInterface as GiftCardOrderItemUnitInterface;
use Madcoders\SyliusGiftCardPlugin\Model\OrderItemUnitTrait as GiftCardOrderItemUnitTrait;
use Sylius\Component\Core\Model\OrderItemUnit as BaseOrderItemUnit;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item_unit')]
class OrderItemUnit extends BaseOrderItemUnit implements GiftCardOrderItemUnitInterface
{
    use GiftCardOrderItemUnitTrait;
}
```

**Product** - marks a product as a gift card product.

```php
# src/Entity/Product/Product.php

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusGiftCardPlugin\Model\ProductInterface as GiftCardProductInterface;
use Madcoders\SyliusGiftCardPlugin\Model\ProductTrait as GiftCardProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements GiftCardProductInterface
{
    use GiftCardProductTrait;
}
```

If your application does not already override these Sylius models, register the overrides too:

```yaml
sylius_order:
    resources:
        order:
            classes:
                model: App\Entity\Order\Order
        order_item_unit:
            classes:
                model: App\Entity\Order\OrderItemUnit

sylius_product:
    resources:
        product:
            classes:
                model: App\Entity\Product\Product
```

A complete worked example lives in `tests/TestApplication/` in this repository.

## 7. Run the migrations

```bash
bin/console doctrine:migrations:migrate
```

This creates `madcoders_gift_card__gift_card`, `madcoders_gift_card__gift_card_transaction`,
`madcoders_gift_card__configuration` and the `madcoders_gift_card__order_gift_cards` join table, and
adds the `gift_card` column to `sylius_product`. The migration is written against the Schema API, so
it runs on MySQL, MariaDB and PostgreSQL alike.

## 8. Configure a channel

Gift cards are channel-scoped. Give each channel a configuration under
*Marketing > Gift card configuration* in the admin - code prefix, code length and validity period.
A channel without one still works; the model defaults apply.

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
