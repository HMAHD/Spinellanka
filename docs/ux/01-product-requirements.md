# 01 — Product Requirements

**Document status:** Client-review draft 1  
**Working title:** Sri Lanka Travel Mobility  
**Product phase:** UX definition before visual design and development

## 1. Purpose

Define the behavior, scope, terminology, roles, and quality bar for a guest transport-booking web app serving international travelers in Sri Lanka.

This document is the functional source of truth for the low-fidelity wireframes. Values that depend on the client’s actual business—vehicle records, prices, policy thresholds, legal wording, proof, and brand content—are intentionally marked as client inputs rather than invented.

## 2. Goals

1. Let an international traveler distinguish four services without local knowledge.
2. Let the traveler check real schedule availability and choose one exact suitable vehicle.
3. Let the traveler submit a complete request without creating an account.
4. Let staff safely review, revise, price, confirm, fulfill, and audit every booking.
5. Keep one reliable schedule for website requests, staff-entered bookings, maintenance, handovers, and preparation buffers.
6. Make pricing, currency, terms, confirmation status, and next steps explicit.
7. Give guests a secure self-service booking page without account complexity.
8. Produce a structure that can later receive an approved brand and high-fidelity UI without redesigning the workflows.

## 3. Non-goals for this phase

- Production software or technical architecture
- A final brand, logo, palette, typography, photography direction, or marketing campaign
- Native iOS or Android apps
- Live on-demand dispatch, nearby-driver discovery, or moving-map vehicle tracking
- Automatic flight-status tracking
- Customer registration, login, profile, saved payment method, or booking history
- Customer promo codes, referrals, loyalty points, membership, or digital driver tips
- Direct accounting-system integration
- Advanced business-intelligence dashboards
- Final legal advice or client policy decisions

## 4. Users and roles

### 4.1 Guest booker

An international traveler—or a person arranging travel for someone else—using the website before arriving in Sri Lanka. The booker does not create an account.

**Needs:** understand services, enter locations remotely, compare exact vehicles, see price context, receive reliable confirmation, pay securely, and manage changes across time zones.

### 4.2 Lead traveler

The passenger for a chauffeured service or primary driver for Rent. They may be the booker or a different person.

### 4.3 Owner/Admin

Authenticated internal user with full control over staff, fleet, drivers, rates, policies, booking rules, business settings, exports, and operations.

### 4.4 Staff

Authenticated internal user who reviews bookings, manages schedule and communications, records payments, performs handovers, and logs incidents. Access to sensitive configuration and user management is restricted.

### 4.5 Driver

The permanent driver tied to an exact fleet vehicle for chauffeured services. The driver receives a limited secure mobile trip link; they do not use the customer site or a full driver portal.

## 5. Service definitions

### 5.1 Airport Pickup

A pre-booked chauffeured arrival journey from a supported Sri Lankan airport to one destination.

**Defining inputs:** arrival airport, flight number, arrival date/time, destination, passengers, luggage, meet-and-greet name, and special requirements.

**Operational behavior:** staff monitors the flight manually. Configurable included waiting, paid waiting, delay, contact, and no-show policies apply.

### 5.2 Airport Transfer

A pre-booked chauffeured departure journey from a selected origin to a supported Sri Lankan airport.

**Defining inputs:** pickup location, departure airport, flight number/date/time, passengers, luggage, and special requirements.

**Operational behavior:** the site recommends a pickup time using route duration plus an airport-arrival buffer. The traveler may adjust it; late choices receive a warning and require staff review.

### 5.3 Ride

A pre-booked chauffeured route with pickup, one or more ordered destinations/stops, and an optional separately timed return leg.

**Defining inputs:** pickup, ordered stops, expected wait per stop, final destination, optional return details, date/time, passengers, luggage, and special requirements.

**Operational behavior:** outbound and return legs reserve the exact vehicle only for their separate service windows plus buffers unless planned waiting is added. Later route changes are priced change requests.

### 5.4 Rent

A pre-booked self-drive hire of one exact vehicle for a selected period. **No driver is included.**

**Defining inputs:** pickup/return locations and times, driver age, nationality, licence-issuing country, expected distance, handover options, extras, and eligibility declarations.

