# 0017 - The plugin's resource forms validate with `Default` as well as their own group

**Status:** accepted.

## Context

`GiftCardType` and `GiftCardConfigurationType` extend Sylius' `AbstractResourceType`, which takes
the validation groups the form runs with as a constructor argument. Both were given exactly one
group in `config/services/forms.xml`:

```xml
<argument type="collection">
    <argument>madcoders_sylius_gift_card</argument>
</argument>
```

Symfony's `FormValidator` evaluates a constraint only when its groups **intersect** the form's.
`Default` and `madcoders_sylius_gift_card` do not intersect. So every constraint the plugin declared
in `Default` was skipped in silence on the two forms that matter most:

- the inline `NotBlank` and `Positive` on `initialAmount` in `GiftCardType`. An inline constraint
  carries `Default` unless it is told otherwise, and neither of these said otherwise;
- the `UniqueEntity` on the gift card code in `config/validation/GiftCard.xml`;
- the inline `GreaterThanOrEqual` on `codeLength` in `GiftCardConfigurationType`.

The consequences were not cosmetic. A blank initial amount reached `GiftCard::setInitialAmount()`,
which throws - so the administrator got a 500 where a field error belonged, and the only reason bad
data never reached the database was that the model refused it. A duplicate code was caught by the
unique index rather than by the form, again as an exception.

Nothing failed, warned or logged. Every unit test stayed green, because a unit test builds the
constraint by hand and never asks which group the form runs with. The `NotNull` on `expiresAt` and
the two constraints on `validityPeriod` escaped only because #31 had already noticed the problem for
those three and listed both groups on each of them - a workaround for the mismatch, applied one
constraint at a time, that nothing obliged the next constraint to copy.

## Decision

**The plugin's resource forms validate with `madcoders_sylius_gift_card` *and* `Default`.**

Three ways to reconcile the two were available, and the trade-off is what makes this worth writing
down: the choice changes which constraints run on every gift card form.

1. **Move every constraint into `madcoders_sylius_gift_card`.** Rejected. It cannot be done for the
   inline ones without repeating `groups:` on each of them forever, and every constraint that
   forgets is silently inert again - the exact failure this is fixing, preserved as a trap. It also
   takes the model's constraints away from a host that validates a gift card outside the plugin's
   forms, which is the path `Default` exists for.
2. **Drop the custom group.** Rejected, and this is the one that looks tidiest. The resource group
   is the documented Sylius convention, and a host that has declared its own constraints in
   `madcoders_sylius_gift_card` - the obvious thing to do when extending a Sylius plugin's resource -
   would find them silently stop running. That is the same bug pointed the other way, and it would
   arrive in a patch release.
3. **Add `Default` to the form's groups.** Chosen. Every constraint runs, whichever group it names.
   Nothing that ran before stops running. A host's constraints in either group keep working.

A constraint that names both groups is still evaluated once. Symfony deduplicates constraints
belonging to more than one of the groups being validated (`RecursiveContextualValidator` marks each
constraint as validated per object), so listing both cannot produce the same error twice.

## Consequences

- **Constraints that were inert now fire.** A shop upgrading gets field errors where it previously
  got a 500 (blank or zero initial amount) or a driver-level integrity violation (duplicate code).
  That is the point, but it is a behaviour change: a host that was relying on a gift card form
  accepting something will now be told it does not.
- **`Default` is the group to declare a new constraint in.** It is what an inline constraint and a
  validation attribute already carry, so the default behaviour is now the correct behaviour.
- The dual listing in `config/validation/*.xml` is kept rather than unwound. It is now belt and
  braces rather than a workaround: `Default` alone would cover every path the plugin has, and naming
  the resource group as well covers a host that validates with only that.
- **This does not make the constraints correct, only reachable**, and one of them needed a second
  change to be any use. Symfony maps a submitted value onto the object during `submit()` and
  validates it *afterwards*, so `NotBlank` and `Positive` on the initial amount were still reached
  too late: `GiftCard::setInitialAmount()` had already been handed the zero and thrown. The field now
  declines the write through a `setter` callback when the value is one the model would refuse, which
  leaves the constraints to report it. Any constraint guarding a setter that throws needs the same
  treatment - running the constraint is necessary and not sufficient.
- `codeLength` on the configuration form is reachable now and still cannot catch a short code, for an
  unrelated reason: the model raises the value to the minimum before the field is validated, and the
  form raises that error itself in a `POST_SUBMIT` listener.

## Rules

1. A new constraint on a gift card resource goes in `Default` unless there is a stated reason not
   to. Do not add a constraint that names only `madcoders_sylius_gift_card`.
2. A form type that extends `AbstractResourceType` is registered with both groups. A new one with
   only the resource group is the bug in this ADR, reintroduced.
3. Wiring like this is proved in a booted container, not in a unit test.
   `tests/Functional/Validator/` is where that belongs: a constraint built by hand says nothing
   about the group the form runs with.
