# 08 — Acceptance and Sign-off

**Working title:** Sri Lanka Travel Mobility — UX Planning Package  
**Revision:** Draft 2  
**Prepared:** 2026-11-23  
**Approval applies to:** requirements, information architecture, user flows, screen/state coverage, and low-fidelity wireframe structure—not final visual design or production implementation.

## 1. Approval options

Select one:

- ☐ **Approved:** the package accurately represents the intended product and may proceed to the next agreed phase.
- ☐ **Approved with recorded changes:** approval is conditional on the change table in section 8 being applied and rechecked.
- ☐ **Not approved:** the package requires another requirements/UX review before design or development.

## 2. Scope acknowledgment

The client confirms that this product is:

- ☐ A responsive web app for international travelers booking before arriving in Sri Lanka.
- ☐ No-login for customers, using required WhatsApp/mobile plus optional email.
- ☐ Advance booking only; not live/on-demand ride-hailing.
- ☐ Built around a current fleet of five exact selectable vehicles.
- ☐ Subject to real availability checks and staff confirmation.
- ☐ English-first, localization-ready, and targeted to WCAG 2.2 AA.

The client confirms the four service definitions:

- ☐ **Airport Pickup:** arrival airport to destination, driver included.
- ☐ **Airport Transfer:** origin to departure airport, driver included.
- ☐ **Ride:** scheduled multi-stop/return route, driver included.
- ☐ **Rent:** self-drive vehicle for a selected duration, driver not included.

## 3. Customer-flow acceptance

### 3.1 Search and availability

- ☐ Home asks the traveler to choose a service before seeing its tailored form.
- ☐ Locations support search, destination map/route preview, optional map pin, and written instructions.
- ☐ All service times are Sri Lanka time, with optional local-time guidance.
- ☐ Passenger/luggage/assistance requirements filter incompatible vehicles.
- ☐ Availability uses full time windows, buffers, manual bookings, maintenance, and holds.
- ☐ Customers choose one exact available vehicle.
- ☐ Vehicle selection starts a visible 15-minute checkout hold.
- ☐ No-result screens provide valid alternatives and staff assistance.
- ☐ Unpriceable routes become transparent custom-quote requests.

### 3.2 Service-specific behavior

- ☐ Airport Pickup collects flight/arrival, destination, meet-and-greet, passenger/luggage, and waiting/no-show context.
- ☐ Airport Transfer recommends pickup time from flight/route context and warns on later override.
- ☐ Ride supports ordered stops, waiting per stop, optional separately timed return legs, and a map of the selected destinations.
- ☐ During an active Ride, the traveler may explicitly enable current-device position on that map while the page is open; permission denial or location failure keeps the static route and itinerary usable, and Spinellanka does not transmit or store the position.
- ☐ Rent clearly states self-drive/no driver, uses 24-hour periods, collects eligibility context, and checks original documents at handover rather than uploading them.

### 3.3 Request, proposal, and payment

- ☐ Checkout shows an itemized USD estimate plus approximate LKR.
- ☐ Refundable deposits are separated from the service total.
- ☐ Submission says request received/pending—not confirmed.
- ☐ Staff may propose vehicle/time/route/extra/price changes.
- ☐ The traveler must accept the current proposal version.
- ☐ Staff/policy controls whether full payment, deposit, or pay later is available.
- ☐ Online card payment uses provider-hosted checkout.
- ☐ Proposals/payment holds have visible deadlines and release availability on expiry.

### 3.4 Guest management and support

- ☐ A secure private link replaces a customer account.
- ☐ The private page shows status, next action, details, proposal, payment, documents, changes/cancellation, and support.
- ☐ The original confirmed booking remains active while a new change proposal is pending.
- ☐ Automatic WhatsApp and optional email failures do not invalidate the booking.
- ☐ Contextual phone/WhatsApp support remains available.
- ☐ Post-service feedback is private unless publication consent and moderation are present.

## 4. Operations-flow acceptance

- ☐ Owner/Admin and Staff use individual MFA-protected accounts with role permissions.
- ☐ Dashboard prioritizes operational work, not decorative analytics.
- ☐ Booking lifecycle includes review, proposal/acceptance, payment, confirmation, in-progress, completion, decline, cancel, and expiry.
- ☐ Online and manually received bookings share one fleet schedule.
- ☐ Unified calendar represents five exact vehicles, permanent tied drivers, holds, pending, confirmed, Rent, handovers, buffers, and maintenance.
- ☐ Staff can create versioned proposals and audited overrides.
- ☐ Online and offline payments/refunds share one history.
- ☐ The exact vehicle’s permanent driver uses a limited secure trip link for chauffeured service.
- ☐ Rent uses mobile collection/return condition checklists and original-document inspection.
- ☐ Vehicle substitutions and Rent extensions require traveler/staff approval as specified.
- ☐ Incidents, exports, sensitive changes, and configuration are permissioned and audited.