**Operational behavior:** duration is calculated in exact 24-hour periods with configured extra-hour/late-return rules. Original passport, licence, and any required permit are inspected at handover; phase one has no document upload.

## 6. Core product rules

- Every service is booked in advance. There is no “Ride now” behavior.
- All booking times are entered, displayed, and stored as Sri Lanka time.
- WhatsApp/mobile with international country code is required. Email is optional.
- The website may show the traveler’s local-time equivalent as guidance, never as the stored booking time.
- The customer chooses an exact vehicle from the current five-vehicle fleet.
- Passenger, luggage, accessibility, and required-feature limits are hard suitability rules.
- A vehicle cannot be offered when its complete requested window plus buffers overlaps a booking, hold, maintenance block, handover, or manual block.
- A chosen vehicle receives a 15-minute checkout hold.
- A submitted booking is pending staff review even when online availability succeeded.
- Staff may propose a changed time, route, vehicle, extras, or price; the traveler must accept the revision.
- The original confirmed booking remains valid until a replacement proposal is accepted.
- Any post-confirmation vehicle substitution requires traveler approval.
- Booking limits, buffers, terms, and pricing rules are configurable by authorized admins.

## 7. Functional requirements

### 7.1 Public information and navigation

- **PUB-001:** Home must identify the product as an advance-booking service, not live ride-hailing.
- **PUB-002:** Home must present Airport Pickup, Airport Transfer, Ride, and Rent as four distinct choices before opening a tailored form.
- **PUB-003:** Each service page must explain what is included, required information, confirmation process, pricing basis, relevant policy, and next step.
- **PUB-004:** The public site must include Fleet, How it works, About, Contact/Support, FAQ, international traveler guidance, and required legal/policy pages.
- **PUB-005:** Public claims, reviews, vehicle images, driver images, licences, insurance statements, and safety claims must be client-supplied and verifiable.
- **PUB-006:** Public pages must support basic technical/content SEO without weakening privacy consent requirements.
- **PUB-007:** Persistent support access must offer phone and WhatsApp; after submission it must include booking context automatically.

### 7.2 Service search forms

- **SRCH-001:** Forms must request only fields relevant to the chosen service.
- **SRCH-002:** Location entry must support place/address search, an optional map pin, and written instructions.
- **SRCH-003:** Supported airports, handover points, and service locations must be admin-configurable.
- **SRCH-004:** Dates and times must be labeled Sri Lanka time at entry and review.
- **SRCH-005:** Forms must collect adults/children as needed, luggage quantity/type, structured accessibility/assistance needs, service-compatible extras, and free-text special requests.
- **SRCH-006:** A traveler must be able to distinguish the booker from the lead passenger or Rent driver.
- **SRCH-007:** Minimum notice and maximum advance-booking horizon must be configurable per service.
- **SRCH-008:** Requests inside the online booking cutoff must become staff-assisted enquiries rather than appear automatically bookable.
- **SRCH-009:** Long forms must preserve non-sensitive progress locally through ordinary interruptions and must revalidate all live values before submission.

### 7.3 Airport Pickup

- **APU-001:** Collect supported arrival airport, flight number, scheduled arrival date/time, destination, passengers, luggage, name-board text, and special needs.
- **APU-002:** Do not require an automated flight-data integration in phase one.
- **APU-003:** Show the configured included-waiting period, extra-wait rate/rule, delay behavior, contact requirement, and no-show terms before submission.
- **APU-004:** Staff must be able to adjust operational pickup timing after manually monitoring the flight and notify affected parties.

### 7.4 Airport Transfer

- **ATR-001:** Collect pickup location/instructions, airport, departure flight number/date/time, passengers, luggage, and special needs.
- **ATR-002:** Calculate a recommended pickup time from estimated route duration, configured airport-arrival buffer, and operational buffer.
- **ATR-003:** Let the traveler choose a different time, show a clear risk warning when later than recommended, and flag it for staff review.
- **ATR-004:** The final confirmed pickup time must appear prominently in all confirmations and driver details.

### 7.5 Ride

