# 0011 - Symfony Workflow only, no winzou wiring

**Status:** accepted. **Supersedes the state machine wiring in**
[0004](0004-gift-card-redemption-as-order-adjustment.md), including its rule 3.

## Context

0004 assumed Sylius 2.x host applications could be running either of two state machine adapters, so
the plugin shipped both wirings for every order transition it hooked: a `winzou_state_machine`
callback block and a Symfony Workflow listener, pointing at the same service.

That assumption was wrong. Sylius 2.x does not install `winzou/state-machine`; it appears only under
`suggest` in Sylius' own `composer.json`. So in every supported installation the winzou callbacks
were dead configuration.

Worse, they were *untestable* dead configuration. The test application uses Symfony Workflow, which
is what CI exercises, so the winzou path was carried without a single assertion against it across
the whole matrix. Configuration that cannot fail in CI but is presented as supported is a liability:
it invites a host to rely on it, and the first time it breaks is in their shop.

The alternative - installing winzou in CI and running a second matrix leg through it - buys coverage
of a path no supported Sylius 2.x installation takes, at the cost of doubling the end-to-end matrix.

## Decision

The plugin wires order transitions through **Symfony Workflow only**.

- Listeners are plain invokable services tagged `kernel.event_listener` on
  `workflow.sylius_order.completed.create` / `.cancel` and the payment equivalents, in
  `config/services/listeners.xml`.
- There is no `config/state_machine/` directory and nothing is prepended for winzou.
- A host that has deliberately installed `winzou/state-machine` and switched Sylius over to it is
  outside what this plugin supports. Such a host has to wire the plugin's listener services to its
  own callbacks; the services are public and take the event's subject, so the work is small, but it
  is theirs.

The same reasoning applies to the state resolver and payment processor decorations added later:
those decorate Sylius services directly and are adapter-independent.

## Consequences

- One wiring to reason about, and CI covers all of it.
- `docs/INSTALLATION.md` no longer promises adapter independence, which it could not honour.
- If Sylius ever ships winzou as a real dependency again, this decision gets revisited rather than
  quietly worked around.

## Rules

1. Order and payment transitions are hooked through **Symfony Workflow event listeners only**. Do
   not reintroduce winzou callbacks, and do not add a `config/state_machine/` directory.
2. Listener classes stay thin: unwrap the event, delegate to a service, return. The service is what
   gets unit tested.
3. Do not carry configuration that CI cannot exercise. If a path cannot be tested in the matrix,
   either add a matrix leg for it or drop the path.
