## Proposed design summary

### Building

A durable execution system containing:

- One dependency-aware roadmap for the complete product.
- One short-lived Git branch per independently mergeable phase.
- A standalone AI execution prompt inside every phase.
- Explicit phase ownership and risk.
- Required reading and mandatory skills per phase.
- Client-input, provider, architecture, and security stop gates.
- Exact verification, CI, browser, and acceptance requirements.
- PR handoff and review-fix procedures.
- A reusable post-launch change/bug prompt.
- A detailed local setup and testing guide for your friend.
- CI artifact, staging, cPanel deployment, rollback, and observation phases.
- A record of completed Phase 00 so later agents do not rebuild the foundation.

### Not building

- No application features during this roadmap task.
- No provider selection beyond already approved directions.
- No invented fleet, pricing, policy, legal, support, or identity content.
- No unsafe FTP-only Laravel deployment workaround.
- No long-lived `develop` branch.
- No phase that exposes incomplete or unsafe customer behavior.
- No automatic AI commits, pushes, merges, migrations, or deployments without maintainer authorization.

## Branch model

Each branch starts from the latest `main` **after all listed prerequisites merge**:

```text
main
 └── 00 Foundation and public shell — COMPLETE
      ├── 01 Hosting, CI and release foundation ─────────────┐
      └── 02 Staff identity, access and audit                │
            └── 03 Catalog, fleet and administration         │
                  ├── 04 Public content and fleet surfaces   │
                  ├── 05 Availability and schedule engine    │
                  └── 06 Pricing, policy and estimates       │
                         └──────────┬─────────────────────────┘
                                    07 Core Airport Pickup slice
                                      ├── 08 Airport Transfer
                                      ├── 09 Ride
                                      ├── 10 Rent
                                      └── 11 Outbox and notifications
                                             └── 12 Proposals and guest control
                                                   ├── 13 Payments and documents
                                                   └── 14 Calendar and driver operations
                                                          └── 15 Rent field operations
                                                                └── 16 Client/provider activation
                                                                      └── 17 Launch hardening
                                                                            └── 18 cPanel go-live
                                                                                  └── Maintenance lane
```

Branches that share satisfied prerequisites may run concurrently. A later numbered branch cannot begin merely because a maintainer is free.

## Phase queue

| Phase | Branch                                  | Primary owner  | Independently usable outcome                                                                                                        |
| ----- | --------------------------------------- | -------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| 00    | Already merged                          | Lead           | Laravel foundation, shared tooling, responsive shell                                                                                |
| 01    | `phase/01-hosting-ci-release`           | Lead           | Hosting capability report, stronger CI, immutable release artifact, deployment path decision                                        |
| 02    | `phase/02-identity-access-audit`        | Lead           | Filament staff shell, individual accounts, MFA, policies, permissions, audit foundation                                             |
| 03    | `phase/03-catalog-fleet-admin`          | Friend, guided | Exact-vehicle, driver, assignment, extra, media, and availability-status administration without fabricated records                  |
| 04    | `phase/04-public-content-fleet`         | Friend, guided | Public service, fleet, process, help, contact, and legal shells that show only verified content                                     |
| 05    | `phase/05-availability-schedule`        | Lead           | Transactional schedule blocks, buffers, maintenance, manual blocks, holds, expiry, and overlap protection                           |
| 06    | `phase/06-pricing-policy-estimates`     | Lead           | Effective-dated rules, integer-money calculations, immutable estimates, audited manual LKR basis                                    |
| 07    | `phase/07-airport-pickup-core`          | Lead           | First complete booking vertical: criteria, exact vehicle, hold, submission, private under-review page, staff review, manual booking |
| 08    | `phase/08-airport-transfer`             | Friend, guided | Airport Transfer workflow following the proven Phase 07 pattern                                                                     |
| 09    | `phase/09-ride`                         | Friend, guided | Ride workflow with ordered stops, waiting, and optional separately timed return                                                     |
| 10    | `phase/10-rent`                         | Lead           | Rent eligibility, 24-hour periods, exact self-drive vehicle, original-document inspection boundary                                  |
| 11    | `phase/11-outbox-notifications`         | Lead           | Transactional outbox, WhatsApp/email adapters, retries, delivery state, staff-visible failure                                       |
| 12    | `phase/12-proposals-guest-control`      | Lead           | Versioned proposals, acceptance, changes, cancellation, private guest lifecycle                                                     |
| 13    | `phase/13-payments-documents`           | Lead           | Hosted payment handoff, verified webhooks, transaction ledger, refunds, receipts and invoices                                       |
| 14    | `phase/14-calendar-driver-operations`   | Lead           | Unified calendar/mobile agenda, tied-driver assignment, scoped driver trip links                                                    |
| 15    | `phase/15-rent-field-incidents-privacy` | Lead           | Handover/return, condition evidence, incidents, exports, data requests, retention execution                                         |
| 16    | `phase/16-client-provider-activation`   | Lead           | Approved real content, fleet media, policy values, legal text, templates, provider sandbox activation                               |
| 17    | `phase/17-launch-hardening`             | Lead           | Accessibility, security, performance, recovery, staff training, backup/restore and operational runbooks                             |
| 18    | `phase/18-cpanel-go-live`               | Lead           | Production release, migration, cron/queue verification, smoke tests, rollback proof and observation window                          |