- **RID-001:** Let the traveler add, remove, and reorder intermediate destinations.
- **RID-002:** Require an expected wait duration for each stop when waiting is part of the service.
- **RID-003:** Update route, duration, availability, and estimate after itinerary changes.
- **RID-004:** Support an optional return as a separately timed leg within one booking.
- **RID-005:** Outbound and return legs must create separate fleet windows unless the customer adds paid waiting/continuous service.
- **RID-006:** A confirmed itinerary change must become a reviewable change request and may alter price and availability.

### 7.6 Rent

- **RNT-001:** State clearly that Rent is self-drive and excludes a driver.
- **RNT-002:** Collect exact pickup/return location and time, age, nationality, licence country, expected distance, handover method, extras, and eligibility declarations.
- **RNT-003:** Calculate duration in 24-hour periods and explain extra-hour/late-return thresholds.
- **RNT-004:** Before submission, summarize mileage allowance/excess rate, fuel rule, insurance/excess, refundable security deposit, age/licence requirements, cancellation terms, and handover requirements.
- **RNT-005:** Do not request passport/licence image upload in phase one.
- **RNT-006:** Require staff to verify original identity/licence/permit documents at handover before releasing the vehicle.
- **RNT-007:** Let a traveler request an extension; recheck future availability and require staff approval and revised pricing before extending.
- **RNT-008:** Support configured office, airport, and hotel delivery/collection points with time windows and fees.
- **RNT-009:** Support additional-driver or relevant rental extras when configured; every driver remains subject to handover checks.

### 7.7 Availability and fleet results

- **AVL-001:** Availability must use exact time windows, not date-only checks.
- **AVL-002:** Availability must include route/rental duration, planned waiting, turnaround/preparation buffer, manual bookings, maintenance, handover, and temporary holds.
- **AVL-003:** For chauffeured services, the vehicle’s permanent tied driver is part of that vehicle’s fulfillment capability.
- **AVL-004:** Rent does not assign or advertise the vehicle’s tied driver.
- **AVL-005:** Results must show only suitable available vehicles as selectable.
- **AVL-006:** Each vehicle card must support real photos, make/model/year, passenger seats, luggage capacity, transmission, A/C, relevant amenities, service inclusion, estimate, deposit, and status.
- **AVL-007:** Selecting a vehicle must start a visible 15-minute hold countdown.
- **AVL-008:** Hold expiry must preserve entered information, explain what happened, and recheck availability.
- **AVL-009:** Final submission must perform an authoritative availability recheck.
- **AVL-010:** If no vehicle is available, show valid nearby time/date/vehicle alternatives and offer staff assistance.
- **AVL-011:** If route or rate calculation is unreliable, preserve entered data and switch transparently to a custom-quote request without showing a misleading estimate.

### 7.8 Estimate and checkout

- **CHK-001:** Show an itemized estimate before submission.
- **CHK-002:** Chauffeured estimate components may include vehicle base, distance, travel time, planned waiting, stops, airport/after-hours fees, extras, taxes/fees, and discounts.
- **CHK-003:** Rent estimate components may include 24-hour rate, extra hours, mileage/excess, delivery/collection, extras, insurance charges, taxes/fees, and refundable security deposit.
- **CHK-004:** Display USD as the primary amount and approximate LKR separately with an exchange-rate basis/time disclaimer.
- **CHK-005:** Identify the final settlement currency in the confirmed proposal and payment page.
- **CHK-006:** Separate refundable deposits from service price and non-refundable fees.
- **CHK-007:** Review must summarize service, itinerary/times, exact vehicle, booker/lead traveler, extras, policies, estimate, and contact channels.
- **CHK-008:** Required acceptance of terms/privacy must be separate from optional marketing consent.
- **CHK-009:** Submission success must show a reference, status, private-link entry, expected response, and support path even if messaging fails.

### 7.9 Guest booking management

- **GST-001:** Every submission must create a secure private booking URL and human-readable reference.
- **GST-002:** The private page must work without an account and expire according to policy.
- **GST-003:** The page must show current status, complete itinerary/rental details, selected vehicle, confirmed driver details when relevant, itemized financial summary, payment status, message history, and documents.
- **GST-004:** The traveler must be able to accept/decline a staff proposal, use allowed payment options, request a change, request cancellation, request a Rent extension, and contact support.
- **GST-005:** The page must not expose internal notes, unrelated customer data, or operationally sensitive information.
- **GST-006:** The original confirmed booking remains active while a change proposal is pending.
- **GST-007:** Failed email/WhatsApp delivery must not invalidate the booking; allow resend and alert staff.
- **GST-008:** Completed bookings may display a private feedback request; public testimonial use requires explicit consent and moderation.

