# 05 — Wireframe Annotations

**Companion artifact:** [Visual walkthrough](../../wireframes/index.html)  
**Fidelity:** structural, grayscale, content-aware, non-interactive review artifact

## 1. How to read the wireframes

The walkthrough is not a proposed brand or final visual style. It is a storyboard of:

- information priority;
- required fields and actions;
- responsive grouping;
- state and policy communication;
- customer/staff handoffs;
- missing client inputs.

### Annotation key

| Mark | Meaning |
|---|---|
| `D#` | Confirmed product decision |
| `R#` | Requirement or behavior |
| `S#` | Required state/edge case |
| `A#` | Accessibility/responsive rule |
| `CLIENT` | Real content, number, policy, proof, or asset is required |
| Dashed frame | Placeholder asset or conditional area |
| Solid heavy frame | Primary decision/action region |
| Gray hatch | Not available yet / pending external action |

## 2. Global structure

### Customer header

Contains:

- working identity placeholder;
- Book menu with four services;
- Fleet, How it works, Help;
- persistent support action;
- language is English-only in phase one, so no inactive language switcher is shown.

During booking, the header simplifies. The selected service and “Sri Lanka time” context remain visible.

### Booking progress

Use text labels, not numbers alone:

1. Service details
2. Available vehicles
3. Traveler details
4. Review request

“Staff review,” “proposal,” “payment,” and “confirmed” happen after submission on the private page; they must not appear as if checkout itself confirms the booking.

### Summary panel

On desktop, route/period, vehicle, timing, and estimate may remain in a sticky right column. On mobile, it collapses into an expandable summary above the primary action. Essential fees/terms must not be hidden.

### Primary action language

Recommended structural labels:

- Check availability
- Choose this vehicle
- Continue to traveler details
- Review request
- Submit booking request
- Review proposal
- Accept proposal
- Continue to secure payment

Avoid “Book now” or “Confirm booking” before staff approval.

## 3. Frame annotations

### WF-00 — Review cover and service contract

**Purpose:** prevent reviewers from mistaking the artifact for final UI.

Required:

- Working title and revision.
- Prominent `LOW-FIDELITY / NOT FINAL UI` marker.
- In-scope/out-of-scope summary.
- Four-service comparison showing driver inclusion and booking basis.
- Shared booking spine.
- “Start review” anchor, not a fake customer-booking CTA.

**Decision tested:** Do stakeholders agree on what the four labels mean?

### WF-01 — Home/service chooser

**Purpose:** help an international traveler pick the correct service before filling a form.

Required hierarchy:

1. “Plan transport in Sri Lanka before you arrive” proposition.
2. Four equally discoverable services.
3. Each service includes one defining sentence and driver/no-driver marker.
4. Shared process and “subject to staff confirmation.”
5. Verified-proof placeholders; no invented ratings.
6. International context: Sri Lanka time, USD + approximate LKR, WhatsApp contact.

**Mobile:** stack service choices as large rows; avoid a dense four-card grid.  
**Desktop:** a ruled 2×2 or horizontal comparison is acceptable if all descriptions remain readable.

### WF-02 — Shared booking spine

**Purpose:** show where service-specific flows converge.

Sequence:

`Tailored details → suitability/availability → exact vehicle → traveler/extras → estimate/terms → request submitted → staff proposal → allowed payment → confirmed`

Annotation must identify:

- availability is not confirmation;
- 15-minute hold begins after vehicle selection;
- final proposal may differ and requires acceptance;
- no account is created.

### WF-03 — Airport Pickup form

**Field groups:**

1. Arrival airport.
2. Flight number and scheduled arrival in Sri Lanka time.
3. Destination with place search/map pin/instructions.
4. Adults/children, luggage, assistance.
5. Name-board text and relevant extras.
6. Waiting/delay/no-show summary.

**Behavior:** changing flight/arrival/destination invalidates stale availability.  
**Client inputs:** supported airports, included waiting, extra wait, no-show policy, meet-and-greet pricing.

### WF-04 — Airport Transfer form

**Field groups:**

1. Pickup place and instructions.
2. Departure airport and flight.
3. Calculated recommended pickup time.
4. Traveler override with risk warning.
5. Passengers/luggage/assistance/extras.

