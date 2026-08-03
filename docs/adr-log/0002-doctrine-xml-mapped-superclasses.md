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
- No Doctrine attributes or annotations on plugin **model** classes.
- The mapping is registered **explicitly** from the extension's `prepend()`, not left to
  DoctrineBundle's bundle auto-detection: auto-detection assumes mapped classes live under
  `<BundleNamespace>\Entity` and derives the class name from the file name, but these are models
  under `\Model`.
- Extensions to Sylius models are shipped as **interface + trait pairs**
  (`ProductInterface`/`ProductTrait`, `OrderInterface`/`OrderTrait`,
  `OrderItemUnitInterface`/`OrderItemUnitTrait`). These traits **do** carry Doctrine attributes -
  they are applied to the *host application's* entities, so their mapping has to be picked up by
  that application's mapping driver, and shipping it in the trait means the host gets the
  association by using the trait rather than by copying XML.
- Table names are prefixed `madcoders_gift_card__`.
- Migrations are written against the **Schema API**, not raw SQL, because CI covers MySQL, MariaDB
  and PostgreSQL. Indexes carry the names Doctrine's ORM derives, so a host application is left in
  sync according to `doctrine:schema:validate`.

## Consequences

- Mapping lives next to the plugin but never claims ownership of the concrete entity.
- Installation requires the host application to declare the entities (documented in
  `docs/INSTALLATION.md`).
- Schema changes ship as Doctrine migrations under `src/Migrations/`.

## Rules

1. No `#[ORM\...]` attributes on the plugin's own models. The extension traits applied to host
   entities are the documented exception.
2. Every schema change gets a migration; never rely on `doctrine:schema:update`.
3. Association targets are declared against **interfaces**, resolved through Sylius' resource
   metadata, so host overrides keep working.