### 7.10 Booking lifecycle

Required statuses:

1. **Checkout hold** — temporary vehicle lock before submission.
2. **Submitted** — customer completed request.
3. **Under review** — staff is checking details.
4. **Changes requested / Proposal sent** — staff or traveler has proposed a revision.
5. **Awaiting acceptance** — traveler must accept the proposal.
6. **Awaiting payment** — required deposit/full payment is outstanding.
7. **Confirmed** — accepted and operationally reserved.
8. **In progress** — service or rental has begun.
9. **Completed** — service or return/handover is complete.
10. **Declined** — staff cannot fulfill request.
11. **Cancelled** — ended by traveler or staff under policy.
12. **Expired** — checkout/proposal/payment deadline passed and the slot was released.

- **LIFE-001:** Every material transition must record actor, timestamp, reason, and customer notification result.
- **LIFE-002:** Status labels and next actions must use customer-friendly wording on the private page.
- **LIFE-003:** Staff must set or inherit a visible proposal/payment deadline.
- **LIFE-004:** Deadline reminders must be configurable.
- **LIFE-005:** Expiry releases the vehicle window and requires reactivation/revalidation before continuing.

### 7.11 Payment and financial documents

- **PAY-001:** Staff or service policy controls which options are available for a booking: full payment, specified deposit, and/or pay later.
- **PAY-002:** Unavailable payment choices must not be shown as selectable.
- **PAY-003:** Online card payment must use a provider-hosted checkout; the application must not collect/store raw card numbers.
- **PAY-004:** Admin must record gateway, cash, card-terminal, and bank-transfer transactions in one payment history.
- **PAY-005:** Track amount, currency, method, status, reference, timestamp, collector, allocation, balance, refund, and notes.
- **PAY-006:** Generate/download an estimate or proposal, payment receipt, and final invoice with appropriate business, customer, currency, tax, fee, and booking data.
- **PAY-007:** Staff may apply an audited manual discount. Customer-facing promotion systems are excluded.

### 7.12 Communications

- **MSG-001:** Submission and material status changes must trigger automatic WhatsApp messages and email when supplied.
- **MSG-002:** Staff alerts must cover new submissions, response deadlines, payment issues, upcoming service/handover, schedule conflicts, notification failures, and incidents.
- **MSG-003:** Admin must show queued/sent/delivered/failed status when the provider supports it and allow manual resend.
- **MSG-004:** Templates must be editable by authorized admins and support future localization.
- **MSG-005:** Messages must state service, reference, current status, next action, deadline where relevant, support contacts, and private link.
- **MSG-006:** Before chauffeured service, send driver name, photo, verified phone/WhatsApp, exact vehicle/plate, meeting point, and urgent support contact.

### 7.13 Admin access and dashboard

- **ADM-001:** Owner/Admin and Staff use individual authenticated accounts with MFA.
- **ADM-002:** Role permissions must protect user access, business settings, sensitive exports, policy, and pricing configuration.
- **ADM-003:** Dashboard must prioritize pending review, traveler action/payment due, today’s arrivals/departures, upcoming Rent handovers/returns, unpaid balances, eligibility flags, conflicts, incidents, and fleet availability.
- **ADM-004:** Advanced analytics charts are excluded; operational counts may link to filtered work queues.

### 7.14 Booking review and calendar

- **OPS-001:** Staff must view and filter all online and manually entered bookings.
- **OPS-002:** Staff must compare request, availability, estimate, customer history for this booking, policy flags, and messages in one review workspace.
- **OPS-003:** Staff may approve, decline with reason, request information, or create a versioned proposal.
- **OPS-004:** A proposal may change vehicle, schedule, route, extras, final price, payment choices, deadline, or policy exception.
- **OPS-005:** Traveler acceptance must lock the accepted proposal version; later edits create a new version.
- **OPS-006:** Unified fleet calendar must support day/week/month, vehicle filters, service filters, status, and conflict indication.
- **OPS-007:** Staff may create manual/phone/WhatsApp bookings and maintenance/operational blocks.
- **OPS-008:** Manual entries must participate in availability immediately.
- **OPS-009:** Conflict warnings must prevent silent double booking; authorized override requires reason and audit.

