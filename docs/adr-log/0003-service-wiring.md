# 0003 - Service wiring in XML, modular by concern

**Status:** accepted

## Context

The plugin wires a fair number of services: applicator, order processor, modifier, operators,
generators, providers, form types, Behat contexts, workflow listeners. Autowiring everything from a
single file makes service ids unstable and hides the tags (`sylius.order_processor`,
`kernel.event_listener`, `sylius.grid`, ...) that the plugin depends on.

## Decision

Services are declared **explicitly in XML**, split by concern under `config/services/`, and pulled
in by a glob from `config/services.php`:

```
config/services/
├── applicator.xml
├── controllers.xml
├── fixtures.xml
├── forms.xml
├── generators.xml
├── listeners.xml
├── menu.xml
├── operators.xml
├── order_processors.xml
└── providers.xml
```

Service ids are prefixed `madcoders_sylius_gift_card.<concern>.<name>`, mirroring Sylius' own
naming so host applications can decorate or replace them predictably.

## Consequences

- Tags and priorities are visible in one place per concern.
- Service ids are part of the public API of the plugin and are covered by semantic versioning.
- Adding a service means editing the XML file for its concern, not a catch-all.

## Rules

1. New service → declare it in the XML file for its concern with an explicit id and arguments.
2. Public ids only where the host application or Behat genuinely needs them.
3. Keep the business logic in the service; keep framework wiring (state machine adapters, event
   listeners) in thin classes that delegate to it.
