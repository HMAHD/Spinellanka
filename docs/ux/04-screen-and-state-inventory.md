# 04 — Screen and State Inventory

**Purpose:** define the complete low-fidelity design and QA coverage. A screen may become a page, step, panel, modal, or responsive state during high-fidelity design; its required information and behavior must remain covered.

## 1. Coverage notation

- **M:** mobile wireframe required
- **D:** desktop wireframe required
- **R:** represented by annotation/state variant rather than a full duplicate frame
- **Client input:** real copy/data/asset needed before final UI

## 2. Public screens

| ID | Screen | M | D | Primary purpose | Key content/actions |
|---|---|---:|---:|---|---|
| PUB-01 | Home / service chooser | ✓ | ✓ | Explain advance booking and start correct service | Four service choices, shared process, international guidance, verified-proof placeholders, support |
| PUB-02 | Airport Pickup detail | R | ✓ | Explain arrival service and open full form | Inclusions, flight/wait policy summary, form, FAQ |
| PUB-03 | Airport Transfer detail | R | ✓ | Explain departure service and guided time | Timing method, warning behavior, form, FAQ |
| PUB-04 | Ride detail | R | ✓ | Explain multi-stop scheduled chauffeured trip | Ordered stops, waiting, return legs, form, FAQ |
| PUB-05 | Rent detail | ✓ | ✓ | Make self-drive/no-driver terms unmistakable | Eligibility, 24-hour basis, handover, deposit/insurance summary, form |
| PUB-06 | Fleet index | R | ✓ | Explain exact-vehicle choice | Five supplied vehicle summaries, filters/service eligibility, availability CTA |
| PUB-07 | Vehicle profile | R | ✓ | Show verified vehicle detail | Photos, specs, capacity, luggage, features, service/rental context, availability CTA |
| PUB-08 | How it works | R | ✓ | Set expectations | Chauffeured/Rent processes, estimate vs final, confirmation, payment, changes |
| PUB-09 | About / trust | R | ✓ | Establish proof-backed trust | Business facts, fleet/driver proof, licences/insurance if supplied, genuine reviews |
| PUB-10 | International traveler guide | R | ✓ | Remove local assumptions | Sri Lanka time, currency, phone format, airport and meeting guidance |
| PUB-11 | Help / FAQ | R | ✓ | Answer common questions | Search, grouped questions, contact escalation |
| PUB-12 | Contact / support | ✓ | R | Reach business | WhatsApp, phone, optional email/form, hours and urgent distinction |
| PUB-13 | Policy/legal template | R | ✓ | Present approved policy | Effective date, section navigation, contextual summaries, contact |
| PUB-14 | Cookie/privacy controls | ✓ | R | Manage optional tracking | Purpose groups, accept/reject/save, withdrawal route |

## 3. Service search screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| SRV-01 | Airport Pickup form | ✓ | ✓ | Collect arrival journey | Airport, flight, arrival date/time, destination, map pin/instructions, passenger/luggage, name board, needs |
| SRV-02 | Airport Transfer form | ✓ | ✓ | Collect departure journey | Origin, airport/flight, departure time, recommended pickup time, override warning, passenger/luggage, needs |
| SRV-03 | Ride route builder | ✓ | ✓ | Build ordered itinerary | Pickup, reorderable stops, waiting per stop, destination, optional return leg, passenger/luggage, needs |
| SRV-04 | Rent search | ✓ | ✓ | Define self-drive period and eligibility | Pickup/return, 24-hour summary, handover, age/nationality/licence country, distance, extras, self-drive notice |
| SRV-05 | Place search / map pin | ✓ | R | Resolve unfamiliar location | Search suggestions, selected place, pin, instructions, unsupported state |
| SRV-06 | Airport time warning | ✓ | R | Explain late transfer choice | Recommended vs chosen time, risk, edit/continue for staff review |
| SRV-07 | Form recovery | ✓ | R | Restore safe draft | Restored fields, stale values needing recheck, discard/continue |