### 7.15 Fleet, driver, pricing, and settings

- **CFG-001:** Authorized admins manage all five exact vehicle profiles, status, photos, capacity, luggage, features, transmission, service eligibility, permanent driver, rates, deposits, and maintenance blocks.
- **CFG-002:** Authorized admins manage permanent driver profile, verified contact, photo, status, and secure trip-link access.
- **CFG-003:** Configurable chauffeured pricing supports vehicle base, distance, duration, waiting, stops, airport, after-hours, extras, tax/fee, and manual override.
- **CFG-004:** Configurable Rent pricing supports 24-hour rate, extra hours, mileage, delivery/collection, extras, insurance/deposit, tax/fee, and manual override.
- **CFG-005:** Settings include supported airports/locations, handover points, booking windows, buffers, exchange rate, policies, extras, notification templates, support details, and legal links.
- **CFG-006:** Configuration changes must be audited and must not silently rewrite historical booking totals or accepted terms.

### 7.16 Driver trip link

- **DRV-001:** A tied driver receives a secure, limited, mobile-friendly link for assigned chauffeured trips.
- **DRV-002:** Show only necessary date/time, route, meeting instructions, traveler contact, vehicle, support, and special-needs information.
- **DRV-003:** Driver may acknowledge and mark En route, Arrived, Started, and Completed.
- **DRV-004:** Driver status updates must timestamp and notify staff; traveler notifications are policy-controlled.
- **DRV-005:** Link must not expose price configuration, internal notes, other bookings, or unrelated customer data.

### 7.17 Rent handover and incidents

- **HND-001:** Mobile-friendly collection checklist must record identity/licence/permit inspection, contract acceptance, payment/deposit, odometer, fuel, existing damage photos, accessories, and signatures.
- **HND-002:** Return checklist must compare odometer, fuel, damage, accessories, timing, charges, deposit/refund status, and signatures.
- **HND-003:** Vehicle cannot be marked handed over while required checks are incomplete without an authorized reason.
- **INC-001:** Private/trip pages must expose urgent support.
- **INC-002:** Staff may create an access-controlled incident for breakdown, accident, damage, no-show, safety issue, or dispute.
- **INC-003:** Incident records support time, people, narrative, attachments, actions, charges, status, and resolution.

### 7.18 Audit, retention, export, and feedback

- **AUD-001:** Audit status, schedule, vehicle, price, payment, discount, refund, policy override, customer-data change, message, export, access, and configuration actions.
- **AUD-002:** Sensitive history must show before/after values where appropriate.
- **DAT-001:** Retention periods must be configurable after client/legal approval.
- **DAT-002:** Support authorized export, correction, anonymization, and deletion workflows while preserving legally required records.
- **DAT-003:** Private links and driver links must expire and be revocable.
- **EXP-001:** Authorized admins may export filtered bookings, fleet schedules, customers, and payments as CSV.
- **EXP-002:** Exports must be permission-controlled and audited.
- **FDB-001:** After completion, request one rating plus optional comment.
- **FDB-002:** Public testimonial use requires explicit traveler consent and staff moderation.

## 8. Pricing and availability model

### 8.1 Estimate versus final amount

- Search/checkout displays a calculated estimate.
- Staff review produces the final proposal.
- The final proposal identifies every changed component.
- The traveler explicitly accepts the final proposal.
- Payment options appear only after or with an accepted/confirmable proposal according to business policy.

### 8.2 Availability windows

A vehicle window equals:

`service start → planned service end + configured turnaround/preparation buffer`

Inputs by service:

- Airport Pickup: pickup/flight context + route estimate + included planned waiting + buffer.
- Airport Transfer: confirmed pickup + route estimate + airport drop-off + buffer.
- Ride: each route leg + planned stops/waiting + buffer; return may be a separate window.
- Rent: handover start → scheduled return + return inspection/preparation buffer.

Temporary checkout holds and accepted proposal/payment holds also block the relevant window according to policy.