The recommended time module must explain its basis: route estimate + airport-arrival buffer. It must not imply a flight guarantee.

### WF-05 — Ride route builder

**Purpose:** construct an ordered, priced itinerary rather than free-text messaging.

Required:

- Pickup row.
- Numbered stop rows with drag/reorder affordance and expected wait.
- Final destination.
- Add stop.
- Optional return leg with separate date/time.
- Route/duration estimate and passenger/luggage fields.

**Mobile:** reorder controls must work without drag-only interaction; provide Move up/Move down alternatives.  
**Return rule:** clearly state that the vehicle is not held between separate legs unless paid waiting/continuous service is included.

### WF-06 — Rent search and eligibility

**First visible statement:** `SELF-DRIVE · DRIVER NOT INCLUDED`.

Field groups:

1. Pickup/return place and exact time.
2. Live 24-hour duration calculation.
3. Driver age, nationality, licence country.
4. Expected distance.
5. Delivery/collection and rental extras.
6. Eligibility and original-document check explanation.

**Policy summary before search:** deposit, insurance/excess, mileage, fuel, late return, cancellation—using client placeholders.

No upload component appears. Original documents are checked at handover.

### WF-07 — Exact vehicle results

**Purpose:** make selection confident and suitability explicit.

Each vehicle row/card includes:

- real-photo placeholder;
- placeholder vehicle identity;
- seats and luggage;
- transmission, A/C, relevant features;
- service inclusion (`driver included` or `self-drive`);
- estimated amount and refundable deposit separated;
- why it suits the entered request;
- availability status;
- details and select actions.

Unavailable/incompatible vehicles should not appear selectable. An optional “Why other vehicles are not shown” explanation may preserve trust without exposing another booking.

### WF-08 — Selected vehicle hold and checkout

Required:

- 15-minute countdown with text explanation.
- Selected exact vehicle and criteria.
- Booker contact: name, country-coded WhatsApp/mobile, optional email, preference.
- “Booking for someone else” reveals lead traveler/Rent driver fields.
- Structured needs and compatible extras.
- Persistent safe progress indication.

**Accessibility:** timer warnings announced without repeated disruption; provide policy-supported time assistance rather than making accessibility needs impossible.

### WF-09 — Review and itemized estimate

Order:

1. Service and Sri Lanka date/time.
2. Route/period and passenger/driver details.
3. Exact vehicle.
4. Itemized estimate in USD plus approximate LKR.
5. Refundable deposit visually separated.
6. Relevant policy summaries.
7. Required terms/privacy acceptance.
8. Optional marketing consent separately.
9. `Submit booking request`.

The estimate is labeled provisional pending staff review. Exchange-rate source/time and final settlement currency rules are visible.

### WF-10 — Submission success

Must work even when messages fail.

Required:

- Request received—not confirmed.
- Booking reference.
- Private booking link/action.
- Expected staff response placeholder.
- WhatsApp/email delivery states.
- Resend/recovery action for failed channel.
- Support with booking context.

### WF-11 — Private booking overview

Desktop may use status/next-action plus detail columns. Mobile must lead with status and one next action.

Sections:

- status timeline;
- action/deadline;
- itinerary/rental details;
- vehicle;
- driver/meeting when assigned;
- proposal versions;
- itemized financial summary and payment history;
- documents;
- change/cancel/extension;
- messages and support.

### WF-12 — Proposal and controlled payment choices

The proposal shows:

- clear differences from request/previous version;
- final price and settlement currency;
- staff explanation;
- acceptance/payment deadline;
- accept, request change, or decline;
- only payment choices enabled for this booking.

A hosted checkout handoff explicitly identifies the provider boundary and return behavior. The application never displays a raw card-number form in these wireframes.

### WF-12A — Hosted payment return

Three provider-return outcomes use the same booking reference and never invite an unsafe duplicate charge:

- **Succeeded:** show amount, currency, transaction reference, receipt, remaining balance, and resulting booking status.
- **Pending/unknown:** explain that settlement is still being checked; provide a status recheck and support without starting another payment automatically.
- **Failed/cancelled:** state that no successful payment was recorded, preserve the booking/proposal deadline where still valid, and offer a permitted retry or another enabled method.

