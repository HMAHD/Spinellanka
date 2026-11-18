# 06 — Admin Operations Specification

**Purpose:** define how Owner/Admin and Staff keep a five-vehicle fleet available, fulfillable, and auditable.

## 1. Operating model

The website’s availability is only trustworthy when every vehicle commitment enters the same schedule:

- website checkout holds;
- submitted requests;
- confirmed bookings;
- phone, email, and WhatsApp bookings entered by staff;
- Rent handover and return windows;
- preparation/turnaround buffers;
- maintenance and operational blocks;
- approved extensions and itinerary changes.

Staff confirmation remains required because route feasibility, eligibility, policies, operational timing, and final price may need human review.

## 2. Roles and permissions

### 2.1 Baseline permission matrix

| Capability | Owner/Admin | Staff | Driver secure link |
|---|---:|---:|---:|
| View assigned/operational bookings | All | All permitted | Current assigned trip only |
| Review and propose booking | ✓ | ✓ | — |
| Confirm/decline/cancel booking | ✓ | ✓, policy-limited | — |
| Create manual booking/block | ✓ | ✓ | — |
| Override conflict/policy | ✓ | Optional explicit permission | — |
| Record payment/refund | ✓ | ✓, policy-limited | — |
| Configure pricing/policies | ✓ | — | — |
| Manage fleet/driver records | ✓ | View/update operational status as permitted | — |
| Manage users/roles | ✓ | — | — |
| Export customer/payment data | ✓ | Optional explicit permission | — |
| View full audit | ✓ | Scope-limited | — |
| View trip details | ✓ | ✓ | Necessary current trip only |
| Update trip status | ✓ | ✓ | Assigned trip only |

Final permissions require client approval. No shared admin credentials.

### 2.2 Security behavior

- Individual account plus MFA.
- Re-authentication for sensitive operations where implementation determines it appropriate.
- Session timeout and revocation.
- Role/permission checked for page, action, API, export, and field—not navigation alone.
- Sensitive export links expire.
- Driver/private guest links are revocable and scoped to one booking/trip.

## 3. Dashboard

The dashboard is an operational triage surface, not an analytics report.

### 3.1 Priority order

1. **Overdue/urgent:** service-day issue, incident, schedule conflict, failed critical message.
2. **Action needed:** new request, traveler response, eligibility issue, payment reconciliation.
3. **Deadlines:** proposals/payments expiring soon.
4. **Today:** airport arrivals/transfers, Ride starts/returns, Rent collections/returns.
5. **Upcoming:** configurable planning horizon.
6. **Fleet status:** five exact vehicles available, held, pending, confirmed, in service/rented, maintenance.

### 3.2 Each item shows

- reference;
- service;
- Sri Lanka date/time;
- exact vehicle or not yet assigned;
- customer/lead traveler label;
- status and next action;
- deadline/urgency;
- assignee;
- warning count.

Counts link to a filtered inbox. Avoid unlabeled color-only urgency.

## 4. Booking inbox

### 4.1 Filters

- status;
- service;
- date range/service date;
- vehicle;
- permanent driver;
- assigned staff;
- customer action required;
- payment/balance;
- incident/conflict/eligibility flag;
- source: website, WhatsApp, phone, email, manual;
- expiring soon.

### 4.2 Search

Search by booking reference, booker/lead traveler name, WhatsApp/mobile, optional email, flight number, and vehicle identifier where permitted.

### 4.3 Safe bulk behavior

Bulk operations should be limited to low-risk actions such as assignment or export selection. Confirmation, decline, cancellation, refund, conflict override, and customer proposal remain booking-specific.

## 5. Booking review workspace

### 5.1 Header

- reference and service;
- current status;
- source and created time;
- service time in Sri Lanka time;
- current assignee;
- next action/deadline;
- warnings.

### 5.2 Request panel

- original request version;
- route/rental period;
- booker and lead traveler;
- passengers/luggage/assistance;
- flight or rental eligibility context;
- extras and special request;
- accepted policy versions;
- customer communication preference.

### 5.3 Operations panel