## 9. Quality requirements

### 9.1 Accessibility

- **NFR-A11Y-001:** Target WCAG 2.2 AA.
- **NFR-A11Y-002:** Complete keyboard use, logical focus order, visible focus, skip links, and no keyboard traps.
- **NFR-A11Y-003:** Every field has a persistent label, instructions where needed, and programmatically associated error.
- **NFR-A11Y-004:** Dynamic availability, price, hold countdown, submission, and status changes are announced appropriately.
- **NFR-A11Y-005:** Do not encode status or error by color alone.
- **NFR-A11Y-006:** Support 200% zoom, reflow, adequate touch targets, and reduced motion.

### 9.2 Responsive behavior

- **NFR-RWD-001:** Customer flows work from narrow mobile through desktop.
- **NFR-RWD-002:** Full admin workflows prioritize desktop but retain critical booking, handover, incident, and calendar actions on mobile.
- **NFR-RWD-003:** Driver link is mobile-first.
- **NFR-RWD-004:** No horizontal page scrolling at supported widths; large tables may use purpose-built responsive alternatives.

### 9.3 Performance and resilience

- **NFR-PERF-001:** Optimize for international mobile roaming and slow connections.
- **NFR-PERF-002:** Use appropriately sized/compressed real images when supplied.
- **NFR-PERF-003:** Show explicit loading, retry, timeout, stale-result, and offline states.
- **NFR-PERF-004:** Preserve safe form progress but never complete a booking without a live authoritative check.
- **NFR-PERF-005:** A duplicate submit must not create duplicate bookings or charges.

### 9.4 Security and privacy

- **NFR-SEC-001:** Use least-privilege access and individual MFA-protected internal accounts.
- **NFR-SEC-002:** Private booking, driver, payment, and handover links must be unguessable, expiring, revocable, and protected from unintended indexing.
- **NFR-SEC-003:** Collect only data needed for booking and fulfillment.
- **NFR-SEC-004:** Separate required terms/privacy acceptance from optional marketing consent.
- **NFR-SEC-005:** Request cookie consent only for non-essential tracking and honor withdrawal.
- **NFR-SEC-006:** Hosted payment provider handles card data.
- **NFR-SEC-007:** Protect exports, audit them, and prevent unauthorized access to sensitive incident and identity information.

### 9.5 Localization

- **NFR-I18N-001:** Launch copy is English.
- **NFR-I18N-002:** Layouts and messages must support future translated expansion.
- **NFR-I18N-003:** Phone, currency, names, and addresses must accept international formats.
- **NFR-I18N-004:** Avoid unexpanded acronyms and locally assumed airport/time terminology.

## 10. Provider-neutral integration boundaries

Required capabilities, not selected vendors:

1. Places/address search, map pin, route distance, and duration.
2. Hosted online payment and payment/refund status callbacks.
3. WhatsApp Business transactional messaging and delivery state when available.
4. Transactional email and delivery/bounce state when available.
5. USD/LKR exchange-rate input with timestamp/source; manual override permitted and audited.

Excluded from phase one:

- live driver/vehicle telemetry;
- automatic flight-status lookup;
- direct accounting sync;
- customer identity-document verification service.

## 11. Client-input dependencies

Development cannot finalize the corresponding area until the client supplies and approves:

- product/business identity and support details;
- five complete vehicle records and permanent driver records;
- service areas, airports, handover points, and operating hours;
- every rate, fee, deposit, tax, exchange-rate rule, and settlement currency;
- lead times, buffers, waiting/no-show, cancellation/refund, and expiry values;
- Rent eligibility, insurance, mileage, fuel, damage, late-return, and handover rules;
- verified licensing, insurance, safety, review, and business claims;
- legal/privacy/cookie/terms content and data-retention periods;
- financial document requirements and legal business/tax identifiers;
- final imagery and content.

See [07-client-input-checklist.md](07-client-input-checklist.md) for the structured collection template.

## 12. Scope-change rule

A new feature is not part of approved scope until it is:

1. recorded in the sign-off change table;
2. linked to affected requirement and screen IDs;
3. assessed for customer, admin, availability, pricing, payment, notification, data, and edge-state impact; and
4. approved by the client and delivery team.
