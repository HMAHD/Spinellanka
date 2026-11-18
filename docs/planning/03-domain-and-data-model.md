# 03 — Domain and data model

## Modeling rules

- Use relational tables and explicit foreign keys for core booking data.
- Avoid a single nullable mega-table and avoid entity-attribute-value storage for service details.
- Preserve every customer-visible proposal, policy acceptance, estimate, payment, and material operational change as a versioned snapshot or append-only event.
- Store money as integer minor units plus ISO currency; never use floating-point arithmetic.
- Business schedule fields are authoritative in `Asia/Colombo`. Sri Lanka has no daylight-saving transition; store local business `DATETIME` values with the fixed timezone invariant. Store infrastructure/audit timestamps in UTC.
- The traveler's local equivalent is presentation guidance and never becomes the schedule authority.
- Public references use non-sequential random identifiers; internal numeric IDs are not exposed as authorization.

## Core records

### Identity and access

- `users` — individual Owner/Admin and Staff accounts, status, MFA state, session/security metadata.
- `roles`, `permissions`, and assignments — capability-level authorization.
- `guest_access_tokens` — hashed random token, booking, purpose, expiry, revocation, last use.
- `driver_access_tokens` — hashed random token, assigned trip, expiry, revocation, last use.

Raw private tokens are shown only when issued. The database stores a cryptographic hash.

### Fleet

- `vehicles` — exact public identity, internal identifier, verified specs, capacities, eligible services, status, turnaround defaults.
- `vehicle_media` — approved image/file references and alt text.
- `drivers` — verified operational/public information and active state.
- `vehicle_driver_assignments` — effective-dated permanent tie and replacement history.
- `extras` and `extra_compatibility` — quantity/capacity, service and vehicle applicability, active dates.

### Booking aggregate

- `bookings` — public reference, service type, current state, source, assigned staff, selected vehicle, current proposal, deadlines, support flags.
- `booking_contacts` — booker and lead traveler/Rent driver snapshots with purpose and consent basis.
- `airport_pickup_details` — airport, flight, arrival, destination, meeting/name-board and waiting context.
- `airport_transfer_details` — origin, airport, flight, departure, recommended and chosen pickup times, warning acceptance.
- `ride_details` and `ride_stops` — ordered pickup/stops/final destination, per-stop waiting, and separately timed return legs.
- `rent_details` — pickup/return, handover locations, eligibility answers, expected distance, additional driver, and extension state.
- `booking_extras` — selected option, quantity, recorded label and price basis.
- `booking_policy_acceptances` — policy version, summary/hash, actor, channel, accepted time.

Service-specific detail tables keep required fields explicit and prevent invalid cross-service combinations.

### Availability

- `vehicle_schedule_blocks` — vehicle, kind, source, local start/end, preparation/turnaround boundary, blocking state, expiry, actor and reason.
- `booking_holds` — opaque hold token, booking draft/session, schedule block, expiry, release reason.

Schedule-block kinds include checkout hold, submitted request, proposal hold, confirmed booking, in-progress service/Rent, handover, return, buffer, maintenance, and manual operational block. Cancelled, declined, and expired records remain historical but no longer block.

Required indexes support `(vehicle_id, starts_at, ends_at, blocks_availability)`, active expiry scans, and source lookup.

### Pricing and proposals

- `pricing_rules` — effective-dated, service/vehicle-scoped calculation components and units.
- `estimates` and `estimate_items` — immutable pre-submission snapshot, USD total, approximate LKR rate/value, assumptions.
- `proposals` and `proposal_items` — immutable version, complete service snapshot, vehicle, schedule, terms, final amounts, deposit, payment choices, deadline, staff explanation.
- `proposal_changes` — field-level comparison to request/previous version.
- `proposal_responses` — accept/decline/expiry event and actor.

Editing a sent proposal creates a new version. A previously accepted or active booking is never silently mutated.

### Payments and documents

- `payment_transactions` — provider/offline reference, proposal, amount/currency, method, state, idempotency key, collector and evidence metadata.
- `payment_events` — verified provider callbacks with unique provider event ID and raw payload stored only as permitted.
- `refunds` — amount/currency, source transaction, reason/policy, approval and lifecycle.
- `financial_documents` — estimate, proposal, receipt and final invoice version/file metadata.

A transaction ledger is append-oriented. Corrections use compensating records rather than rewriting settled history.

### Messaging and integrations

- `outbox_events` — domain event, idempotency key, payload version, availability/attempt state.
- `message_deliveries` — channel, template version, recipient snapshot, provider reference, attempts and final state.
- `webhook_receipts` — provider, event ID, signature result, received/processed state and safe retained payload.

### Operations

- `handovers` — collection/return appointment and actual event, staff, document-check outcomes, signatures.
- `vehicle_condition_records` and `condition_attachments` — odometer, fuel, accessories, damage map/photos, comparison.
- `driver_trip_updates` — acknowledge/en-route/arrived/started/completed/issue events.
- `incidents` and `incident_actions` — type/severity, people, facts, attachments, financial impact, communication, owner and resolution.
- `audit_events` — actor, action, object, before/after summary, reason, channel, timestamp and security context.
- `data_requests` and `exports` — permitted scope, purpose, generated file, expiry, completion and audit.

## Lifecycle ownership

The booking state machine is explicit:

`Submitted → Under review → Proposal sent → Awaiting acceptance → Awaiting payment → Confirmed → In progress → Completed`

Terminal/alternate states are `Declined`, `Cancelled`, and `Expired`. A change request creates a pending revision branch while the accepted booking remains authoritative until the new proposal is accepted and any required payment condition passes.

Only application services may change lifecycle fields. Controllers, Livewire components, Filament actions, queue jobs, and webhook handlers call those services rather than updating state directly.

## Critical invariants

1. One vehicle cannot have overlapping blocking intervals unless a named permission, reason, and audit event authorize an operationally valid override.
2. A booking cannot be confirmed from an expired or superseded proposal.
3. A hosted payment return is not proof of settlement; verified provider state is authoritative.
4. A payment/refund event is processed once per provider idempotency identifier.
5. The exact vehicle's effective permanent driver is the expected chauffeured driver; substitution follows traveler approval rules.
6. Rent creates no driver assignment.
7. A manual booking enters the same schedule transaction as an online booking.
8. A failed notification does not roll back a committed booking/proposal/payment event.
9. Private and driver tokens reveal only their scoped record and no data after expiry/revocation.
10. Every price override, schedule override, policy exception, export, refund, handover, and incident change is attributable to an internal user.

## Retention boundary

Retention periods remain client/legal-owned values, but the schema separates classes so approved policies can be applied independently:

- contact and booking records;
- proposals/policy acceptance;
- payment/invoice records;
- handover/condition/incident evidence;
- audit/security events;
- message delivery metadata;
- expiring exports and private tokens.

No uploaded identity-document copy is included in phase one. Staff records that required originals were inspected.
