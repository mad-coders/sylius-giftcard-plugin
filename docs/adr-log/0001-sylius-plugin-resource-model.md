# 0001 - Built on the Sylius resource model

**Status:** accepted

## Context

The plugin introduces persistent entities (gift cards, their transactions, per-channel
configuration) that need admin CRUD, grids, forms, repositories and factories. Sylius already
provides all of this through `sylius/resource-bundle`: register a resource, get a controller,
factory, repository, form handling and grid integration for free, all overridable by the host
application.

The alternative - hand-written controllers and services per entity - would duplicate that
machinery and break the extension points host applications expect from a Sylius plugin.

## Decision

Every persistent entity is registered as a **Sylius resource** in `config/resources.yaml`, using
the stock `ResourceController`, `Factory` and `EntityRepository` unless there is a concrete reason
to specialise. Custom repositories extend `Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository`.

Resource names are prefixed `madcoders_sylius_gift_card.*`; service ids follow the same prefix.

## Consequences

- Admin CRUD is configuration (routes + grids), not code.
- Host applications can swap any model, factory, repository or form through standard Sylius
  resource configuration.
- Entities must be `ResourceInterface` implementations with the identifier accessible.

## Rules

1. New persistent entity → register it as a resource; do not hand-roll a controller.
2. Custom controller actions only where the stock resource controller genuinely cannot express the
   behaviour, and then as a separate, narrowly scoped controller.
3. Never reference a concrete model class where the interface exists; resolve models through the
   resource configuration (`%madcoders_sylius_gift_card.model.gift_card.class%`).