The return page is not itself proof of settlement; the recorded provider status remains authoritative.

### WF-12B — Guest change, cancellation, and Rent extension

The three request forms begin from the currently active booking:

- **Change:** identify fields to revise, show likely price/availability review, and keep the original confirmed commitment active until a revision is accepted.
- **Cancellation:** show the applicable policy version and estimated fee/refund before final submission; staff records the outcome.
- **Rent extension:** compare current and requested return, require a fresh exact-vehicle availability check, and keep the existing return deadline until approval.

All actions create reviewable requests; none silently rewrites an accepted proposal.

### WF-13 — Confirmed/service-day booking

For chauffeured service:

- final pickup/route;
- exact vehicle and plate;
- permanent driver photo/name/verified contact;
- meeting instructions;
- payment/balance;
- urgent support.

For Rent:

- handover/return appointment;
- original-document checklist;
- deposit/payment;
- pickup location/instructions;
- extension request.

### WF-14 — Recovery-state board

Demonstrate reusable patterns for:

- no suitable vehicle;
- custom quote fallback;
- hold expired;
- stale availability;
- payment failed/pending;
- message delivery failed;
- expired private link;
- vehicle replacement proposal.

Every state includes what happened, what remains safe/valid, and the next valid action.

### WF-15 — Admin operations dashboard

**Purpose:** answer “What needs attention now?”

Priority blocks:

1. pending booking reviews;
2. traveler acceptance/payment deadlines;
3. today’s arrivals/departures;
4. Rent collections/returns;
5. conflicts/incidents;
6. unpaid balances;
7. five-vehicle availability snapshot.

No decorative analytics charts. Counts link to filtered work queues.

### WF-15A — Booking inbox, manual booking, and incident

This frame makes three high-frequency operational entry points visible:

- **Booking inbox:** search and filter by reference, service, date, exact vehicle, status, assignee, deadline, payment/message issue, and source; destructive or commitment-changing actions remain booking-specific.
- **Manual booking:** phone, WhatsApp, and email requests collect the same schedule, suitability, traveler, consent, price/payment, and policy essentials as online requests; a saved blocking state immediately affects public availability.
- **Incident:** type/severity, connected booking/vehicle/driver, facts, people, evidence, actions, financial impact, communication, assignee, and resolution remain permission-controlled and audited.

### WF-16 — Admin booking review

Desktop composition:

- **Request:** service, route, traveler, needs, messages.
- **Operations:** availability timeline, vehicle/driver, conflicts, policy checks.
- **Proposal:** itemized editable final offer, payment choices, deadline, customer message.

Primary actions are status-aware: request info, propose change, confirm/approve, decline. Sensitive override requires reason.

Mobile shows one column with a sticky status-aware action area.

### WF-17 — Unified five-vehicle calendar

This is the operational centerpiece.

Required visual grammar:

- rows = exact vehicles, each labeled with permanent driver;
- horizontal time axis;
- distinct outlined patterns/labels for checkout hold, pending, confirmed, Rent, maintenance, handover, and buffer;
- conflict indicator never depends on color alone;
- filters and day/week/month;
- create manual booking or block;
- mobile alternative defaults to agenda/list.

Do not expose customer details at calendar overview density beyond what staff needs.

### WF-17A — Five-vehicle mobile agenda

The mobile alternative is a purpose-built chronological list, not a compressed desktop calendar. It must show:

- all five exact vehicles, even when a vehicle has no task;
- permanent tied-driver context for chauffeured work and an explicit “no driver” Rent state;
- confirmed, pending, checkout-hold, manual-booking, Rent, handover/return, maintenance, and buffer labels;
- visible deadlines and conflict text that does not depend on color;
- filters plus permissioned new-manual-booking and add-block actions;
- a safe conflict path: change time, choose another vehicle, shorten/adjust allowed waiting, decline, or authorized audited override.

### WF-18 — Rent collection

Mobile-first checklist with completion progress.

Collection:

- verify original passport/licence/permit;
- contract and payment/deposit;
- odometer/fuel;
- existing damage photos;
- accessories;
- staff/renter signatures;
- release vehicle.

