# 0012 - Rate limiting redemption on the client address, and one message for every refusal

**Status:** accepted

## Context

A gift card code is a bearer instrument: whoever types it spends it. The shop redeem field is an
anonymous POST - a guest cart is enough to reach it - and it accepted unlimited attempts, so the
codes were only as safe as they were long. `GiftCardCodeGenerator` avoids ambiguous characters and
`GiftCardConfiguration` enforces a 12 character minimum, which makes a single guess very unlikely to
land; it does nothing about a script that guesses all night.

The same endpoint also answered a second, quieter question. It returned *different* messages for
"there is no gift card with this code" and "this gift card cannot be used", so an attacker learned
which codes were real without ever needing one that could be spent. That contradicted the care taken
elsewhere: `GiftCardApplicator::remove()` resolves against the cart's own cards precisely so that
removal cannot enumerate codes, and `GiftCardNotFoundException` masks the code it was given.

Two decisions had to be made, and both are load-bearing:

1. what the limiter counts against - the thing that identifies "one client";
2. what a refused customer is told.

## Decision

### The limiter is keyed on the client address

`Request::getClientIp()`, as Symfony resolves it - so a shop behind a load balancer that has
configured trusted proxies gets the real client rather than its own edge.

Only **failed** attempts are counted, and a successful redemption **clears** the tally for that
client. The threshold, the window and whether the limiter runs at all are host configuration
(`madcoders_sylius_gift_card.redemption_rate_limit`), defaulting to **10 failures per 15 minutes,
on**.

Storage is a plugin-owned cache pool, `madcoders_sylius_gift_card.cache.rate_limiter`, so a shop
running more than one web node can point that pool at a shared Redis. A limiter whose state is
per-node is a limiter divided by the number of nodes.

Every refused request is logged at `warning` on the `security` channel with the key and the number of
failed attempts. Never the submitted code: the limiter's interface does not accept one, so it cannot
leak one.

### One message for every failed apply

`madcoders_sylius_gift_card.cart.not_usable` - "This gift card code cannot be used. Check it and try
again." - replaces the three messages that used to distinguish an unknown code, a card that is
expired, disabled or spent, and a card belonging to another channel.

Hitting the limit is a separate message, because it is about the client rather than about the code
and therefore reveals nothing: it says the same thing whether the codes tried were real or not.

Removing a card is **not** rate limited. It never consults the repository, so repeating it tells
nobody anything.

## Consequences

- A run of guesses gets at most `limit` tries per window per address, and the shop gets a log line
  per refusal to alert on.
- Customers on a shared address - an office, a school, mobile carrier-grade NAT - share one
  allowance. This is the cost of the choice, and it is bounded by counting only failures: a customer
  entering codes they actually hold never spends a token, and the first correct code clears whatever
  their colleagues got wrong.
- A customer who mistypes their own code past the threshold has to wait out the window. The window is
  short and the threshold is generous for exactly this reason.
- A customer whose card has genuinely expired is no longer told so at the redeem field. That
  information is not lost, it moved to where it is safe: *My gift cards* in the account shows the
  cards the customer bought or redeemed, with their balance, and it is behind a login and scoped to
  the cards that are actually theirs.
- The limiter is optional. `symfony/rate-limiter` is a `suggest`, not a `require`, so a host that has
  not installed it boots and redeems exactly as before - unthrottled. `docs/INSTALLATION.md` says so
  in as many words.

## Alternatives considered

**Key on the session.** Rejected: a session is a cookie the attacker controls. Clearing it costs a
script one line, so this is a limiter that only ever inconveniences honest customers.

**Key on the logged-in customer.** Rejected as the primary key: the exposed case is the anonymous
one - a guest cart is enough to reach the endpoint - so a customer-keyed limiter simply would not
apply to the attack it exists to stop. Registration is also cheap, so it would not stop a determined
attacker even where it did apply.

**Key on address *and* session together.** Rejected: any composite key is only as strong as the part
the attacker can rotate. Adding the session to the address multiplies the number of buckets one
machine can occupy, which makes the limiter weaker, not stronger.

**Address plus customer where one is logged in.** Rejected for 1.0 as complexity without a payoff: it
would give an authenticated customer their own allowance on a shared office address, but it also
gives an attacker a fresh allowance per registration. The bounded version of that idea - a *higher*
threshold for authenticated customers - can be added later without changing this decision.

**Sliding window instead of fixed window.** Rejected: the cheaper policy is enough here. The window
resetting a little abruptly costs an attacker far more than it costs a shop.

**Keeping the distinction between "no such code" and "not redeemable" for logged-in customers only.**
Considered because the ticket asks for it explicitly, and rejected: it would mean the endpoint
answers "does this code exist?" for anybody willing to register an account, and registration is free.
The distinction is only genuinely safe when the card is known to belong to the person asking, which
is the account page's job and not the redeem field's.

## Rules

1. Anything that answers a question about a submitted code before the limiter has been consulted is a
   bug. `isBlocked()` is asked before the applicator, not after.
2. Failed applies get one message. A new failure mode gets the *same* message, unless it can be shown
   not to disclose whether a code exists.
3. The limiter never receives a gift card code, and nothing that does may log one.
4. Endpoints that resolve against the cart rather than the repository - removal today - are not rate
   limited, and do not become so without first becoming repository-backed.
5. A host must be able to boot without `symfony/rate-limiter`. Services that need it stay behind the
   conditional load in `MadcodersSyliusGiftCardExtension`.