## 4. Availability and selection screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| AVL-01 | Availability loading | R | R | Explain active search | Criteria summary, progress, cancel/edit, accessible status |
| AVL-02 | Available vehicle results | ✓ | ✓ | Compare suitable exact vehicles | Criteria, count, detailed cards, price/deposit, capacity, availability, select |
| AVL-03 | Vehicle detail / compare | ✓ | R | Inspect one exact vehicle | Gallery placeholders, specs, service inclusions, rental terms, estimate, select |
| AVL-04 | 15-minute hold state | ✓ | ✓ | Complete checkout before expiry | Vehicle summary, countdown, accessible warning, release/edit behavior |
| AVL-05 | No availability | ✓ | ✓ | Recover without dead end | Reason-safe explanation, nearby time/date options, vehicles, edit, assistance |
| AVL-06 | Custom quote fallback | ✓ | R | Preserve unusual request | Entered details, why estimate unavailable, expected response, submit assistance request |
| AVL-07 | Hold expired | ✓ | R | Revalidate without data loss | Expiry explanation, same-vehicle recheck, alternatives |
| AVL-08 | Availability became stale | R | R | Require authoritative recheck | Changed criteria/result age, recheck action, no silent submit |

## 5. Checkout and submission screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| CHK-01 | Booker details | ✓ | R | Capture required contact | Name, country-coded WhatsApp/mobile, optional email, preferred channel |
| CHK-02 | Lead traveler / Rent driver | ✓ | R | Support third-party booking | Same as booker toggle, separate identity/contact, relationship, consent |
| CHK-03 | Needs and extras | ✓ | R | Complete fulfillment details | Structured assistance, compatible extras/quantity/price, free-text request |
| CHK-04 | Review and itemized estimate | ✓ | ✓ | Validate all details | Service, Sri Lanka time, route/period, exact vehicle, people, extras, itemization, refundable deposit |
| CHK-05 | Terms and consent | ✓ | R | Capture valid acceptance | Contextual policy summaries, required terms/privacy, optional marketing separate |
| CHK-06 | Submission processing | R | R | Prevent duplicates | Single in-progress action, no repeat submission, recovery message |
| CHK-07 | Request success | ✓ | ✓ | Prove submission and next step | Reference, Pending review, private link, expected response, channel delivery, support |
| CHK-08 | Partial notification failure | ✓ | R | Keep booking recoverable | Reference/private link, failed channel, resend, staff aware |
| CHK-09 | Submission error | ✓ | R | Safely retry | What was/was not created, preserve data, retry/support, duplicate protection |

## 6. Private guest booking screens/states

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| GST-01 | Booking overview — Under review | ✓ | ✓ | Show current status and expectation | Reference, status, timeline, full details, contact/support, no payment CTA yet |
| GST-02 | Proposal available | ✓ | ✓ | Review changed/final offer | Version changes, itemization, vehicle/time/route, deadline, accept/decline/change |
| GST-03 | Payment choices | ✓ | R | Select allowed payment route | Only enabled choices, amount/balance, currency, deadline, hosted checkout action |
| GST-04 | Hosted payment return | ✓ | R | Show success/pending/failure | Transaction status, booking impact, retry/recheck/support |
| GST-05 | Confirmed booking | ✓ | ✓ | Prepare for service | Confirmed details, payment/balance, documents, driver/meeting when available, support |
| GST-06 | Change request | ✓ | R | Request safe revision | Current details, requested changes, impact notice, original remains active |
| GST-07 | Cancellation request | ✓ | R | Understand policy and request cancel | Fee/refund estimate, reason, confirmation, review status |
| GST-08 | Rent extension request | ✓ | R | Request later return | Current/new return, availability review, estimated impact, original deadline remains |
| GST-09 | Documents | R | R | Download records | Proposal/estimate, receipts, final invoice, version/date/currency |
| GST-10 | Driver and meeting details | ✓ | R | Reassure traveler | Driver name/photo/contact, exact vehicle/plate, meeting point, urgent support |
| GST-11 | In-progress service | ✓ | R | Provide service-day context | Current service state, route/return, driver/support, urgent issue |
| GST-12 | Completed + feedback | ✓ | R | Close service | Final invoice, payment/refund, one-question rating, comment, publication consent |
| GST-13 | Declined | ✓ | R | Explain inability to fulfill | Reason category, released hold, payment/refund status, alternatives/support |
| GST-14 | Cancelled | ✓ | R | Confirm closure | Actor/reason, fee/refund, released schedule, documents/support |
| GST-15 | Expired proposal/payment | ✓ | R | Explain released slot | Missed deadline, current availability not promised, reactivate/contact action |
| GST-16 | Invalid/expired private link | ✓ | R | Recover safely | No booking data, safe support/recovery process, reference guidance |