A failed required eligibility check blocks release unless an authorized exception and reason exist.

### WF-18B — Rent return

The mobile return comparison includes:

- actual return time;
- odometer/mileage and fuel;
- damage/accessory comparison;
- charges and traveler acknowledgment;
- deposit/refund outcome;
- complete or open incident.

An extension appears authorized only after exact-vehicle availability, staff approval, traveler acceptance, and payment rules have all passed.

### WF-19 — Driver secure trip link

No global navigation or pricing.

Required:

- date/time and vehicle;
- pickup/meeting and ordered route;
- only necessary lead traveler contact/needs;
- urgent support;
- one current state action: Acknowledge → En route → Arrived → Started → Completed.

Expired/revoked state reveals no trip information.

### WF-19A — Driver-link recovery

Separate invalid, expired, and revoked states provide reason-appropriate recovery while sharing the same privacy boundary:

- reveal no booking, route, traveler, vehicle, or assignment data;
- do not confirm whether a guessed token matches a real booking;
- direct the driver to verified dispatch contact or the latest staff message;
- require a newly issued, trip-scoped link after expiry/revocation rather than reviving cached access.

### WF-20 — Configuration and client inputs

Shows that authorized admins can maintain:

- five vehicle and tied-driver records;
- pricing and extras;
- supported locations/airports/handover;
- booking limits and buffers;
- policies;
- currency/exchange rate;
- messages;
- business/support details;
- users/roles;
- retention.

This frame is not a complete CMS design; it validates that business-critical values are not hard-coded.

## 4. Responsive rules

### Breakpoint-independent principles

- Group by task, not by arbitrary card count.
- Keep persistent labels; placeholder text is not a label.
- Avoid horizontal form layouts that collapse unpredictably.
- Place more space above a new section than between its label and controls.
- Make route order and status order visible without color.
- Do not hide legal/price information behind hover.

### Mobile

- One primary column.
- Primary action follows the content it commits; sticky action is allowed when summary remains accessible.
- Forms use native-friendly date/time controls where feasible but always label Sri Lanka time.
- Vehicle cards become full-width rows.
- Itemized estimate stays expanded at final review.
- Admin calendar defaults to agenda and opens timeline detail on demand.
- Tables become labeled record stacks, not clipped desktop tables.

### Desktop

- Maximum readable line length for prose.
- Search forms may use two columns only when reading order remains obvious.
- Results favor vertical comparison; avoid five tiny vehicle cards in one row.
- Checkout/private pages may use sticky summary.
- Admin review/calendar may use dense multi-column workspace with keyboard support.

## 5. Accessibility annotations

Every high-fidelity screen must retain:

- semantic heading order;
- skip links for public and admin shells;
- visible focus and logical focus restoration after dynamic updates;
- text status paired with any icon/pattern;
- programmatic error summary and field links;
- announcements for availability results, recalculation, hold countdown thresholds, status, and payment return;
- no drag-only stop reordering;
- no automatic timeout without warning/recovery;
- target sizes meeting WCAG 2.2 AA expectations;
- reduced-motion behavior;
- no image containing essential text.

## 6. Content rules

- Write for international travelers using plain English.
- Expand unfamiliar terms on first use.
- Always distinguish Sri Lanka time from the visitor’s local time.
- Avoid “instant,” “guaranteed,” or “confirmed” before the relevant state exists.
- Use “estimated total” before staff review and “final proposal” afterward.
- Label approximate LKR separately from the settlement amount.
- State “driver included” or “self-drive · driver not included” wherever ambiguity could recur.
- Explain why information is requested, especially flight, location pin, eligibility, identity, and assistance needs.

## 7. What high-fidelity design may change

- Composition, grid, typography, approved color, imagery, iconography, component styling, and motion.
- Whether a flow is rendered as a page, stepper, drawer, or progressive section.
- Exact microcopy after content review.

It may not change without recorded approval:

- service definitions;
- required decisions/fields;
- availability and confirmation rules;
- exact-vehicle selection;
- guest private-link model;
- proposal acceptance and payment control;
- admin lifecycle/calendar responsibilities;
- Rent self-drive and handover behavior;
- driver-link privacy boundary;
- required states and accessibility outcomes.
