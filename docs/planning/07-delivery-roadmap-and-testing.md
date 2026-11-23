# 07 — Delivery roadmap and testing

## Delivery principle

Each phase is independently mergeable and deployable. No phase relies on an unfinished later phase to be safe. Production remains behind an explicit launch decision until client data, provider accounts, legal content, and hosting readiness pass their gates.

## Phase 1 — Foundation and public presence

Ship:

- Laravel 13 project, module boundaries, code-quality tools, CI and cPanel release pipeline;
- production design tokens and responsive shell;
- public home/service/fleet/how-it-works/help/contact/legal templates with approved or clearly pending content;
- exact five-vehicle catalog schema and admin management;
- individual staff authentication, MFA, roles/policies and audit foundation;
- database queue/session/cache, scheduler, health route, logs and backup hooks;
- WhatsApp/phone inquiry fallback while booking is not yet enabled.

Usable outcome: a secure, deployable public site and authenticated fleet/content administration. Customers can understand services and contact the operator without encountering a nonfunctional booking promise.

Acceptance:

- cPanel readiness and rollback demonstrated;
- staff MFA and permissions tested;
- public pages meet responsive, SEO and WCAG baseline;
- no unverified business claim or fleet data appears.

## Phase 2 — Core advance booking MVP

Ship:

- all four service-specific forms;
- place/route adapter and manual/custom-quote fallback;
- passenger/luggage/assistance suitability;
- exact-vehicle availability, buffers, maintenance/manual blocks and locked overlap checks;
- 15-minute checkout hold;
- itemized estimate snapshots and USD/approximate-LKR presentation;
- guest contact/review/terms/submission;
- on-screen reference and private under-review page;
- staff inbox, booking review, manual booking and basic schedule view;
- email/WhatsApp outbox with visible failure/retry state.

Usable outcome: travelers can submit real requests and staff can review them while every online/offline commitment shares one schedule. Staff may confirm and communicate manually under approved policy before automated proposal/payment features exist.

Acceptance:

- concurrent requests cannot reserve the same vehicle window;
- every form recovers from validation, stale availability, hold expiry and provider failure;
- customer submission is never labeled confirmed;
- manual booking blocks public availability immediately;
- critical customer and staff journeys pass mobile/desktop browser tests.

## Phase 3 — Proposal, payment and guest control

Ship:

- immutable proposal versions and comparisons;
- traveler acceptance/decline/change request;
- policy-controlled full/deposit/pay-later choices;
- selected hosted payment integration and verified idempotent webhooks;
- online/offline transaction ledger, receipts, refunds and reconciliation;
- cancellation and Rent extension requests;
- confirmed/service-day/completed/declined/cancelled/expired guest states;
- financial documents and message templates/reminders.

Usable outcome: the complete no-account booking lifecycle operates through the private link with controlled payment and staff-reviewed changes.

Acceptance:

- superseded/expired proposals cannot confirm;
- duplicate or delayed callbacks cannot double-charge or double-confirm;
- the accepted booking remains active during a pending revision;
- every financial state explains amount, currency, balance, deadline and support path;
- payment provider test suite and reconciliation scenarios pass.

## Phase 4 — Field and operational completion

Ship:

- five-vehicle day/week/month calendar and mobile agenda;
- tied-driver assignment and secure trip links;
- driver acknowledge/en-route/arrived/started/completed/issue flow;
- Rent collection/return document checks, condition records, signatures and photos;
- incidents, vehicle substitution, handover charges and deposit/refund outcome;
- permissioned exports, data requests and complete audit views;
- operational dashboard alerts for conflicts, deadlines, failed messages and payments.

Usable outcome: daily dispatch, chauffeured trips, Rent handover/return and exceptions are managed in the same audited system.

Acceptance:

- drivers see one assigned trip and no prices/internal notes;
- Rent release is blocked until required checks pass or a permitted exception is audited;
- calendar and mobile agenda represent every blocking state without color alone;
- incidents and sensitive evidence are permission-controlled;
- service-day and offline/reconnection scenarios pass on real mobile devices.

## Phase 5 — Launch hardening and handoff

Ship:

