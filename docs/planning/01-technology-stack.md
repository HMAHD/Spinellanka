# 01 — Technology stack

## Decision

Use a **Laravel 13 modular monolith** with server-rendered customer pages and a Filament operations panel. Build and test assets in GitHub Actions; run the production application on Apache, PHP, and MariaDB/MySQL in cPanel.

## Runtime and platform

| Layer | Selection | Reason |
|---|---|---|
| OS/hosting | Existing Linux cPanel account | Client-owned hosting constraint |
| Web server | Apache 2.4 | Native cPanel LAMP path and `.htaccess` support |
| PHP | PHP 8.4 preferred; 8.3 minimum | Laravel 13 requires PHP 8.3; 8.4 gives a longer runway where cPanel supports it |
| Framework | Laravel 13.x, locked by Composer | Current supported Laravel line; strong fit for queues, notifications, scheduling, validation, signed URLs, storage, and testing |
| Database | MariaDB 10.11 LTS preferred; MySQL 8.0 fallback | Available on mainstream cPanel, transactional, sufficient for five-vehicle concurrency |
| Dependency manager | Composer 2 | Standard PHP dependency and reproducible-lock workflow |
| Asset build | Vite with Node 26 LTS in CI only | Production server receives compiled assets and does not need Node |
| CSS | Tailwind CSS 4 with project tokens | Fast implementation of a constrained responsive system without copying the discarded reference UI |
| Browser behavior | Alpine.js plus Livewire 4 | Reactive forms and operational tools without a separate SPA/API application |

## Customer application

Use Blade layouts and Livewire components for:

- the four service-specific search forms;
- location selection and route summaries;
- suitability and exact-vehicle results;
- the 15-minute checkout hold;
- guest contact/review/submission;
- the secure private booking page;
- proposal acceptance, payment handoff, changes, cancellation, and Rent extension.

Public pages remain server-rendered for SEO, resilience on roaming connections, and simple error recovery. JavaScript enhances the workflow but does not become a second application architecture.

## Internal operations

Use **Filament 5 Panel Builder** for individual staff accounts, navigation, resources, forms, tables, actions, and policy-aware administration. Build custom Filament/Livewire pages for the surfaces where generic CRUD is not enough:

- operations dashboard;
- booking review and proposal comparison;
- five-vehicle day/week calendar and mobile agenda;
- Rent collection/return checklists;
- incident handling;
- audit and export workflows.

Core booking, pricing, availability, payment, and audit rules live in application/domain services, never in Filament page classes. The panel is a client of the same domain rules as the customer flow.

## Authentication and authorization

- Customers have no accounts.
- Owner/Admin and Staff use Laravel-authenticated individual accounts.
- Filament's supported multi-factor authentication is required for internal users.
- Laravel policies enforce authorization at record and action level.
- `spatie/laravel-permission` is the one planned authorization package because the approved requirements include capability-level permissions such as conflict override, export, refunds, configuration, and audit access.
- Driver access uses a random, expiring, revocable, trip-scoped token rather than an account.

## State, validation, and background work

- PHP backed enums represent service and lifecycle vocabularies.
- Explicit transition services enforce allowed booking state changes; no third-party state-machine package is needed.
- Laravel Form Requests and domain validation enforce input and business rules.
- Laravel database queues handle outbound messages, payment reconciliation, document creation, and non-blocking integration work.
- Laravel Scheduler triggers hold expiry, reminders, queue draining, retention jobs, health checks, and backups through cPanel cron.
- Redis and Horizon are deliberately excluded from launch. The queue/cache drivers can move to Redis later without changing domain behavior if hosting changes.

## Quality stack

| Concern | Selection |
|---|---|
| Unit and feature tests | Pest on PHPUnit |
| Browser-critical journeys | Playwright against a built test environment |
| Static analysis | Larastan/PHPStan at a strictness level increased during implementation |
| Formatting | Laravel Pint |
| Dependency security | Composer audit plus GitHub dependency alerts |
| Frontend formatting/linting | Prettier and the smallest required ESLint surface |
| Accessibility | axe in browser tests plus manual keyboard/screen-reader checks |
| Performance | Laravel query inspection in development and launch-budget checks in CI/manual QA |

## Package policy

Framework features are preferred over packages. A package may be introduced only when it:

1. solves an approved requirement;
2. supports Laravel 13 and the selected PHP runtime;
3. has an active release and security posture;
4. does not own core business data in an opaque schema;
5. can be replaced without rewriting the booking domain;
6. receives an automated test around the behavior Spinellanka relies on.

Planned non-framework dependencies are limited to Filament, Livewire, Spatie Permission, Pest, Larastan, and integration SDKs selected after provider verification.

## External provider decisions and gates

The application contracts are fixed now; account-specific vendor activation is deferred only where ownership, pricing, legal, or merchant facts are not yet available.

| Capability | Recommended launch direction | Why activation is deferred | Decision owner | Hard deadline/gate |
|---|---|---|---|---|
| Place search and routing | Google Maps Platform Places and Routes APIs behind provider-neutral adapters | Billing account, allowed domains, Sri Lanka coverage/cost limits, and contractual approval require the client's account | Technical owner with client cost approval | Approved key and quota before Phase 2 route integration begins |
| WhatsApp | Meta WhatsApp Cloud API, client-owned Business Portfolio and number | Business verification, number ownership, message-template approval, and support process belong to the operator | Client business owner | Verified account/templates before Phase 2 transactional messaging is enabled |
| Transactional email | Postmark with a client-owned sending domain | DNS authentication, sender identity, cost, and privacy terms require client approval | Client business owner with technical owner | Domain and production server approved before Phase 2 email is enabled |
| Hosted payments | Sri Lankan merchant gateway selected from options supported by the client's bank and legal entity | Merchant eligibility, settlement currency, deposits, partial payment, refunds, 3-D Secure, fees, and webhook quality cannot be inferred | Client finance/legal owner with technical verification | Signed sandbox/merchant choice before Phase 3 implementation begins |
| Error reporting | Sentry SaaS with PII scrubbing, or a client-approved equivalent | Data-processing terms, region, retention, and plan cost need approval | Technical/privacy owner | Approved before the first production-like Phase 1 deployment |
| Off-host backups | Encrypted S3-compatible storage in a client-owned account | Region, retention, recovery ownership, and cost are operational decisions | Hosting/technical owner | Provisioned and restore-tested before any production data is accepted |
| Product analytics | No third-party analytics by default; add consent-aware Plausible or equivalent only after privacy approval | Marketing need and consent/legal basis are not established | Product/privacy owner | Decision before Phase 5 analytics work; omission does not block core launch |
| Exchange rate | No live provider at launch; use an audited staff-entered rate with source note and effective time | A live dependency adds failure/cost without improving the legally stated approximate value | Product/finance owner | Rate source and update procedure approved before Phase 2 estimates go live |

No production credential is required during foundation scaffolding. Secrets are introduced only at the named gate and stored in deployment secret stores, never in Git.

## Close alternatives

The closest alternative is Laravel with Inertia and Vue. It would improve highly interactive client-side screens but would add API-like page contracts, more JavaScript state, and heavier browser bundles. Livewire is the better default for form-heavy advance booking and a small cPanel-hosted operations team. A future isolated map or calendar widget may use a focused JavaScript library without changing the overall architecture.

## Premise collapse

This choice depends on PHP 8.3+ CLI, cron, and safe document-root control. If the purchased cPanel plan cannot provide those, the correct response is a hosting upgrade—not Laravel 12, unsafe public-root workarounds, or synchronous execution of every integration.