- exact selected vehicle and permanent driver for chauffeured service;
- schedule window and buffers;
- nearby vehicle timeline;
- capacity/suitability results;
- airport timing warning;
- Rent eligibility/term flags;
- overlapping holds/requests/blocks;
- client-configured policy checks.

### 5.4 Financial panel

- calculated estimate inputs;
- itemized components;
- USD and approximate LKR basis;
- editable final amounts with reason/audit;
- refundable deposit separately;
- manual discount with reason;
- allowed payment choices;
- acceptance/payment deadline;
- settlement currency;
- prior proposal/payment/refund history.

### 5.5 Actions

Based on status and permission:

- claim/assign to staff;
- request more information;
- create/send proposal;
- approve/confirm when requirements are met;
- decline with reason and alternatives;
- cancel under policy;
- resend message;
- record payment/refund;
- open incident;
- view audit.

Actions that change customer commitment require confirmation summarizing impact.

## 6. Proposal versioning

Each proposal stores:

- version number;
- source request/previous version;
- complete service details;
- exact vehicle;
- service windows;
- itemized price and currency;
- refundable deposit;
- payment choices;
- deadline;
- included policy versions;
- staff explanation;
- creator/time;
- traveler acceptance/decline/expiry event.

When staff edits a sent proposal, create a new version; do not silently mutate the version the traveler saw.

### 6.1 Change comparison

Show additions, removals, and changed values for:

- vehicle;
- date/time;
- locations/stops/waiting;
- passenger/eligibility details;
- extras;
- price components/deposit/currency;
- payment option/deadline;
- policy terms.

## 7. Unified fleet calendar

### 7.1 Calendar objects

| Object | Blocks availability | Notes |
|---|---:|---|
| Checkout hold | Yes, temporary | 15-minute default |
| Submitted request | According to pending-hold policy | Client-configurable duration |
| Proposal awaiting acceptance/payment | Yes until visible deadline | Release on expiry |
| Confirmed booking | Yes | Authoritative commitment |
| In-progress service/Rent | Yes | Actual times may update |
| Preparation/turnaround buffer | Yes | Derived/configurable |
| Rent handover/return | Yes within operational window | Must not be hidden by rental bar |
| Maintenance/operational block | Yes | Reason and actor required |
| Declined/cancelled/expired | No | Retained historically |

### 7.2 Views

- **Day:** detailed times and conflicts.
- **Week:** primary planning view.
- **Month:** demand/maintenance overview, not precise editing.
- **Mobile agenda:** chronological tasks grouped by vehicle/date.

### 7.3 Conflict behavior

- Prevent silent overlap.
- Show which windows conflict without exposing customer data outside permissions.
- Offer valid actions: change time, choose other vehicle, shorten/adjust planned waiting, decline, or authorized override.
- Override requires permission, reason, and audit, and should not create an operationally impossible confirmation.

### 7.4 Manual booking

Staff-entered booking uses the same essential model:

- source;
- service;
- date/time/locations;
- exact vehicle;
- booker/lead traveler contact and consent basis;
- passenger/luggage/eligibility;
- price/payment;
- status;
- policy acceptance method;
- message preference.

It blocks availability as soon as saved in a blocking state.

## 8. Vehicle and permanent driver model

### 8.1 Vehicle profile

- public display name;
- internal ID and registration/plate access-controlled as appropriate;
- make/model/year supplied by client;
- real images and alt text;
- seats and luggage limits;
- transmission, A/C, features;
- accessibility/equipment support;
- eligible services;
- active/maintenance/retired status;
- permanent tied driver;
- service-specific pricing/deposits;
- buffer rules;
- maintenance history/blocks.

### 8.2 Permanent driver

- identity and verified public name;
- client-approved photo;
- verified phone/WhatsApp;
- tied vehicle;
- active/unavailable status;
- secure trip-link access/revocation;
- operational notes with restricted access.

For chauffeured services, the exact vehicle’s tied driver is the expected driver. A change of vehicle therefore changes the driver details and requires the same traveler-approved substitution process when post-confirmation.