## 5. Quality acceptance

- ☐ Customer flows are wireframed for mobile and desktop.
- ☐ Full admin is represented for desktop; critical staff/handover/driver actions are represented for mobile.
- ☐ Required loading, empty, no-result, error, stale, expired, failure, and recovery states are documented.
- ☐ WCAG 2.2 AA is the stated design/development target.
- ☐ Slow/interrupted connections preserve safe progress but cannot bypass live availability/payment checks.
- ☐ Required consent is separated from optional marketing consent.
- ☐ Private/admin/driver/payment areas are non-indexable and appropriately protected.
- ☐ Public trust claims and imagery must be client-supplied and verifiable.

## 6. Explicitly deferred / out of current approval

The client acknowledges that approval of this package does **not** approve or include:

- ☐ Final product name, logo, brand identity, palette, typography, or photography direction.
- ☐ High-fidelity UI or final copy.
- ☐ Production code, architecture, data model, hosting, estimate, or delivery schedule.
- ☐ Named map, payment, WhatsApp, email, exchange-rate, or infrastructure vendors.
- ☐ Native applications.
- ☐ Live ride dispatch, live vehicle tracking, or automatic flight tracking.
- ☐ Customer accounts, loyalty, referrals, promo-code campaigns, or digital tipping.
- ☐ Accounting integration or advanced analytics dashboard.
- ☐ Final prices, policy values, legal wording, or unverified commercial claims.

## 7. Decision register

This register summarizes confirmed discovery decisions.

### Product and audience

| Decision | Confirmed outcome |
|---|---|
| Audience | International travelers, including third-party bookers |
| Platform | Mobile-first responsive web; desktop included; no native app |
| Customer identity | Guest booking, no account/OTP; WhatsApp required, email optional |
| Language | English first; localization-ready |
| Coverage | Sri Lanka-wide requests subject to real availability and staff review |
| Scheduling | All four services are advance booked; no live/on-demand behavior |

### Services

| Decision | Confirmed outcome |
|---|---|
| Airport Pickup | Arrival airport → destination; driver; flight/meet-and-greet context |
| Airport Transfer | Origin → departure airport; driver; guided pickup time |
| Ride | Chauffeured route, multiple ordered destinations, timed waiting, optional return |
| Rent | Exact self-drive vehicle; no driver; 24-hour periods |
| Exact vehicle | Customer chooses one of the actual five vehicles |
| Permanent driver | Tied to exact vehicle for chauffeured service |

### Availability and checkout

| Decision | Confirmed outcome |
|---|---|
| Availability | Time-window schedule with buffers, blocks, manual bookings, and conflicts |
| Suitability | Hard passenger/luggage/accessibility/feature filters |
| Checkout hold | 15 minutes after exact-vehicle selection |
| Location | Search + destination map/route preview + optional pin + instructions; Ride may show permission-gated current-device position locally while its page is open, with no server transmission/storage |
| Time zone | Sri Lanka time; optional traveler local equivalent |
| Price | Itemized estimate before submission; staff confirms final |
| Currency | USD primary + approximate LKR |
| Status after submit | Pending staff confirmation |
| No availability | Valid alternatives + staff assistance |
| Calculator failure | Preserve data and request custom quote |

### Proposal, payment, and guest control

| Decision | Confirmed outcome |
|---|---|
| Staff changes | Versioned proposal requiring customer acceptance |
| Payment choices | Full/deposit/pay later controlled per booking/policy |
| Online card UX | Provider-neutral hosted checkout |
| Offline payments | Recorded with online payments in one history |
| Deadline | Visible configurable expiry that releases vehicle slot |
| Guest management | Secure private link and reference; no login |
| Original during change | Remains active until revision accepted |
| Financial documents | Estimate/proposal, receipt, final invoice |
| Cancellation | Configurable per service |

### Operations

| Decision | Confirmed outcome |
|---|---|
| Internal roles | Owner/Admin and Staff; individual accounts + MFA |
| Dashboard | Compact operations dashboard, no advanced analytics |
| Calendar | Unified five-vehicle day/week/month + mobile agenda |
| Manual bookings | Included in same availability schedule |
| Messaging | Automatic WhatsApp + email when supplied, with delivery/retry state |
| Driver access | Lightweight secure mobile trip link |
| Rent verification | Original documents checked at handover; no upload |
| Rent handover | Digital condition/check/signature workflow |
| Incident handling | Urgent support + access-controlled incident record |
| Audit/export | Full material audit + permissioned CSV export |