## 7. Admin authentication and shell

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| ADM-01 | Login | R | ✓ | Authenticate individual staff | Email/user, password or selected auth method, accessible errors |
| ADM-02 | MFA challenge | R | ✓ | Complete secure sign-in | Code/method, retry, recovery route |
| ADM-03 | Password/account recovery | R | R | Regain authorized access | Identity-safe request, neutral confirmation, admin support |
| ADM-04 | Session expired / forbidden | R | R | Recover or explain permission | Re-authenticate, preserve safe location, request access |
| ADM-05 | Admin shell/navigation | ✓ | ✓ | Reach role-allowed work | Navigation, global search, alerts, user/session, responsive agenda behavior |

## 8. Admin operations screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| OPS-01 | Operations dashboard | ✓ | ✓ | Show work requiring attention | Pending reviews, deadlines, today, handovers, unpaid, conflicts, incidents, fleet status |
| OPS-02 | Booking inbox/list | ✓ | ✓ | Find and prioritize bookings | Filters, search, status, service, dates, assignee, deadline, bulk-safe actions only |
| OPS-03 | Booking review workspace | ✓ | ✓ | Make fulfillment decision | Request, timeline, availability, traveler, estimate, policy flags, communications, actions |
| OPS-04 | Proposal editor | R | ✓ | Create versioned final/revised offer | Changed fields, itemization, payment choices, deadline, message preview |
| OPS-05 | Proposal comparison | R | ✓ | Audit differences | Previous/request/current values, actor, acceptance status |
| OPS-06 | Message center/history | ✓ | R | Send and verify communications | Channels, templates, delivery state, resend, internal note separation |
| OPS-07 | Unified fleet calendar | ✓ | ✓ | Protect availability | Five vehicle rows, tied drivers, holds, pending, confirmed, buffers, blocks, handovers, conflicts |
| OPS-08 | Manual booking | ✓ | R | Record phone/WhatsApp/offline booking | Same essential fields, source, availability, customer consent basis, payment |
| OPS-09 | Maintenance/operational block | ✓ | R | Remove vehicle from availability | Vehicle, start/end, buffer, reason, note, conflict impact |
| OPS-10 | Payment history/record | ✓ | R | Reconcile all methods | Transactions, balance, add offline payment, refund, evidence/reference, audit |
| OPS-11 | Refund action | R | R | Record/trigger refund | Amount, currency, original transaction, policy/reason, confirmation, outcome |
| OPS-12 | Incident list/detail | ✓ | R | Resolve operational issues | Type/severity, people, facts, attachments, actions, charges, resolution, access |
| OPS-13 | Feedback moderation | R | R | Review post-service feedback | Rating/comment, consent, publish eligibility, internal follow-up |
| OPS-14 | Audit history | R | ✓ | Inspect material changes | Actor, timestamp, object, before/after, reason, source/IP/device if policy allows |
| OPS-15 | Export builder/history | R | ✓ | Export allowed data safely | Dataset, filters, fields, privacy warning, generate, expiry/download, audit |

