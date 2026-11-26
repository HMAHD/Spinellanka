# EGM Phase 12 Concepts Brief: Versioned Proposals & Private Guest Control

## 1. Problem Statement & Context
Spinellanka handles advance-booked chauffeured travel (Airport Pickup, Airport Transfer, Ride) and self-drive rentals (Rent) across an exact fleet of five vehicles. A submitted booking request is not immediately confirmed; staff must review the request, verify availability and route feasibility, and when needed, issue an authoritative proposal offering adjustments (e.g. safer airport departure time, exact vehicle substitution, custom pricing adjustments, or deposit requirements).

Furthermore, once a booking is confirmed, travel plans can evolve. A guest may request a change or cancellation, or staff may need to propose an adjustment. During any revision, the previously confirmed booking must remain active and authoritative until the new proposal is explicitly accepted and any required payment terms are met.

Access for travelers is strictly governed by high-entropy, expiring, revocable private links—there are no customer accounts, passwords, or OTPs.

## 2. Core Architectural Pillars

### Pillar A: Immutable Proposal Versioning & Snapshotting
- A proposal is never mutated in place once sent.
- Each proposal belongs to a booking, has an explicit sequential version number (`v1`, `v2`, ...), an opaque reference, itemized pricing components, approved payment options (`full`, `deposit`, `pay_later`), a visible deadline, and a full immutable snapshot of the offered service.
- The `Booking` model points to `current_proposal_id`. Only the current, unexpired proposal in `sent` status can be accepted or declined.
- When staff revises an offer, a new proposal version is created in `draft` or `sent` status, marking the previous version `superseded`.

### Pillar B: Accepted-Booking Preservation During Revisions
- When an already confirmed booking enters revision (due to a change request or staff amendment), the booking's confirmed status and schedule blocks remain active.
- The revision proceeds on a parallel proposal track.
- If the new proposal is declined or expires, the original confirmed booking remains intact.
- If the new proposal is accepted, the booking transitions to the new proposed state, updating schedule blocks and pricing atomically.

### Pillar C: Proposal Schedule Holds & Expiry
- An active proposal offering a vehicle window holds the vehicle via `ScheduleBlockKind::ProposalHold` with `expires_at = $proposal->expires_at`.
- When a proposal expires (via scheduler or on access), the proposal hold is safely released without affecting any existing confirmed booking schedule blocks.

### Pillar D: Private Guest Control Surface & Token Security
- Travelers access `/booking/status/{token}`.
- Tokens are 256-bit cryptographically secure random values; only SHA-256 hashes are persisted in the database.
- Private links support token rotation (revoking old token, issuing new token), scoped purpose, expiry, and neutral error handling (`noindex, nofollow`, `Referrer-Policy: no-referrer`, identical response for non-existent, expired, or revoked tokens).
- Accessible native `<dialog>` overlays allow travelers to accept, decline, request changes, cancel, or request Rent extensions without leaving the private surface.

### Pillar E: Payment Boundary (Phase 12 Scope Boundary)
- If a proposal requires online card payment (`full` or `deposit`), traveler acceptance advances the booking to `AwaitingPayment`.
- Actual hosted checkout session creation, webhook settlement, and transaction ledger recording are strictly deferred to Phase 13.
- If a proposal allows `pay_later`, acceptance confirms the booking directly.