Your friend may help add tests or presentation work to high-risk phases, but cannot own:

- concurrency or schedule locking;
- payment/webhook behavior;
- private token security;
- authorization boundaries;
- destructive or data-transforming migrations;
- deployment, secrets, backup, or rollback;
- architecture or approved product-rule changes.

## Every phase document will contain

1. **Outcome and usable stopping point**
2. **Branch name and exact prerequisites**
3. **Owner and risk rating**
4. **Required reading**
5. **Mandatory skills and when to invoke them**
6. **Approved scope**
7. **Explicit non-scope**
8. **Expected modules and file areas**
9. **Decisions already fixed**
10. **Decisions that require maintainer/client approval**
11. **Client-input and provider gates**
12. **AI stop conditions**
13. **Implementation sequence**
14. **Required automated tests**
15. **Required real-browser/manual checks**
16. **CI merge gates**
17. **Security and data checks**
18. **Documentation and decision-record updates**
19. **Rollback considerations**
20. **PR handoff checklist**
21. **Review-feedback repair loop**
22. **A standalone copyable AI prompt**

No phase prompt will contain `TBD`, `TODO`, or “decide later.” Missing external inputs become named blockers with an owner and an exact stop action.

## Mandatory AI behavior

Every phase prompt will require the AI to:

- Read [CLAUDE.md](CLAUDE.md), [PRODUCT.md](PRODUCT.md), the relevant phase document, applicable planning/UX documents, [DESIGN.md](DESIGN.md), and existing tests.
- Query Graft before reading application source.
- Preserve the Laravel modular-monolith boundaries.
- Put booking, availability, pricing, payment, audit, and lifecycle behavior in domain/application services.
- Run Modern Web Guidance before browser work.
- Run Impeccable for public UI and design-system changes.
- Use the complete EGM workflow for multi-component architecture.
- Use Hunt before fixing an unknown defect.
- Use runtime/browser verification for UI behavior.
- Treat Ask Matt as user-invoked only.
- Keep Caveman style out of repository prose, comments, commits, and external text.
- Never invent client-owned facts.
- Never add generated-by text, watermarks, signatures, or co-author trailers.
- Stop rather than guess when a request conflicts with product truth, security, architecture, approved design, or unavailable evidence.
- Avoid committing or pushing until checks pass and the maintainer explicitly requests it.

If an AI lacks a mandatory repository skill, tool, or runtime, it must stop and name the missing capability rather than silently substitute a weaker process.

## Post-launch change and bug protocol

The reusable prompt will force any AI to:

1. Classify the request as:
    - confirmed bug;
    - client-requested product change;
    - content/data update;
    - policy/legal update;
    - provider/operations change;
    - architecture change;
    - subjective design preference.
2. Record the request and evidence before editing.
3. Locate the controlling authority:
    - acceptance/sign-off;
    - product requirements;
    - architecture;
    - security;
    - design system;
    - provider or operational record.
4. Reproduce bugs before fixing them.
5. Map impact across domain, database, UI, security, integrations, deployment, tests, and documentation.
6. Use the narrowest responsible change.
7. Create a `CHG-<issue-number>` or `BUG-<issue-number>` record.
8. Run the applicable skills.
9. Stop for approval when the change alters:
    - product invariants;
    - data retention or legal meaning;
    - pricing/payment behavior;
    - access or security boundaries;
    - schema compatibility;
    - provider commitments;
    - palette, design hierarchy, or public claims.
10. Prove the change in tests and the real runtime.
11. Update every affected decision and operational document.
12. Produce a PR handoff with evidence, risks, rollback, and unresolved client inputs.

Maintenance branches will use:

- `change/CHG-<issue>-<short-name>`
- `fix/BUG-<issue>-<short-name>`
- `hotfix/BUG-<issue>-<short-name>` only for production incidents

## Local setup guide

The detailed guide will cover:

- macOS/Linux and Windows through WSL2;
- PHP 8.4 preferred and PHP 8.3 compatibility;
- Composer 2;
- Node 26 through `.nvmrc`;
- npm 11 and canonical `package-lock.json`;
- optional Bun;
- MySQL 8/MariaDB 10.11 database creation;
- local database user and `.env` setup;
- Laravel key generation and migrations;
- Composer/npm installation;
- application, queue, and Vite startup;
- local URLs and smoke checks;
- tests, formatting, analysis, audits, and build;
- safe local database reset warnings;
- exact agent-skill installation and verification;
- branch creation and PR workflow;
- troubleshooting for PHP extensions, ports, MySQL, writable folders, Vite, queues, and stale caches;
- prohibition on sharing `.env`, application keys, provider credentials, or production data.

## CI and review gates

`main` will require:

- pull request;
- your approval;
- current branch rebased or merged from current `main`;
- Composer lock validation and install;
- Pint;
- Larastan/PHPStan;
- Pest;
- frontend formatting and production build;
- Composer and production npm audits;
- Playwright and axe for affected journeys once introduced;
- migration compatibility review;
- documentation checks;
- no unresolved review comments.

GitHub’s production environment will require manual approval where the repository plan supports required reviewers. If the private-repository plan does not support that feature, production remains `workflow_dispatch`-only with secrets isolated to the production environment and a documented manual approval step.

## cPanel position

The first deployment phase will test:

- PHP 8.3+ web and CLI;
- required extensions;
- MySQL/MariaDB transactions;
- cron;
- safe `public/` document root;
- writable private application directories;
- cPanel Git Version Control;
- `.cpanel.yml` deployment tasks;
- SSH or browser Terminal;
- symlink support;
- release storage limits;
- SSL, backup, and restore capabilities.

Accepted paths:

1. GitHub Actions builds an immutable artifact; restricted SSH activates it.
2. GitHub Actions transports the artifact; cPanel Git/`.cpanel.yml` runs the verified activation sequence.
3. SFTP transports the artifact; verified cPanel Terminal activates it.

Rejected path:

- File Manager/FTP upload with no server-side PHP command execution, no migrations, and no atomic activation.

If the capability gate fails, Phase 18 stops and requires a better cPanel tier or Laravel-capable host. No public deployment endpoint will be added as a workaround.

## Documentation layout

This is intentionally more than eight files because each phase prompt must stand alone:

- `docs/roadmap/README.md`
- `docs/roadmap/branch-and-pr-workflow.md`
- `docs/roadmap/phases/00-...md` through `18-...md`
- `docs/roadmap/prompts/change-or-bug.md`
- `docs/roadmap/prompts/review-fix.md`
- `docs/roadmap/change-records.md`
- `docs/development/local-setup.md`
- updates to [docs/planning/07-delivery-roadmap-and-testing.md](docs/planning/07-delivery-roadmap-and-testing.md)
- updates to [docs/planning/README.md](docs/planning/README.md)
- an onboarding pointer from [README.md](README.md)

The roadmap work itself will use the full EGM brainstorm, draft, review, and resolve sequence on branch `docs/full-project-roadmap`.

## Assumption and failure boundary

This plan assumes the client will eventually supply verified fleet data, rates, policies, legal wording, provider accounts, and imagery. Missing inputs will not block safe internal foundations, but they will block activation of dependent customer behavior and production launch.

Strict phase-number parallelism was rejected because it would let a later branch build against unmerged interfaces and force your friend to resolve architecture conflicts.

## Deferred facts with owners

- **cPanel capabilities:** hosting owner; resolved in Phase 01.
- **Fleet and business content:** client fleet/content owner; required before Phases 03, 04, and 16 activate real records.
- **Pricing and policy examples:** client operations/finance owner; required before Phase 06 acceptance.
- **Rent/legal/retention rules:** client legal/insurance owner; required before Phases 10, 15, and 16.
- **Payment merchant account:** client finance/legal owner; required before Phase 13.
- **WhatsApp/email accounts:** client business owner; required before Phase 11 production activation.
- **Production approval:** you, after Phase 17 evidence and Phase 18 rehearsal.

### Sources

- [cPanel Git deployment](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/)
- [cPanel Git Version Control](https://docs.cpanel.net/cpanel/files/git-version-control/)
- [cPanel Terminal requirements](https://docs.cpanel.net/cpanel/advanced/terminal-in-cpanel/)
- [GitHub deployment environments](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments)
- [GitHub deployment reviews](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/review-deployments)
- [Laravel 13 deployment](https://laravel.com/framework/docs/13.x/deployment)
- [Laravel 13 scheduling](https://laravel.com/framework/docs/13.x/scheduling)

Approve this structure, and I will create branch `docs/full-project-roadmap`, run the full EGM workflow, write all roadmap/setup/prompt documents, validate their links and instructions, and leave the branch ready for your review without committing or pushing.