Rent exposes no driver and assigns no driver task.

## 9. Pricing operations

### 9.1 Chauffeured components

Configurable by service and vehicle:

- base charge;
- distance;
- travel time;
- planned stop waiting;
- included/extra airport waiting;
- extra stop;
- airport/parking/toll if applicable;
- after-hours/seasonal rule if approved;
- extra/equipment;
- tax/fee;
- manual discount;
- manual override.

### 9.2 Rent components

- 24-hour vehicle rate;
- additional hour / next-day threshold;
- mileage allowance and excess;
- delivery/collection;
- insurance/add-on;
- refundable security deposit;
- additional driver/extra;
- fuel or late-return charge as policy defines;
- tax/fee;
- manual discount/override.

### 9.3 Rule governance

- Effective dates and active/inactive state.
- Test calculation before publication.
- Existing accepted bookings retain their recorded proposal unless explicitly revised.
- Override shows calculator value, changed value, difference, reason, and actor.
- Exchange rate stores value, source/method, timestamp, and settlement-currency disclaimer.

## 10. Payment operations

### 10.1 Payment choices

Staff or policy can offer one or more:

- full online payment;
- specified online deposit;
- pay later;
- supported offline method such as bank transfer, cash, or card terminal.

The customer sees only allowed choices. Deposit amount and remaining balance are explicit.

### 10.2 Payment record

- transaction ID/reference;
- booking and proposal version;
- amount/currency;
- method/channel;
- initiated/settled time;
- success/pending/failed/refunded/partially refunded;
- collector/recorder;
- evidence/reference for offline payment;
- allocation to service/deposit/balance;
- audit history.

### 10.3 Reconciliation states

- Gateway success callback received.
- Gateway shows pending.
- Customer returned but provider state unknown.
- Payment succeeded but booking update delayed.
- Duplicate callback/attempt.
- Offline proof awaiting verification.
- Refund initiated/pending/completed/failed.

Staff should see actionable reconciliation, not merely “Payment error.”

## 11. Messaging operations

### 11.1 Event matrix

| Event | Customer WhatsApp | Customer email if supplied | Staff alert | Driver link/message |
|---|---:|---:|---:|---:|
| Request submitted | ✓ | ✓ | ✓ | — |
| More information requested | ✓ | ✓ | ✓ | — |
| Proposal sent/revised | ✓ | ✓ | ✓ | — |
| Deadline reminder | ✓ | ✓ | ✓ | — |
| Payment success/failure needing action | ✓ | ✓ | ✓ | — |
| Booking confirmed | ✓ | ✓ | ✓ | Assignment when relevant |
| Driver/meeting details ready | ✓ | ✓ | ✓ | ✓ |
| Operational timing changed | ✓ | ✓ | ✓ | ✓ |
| Change/cancellation outcome | ✓ | ✓ | ✓ | If assignment affected |
| Rent handover reminder | ✓ | ✓ | ✓ | — |
| Incident/service disruption | Policy-controlled | Policy-controlled | ✓ urgent | If relevant |
| Completed/feedback | ✓ | ✓ | — | — |

### 11.2 Template rules

- Plain English and localization-ready variables.
- Include reference, status, next action, deadline, support, and private link where relevant.
- Do not include unnecessary sensitive information.
- Preview with missing-variable validation.
- Template versions are audited.

### 11.3 Delivery failure

- Booking action remains valid.
- Record provider response and attempts.
- Retry according to configured policy.
- Alert staff for critical failures.
- Offer manual resend and alternate supplied channel.

## 12. Rent collection and return

### 12.1 Preconditions for release

- Confirmed Rent booking.
- Required payment/deposit condition satisfied.
- Original identity and driving documents inspected.
- Eligibility confirmed against approved policy.
- Contract/terms accepted.
- Vehicle condition record completed.
- Required signatures captured.

Any override requires specific permission and reason; legal/client policy may prohibit overrides entirely.

### 12.2 Collection record