- approved real fleet imagery/content, prices, policies, legal/privacy/retention text and support operations;
- production provider accounts, webhooks, domain restrictions and templates;
- consent-aware funnel analytics and public SEO metadata;
- performance, accessibility, security and backup/restore fixes;
- staff training, operating procedures, support/escalation and launch runbook;
- production data import if the operator supplies an authoritative source.

Usable outcome: the approved system can accept real customer data and payments with trained operations and a tested recovery path.

Acceptance:

- WCAG 2.2 AA audit has no launch-blocking defects;
- critical pages meet agreed performance budgets on a throttled mobile connection;
- penetration test findings are resolved or explicitly accepted by an authorized owner;
- backup restore and application rollback are demonstrated;
- all launch-blocking client checklist items have owner, source, approval and effective date;
- go-live smoke tests and observation window are assigned.

## Test pyramid

### Unit

- duration/buffer calculations;
- suitability and eligibility rules;
- pricing components, money and exchange display;
- lifecycle transitions and deadlines;
- proposal comparison;
- token expiry/revocation;
- provider payload mapping and idempotency.

### Feature/integration

- each service form and validation branch;
- locked availability/hold/expiry/confirmation transactions;
- manual booking and maintenance blocks;
- proposal/payment/refund lifecycle;
- private/driver authorization boundaries;
- queue/outbox retry and failure state;
- policy/permission/audit requirements;
- handover/incident/data-request workflows.

### Concurrency

Run parallel tests for:

- two travelers selecting the same vehicle/window;
- hold expiry racing submission;
- staff proposal racing a maintenance block;
- Rent extension racing a later booking;
- duplicate payment webhook and browser return;
- two staff members applying conflicting updates.

### Browser

Playwright covers the minimum release journeys:

1. Airport Pickup request on mobile.
2. Airport Transfer recommended-time override warning.
3. Ride with ordered stops, separate return, selected-destination route map, and explicit customer-device location permission/granted/denied/unavailable states.
4. Rent eligibility, 24-hour summary and exact vehicle.
5. No availability and stale/expired hold recovery.
6. Staff manual booking and proposal.
7. Guest proposal acceptance and hosted payment return states.
8. Change/cancel/extension while original remains active.
9. Driver invalid/active/completed link.
10. Rent collection/return blocked and successful paths.

### Nonfunctional

- axe automated accessibility plus manual keyboard/screen-reader review;
- 320px, 390px, tablet and desktop responsive checks;
- slow/failed network and interrupted external-provider tests;
- Ride geolocation denial, unsupported/inaccurate/interrupted fixes, watch cleanup, static-route fallback, and proof that coordinates never leave browser memory;
- authorization/security regression suite;
- migration rollback compatibility;
- backup restore and cPanel release rollback;
- SQL/query-count and page-weight budgets.

## CI merge gate

Every change to main requires:

- locked dependency install;
- Pint pass;
- Larastan/PHPStan pass;
- Pest pass;
- frontend build and lint/format pass;
- critical Playwright and axe pass for affected journeys;
- dependency vulnerability audit;
- migration compatibility review when schema changes;
- documentation update when behavior, configuration, policy, or operations change.

## Release strategy

- Short-lived feature branches and reviewed pull requests.
- Feature flags only where a partially deployed external integration must remain inaccessible; flags are removed after rollout.
- Backward-compatible schema changes one release before destructive cleanup.
- Staging uses production-like PHP/database versions and provider sandbox accounts.
- Production deploy followed by automated health check and focused manual smoke test.
- Keep previous release and migration compatibility through the observation window.

## External accounts needed before their phase

| Account/key | Needed for | Owner |
|---|---|---|
| Map/place/route provider | Phase 2 location and route estimates | Product/technical owner with client cost approval |
| Meta WhatsApp Business | Phase 2 transactional WhatsApp | Client business owner |
| Transactional email domain/provider | Phase 2 email delivery | Client business/technical owner |
| Hosted payment merchant/gateway | Phase 3 payment and refunds | Client finance/legal owner |
| Error monitoring | Phase 1 production diagnostics | Technical owner |
| Analytics/consent configuration | Phase 5 public funnel measurement | Product/privacy owner |
| Off-host backup destination | Phase 1 recovery | Hosting/technical owner |

No production credential is requested or stored until its phase begins and the corresponding business owner approves its terms and cost.
