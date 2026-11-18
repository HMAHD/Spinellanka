# 02 — System architecture

## Shape

Spinellanka is one deployable Laravel application organized as a modular monolith. Modules exchange typed application commands/events inside the process and share one transactional database.

```text
Traveler browser       Staff browser       Driver/Rent field link
       |                     |                       |
       +---------------------+-----------------------+
                             |
                     Apache + PHP runtime
                             |
                    Laravel application
   +-------------+-----------+-----------+-------------+
   |             |           |           |             |
Booking      Availability  Pricing    Operations   Identity/access
   |             |           |           |             |
   +-------------+-----------+-----------+-------------+
                             |
                       MariaDB/MySQL
                             |
                 database queue + scheduler
                             |
        +-----------+--------+---------+-----------+
        |           |                  |           |
      Maps       WhatsApp/email     Payments    Documents/storage
```

There is no public API, microservice, or separate admin backend at launch. Integration adapters sit behind application interfaces so provider changes do not alter booking rules.

## Module boundaries

### Catalog

Owns the five exact vehicles, verified public facts, service eligibility, permanent-driver tie, extras, support locations, and active/maintenance status.

### Booking

Owns service requests, booker/lead-traveler snapshots, service-specific details, lifecycle, change/cancellation requests, proposal versions, acceptance, and final confirmation.

### Availability

Owns checkout holds, booking windows, preparation/turnaround buffers, maintenance/operational blocks, handover windows, overlap checks, expiry, and authorized conflict decisions.

### Pricing

Owns effective-dated pricing rules, itemized estimate snapshots, USD/LKR presentation basis, staff adjustments, refundable deposits, final proposal totals, and explanation/audit of overrides.

### Payments

Owns allowed payment choices, hosted-checkout sessions, online/offline transaction records, provider events, balance, refund records, receipts, and reconciliation states. It never stores raw card data.

### Messaging

Owns notification events, template versions, WhatsApp/email deliveries, retries, failure visibility, and message history. A failed message never invalidates a committed booking action.

### Operations

Owns staff inbox/query models, assignments, handovers, driver trip status, incidents, documents, exports, and operational dashboards.

### Identity and access

Owns staff accounts/MFA, roles/permissions, private guest access tokens, driver tokens, session revocation, and rate limits.

### Audit and privacy

Owns append-only material audit events, retention jobs, data requests, export expiry, and anonymization/deletion rules approved by the business.

## Primary request path

1. The traveler enters service-specific criteria in Sri Lanka time.
2. The application validates route/period, passengers, luggage, assistance, and eligibility context.
3. Availability serializes the selected vehicle check inside a database transaction.
4. Pricing creates an immutable itemized estimate snapshot.
5. Exact-vehicle selection creates a blocking 15-minute schedule hold.
6. Submission converts the hold into a submitted request/blocking state atomically and issues a private access link.
7. An outbox event queues WhatsApp/email and alerts staff.
8. Staff reviews the same schedule authority, then sends a versioned proposal.
9. Traveler acceptance and any required payment produce confirmation only after current proposal, deadline, availability, and payment conditions pass.

## Exact-vehicle concurrency

Only five vehicles exist, so correctness is more valuable than speculative throughput.

For every write that can block a vehicle:

1. begin a database transaction;
2. lock the selected vehicle row with `SELECT ... FOR UPDATE` semantics;
3. expire stale holds for that vehicle;
4. query all blocking intervals that overlap the requested interval;
5. reject, propose another option, or record a permissioned override;
6. insert/update the authoritative schedule block;
7. commit.

This serializes competing writes per vehicle and prevents two requests from confirming the same window without requiring Redis or distributed locks. Reads may use indexed overlap queries; final selection and confirmation always repeat the locked check.

## Transactional outbox

Booking/payment state and a corresponding outbox event are committed in the same database transaction. Queue workers dispatch the event afterward. This prevents a successful booking from disappearing because WhatsApp, email, or a provider is temporarily unavailable.

Every integration command has:

- a stable idempotency key;
- attempt count and last error;
- retry policy tied to a named failure mode;
- final failed state visible to staff;
- no automatic duplicate customer charge or duplicate proposal.

## cPanel background execution

No resident worker is assumed. cPanel cron starts short-lived queue workers that drain available database jobs and exit before the next scheduled run. A non-overlap lock prevents duplicate cron execution. The scheduler also expires holds and proposals, sends reminders, performs retention work, and checks backups.

At launch, tasks requiring sub-minute response stay synchronous only when they are local and fast. External calls are queued unless their result is required to complete a hosted handoff. The on-screen reference/private link is always the booking recovery path.

## Integration contracts

Provider selection is intentionally isolated behind these capabilities:

- `PlaceSearch` and `RouteEstimate`;
- `HostedPayment` and `PaymentWebhookVerifier`;
- `WhatsAppSender`;
- `TransactionalMailer`;
- `ExchangeRateSource`;
- `ErrorReporter`.

The launch recommendation is Google Maps Platform for place/route quality, Meta WhatsApp Cloud API for direct messaging, Postmark for transactional email, and a hosted Sri Lankan payment gateway approved by the client's merchant bank. Payment provider selection is a finance-owned procurement gate because settlement currency, refunds, deposits, merchant eligibility, and cPanel origin/webhook requirements must be verified with the actual account.

The initial LKR approximation may use an audited admin-entered rate with value, source note, effective time, and actor. This avoids making booking availability depend on an exchange-rate API.

## Degradation rules

| Failure | Safe behavior |
|---|---|
| Maps/place API unavailable | Preserve entered text/pin; allow staff-reviewed custom quote when route cannot be priced safely |
| Pricing calculation unavailable | Remove estimate, preserve criteria, submit transparent custom-quote request |
| WhatsApp/email unavailable | Commit booking action, show reference/private link, queue retry, alert staff |
| Payment callback delayed | Show pending; reconcile idempotently; never prompt an automatic duplicate charge |
| Queue cron delayed | Persist outbox jobs; dashboard shows aging critical deliveries |
| Database unavailable | Fail closed for availability, booking, payment, and private-link changes |
| Driver link invalid/revoked | Reveal no trip data and route to verified dispatch contact |

## Scale and rollback

The first pressure point at 10× expected load is external API/queue latency, not PHP rendering or the five-row availability lock. Index schedule intervals, booking status/deadlines, message state, and provider event IDs before launch. Move queue/cache to Redis only after measured database-queue contention.

Application releases are rollbackable by switching the current release pointer and restoring the previous cached configuration. Database migrations must be backward-compatible for one release; destructive column/table removal is deferred to a later cleanup release after rollback risk has passed.