## 9. Fleet and configuration screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| CFG-01 | Fleet list | R | ✓ | Manage five exact vehicles | Status, next booking, permanent driver, maintenance, profile completeness |
| CFG-02 | Vehicle editor | R | ✓ | Maintain selectable vehicle facts | Identity, plate/internal ID, photos, specs, capacity, features, service eligibility, rates/deposits |
| CFG-03 | Permanent driver list/editor | R | ✓ | Maintain tied driver | Vehicle tie, verified contact/photo, availability/status, link access |
| CFG-04 | Pricing rule list/editor | R | ✓ | Configure estimates | Service/vehicle components, units, effective dates, tax/fees, override control, test calculation |
| CFG-05 | Extras list/editor | R | ✓ | Configure compatible options | Service/vehicle compatibility, quantity/capacity, price, active dates |
| CFG-06 | Locations/airports/handover | R | ✓ | Configure supported geography | Type, address/pin, hours, fees, services, active status |
| CFG-07 | Booking windows/buffers | R | ✓ | Configure schedule rules | Minimum notice, horizon, prep/turnaround, deadlines, service/vehicle applicability |
| CFG-08 | Policies and terms | R | ✓ | Manage approved policy versions | Type, summary, full content/link, effective date, required reacceptance |
| CFG-09 | Currency/exchange rate | R | ✓ | Control dual display | Source/manual value, timestamp, settlement currency, disclaimer, audit |
| CFG-10 | Message templates | R | ✓ | Maintain transactional copy | Event, channel, variables, language, preview/test, active version |
| CFG-11 | Business/support settings | R | ✓ | Maintain public/financial identity | Name, contacts, hours, legal/tax identifiers, invoice details, urgent support |
| CFG-12 | Users and roles | R | ✓ | Protect internal access | Individual users, role, MFA state, status, last access, revoke/recovery |
| CFG-13 | Retention/data request | R | ✓ | Apply approved data policy | Retention classes, request queue, export/correct/anonymize/delete, legal hold |

## 10. Driver screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| DRV-01 | Assignment summary | ✓ | — | Acknowledge trip | Date/time, vehicle, route, meeting, traveler/needs, support, acknowledge |
| DRV-02 | Active trip | ✓ | — | Update operational state | Current status action, route/stops, contact, support, incident link |
| DRV-03 | Complete trip | ✓ | — | Close driver task | Completion confirmation, basic issue prompt, timestamp |
| DRV-04 | Invalid/expired link | ✓ | — | Protect data/recover | No trip data, contact dispatch/admin |

## 11. Rent handover screens

| ID | Screen | M | D | Primary purpose | Required content/actions |
|---|---|---:|---:|---|---|
| HND-01 | Today’s handovers/returns | ✓ | R | Select task | Time, vehicle, renter, status, missing prerequisites, urgent flag |
| HND-02 | Collection identity/eligibility | ✓ | R | Verify originals | Passport/licence/permit checks, driver match, age/eligibility, fail/escalate |
| HND-03 | Collection condition checklist | ✓ | R | Record release state | Payment/deposit, contract, odometer, fuel, damage photos, accessories, signatures |
| HND-04 | Return condition checklist | ✓ | R | Compare return state | Time, odometer, fuel, damage/accessories comparison, photos, signatures |
| HND-05 | Charges/deposit outcome | ✓ | R | Close financial handover | Additional charges, traveler acknowledgment, deposit/refund, incident, complete |

## 12. Global component/state inventory

### 12.1 Form field states

Every field type must define:

- default;
- hover where applicable;
- keyboard focus;
- populated;
- valid confirmation only where useful;
- invalid with specific guidance;
- warning that does not block;
- disabled with explanation;
- read-only confirmed value;
- loading suggestions/calculation;
- no suggestions;
- service unavailable/retry;
- restored draft/stale value.

### 12.2 Page-level states

| State | Required behavior |
|---|---|
| Initial | Clear purpose, required/optional notation, no premature error |
| Loading | State what is happening; preserve layout; announce status |
| Slow | Offer patience/retry guidance without duplicating request |
| Empty | Explain why empty and provide first valid action |
| No result | Preserve criteria and offer corrections/assistance |
| Partial data | Show available information and identify unavailable component |
| Error | Specific, recoverable, no blame, preserve safe data |
| Offline | Explain live completion is unavailable; preserve safe progress |
| Stale | Explain affected data and require recheck |
| Success | Confirm exact result, reference, next action, and support |
| Expired | Explain what expired, what was released, and recovery path |
| Forbidden | Reveal no sensitive data; explain access route |
| Duplicate | Show existing outcome/reference rather than creating a second object |