### Quality and boundaries

| Decision | Confirmed outcome |
|---|---|
| Accessibility | WCAG 2.2 AA target |
| Connectivity | Slow-network resilient; live validation still required |
| Privacy | Minimal, purpose-specific collection/consent; policy-driven retention |
| Integrations | Capability requirements only; vendor selection deferred |
| Trust | Verified client-supplied claims and imagery only |
| Current visual fidelity | Neutral grayscale wireframes with placeholders |
| Deliverable | Markdown package + standalone visual walkthrough |

## 8. Requested-change table

Only items entered here modify this draft.

| Change ID | Request | Reason | Affected requirement/screen IDs | Owner | Decision | Applied in revision |
|---|---|---|---|---|---|---|
| CHG-001 | Add a selected-destination map to Ride and an optional, permission-gated current-device marker while the active Ride page is open; no location reaches Spinellanka servers or storage | Give the traveler local visual progress without adding dispatch or vehicle tracking | Sections 3.1, 3.2, 6, 7; Ride booking and active guest Ride map | Project lead / product owner, 2026-11-23 | Approved as browser-local only; privacy copy remains an owner-approved launch input | Draft 2 |
| CHG-002 | Replace Inter with self-hosted Allo 1.0.3 under SIL OFL 1.1 across the public/customer design system | Adopt the requested friendly screen-optimized type family without adding a second font or third-party runtime request | Production design direction, public shell, customer forms/status surfaces, DESIGN.md and Impeccable records when shipped | Project lead / design owner, 2026-11-23 | Approved; implementation must include license, fallback, performance, and rendered-layout verification | Draft 2 |
| CHG-003 | Record staging-only synthetic fixtures allowance (`staging_synthetic` configuration packages, synthetic 5-vehicle/rate/driver fixtures, and synthetic placeholder media markers) for Phase 16 reconciliation and end-to-end staging rehearsal while keeping production configuration export and live promotion blocked until real client inputs/imagery are supplied | Unblock Phase 16 reproducible configuration import/rollback testing and four synthetic E2E staging journeys without fabricating client-approved production claims or real imagery | `PRODUCT.md`, `DESIGN.md`, `docs/ux/07-client-input-checklist.md`, Phase 16 staging fixtures & configuration transfer | Project lead / release integrator, 2026-11-27 | Approved for staging rehearsal only (`!app()->isProduction()`); production export remains strictly blocked (`production_approved` rejects synthetic markers) | Draft 2 (Phase 16) |

## 9. Client-input ownership

The client acknowledges that the [client input checklist](07-client-input-checklist.md) contains required real-world values and assets.

- ☐ Every critical input has an owner.
- ☐ Legal/insurance/rental-policy inputs have an authorized reviewer.
- ☐ Fleet and driver data will be verified before final UI or launch.
- ☐ Price examples will be supplied for rule testing.
- ☐ No competitor/reference content will be copied without separate rights and approval.

## 10. Acceptance criteria for next phase

The package may proceed to high-fidelity UI when:

1. sections 2–5 are approved;
2. every accepted change in section 8 is applied;
3. actual brand direction or an explicit branding brief is available;
4. real five-vehicle data and representative imagery are supplied; and
5. unresolved values that affect visible content are identified with owners.

The product may proceed to development planning when:

1. UX scope is approved;
2. pricing, availability, eligibility, payment, cancellation, waiting/no-show, and Rent policy values are approved;
3. legal/privacy/retention requirements have an authorized owner;
4. implementation/vendor decisions are explicitly opened as a new phase; and
5. acceptance tests are derived from requirement and screen IDs.

## 11. Sign-off record

### Client

- **Name:** ______________________________________________
- **Role:** _______________________________________________
- **Decision:** Approved / Approved with changes / Not approved
- **Signature or recorded approval:** ______________________
- **Date:** _______________________________________________
- **Revision approved:** __________________________________

### Delivery/product representative

- **Name:** ______________________________________________
- **Role:** _______________________________________________
- **Signature or recorded approval:** ______________________
- **Date:** _______________________________________________

### Operations representative

- **Name:** ______________________________________________
- **Role:** _______________________________________________
- **Signature or recorded approval:** ______________________
- **Date:** _______________________________________________

### Legal/finance acknowledgment for later inputs

This is not approval of final legal or financial wording. It confirms ownership of those inputs.

- **Name/role:** __________________________________________
- **Date:** _______________________________________________

## 12. Revision history

| Revision | Date | Summary | Approved |
|---|---|---|---|
| Draft 1 | 2026-11-16 | Initial decision-complete UX planning package | Pending |
