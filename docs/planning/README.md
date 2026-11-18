# Spinellanka — Post-approval implementation plan

**Status:** Proposed technical baseline — awaiting implementation approval
**Prepared:** 2026-11-18
**Product authority:** [Approved UX requirements](../ux/01-product-requirements.md)

## Recommended direction

Build Spinellanka as a **Laravel 13 modular monolith** on a conventional cPanel LAMP account:

- PHP 8.4 preferred; PHP 8.3 is the minimum accepted runtime.
- Apache 2.4 with the domain document root pointed at Laravel's `public/` directory.
- MariaDB 10.11 LTS preferred; MySQL 8.0 is an acceptable host-provided alternative.
- Server-rendered Blade pages with Livewire 4 and Alpine.js for customer interactions.
- Filament 5 for the authenticated operations panel, with custom pages for the booking workspace, exact-vehicle calendar, handovers, and incidents.
- Tailwind CSS 4 compiled by Vite in CI; Node is not required in production.
- Database-backed queue, cache, and session stores so the app can operate without Redis or a resident process manager.
- GitHub Actions for test/build/release artifacts, then SSH deployment to cPanel; SFTP may transport artifacts when cPanel Terminal handles activation.

This gives a small five-vehicle operator one deployable application, one database, one audit trail, and one availability authority. It avoids a SPA, public API, microservices, Redis, containers, and other infrastructure that cPanel does not need.

## Load-bearing hosting gate

This plan assumes the cPanel account provides **PHP 8.3 or newer for both web and CLI**, the Laravel-required extensions, MariaDB/MySQL, cron jobs, SSL, a writable application directory outside the public document root, and shell-based release activation through SSH or cPanel Terminal. SFTP may be used for artifact transport.

If PHP CLI, cron, or safe `public/` document-root control is unavailable, do not weaken the architecture or expose the application root. Move to a Laravel-capable cPanel plan or a small managed VPS before implementation.

## Planning documents

| Document | Decision covered |
|---|---|
| [01 — Technology stack](01-technology-stack.md) | Framework, runtime, frontend/admin approach, dependencies, rejected alternatives |
| [02 — System architecture](02-system-architecture.md) | Modules, request paths, integration boundaries, queues, failure handling |
| [03 — Domain and data model](03-domain-and-data-model.md) | Core entities, time/money rules, lifecycle, invariants, concurrency |
| [04 — Security and privacy](04-security-and-privacy.md) | Staff MFA, private links, PII, payments, webhooks, audit, retention |
| [05 — cPanel deployment and operations](05-cpanel-deployment-and-operations.md) | Hosting gate, directory model, CI/CD, cron, backup, rollback, monitoring |
| [06 — Production design direction](06-production-design-direction.md) | Approved 60/30/10 palette, typography, layout, components, accessibility |
| [07 — Delivery roadmap and testing](07-delivery-roadmap-and-testing.md) | Independently mergeable phases, CI checks, release and acceptance strategy |

## Scope of this plan

Included:

- technology and architecture decisions;
- production visual direction;
- database/domain boundaries;
- cPanel deployment model;
- security, testing, observability, backup, and rollback;
- integration contracts and procurement gates;
- staged implementation order.

Not included:

- production code or framework scaffolding;
- client rates, fleet facts, legal wording, policy values, or imagery that remain client inputs;
- payment, messaging, map, or email credentials;
- a commitment to any payment gateway until the client's Sri Lankan merchant account and settlement requirements are verified.

## Planned surface delta

Implementation adds these durable surfaces and no others at launch:

- public marketing/service pages and four advance-booking workflows;
- one scoped private guest-booking URL family;
- one smaller scoped driver-trip URL family;
- one MFA-protected staff panel;
- provider webhook endpoints for the selected hosted payment and messaging integrations;
- Laravel's authenticated/controlled operational jobs and public `/up` health response;
- one MariaDB/MySQL schema and one private file-storage area.

There is no public API, native app, customer account system, long-running websocket service, or launch-time CLI for business users. Required external services and configuration groups are named in [01 — Technology stack](01-technology-stack.md) and [05 — cPanel deployment and operations](05-cpanel-deployment-and-operations.md).

**Minimal path considered:** publish a static service site and send every request to WhatsApp. It is cheaper initially but cannot provide authoritative exact-vehicle availability, checkout holds, conflict-safe manual bookings, proposal/payment history, or audited Rent handovers. The modular monolith earns its additional cost by owning those approved requirements in one transaction boundary.

## Alternatives rejected

- **WordPress/plugin assembly:** booking availability, proposal versioning, private guest links, payment reconciliation, handover records, and audit behavior are domain logic rather than a content-site plugin problem.
- **Symfony application:** technically sound, but Laravel has the better fit for a small team, cPanel, Livewire, Filament, notifications, queues, scheduling, and the referenced ecosystem.
- **React/Vue SPA plus API:** adds two application surfaces, token/auth complexity, and a Node runtime/build discipline without improving this no-account booking workflow.
- **Microservices:** five vehicles and one operations team need transaction integrity, not distributed coordination.
- **Redis/Horizon at launch:** useful later, but not required for the expected load and commonly unavailable on shared cPanel.

## Evidence used

The recommendation was checked against:

- [PHP LibHunt](https://php.libhunt.com/) for current PHP category coverage;
- [Awesome PHP](https://github.com/ziadoz/awesome-php) for maintained framework, quality, security, queue, and deployment options;
- [Awesome Selfhosted — PHP](https://awesome-selfhosted.net/platforms/php.html) for conventional PHP/MySQL deployment patterns;
- [Awesome Laravel](https://github.com/filastudio/awesome-laravel) for the current Laravel/Livewire/Filament ecosystem;
- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) and [deployment requirements](https://laravel.com/docs/13.x/deployment);
- [Filament 5 installation requirements](https://filamentphp.com/docs/5.x/introduction/installation);
- [cPanel cron documentation](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).

These references are evidence, not dependency shopping lists. Every non-framework package must still justify its maintenance and security cost before installation.
