# 0002 - Doctrine mapping as XML mapped superclasses

**Status:** accepted

## Context

A Sylius plugin's models must be extensible by the host application: the application declares its
own entity extending the plugin's model, and maps it in its own configuration. If the plugin maps
its models as concrete entities (or with attributes on the class), the host application cannot
extend them without fighting the mapping.

The plugin also extends Sylius' own models (`Product`, `Order`, `OrderItemUnit`) with extra state,
which the host application must opt into on its own entity classes.

## Decision

- Plugin models are mapped in **XML** under `config/doctrine/`, as `<mapped-superclass>`, and the
  concrete entity is provided by the host application (Sylius' standard resource override flow).
- No Doctrine attributes or annotations on model classes.
- Extensions to Sylius models are shipped as **interface + trait pairs**
  (`ProductInterface`/`ProductTrait`, `OrderInterface`/`OrderTrait`,
  `OrderItemUnitInterface`/`OrderItemUnitTrait`). The host application applies the trait to its own
  entity and maps the added fields; the plugin ships the mapping fragment it can.
- Table names are prefixed `madcoders_gift_card__`.

## Consequences

- Mapping lives next to the plugin but never claims ownership of the concrete entity.
- Installation requires the host application to declare the entities (documented in
  `docs/INSTALLATION.md`).
- Schema changes ship as Doctrine migrations under `src/Migrations/`.

## Rules

1. No `#[ORM\...]` attributes in `src/Model/` or `src/Entity/`.
2. Every schema change gets a migration; never rely on `doctrine:schema:update`.
3. Association targets are declared against **interfaces**, resolved through Sylius' resource
   metadata, so host overrides keep working.