### 12.3 Status vocabulary

Customer labels must be plain-language equivalents of the lifecycle:

| Internal status | Customer-facing example |
|---|---|
| Submitted | Request received |
| Under review | We’re checking your request |
| Proposal sent | Your booking offer is ready |
| Awaiting acceptance | Review and accept by [deadline] |
| Awaiting payment | Payment action needed |
| Confirmed | Booking confirmed |
| In progress | Service in progress |
| Completed | Service completed |
| Declined | We could not confirm this request |
| Cancelled | Booking cancelled |
| Expired | Offer expired; availability must be checked again |

Final copy remains subject to content design and client approval.

## 13. Responsive coverage matrix

| Area | Mobile | Desktop | Notes |
|---|---|---|---|
| Home/service chooser | Full | Full | Different composition, same hierarchy |
| All four service forms | Full | Full | Desktop may use sticky summary |
| Results/vehicle detail | Full | Full | No hover-only information |
| Checkout/private booking | Full | Full | Actions remain visible and ordered |
| Admin dashboard/inbox/review | Critical/full | Full | Mobile prioritizes action, not dense overview |
| Fleet calendar | Agenda + scrollable detail | Full timeline | Same source data |
| Configuration | Emergency/read where useful | Full | Desktop primary |
| Driver link | Full | Not required | Mobile-first secure link |
| Rent handover | Full | Reference/optional | Designed for use beside vehicle |

## 14. Visual placeholder rules

Until client inputs arrive:

- Use “Vehicle 01–05,” never invented makes/models.
- Use framed labels such as “Real vehicle photo required,” never stock vehicle photos presented as real fleet.
- Use `[USD amount]`, `[LKR equivalent]`, `[policy value]`, and `[deadline]` rather than fabricated prices.
- Use “Driver for Vehicle 01” rather than invented names.
- Use verified reference airport names only when the client confirms supported coverage; otherwise label “Supported airport.”
- Do not show invented star ratings, review counts, years in business, licence numbers, or safety claims.

## 15. Wireframe traceability

This map is the coverage contract between this inventory and the [visual walkthrough](../../wireframes/index.html). **Frame** means the decision-bearing structure is visible. **Linked annotation** means a dedicated duplicate is intentionally avoided and the named frame or state carries the required behavior. Mobile/desktop variations follow section 13; a linked annotation is not permission to omit the behavior from later high-fidelity work.

