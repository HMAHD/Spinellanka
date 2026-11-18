# Sri Lanka Travel Mobility — UX Planning Package

**Status:** Client-review draft 1  
**Scope:** Requirements and low-fidelity UX only  
**Working title:** Placeholder until the client supplies the product name  
**Last updated:** 2026-11-16

This package defines a no-login transport booking web app for international travelers arranging services in Sri Lanka. It is intended to secure client approval of scope, flow, information, and operational behavior **before** high-fidelity UI design or development begins.

## Start here

1. Open the [visual wireframe walkthrough](wireframes/index.html) in a browser for the client review.
2. Use the [printable wireframe PDF](wireframes/sri-lanka-travel-mobility-wireframes.pdf) for offline review or sign-off meetings.
3. Read the [product requirements](docs/ux/01-product-requirements.md) for agreed behavior and scope.
4. Review the [information architecture](docs/ux/02-information-architecture.md) and [user flows](docs/ux/03-user-flows.md).
5. Use the [screen and state inventory](docs/ux/04-screen-and-state-inventory.md) as the design/development coverage checklist.
6. Use the [wireframe annotations](docs/ux/05-wireframe-annotations.md) alongside the visual walkthrough.
7. Validate staff processes against the [admin operations specification](docs/ux/06-admin-operations.md).
8. Ask the client to complete the [content and business-input checklist](docs/ux/07-client-input-checklist.md).
9. Record approval with the [acceptance and sign-off checklist](docs/ux/08-acceptance-and-signoff.md).

The [documentation index](docs/ux/README.md) explains the purpose and authority of each file.

## Product in one paragraph

International travelers choose one of four advance-booked services—Airport Pickup, Airport Transfer, Ride, or self-drive Rent—enter service-specific details, see exact suitable vehicles from a five-vehicle fleet, check real schedule availability, review an itemized estimate, and submit without creating an account. Staff reviews every request, may propose changes, and confirms it through a private booking link with automatic WhatsApp and optional email notifications. The same schedule powers online availability, staff-entered bookings, maintenance blocks, vehicle handovers, and permanent driver assignments.

## Four services

| Service | Includes driver | Booking basis | Defining information |
|---|---:|---|---|
| Airport Pickup | Yes | Arrival journey | Airport, flight, destination, meet-and-greet, passengers/luggage |
| Airport Transfer | Yes | Departure journey | Origin, departure flight, recommended pickup time, passengers/luggage |
| Ride | Yes | Route/legs | Pickup, ordered stops with waiting, destination, optional return leg |
| Rent | No | Time | Exact self-drive vehicle, 24-hour periods, handover/return, eligibility |

## Important interpretation rules

- These are **not** Uber-style live rides. Every service is arranged in advance.
- The customer chooses an **exact vehicle**, not merely a class.
- Online availability is real, but submission remains **pending staff confirmation**.
- “Rent” means **self-drive vehicle hire without a driver**.
- Customer login and registration are intentionally excluded.
- The visual walkthrough is a **grayscale structural wireframe**, not an approved brand or final UI.
- Any bracketed or labeled value is a placeholder until the client supplies verified information.

## Reference boundaries

The following pages were supplied to understand airport-booking features only:

- https://less.lk/best-taxis-from-colombo-airport
- https://less.lk/airport_transfer

Their UI, UX, visual design, claims, and content are **not** design authority for this project.

## Deferred until after UX approval

- Final product name and brand identity
- High-fidelity visual design
- Production frontend/backend implementation
- Technical architecture and implementation estimates
- Named map, payment, WhatsApp, email, or exchange-rate vendors
- Client-approved legal wording, rates, policies, and tax rules
- Native applications
- Live vehicle tracking and automatic flight tracking
- Loyalty, referrals, customer promo codes, and digital tipping
- Accounting integration and advanced analytics dashboards

## File authority

If two files appear to conflict:

1. Client-approved changes recorded in [08-acceptance-and-signoff.md](docs/ux/08-acceptance-and-signoff.md) take priority.
2. [01-product-requirements.md](docs/ux/01-product-requirements.md) is the functional source of truth.
3. [04-screen-and-state-inventory.md](docs/ux/04-screen-and-state-inventory.md) controls coverage.
4. The visual walkthrough illustrates the requirements but must not silently add functionality.

No production implementation should begin until unresolved client-input items that affect pricing, legality, availability, or fulfillment are supplied and signed off.
