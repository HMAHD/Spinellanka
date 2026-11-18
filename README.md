# Spinellanka

**Status:** Foundation and responsive public shell implemented; booking workflows await verified operational inputs
**Platform:** Responsive Laravel web application
**Hosting target:** cPanel/LAMP
**Last updated:** 2026-11-18

Spinellanka is an advance-booking transport platform for international travelers arranging Sri Lankan mobility. Customers choose Airport Pickup, Airport Transfer, chauffeured Ride, or self-drive Rent; select an exact suitable vehicle from the operator's five-vehicle fleet; and submit without creating an account. Staff reviews every request, manages one authoritative vehicle schedule, and confirms the booking through a private secure link.

The repository now contains the Laravel 13 foundation, shared developer and agent environment, and the first production public shell. Booking, fleet, pricing, payment, messaging, and staff workflows remain gated by the approved delivery roadmap and verified client inputs.

## Repository guide

### Approved product and UX

- [Product context](PRODUCT.md)
- [UX documentation index](docs/ux/README.md)
- [Product requirements](docs/ux/01-product-requirements.md)
- [Information architecture](docs/ux/02-information-architecture.md)
- [User flows](docs/ux/03-user-flows.md)
- [Screen and state inventory](docs/ux/04-screen-and-state-inventory.md)
- [Wireframe annotations](docs/ux/05-wireframe-annotations.md)
- [Admin operations](docs/ux/06-admin-operations.md)
- [Client input checklist](docs/ux/07-client-input-checklist.md)
- [Acceptance and sign-off](docs/ux/08-acceptance-and-signoff.md)
- [Grayscale wireframe design system](docs/ux/09-wireframe-review-design-system.md)

### Client-review artifacts

- [Responsive wireframe walkthrough](wireframes/index.html)
- [Printable wireframe PDF](wireframes/sri-lanka-travel-mobility-wireframes.pdf)

The walkthrough is a grayscale structural artifact. It is not the production visual design.

### Approved implementation plan

- [Planning overview and approval gate](docs/planning/README.md)
- [Technology stack](docs/planning/01-technology-stack.md)
- [System architecture](docs/planning/02-system-architecture.md)
- [Domain and data model](docs/planning/03-domain-and-data-model.md)
- [Security and privacy](docs/planning/04-security-and-privacy.md)
- [cPanel deployment and operations](docs/planning/05-cpanel-deployment-and-operations.md)
- [Production design direction](docs/planning/06-production-design-direction.md)
- [Delivery roadmap and testing](docs/planning/07-delivery-roadmap-and-testing.md)

## Development

Use Node 26 from `.nvmrc`, PHP 8.4 where available, and PHP 8.3 as the compatibility floor.

```sh
nvm use
./scripts/check-environment
./scripts/bootstrap
```

See [shared development environment](docs/development/environment.md) for teammate setup, pinned agent tools, audit notes, and manual setup steps. Run `composer verify`, `npm run format:check`, and `npm run build` before handoff.

## Recommended implementation baseline

- Laravel 13 modular monolith.
- PHP 8.4 preferred; PHP 8.3 minimum.
- Apache 2.4 with MariaDB 10.11 preferred or MySQL 8.0 fallback.
- Blade + Livewire 4 + Alpine.js for customer workflows.
- Filament 5 for authenticated staff operations.
- Tailwind CSS 4 and Vite, compiled in GitHub Actions.
- Database-backed queue, cache, and sessions; cPanel cron for Laravel Scheduler and short-lived queue drains.
- Hosted payment checkout and provider-neutral map, WhatsApp, email, and payment adapters.
- No SPA, separate API, Redis, Horizon, microservices, or production Node runtime at launch.

The hosting account must provide PHP 8.3+ for web and CLI, required extensions, cron, SSL, writable Laravel directories, shell-based activation, and a domain document root targeting Laravel's `public/` directory. If it cannot, hosting must be upgraded rather than weakening the security boundary.

## Production visual direction

Only a color palette was retained from the temporary client reference; that file was deleted before Git initialization and was never committed. No reference layout, typography, copy, imagery, component, or interaction is design authority.

- **60% Cloud Canvas:** `#F8FAFC`
- **30% Spinel Navy:** `#0C1445`
- **10% Ceylon Amber:** `#FBBF24`

See the [production design direction](docs/planning/06-production-design-direction.md) for accessibility, typography, layout, components, imagery, and semantic-state rules.

## Product rules that implementation must preserve

- All services are scheduled in advance; there is no live ride dispatch.
- Airport Pickup, Airport Transfer, and Ride include a driver; Rent is self-drive.
- The customer selects an exact vehicle, not a vehicle class.
- All customer schedule times use Sri Lanka time.
- A selected vehicle receives a 15-minute checkout hold.
- Every submitted request remains pending until staff confirmation.
- Online, phone/WhatsApp, maintenance, handover, and buffer blocks share one authoritative schedule.
- Customers have no account; private booking links are scoped, expiring, and revocable.
- Estimates are itemized in USD with an approximate LKR value.
- Hosted payment providers handle card data; Spinellanka stores no raw card details.
- Staff use individual MFA-protected accounts and permissioned actions.
- WCAG 2.2 AA is the accessibility target.

## Authority and approval

If documentation conflicts:

1. Client-approved changes recorded in [acceptance and sign-off](docs/ux/08-acceptance-and-signoff.md) take priority.
2. [Product requirements](docs/ux/01-product-requirements.md) control functionality.
3. [Screen and state inventory](docs/ux/04-screen-and-state-inventory.md) controls UX coverage.
4. The planning documents control the approved technical implementation.
5. Wireframes illustrate the requirements but cannot silently add product behavior.

Implementation follows the approved written technical plan. Client-owned fleet facts, rates, policy values, legal/privacy/retention wording, support operations, and production provider accounts remain launch gates rather than reasons to fabricate data.