### 15.1 Public and service entry

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| PUB-01 | [WF-01A/B](../../wireframes/index.html#entry) | Desktop and mobile service chooser frames |
| PUB-02, SRV-01 | [WF-03](../../wireframes/index.html#services) | Airport Pickup detail/form frame; policy/FAQ content is annotated client input |
| PUB-03, SRV-02, SRV-06 | [WF-04](../../wireframes/index.html#services) | Airport Transfer detail/form and recommended-time warning frame |
| PUB-04, SRV-03 | [WF-05](../../wireframes/index.html#services) | Ride detail and ordered-route builder frame |
| PUB-05, SRV-04 | [WF-06](../../wireframes/index.html#services) | Mobile Rent search plus desktop-responsive form annotation |
| PUB-06, PUB-07, AVL-02, AVL-03 | [WF-07](../../wireframes/index.html#selection) | Exact-fleet results, profile/detail action, verified facts, suitability, and estimate frame |
| PUB-08 | [WF-02](../../wireframes/index.html#services) and review cover | Shared process/spine frame for chauffeured and Rent paths |
| PUB-09 | [WF-01A](../../wireframes/index.html#entry) and [WF-20B](../../wireframes/index.html#inputs) | Linked trust-proof and client-evidence annotations; no claim may be invented |
| PUB-10 | [WF-01A/B](../../wireframes/index.html#entry) and [WF-02](../../wireframes/index.html#services) | Linked international guidance for Sri Lanka time, currency, phone, and booking model |
| PUB-11, PUB-12 | [WF-01A/B](../../wireframes/index.html#entry), [WF-10](../../wireframes/index.html#selection), and [WF-14](../../wireframes/index.html#recovery) | Linked help/contact/support entry and contextual recovery actions |
| PUB-13 | [WF-09](../../wireframes/index.html#selection) and [WF-20](../../wireframes/index.html#field) | Linked policy summary, accepted version, and policy-management annotation |
| PUB-14 | [WF-09](../../wireframes/index.html#selection) and [WF-20](../../wireframes/index.html#field) | Linked required/optional consent separation and privacy-control configuration annotation |
| SRV-05 | [WF-03/04](../../wireframes/index.html#services) | Linked place-search, pin, and written-instruction fields; unsupported place uses WF-14 recovery grammar |
| SRV-07 | [WF-14](../../wireframes/index.html#recovery) | Linked restored/stale-data recovery state |

### 15.2 Availability, checkout, and submission

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| AVL-01 | [WF-02](../../wireframes/index.html#services) | Linked availability-progress step with accessible status/cancel-edit behavior specified in annotation |
| AVL-04 | [WF-08](../../wireframes/index.html#selection) | Mobile hold frame; desktop uses the same timer/banner behavior |
| AVL-05, AVL-06, AVL-07, AVL-08 | [WF-14](../../wireframes/index.html#recovery) | Visible no-availability, custom-quote, hold-expired, and stale-result states |
| CHK-01, CHK-02, CHK-03 | [WF-08](../../wireframes/index.html#selection) | Booker, third-party traveler/Rent driver, needs, and extras frame |
| CHK-04, CHK-05 | [WF-09](../../wireframes/index.html#selection) | Review, itemized estimate, terms, privacy, and separate marketing consent frame |
| CHK-06 | [WF-09/10](../../wireframes/index.html#selection) | Linked single-submit processing transition; duplicate submission must resolve to the recorded reference |
| CHK-07, CHK-08 | [WF-10](../../wireframes/index.html#selection) | Request success and partial notification-failure frame/annotation |
| CHK-09 | [WF-14](../../wireframes/index.html#recovery) | Linked recoverable error grammar: preserve safe data, state whether a request exists, retry/support |

### 15.3 Private guest booking

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| GST-01 | [WF-11](../../wireframes/index.html#guest) | Under-review private booking frame |
| GST-02, GST-03 | [WF-12](../../wireframes/index.html#guest) | Versioned proposal and policy-controlled payment choices frame |
| GST-04 | [WF-12A](../../wireframes/index.html#wf-payment-return) | Hosted payment success, pending, and failed/cancelled return states |
| GST-05, GST-10, GST-11 | [WF-13](../../wireframes/index.html#guest) | Confirmed/service-day frame with exact vehicle, driver/meeting, balance, and urgent support |
| GST-06, GST-07, GST-08 | [WF-12B](../../wireframes/index.html#wf-guest-actions) | Change, cancellation, and Rent extension request frame; original commitment remains active |
| GST-09 | [WF-11/13](../../wireframes/index.html#guest) | Linked document area for proposal, receipt, and final invoice with version/date/currency |
| GST-12 | [WF-13](../../wireframes/index.html#guest) | Linked completion/final-invoice/private-feedback annotation |
| GST-13, GST-14, GST-15 | [WF-14](../../wireframes/index.html#recovery) | Linked closure/expiry grammar with released availability, financial outcome, alternatives, and support |
| GST-16 | [WF-14](../../wireframes/index.html#recovery) | Visible invalid/expired private-link state with no booking data |

### 15.4 Admin access and operations

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| ADM-01, ADM-02 | [WF-15](../../wireframes/index.html#operations) | Linked admin-entry contract: individual account, MFA, accessible authentication errors; authentication vendor/visuals remain deferred |
| ADM-03, ADM-04 | [WF-14](../../wireframes/index.html#recovery) and [WF-15](../../wireframes/index.html#operations) | Linked identity-safe recovery, session-expired, re-authentication, and forbidden-state behavior |
| ADM-05 | [WF-15](../../wireframes/index.html#operations) and [WF-17A](../../wireframes/index.html#wf-mobile-agenda) | Desktop admin shell and mobile critical-task/agenda behavior |
| OPS-01 | [WF-15](../../wireframes/index.html#operations) | Operations dashboard frame |
| OPS-02 | [WF-15A](../../wireframes/index.html#wf-admin-work-queues) | Searchable/filterable booking inbox frame |
| OPS-03 | [WF-16](../../wireframes/index.html#operations) | Responsive booking review workspace frame |
| OPS-04, OPS-05 | [WF-16](../../wireframes/index.html#operations) | Versioned proposal editor plus change/previous-version comparison annotation |
| OPS-06 | [WF-15/16](../../wireframes/index.html#operations) | Linked message status/history, request-information, delivery-state, resend, and internal-note boundary |
| OPS-07 | [WF-17](../../wireframes/index.html#operations) and [WF-17A](../../wireframes/index.html#wf-mobile-agenda) | Desktop five-row timeline and mobile five-vehicle agenda |
| OPS-08 | [WF-15A](../../wireframes/index.html#wf-admin-work-queues) | Manual booking form using authoritative availability |
| OPS-09 | [WF-17](../../wireframes/index.html#operations) and [WF-17A](../../wireframes/index.html#wf-mobile-agenda) | Maintenance/block state, conflict impact, and add-block action |
| OPS-10, OPS-11 | [WF-16](../../wireframes/index.html#operations) and [WF-12A](../../wireframes/index.html#wf-payment-return) | Linked unified transaction history, offline record/refund action, and reconciliation states |
| OPS-12 | [WF-15A](../../wireframes/index.html#wf-admin-work-queues) | Incident detail/handling frame |
| OPS-13 | [WF-13](../../wireframes/index.html#guest) and [WF-20](../../wireframes/index.html#field) | Linked private feedback, consent, moderation, and configuration annotation |
| OPS-14, OPS-15 | [WF-16](../../wireframes/index.html#operations) and [WF-20](../../wireframes/index.html#field) | Linked audit boundary and permissioned, expiring export/data-operation annotation |

### 15.5 Fleet and configuration

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| CFG-01, CFG-02, CFG-03 | [WF-20](../../wireframes/index.html#field) and [WF-17](../../wireframes/index.html#operations) | Fleet, vehicle, permanent-driver, status, eligibility, buffer, and maintenance management annotations |
| CFG-04, CFG-05 | [WF-20](../../wireframes/index.html#field) and [WF-16](../../wireframes/index.html#operations) | Pricing/extras configuration and test/override effect annotation |
| CFG-06, CFG-07 | [WF-20](../../wireframes/index.html#field) | Locations, airports, handover places, windows, deadlines, and buffer configuration annotation |
| CFG-08, CFG-09, CFG-10 | [WF-20](../../wireframes/index.html#field) | Versioned policies, currency/rate, and message-template configuration annotation |
| CFG-11, CFG-12, CFG-13 | [WF-20](../../wireframes/index.html#field) | Business/support, users/roles, retention, and data-request configuration annotation |

### 15.6 Driver and Rent handover

| Screen ID(s) | Walkthrough evidence | Coverage |
|---|---|---|
| DRV-01, DRV-02, DRV-03 | [WF-19](../../wireframes/index.html#field) | Mobile assignment, active-state progression, completion, route/contact, and support frame |
| DRV-04 | [WF-19A](../../wireframes/index.html#wf-driver-link-recovery) | Invalid, expired, and revoked link frame with zero trip data |
| HND-01 | [WF-17A](../../wireframes/index.html#wf-mobile-agenda) | Mobile chronological handover/return task entry and status annotation |
| HND-02, HND-03 | [WF-18](../../wireframes/index.html#field) | Collection identity/eligibility and release-condition checklist frame |
| HND-04, HND-05 | [WF-18B](../../wireframes/index.html#field) | Return comparison, additional charges, deposit/refund, incident, and completion frame |

## 16. Inventory acceptance gate

The UX package is coverage-complete when:

- every row marked M or D has a full frame or an explicitly linked annotation;
- every global state is demonstrated at least once and specified for reuse;
- all four services preserve their unique information;
- customer, private guest, admin, driver, and handover boundaries are represented;
- placeholders are visibly placeholders; and
- the client approves any intentional omission in the sign-off change table.
