# EGM Phase 14 Concepts Brief: Operations Calendar & Driver Trip Access

## 1. Problem Statement & Context
Spinellanka operates a boutique five-vehicle fleet in Sri Lanka providing four advance-booked services: Airport Pickup, Airport Transfer, Ride, and Rent. Following Phases 12 and 13, customer proposals and payments are managed through private guest links with immutable ledgers.

Operations staff now require an authoritative, unified scheduling and dispatch command surface. Concurrently, chauffeured drivers (who do not have user accounts or native mobile apps) require secure, ephemeral mobile trip access to execute confirmed journeys, contact international travelers on the service day, and report execution milestones without compromising sensitive customer pricing or internal operational records.

---

## 2. Core Architectural Pillars

### Pillar A: Authoritative Schedule Projections (Zero Duplicate Store)
- `VehicleScheduleBlock` remains the **single authoritative store** for availability and schedules.
- No second calendar database or cache is introduced.
- The projection engine (`OperationsScheduleProjectionService`) projects day, week, month, and mobile agenda views from `VehicleScheduleBlock` using `Asia/Colombo` date boundaries.
- **Accessibility & Non-Color Discrimination**: Every schedule block kind and status is distinguished by text badges, semantic icons, and structural borders—never color alone (WCAG 2.2 AA).

### Pillar B: Operations Dashboard Alerts
- Real-time aggregation of operational friction:
  1. **Schedule Conflicts**: Overlapping blocking blocks on any vehicle.
  2. **Imminent Deadlines**: Checkout holds expiring (< 5 min), proposal deadlines expiring (< 2h).
  3. **Stale Requests**: Unreviewed customer submissions (> 24h).
  4. **Unassigned Chauffeured Trips**: Confirmed bookings within 48h lacking an assigned driver.
  5. **Payment Friction**: Upcoming trips with uncollected balances or pending offline payments.
  6. **Failed Deliveries**: Outbox messages in failed state.

### Pillar C: Effective Tied-Driver Resolution & Replacement Governance
- Each vehicle maintains a historical and effective permanent tied driver via `vehicle_driver_assignments`.
- For chauffeured bookings (Airport Pickup, Airport Transfer, Ride), the system automatically resolves the vehicle's permanent tied driver for the service date.
- **Permissioned Replacement**: If the tied driver is unavailable, authorized staff (`booking.review` or `booking.confirm`) can assign an approved replacement driver.
- Replacement requires mandatory recorded `replacement_reason`, user attribution, and audit logging.
- Traveler assurance: The guest status page displays the active verified driver's name and contact, keeping fulfillment transparent.

### Pillar D: Separate Hashed Driver Trip Tokens & Privacy Shell
- Drivers use a lightweight, mobile-optimized private link (`/driver/trip/{token}`).
- **Cryptographic Security**: Tokens use $\ge$ 256 bits of entropy (`random_bytes(32)`), stored strictly as a SHA-256 hash in `driver_access_tokens`.
- **Absolute Separation**: Driver tokens are distinct from guest access tokens and never grant guest or administrative capabilities.
- **Strict Data Allowlist**: Drivers see only operational data (pickup time, locations, flight number, passenger/luggage count, lead traveler first name and phone/WhatsApp). They NEVER see prices, currencies, payment balances, deposits, discounts, internal staff notes, or other bookings.
- **Invalid-Link Privacy Shell**: Invalid, expired, or revoked tokens return a neutral shell with `noindex, nofollow` and `Referrer-Policy: no-referrer`, revealing zero trip existence or customer PII.
- **Token Lifecycle**: Automatically expires 48 hours post-trip; revoked and rotated immediately upon driver reassignment.

### Pillar E: Monotonic & Idempotent Driver Milestones
- Allowed driver status sequence:
  `Assigned` $\rightarrow$ `Acknowledged` $\rightarrow$ `En Route` $\rightarrow$ `Arrived at Pickup` $\rightarrow$ `Trip Started` $\rightarrow$ `Trip Completed`
  (or `Issue Reported` at any point prior to completion).
- State transitions are strictly monotonic and idempotent: duplicate submissions or retries return success without corrupting state.
- Offline resilience: Mobile action queuing handles intermittent cellular connectivity without allowing stale actions to bypass server rules.
- Completing a trip triggers `BookingLifecycleService::complete()`, advancing booking state and queuing post-trip review invitations via outbox.

### Pillar F: Filament Operations Calendar & Staff Controls
- Unified Operations Calendar in Filament with Month, Week, Day, and Mobile Agenda viewports.
- Integrated Driver Assignment action on confirmed bookings with one-click link generation, rotation, and revocation.
- Audited actions and transactional outbox events: `driver.assigned`, `driver.trip_updated`, `driver.issue_reported`.

