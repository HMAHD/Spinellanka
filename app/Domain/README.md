# Domain modules

Spinellanka remains one Laravel deployment and one transactional database. Domain code is grouped by business ownership rather than delivery framework.

- **Catalog:** exact vehicles, verified facts, eligibility, drivers, extras, and maintenance state.
- **Booking:** service requests, traveler snapshots, service details, lifecycle, proposals, and confirmation.
- **Availability:** holds, schedule blocks, buffers, overlap checks, expiry, and audited overrides.
- **Pricing:** effective rules, immutable estimates, proposal totals, exchange display, and overrides.
- **Payments:** hosted checkout, transaction ledger, webhooks, balances, refunds, and reconciliation.
- **Messaging:** transactional outbox, templates, delivery attempts, retries, and visible failures.
- **Operations:** staff workspaces, handovers, trip updates, incidents, documents, and exports.
- **Identity:** staff access, roles, private guest links, driver links, and rate limits.
- **Audit:** material events, retention, data requests, and expiring exports.

Controllers, Livewire components, Filament pages, jobs, and webhook handlers call application/domain services. They must not own lifecycle, pricing, availability, payment, or audit rules.

Create a module directory only when its first behavior lands. Keep public interfaces narrow and keep provider adapters outside core rules.
