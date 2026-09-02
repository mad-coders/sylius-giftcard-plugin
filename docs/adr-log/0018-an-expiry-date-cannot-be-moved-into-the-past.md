# 0018 - An expiry date cannot be moved into the past; retirement goes through the balance

**Status:** accepted. Completes [0015](0015-every-gift-card-expires.md).

## Context

[0015](0015-every-gift-card-expires.md) made an expiry date mandatory and said so plainly: all three
places it is enforced check that a date is **present**, and none of them says the date is sensible.
An administrator could type `2020-01-01` into the create form and issue a card that was dead the
moment it existed, or edit a live card's date backwards and turn a customer's $200 into nothing.

Neither is a balance change, so `ai/coding-rules.md` invariant 3 - every change to a balance writes
a `GiftCardTransaction` - is not violated in the letter. It is gutted in the spirit. The rule exists
so a balance can always be explained, and a card whose $200 shows in the admin, sits in the shop's
liability report and cannot be spent by anybody is the least explicable state the model can reach.
The card's history shows nothing at all.

The obvious constraint - `GreaterThan('now')` on `expiresAt` - is wrong, and wrong in a way that
would not have shown up until a shop upgraded. The migration in
[#31](0015-every-gift-card-expires.md) deliberately dates cards into the past: a card created three
years ago in a channel with a one-year period comes out already expired, by design. Under
`GreaterThan('now')` every one of those cards becomes uneditable. An administrator could not disable
one, could not correct its message, could not save the form at all without inventing a new expiry
date - which is precisely the lie the constraint was meant to prevent.

There are also real reasons to take a card out of circulation on purpose: a card issued in error, one
bought with a charged-back payment, a batch printed with the wrong terms. Assuming there are none
would be as wrong as assuming any backdating is fine.

## Decision

**An expiry date may not be moved into the past. Deliberate retirement goes through the balance,
where it is already recorded.**

### The rule

`GiftCardExpiryNotInThePast`, a **class** constraint on `GiftCard`, refuses a submitted expiry that
is in the past **unless the card was already expired before this write**. A class constraint rather
than a field one because the question cannot be answered from the submitted value alone; the
previous value is read from Doctrine's unit of work through
`StoredGiftCardExpiryProviderInterface`, which still holds the row as it was loaded because
validation happens long before the flush.

That gives four answers, and each is the one wanted:

| | |
|---|---|
| Create a card dated in the past | **Refused.** There is no previous date, so the card is not already expired. |
| Edit a live card's date backwards | **Refused.** A spendable balance would stop being spendable. |
| Edit a card that already expired | **Allowed.** Today has taken the balance already; the write takes nothing. |
| Shorten an expiry to a date still in the future | **Allowed, with no friction.** |

The last row is deliberate. This is about the past, not about all reductions - a shop that decides a
card should run out sooner is entitled to say so, and the balance stays spendable until it does.

### Refused, not confirmed

Issue #45 allowed either a refusal or an explicit confirmation. **Refusal**, because the confirmation
would have been offering the administrator a worse version of something the plugin already does
better.

Retiring a card by backdating it leaves the money on the card. The balance is still there, still on
the shop's books, still shown in the admin - it simply cannot be spent, and the card's history says
nothing about why. **Taking the balance off the card does the same job honestly**: the *Adjust
balance* action debits the remainder through `GiftCardBalanceModifier`, which writes a
`GiftCardTransaction` in the same unit of work, so the card ends at zero, stops being redeemable
(invariant 4 requires `amount > 0`), and the ledger says who took what and when. The money is
accounted for instead of being stranded.

So deliberate retirement stays possible - #45's criterion 4 - and it is audited, because the path it
now goes through was audited from the start. Nothing new had to be built to make that true, and the
constraint message names the alternative rather than leaving the administrator at a form that just
says no.

### What this does not do

The constraint judges the value, not the intent. It cannot tell a card dated `2020-01-01` by mistake
from one dated `2020-01-01` on purpose, and does not try to - it refuses both and points at the
action that expresses the intent properly.

**A date one minute in the future is waved through, and sixty seconds later the card is in the state
described at the top of this ADR.** The widget is minute-granular, so an administrator can set a live
$200 card to expire almost immediately, save, and produce a balance that is on the books, visible and
unspendable, with nothing in the history. That is not an oversight to be fixed later; it is the
unavoidable other side of #45's criterion 5, which requires shortening to a future date to stay
frictionless. A rule that closed it would have to refuse *every* shortening, or write a ledger entry
on every one - and both were considered and rejected, because they punish the ordinary case of a
shop correcting its own terms in order to catch an adversary who could simply wait a minute.

**So what this delivers is a speed bump, not an invariant.** It closes the case an administrator
reaches by accident and the case that leaves no trace at all in a form submission; it does not stop
somebody determined to strand a balance. Read the rules below as what the code enforces, not as a
guarantee about balances.

A zero-balance card also still shows its original expiry date, because that date is a true statement
about the card's terms. What changed is the balance, and the ledger explains it.

## Consequences

- **The #31 migration is unaffected.** It writes SQL and never passes through the validator, and the
  cards it dates into the past stay editable afterwards because the rule asks about the *stored*
  date.
- **A host importing pre-printed cards with historical dates through the validator is refused.**
  That is a behaviour change. Importers that persist without validating - the plugin's own fixture
  and example factories among them - are untouched.
- The constraint costs one identity-map lookup per validation and no query.
- `StoredGiftCardExpiryProviderInterface` is a new extension point. A host on a different persistence
  arrangement can answer the question its own way; answering `null` means "no previous date to
  compare against", never "the card had no expiry".
- **`null` does not mean "refuse".** A detached card - an importer or a Messenger handler clearing
  its entity manager every few thousand rows - is indistinguishable from a card being created, and
  Doctrine's `getEntityState()` reports the assumption it is given rather than going to the database.
  Judging that as a creation would refuse a legacy card being edited only to disable it, in exactly
  the batch jobs least able to explain themselves. The constraint therefore falls back to the card's
  identity: no id, no history, judge the submitted date on its own; an id and no readable previous
  date, leave it alone.
- ADR 0015's fourth point said a date in the past "is a separate gap and needs its own ticket". This
  is that ticket, and 0015 should now be read with this alongside it.

## Rules

1. **An expiry date may not be moved into the past.** This is what the code enforces, and it is
   deliberately narrower than "nothing may make a spendable balance unspendable without writing to
   the card's history" - see *What this does not do*. A future date, however near, is allowed.
   Do not quote the wider version as though it held.
2. Taking a card out of circulation on purpose means taking its balance to zero, not backdating it.
   A new retirement mechanism that leaves the balance sitting on the card reopens exactly this.
3. The rule is about the past. Do not turn it into "the expiry may never be shortened" - that is a
   different rule, it was not asked for, and it would stop a shop correcting its own terms.
4. Any *new* way to strand a balance - a bulk expiry action, an import that re-dates, a scheduled
   sweep - has to write to the card's history, because none of them has criterion 5 to answer to and
   none of them is a single administrator making one visible edit.
