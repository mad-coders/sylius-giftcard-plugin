# 0015 - Every gift card expires, and one service decides when

**Status:** accepted.

## Context

A gift card could be issued with no expiry date at all, and that was the *default*:
`GiftCardConfiguration::calculateExpiryDate()` returned null for a channel with no configuration, a
blank validity period, or one it could not parse, and `PrepareGiftCardOnCreateListener` then left
`expiresAt` null. Nothing anywhere required one.

Three things make that worse than untidy:

- **The liability never goes away.** A card with no expiry sits on the shop's books forever. It is
  not a rounding error either: unclaimed gift card balances are a real line in a real ledger, and
  one that cannot age out cannot be released.
- **Several jurisdictions require the expiry to be stated at the point of sale.** A shop that cannot
  state one because the plugin never issued one is not in a position to comply.
- **The failure was silent in both directions.** An operator who typed `1 yaer` saved the form
  cleanly and walked away believing the channel expired its cards. It did not, and nothing said so
  until somebody went looking a year later.

There were also four separate places deciding an expiry date - the factory, the resource listener,
the fixture factory, and the model method all three of them called - and every one of them had a
null branch. Any new issuing path would have had to remember to add its own.

## Decision

**Every gift card has an expiry date, and `GiftCardExpiryCalculatorInterface` is the only thing that
produces one.**

1. **The calculator cannot return null.** `calculate(): \DateTimeImmutable` - not nullable, so no
   caller has a null branch to get wrong. A null configuration, a blank period, an unparseable one
   and one that does not move the date forward (`0 days`, `-1 year`) all fall back to the plugin's
   default period of **one year**. None of them yields a card without an expiry, and none yields one
   that is already expired.
2. **It holds the default twice, deliberately.** `DEFAULT_VALIDITY_PERIOD` is the human form
   (`'1 year'`) that the configuration form defaults to and the documentation quotes;
   `DEFAULT_INTERVAL_SPEC` is `P1Y`, the form PHP cannot fail to parse. The fallback uses the
   second, because a fallback that could itself fail to parse would put back the null this class
   exists to eliminate. A unit test asserts the two agree.
3. **`GiftCardConfiguration::calculateExpiryDate()` is gone.** The configuration holds the period;
   turning a period into a date is the calculator's job. Everything that issues a card - the
   factory, the resource listener, the fixture factory, the admin form - goes through it.
4. **The rule is enforced at three depths**, in the tradition of
   [0013](0013-gift-card-sale-mode.md):
   - **The calculator**, so nothing that issues a card can produce one without a date.
   - **A `NotNull` constraint** on `GiftCard::expiresAt`, so the admin form refuses a cleared field
     with a message naming it rather than a driver-level error. Registered for both the `Default`
     group and the plugin's own `madcoders_sylius_gift_card` resource group, because the two
     validation paths use different ones and a constraint in only one of them is silently skipped by
     the other.
   - **`expires_at NOT NULL`**, so an importer, a console command or a host application's own code
     cannot write a card without one either. This is the layer nothing can talk its way past.

   **All three enforce the same narrow thing: that a date is present.** None of them says the date is
   sensible. #31 asked only that the field be unclearable, so that is what this delivers. Read "three
   depths" as three places a *missing* date is caught, not as a claim that the value is validated.

   The separate gap this left - an administrator typing `2020-01-01` into the create form, or editing
   a live card's date backwards, and killing a spendable balance with no warning and no ledger entry -
   was closed by [0018](0018-an-expiry-date-cannot-be-moved-into-the-past.md). Read the two together.
5. **A channel must state a validity period, in words the plugin can act on.** The configuration
   form requires it (`NotBlank`) and refuses one that cannot produce a future date (`ValidityPeriod`,
   which asks the calculator rather than parsing again - a second parser would eventually disagree
   with the first, and the disagreement would look like a channel that saves cleanly and quietly
   issues cards on the default period).
6. **The admin create form pre-fills the date.** An expiry is a term of the sale, so the
   administrator sees what they are about to issue and may change it. They may not clear it.
7. **The migration back-fills.** Cards with no expiry are given one from their own creation date
   plus their channel's validity period, before the column stops accepting null.

### "Never expires" is no longer expressible, on purpose

The fixtures used to say `expires_at: never`, and there was a demo card built on it. Both are gone.

A mandatory expiry with an escape hatch is not a mandatory expiry. Keeping `never` would have meant
the plugin could still produce the exact state the change exists to prevent, and the guarantee every
other layer now depends on - the NOT NULL column, the non-nullable calculator return, the templates
that no longer branch - would have had to stay conditional.

A shop that wants a card to outlive everybody says so with a long validity period. That is not a
worse answer, it is a more honest one: the liability is still dated, still reportable, and still
ages out eventually. The demo suite now carries `GIFT-LONGLIFE`, twenty-five years out, in place of
the card that never expired.

### What the create form cannot do, and why

The channel is chosen on the same form as the expiry, so at the moment the form is built there is
usually no channel to read a validity period from. In a **single-channel shop** - most of them -
that one channel is the answer and its own period is used. In a **multi-channel shop** the field is
pre-filled with the plugin's default period instead, visible and editable, rather than with a date
silently computed from a channel the administrator has not picked yet.

Following the channel selection live would need JavaScript, and this admin deliberately does not use
any. The honest statement is that a multi-channel operator creating a card for a channel with an
unusual period has to change the date by hand, and can see exactly what they are changing.

## Consequences

- **This is a behaviour change for anyone on RC.2.** Cards that had no expiry get one from their
  creation date, which means a card created three years ago in a channel with a one-year period
  comes out **already expired**. That is what "measured from the card's creation date" means, and it
  is money a holder can no longer spend. Tell the holders before running it, not after.

  The migration counts those cards and reports them through the migration logger, but that report
  **cannot be relied on**: whether it reaches a console depends entirely on where the host routes
  that logger, and in a stock Sylius application it goes nowhere. `docs/INSTALLATION.md` therefore
  gives the operator the two queries instead - one before the upgrade, one after - which is the only
  answer that does not depend on somebody else's logging configuration.
- Templates no longer branch on a null expiry, and the purchase email always states the date -
  which is the point of the jurisdictional half of this.
- The `madcoders_sylius_gift_card.ui.never_expires` translation key is gone from both catalogues.
- A host application that reads `GiftCardInterface::getExpiresAt()` still gets a nullable return,
  because a card that has been constructed but not yet prepared has no date. Everything that reaches
  the database has one.
- `GiftCardConfigurationInterface::getValidityPeriod()` stays nullable, because rows written before
  this release can hold null. **Null there does not mean "never expires"** - the calculator resolves
  it to the default - and the interface says so.

## Rules

1. Nothing computes an expiry date except `GiftCardExpiryCalculatorInterface`. A new issuing path is
   not done until it asks that service.
2. The calculator's return type stays non-nullable. Making it nullable to express some new case puts
   back every null branch this removed.
3. A blank or unparseable validity period means "use the default", never "never expires". Any code
   that reads it the second way is a bug.
4. There is no way to ask for a card without an expiry - not in a fixture, not on a form, not in the
   database. Adding one reopens the liability this closed.
5. Anything that shows a customer a gift card shows its expiry date, because that date is a term of
   the sale.
