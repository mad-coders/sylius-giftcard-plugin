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

### The limiter is keyed on the client network, not the client address

`Request::getClientIp()`, as Symfony resolves it, **aggregated to a /64 for IPv6**. A bare IPv6
address is not a client: a routed /64 comes free with any cheap VPS, so keying on the address would
hand one machine 2^64 allowances - the very objection this record raises below against composite keys.
/64 is the standard end-site allocation, so that is the unit. IPv4 is left alone, being scarce enough
to be the unit already.

The limiter **refuses to arm** rather than key on something it knows is wrong. If a request carries
`X-Forwarded-For` or `Forwarded` while `framework.trusted_proxies` is unset, `getClientIp()` returns
the edge address and every customer behind that CDN shares one bucket - eleven bad codes would stop
redemption for the whole shop, silently, and repeatably. So that case is logged and not limited. The
same goes for a request with no client address at all: one shared bucket for everything
unidentifiable lets any one of them lock out the rest.

Only **failed** attempts are counted. A successful redemption forgives the failures before it, but
**only once per window, and only when a card was newly applied**. Both halves of that are load
bearing. Applying a card does not debit it, and applying one the cart already holds is a silent no-op
that still succeeds - so an unlimited forgiveness would have sold unlimited guessing for the price of
the cheapest card in the shop: nine wrong codes, then your own, for ever. Removing and re-applying
makes even a genuinely new application repeatable, which is why the cap sits on top of the guard.
The cost is bounded: a client can reach at most twice its allowance in a window.

There is a second, much looser window over the **whole shop**, because a per-client limit is only as
good as the number of clients an attacker can afford. Reaching it logs at `error`. It does **not**
block by default (`shop_blocks: false`): refusing every redemption in the shop is a kill switch that
anybody with a botnet could pull deliberately, and taking the money path down is a worse outcome than
guessing continuing to be merely expensive. A host that would rather have the bound can turn it on.

The threshold, the window, the shop-wide threshold and whether the limiter runs at all are host
configuration (`madcoders_sylius_gift_card.redemption_rate_limit`), defaulting to **10 failures per
15 minutes per client, 200 per shop, on**. The window is validated at compile time, because
`RateLimiterFactory` parses it in its constructor and the factory is lazy - an unparseable interval
would otherwise pass `lint:container` and then 500 on the first customer to type a code.

Storage is a plugin-owned cache pool, `madcoders_sylius_gift_card.cache.rate_limiter`, so a shop
running more than one web node can point that pool at a shared Redis. A limiter whose state is
per-node is a limiter divided by the number of nodes.

The counter is **guarded by a lock** where the host has `symfony/lock`. Without one, consuming from a
window is a read-modify-write with no mutual exclusion, so concurrent posts all read the same count
and all store one more: the effective allowance per round trip becomes the number of PHP workers
rather than the configured limit. The lock is wired `on-invalid="null"`, so a host without the
component degrades to the unsynchronised counter rather than failing to boot.

The refusal is logged at `warning` on the `security` channel **once per client per window**, at the
attempt that exhausts the allowance rather than on every request that is then refused. Logging each
refused request would have let whoever is hammering the shop choose how fast it writes to disk. The
line carries the key, the limit, the retry time and the shop-wide failure count - the last because it
is the only number in it that varies, and so the only one that separates one customer fumbling from a
wave. Never the submitted code: the limiter's interface does not accept one, so it cannot leak one.

### One message for every failed apply

`madcoders_sylius_gift_card.cart.not_usable` - "This gift card code cannot be used. Check it and try
again." - replaces the three messages that used to distinguish an unknown code, a card that is
expired, disabled or spent, and a card belonging to another channel.

Hitting the limit is a separate message, because it is about the client rather than about the code
and therefore reveals nothing: it says the same thing whether the codes tried were real or not.

Removing a card is **not** rate limited. It never consults the repository, so repeating it tells
nobody anything.

## Consequences

- A run of guesses gets at most `2 x limit` tries per window per network - the allowance, plus the one
  forgiveness a real card buys - and the shop gets one log line per client per window to alert on.
- Customers on a shared address - an office, a school, mobile carrier-grade NAT - share one
  allowance. This is the cost of the choice, and it is bounded by counting only failures: a customer
  entering codes they actually hold never spends a token, and the first correct code clears whatever
  their colleagues got wrong.
- Behind a CDN with `trusted_proxies` unconfigured there is **no** limiting at all, and a warning in
  the log saying so. That is deliberate: the alternative is a silent shop-wide lockout, and a control
  that takes the shop down when misconfigured is not a control, it is a second vulnerability.
- Without `symfony/lock` the per-request allowance is only approximate under concurrency. The window
  still bounds a serial attacker; a parallel one gets roughly worker-count attempts per round trip
  more than the configuration says.
- A customer who mistypes their own code past the threshold has to wait out the window. The window is
  short and the threshold is generous for exactly this reason.
- A customer whose card has genuinely expired is no longer told so at the redeem field. That
  information is not lost, it moved to where it is safe: *My gift cards* in the account shows the
  cards the customer bought or redeemed, with their balance, and it is behind a login and scoped to
  the cards that are actually theirs.
- The limiter is optional. `symfony/rate-limiter` is a `suggest`, not a `require`, so a host that has
  not installed it boots and redeems exactly as before - unthrottled. `docs/INSTALLATION.md` says so
  in as many words. That quiet degradation applies to the **default only**: a host that writes
  `enabled: true` and has not installed the component fails the container build, because the one
  thing worse than no limiter is a shop owner who believes they have one.

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

That argument cuts both ways, and it is why the key is a **network** rather than an address: a bare
IPv6 address is itself a rotatable part, since a single cheap VPS is routed a whole /64. Aggregating
to /64 and adding the shop-wide window are what keep this record from arguing against itself.

**Blocking on the shop-wide window by default.** Rejected. It is the same failure the untrusted-proxy
guard exists to avoid, arrived at from the other direction: an attacker who cannot guess a code can
instead deliberately exhaust the shop-wide allowance and stop every customer in the shop from paying
with a gift card, for the cost of a few hundred wrong codes. Alerting is the default; the block is
available (`shop_blocks: true`) for hosts who would rather bound the guessing than keep the money path
up.

**Refunding one token per success instead of resetting the window.** Considered as a gentler cap and
rejected as no cap at all: a success is repeatable on demand, so a refund per success is a refund per
round trip. What bounds the attack is that forgiveness is finite *per window*, not that it is small.

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
   conditional load in `MadcodersSyliusGiftCardExtension` - but a host that asks for the limiter in as
   many words gets an error, not a silent no-op.
6. Credit given for a successful redemption must be finite per window. Anything a caller can repeat
   for free is not evidence they are not guessing, and "the customer holds a real card" is repeatable
   for free.
7. The limiter fails **open**, loudly, whenever it cannot identify a client it trusts. A control that
   locks the shop out when misconfigured is a second vulnerability, not a stricter first one.
8. Nothing an attacker controls may decide how often the shop writes a log line. Refusals are logged
   at the transition into the refused state, once per key per window.