- actual collection time/location;
- staff member;
- renter/additional driver checks;
- document types checked—avoid unnecessary document copies in phase one;
- odometer and fuel;
- damage map/photos;
- accessories/equipment;
- notes;
- signatures;
- vehicle state changed to In progress.

### 12.3 Return record

- actual return time/location;
- extension authorization;
- odometer/mileage;
- fuel;
- damage/accessory comparison;
- traveler acknowledgment;
- additional charge proposal/payment;
- incident link;
- deposit/refund decision;
- completion and final invoice.

## 13. Driver secure trip link

### 13.1 Access

- Unique, expiring/revocable, trip-scoped link.
- No access to other trips through navigation.
- Invalid/expired link reveals no booking data.

### 13.2 Visible data

- date/time in Sri Lanka time;
- exact tied vehicle;
- pickup/meeting instructions;
- ordered route and planned waiting;
- necessary lead traveler contact;
- relevant assistance/safety notes;
- dispatch/support contact.

### 13.3 Actions

- Acknowledge assignment.
- En route.
- Arrived.
- Started.
- Completed.
- Report issue / call support.

Every update records time and informs staff. Customer notification rules are configurable.

## 14. Incidents

### 14.1 Types

- vehicle breakdown;
- accident;
- damage;
- passenger/customer no-show;
- driver no-show/unavailability;
- safety or accessibility issue;
- payment/refund dispute;
- Rent eligibility/handover failure;
- lost item;
- other operational dispute.

### 14.2 Record

- type/severity/status;
- booking/vehicle/driver;
- people involved;
- occurred/reported times;
- factual narrative;
- attachments;
- immediate actions;
- external references when supplied;
- financial impact;
- customer communication;
- owner/assignee;
- resolution and follow-up.

Access is permission-controlled and audited.

## 15. Audit and data operations

### 15.1 Audit events

At minimum:

- authentication/security actions;
- user/role changes;
- booking status and assignment;
- date/time/route/vehicle/driver;
- proposal and price component;
- discount/override;
- payment/refund;
- policy acceptance/override;
- message send/resend/template change;
- handover/return/incident;
- fleet/configuration;
- data export/correction/anonymization/deletion.

### 15.2 CSV export

Authorized user chooses:

- dataset;
- filters/date range;
- permitted fields;
- purpose acknowledgement where policy requires.

System records request, creator, scope, generated time, download, expiry, and result. Exports do not become public permanent files.

### 15.3 Retention and data requests

Exact periods are client/legal inputs. UX supports:

- record-class retention rules;
- legal/operational hold where approved;
- customer data access/export;
- correction;
- anonymization/deletion where allowed;
- link/token expiry/revocation;
- request status and audit.

## 16. Daily operating rhythm

### Opening / start of shift

1. Review urgent dashboard items and notification failures.
2. Check today’s airport services, Ride legs, Rent collections/returns.
3. Confirm vehicle condition, maintenance blocks, and permanent-driver readiness.
4. Review pending requests and expiring proposals/payments.
5. Resolve schedule conflicts.

### During shift

1. Review new requests.
2. Send proposals and respond to traveler changes.
3. Record offline bookings/payments immediately.
4. Monitor service-day messages and trip status.
5. Complete Rent checklists and incidents.
6. Keep calendar windows accurate when actual timing changes.

### Close / handoff

1. Reconcile completed services and outstanding balances.
2. Confirm Rent return/deposit outcomes.
3. Resolve or hand off open incidents.
4. Review next-day services and driver details.
5. Record shift notes in booking/incident contexts—not a separate unaudited channel.

## 17. Operations acceptance criteria

- A staff member can see all work requiring action without reading every booking.
- A manually received booking blocks website availability promptly.
- A proposal cannot be silently edited after customer viewing.
- The calendar makes holds, pending, confirmed, Rent, maintenance, handover, and buffers distinguishable without color alone.
- Staff can explain every final price difference from the estimate.
- Online and offline payments reconcile in one history.
- A Rent vehicle cannot be released without required checks or an audited permitted exception.
- A driver cannot view unrelated trips or prices.
- Every material override, export, and data change is attributable to an individual account.
