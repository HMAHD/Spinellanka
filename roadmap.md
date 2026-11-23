# Spinellanka Full-Project Execution Roadmap

**Status:** Active execution plan  
**Last reviewed:** 2026-11-23  
**Delivery model:** Dependency-ready queue, one short-lived branch and pull request per remaining phase  
**Merge owner:** Project lead  
**Production schedule authority:** `Asia/Colombo`

## 1. Purpose

This document is the single execution roadmap from the current Laravel foundation through production launch and post-launch maintenance. It is written for two maintainers working with AI assistants. One maintainer is the project lead; the second maintainer receives only guided, lower-risk work. Every phase below defines its prerequisites, branch, owner, deliverable, stop conditions, verification, pull-request handoff, and a standalone prompt that can be copied into an AI coding session.

An AI prompt cannot guarantee error-free work. The safety boundary is the combination of narrow phase scope, verified inputs, protected `main`, mandatory CI, explicit stop conditions, project-lead review, and human-controlled merge and deployment.

This roadmap decomposes the five planned delivery phases in `docs/planning/07-delivery-roadmap-and-testing.md`; it does not itself establish client approval or replace their product scope and acceptance criteria.

## 2. Authority and conflict order

Before changing the application, use these sources in this order:

1. An **Approved** or **Approved with recorded changes** revision of `docs/ux/08-acceptance-and-signoff.md` governs client-approved product/UX behavior and decided `CHG-###` changes. Its current `Draft 2` / `Pending` revision is proposed input, not full client sign-off; individually recorded change decisions still require the named owner's authority and evidence.
2. `PRODUCT.md` governs product truth and non-negotiable behavior.
3. `docs/planning/01-technology-stack.md` through `docs/planning/05-cpanel-deployment-and-operations.md` plus `docs/planning/07-delivery-roadmap-and-testing.md` govern architecture, data, security, deployment, and delivery constraints.
4. `docs/ux/07-client-input-checklist.md` governs the provenance/readiness of client-owned facts and policies; a blank or unchecked item is unavailable.
5. `DESIGN.md`, `.impeccable/design.json`, and `.impeccable/surfaces/` govern the currently shipped public visual system. `docs/planning/06-production-design-direction.md` records its initial direction and does not override a later shipped design rule unless an approved design change updates both.
6. This roadmap governs phase order, branch ownership, implementation scope, and handoff rules.
7. Current code and tests govern already-shipped behavior that does not conflict with the applicable authorities above.

If two authorities conflict, stop and record the exact file and section references. The project lead coordinates resolution, but only the authorized owner for the affected product, legal, finance, privacy, security, architecture, or design domain may approve a source change. Record approver, role, date, affected revision, and a non-secret evidence reference before work resumes. Never silently reinterpret a product invariant.

Until an acceptance revision is signed off, only a phase separately authorized by the project lead may start, and all client-dependent behavior remains `Waiting`. A phase status of `Ready` records satisfied technical prerequisites; it never grants permission to start, push, merge, or deploy.

## 3. Product invariants that every phase preserves

- Spinellanka offers four advance-booked services: Airport Pickup, Airport Transfer, Ride, and Rent.
- Airport Pickup, Airport Transfer, and Ride include a driver. Rent is self-drive.
- Customers choose an exact vehicle, not a vehicle class.
- The current fleet count is five, but no vehicle facts appear until the client verifies them.
- `Asia/Colombo` is the authoritative business schedule timezone. A traveler-local equivalent is display guidance only.
- Exact-vehicle selection creates a visible 15-minute checkout hold.
- Submission is a request and remains pending until staff confirmation.
- Customers have no account, password, or OTP.
- A valid request requires an international WhatsApp/mobile contact; email is optional. Booker and lead-traveler/Rent-driver details have separate purposes. Required booking/privacy acceptance is separate from optional, initially unchecked marketing consent.
- Guest and driver access use separate, scoped, expiring, revocable, high-entropy private links. Only token hashes are stored.
- Money uses integer minor units and ISO currency codes. USD is primary display currency; LKR is explicitly approximate.
- Browser payment returns are never proof of settlement. Hosted checkout and verified provider webhooks are required.
- Online, phone, WhatsApp, maintenance, handover, and manual bookings share one authoritative vehicle schedule.
- A failed message never rolls back committed booking, proposal, or payment state.
- A previously accepted booking remains authoritative while a revision is pending.
- Provider integrations stay behind provider-neutral interfaces.
- Ride shows selected destinations on a route map. On the active Ride page, an explicitly enabled current-device marker may update in browser memory only; no coordinate reaches Spinellanka servers, providers, analytics, logs, storage, or business state. This is not driver/vehicle tracking, live dispatch, trip proof, or emergency monitoring.
- No production fact, price, policy, legal text, review, support promise, credential, or image may be invented.

## 4. Engineering and security boundaries

- Keep one Laravel 13 modular monolith and one transactional MariaDB/MySQL database.
- Use Blade, Livewire 4, Alpine.js, Tailwind CSS 4, and Vite for public/customer workflows; use Filament 5 for staff operations.
- Keep controllers, Livewire components, Filament pages/actions, jobs, and webhook handlers thin.
- Put lifecycle, availability, pricing, payment, authorization, and audit rules in domain/application services.
- Use database-backed queue, cache, and sessions. Do not add Redis, Horizon, a SPA, a public API, a microservice, or a production Node runtime without an approved architecture change.
- Prefer Laravel/framework behavior over new packages. Any new package must satisfy `docs/planning/01-technology-stack.md` and have a focused automated test.
- Use PHP 8.3-compatible syntax. PHP 8.4 remains the preferred local runtime.
- Require individual MFA-protected staff accounts. Never create a shared admin account.
- Apply policies/permissions to routes, record queries, Livewire/Filament actions, queued commands, exports, and sensitive operations.
- Keep `.env`, application keys, provider secrets, private tokens, production data, backups, and credentials out of Git, prompts, logs, screenshots, and PR descriptions.
- Do not store raw card data, identity-document images, or Ride current-device coordinates. Staff record approved original-document inspection outcomes; Ride geolocation remains ephemeral browser memory with explicit permission and cleanup.
- Use backward-compatible expand/contract migrations. Keep the previous release able to run during the observation window.
- Never weaken Laravel security to fit cPanel. If hosting lacks PHP CLI, cron, or safe `public/` document-root control, upgrade or move hosting.

## 5. Required AI and design workflow

For every phase:

1. Read repository `CLAUDE.md` and this roadmap before acting.
2. Query Graft before reading or searching application source. Use direct file tools for documentation, configuration, and files Graft does not index.
3. Run full EGM (`/egm-brainstorm`, `/egm-draft`, `/egm-review`, `/egm-resolve`) before a multi-component architecture change. Store its artifacts in `docs/designs/`.
4. Run Modern Web Guidance before editing HTML, CSS, client-side JavaScript, forms, accessibility, performance, or browser-platform behavior. Apply relevant fallbacks and cite the guides in the handoff.
5. Run Impeccable for public UI and design-system work. Preserve `.impeccable/surfaces/`, update `DESIGN.md` and `.impeccable/design.json` when the shipped system changes, and inspect 320px, 390px, 768px tablet, and 1440px renders.
6. Invoke Ask Matt only when the user explicitly requests `/mattpocock-skills:ask-matt`.
7. Use Caveman only when the user invokes it. Keep repository prose, code comments, commits, PR text, and third-party messages in normal English.
8. Diagnose bugs before changing code. Reproduce the symptom and state one evidence-backed root cause.
9. Run the application and interact with affected user journeys; compilation alone is not runtime proof.

If a required skill or runtime is unavailable, stop and report the missing capability. Do not silently replace the required process.

### Exact tool invocations and pass evidence

Run these from the repository root. Replace the example query/target with the current phase's single capability or surface.

| Gate                          | Exact invocation                                                                                                                                                                                                                                                                                                   | Required evidence and pass condition                                                                                                                                                                                                                                                                                                                     |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Graft source lookup           | Use the connected `graft_find_code` MCP tool, or `graft ask "where is the affected behavior" --source`; use `graft grep "Symbol"` when every occurrence is required and `graft callers Symbol --depth all` before a shared refactor.                                                                               | The handoff names the returned application files/symbols and blast radius. If Graft is unavailable or stale, stop and repair/align the shared environment before application-source work.                                                                                                                                                                |
| Full EGM                      | In order: `/egm-brainstorm`, `/egm-draft`, `/egm-review`, `/egm-resolve`.                                                                                                                                                                                                                                          | A resolved, decision-complete artifact exists under `docs/designs/`, has no unresolved blocker, and the applicable planning document reflects the approved decision.                                                                                                                                                                                     |
| Modern Web Guidance           | `npx -y modern-web-guidance@latest search "<2–5 word capability>" --skill-version 2026_11_10-47b4c80e`, then `npx -y modern-web-guidance@latest retrieve "<matching-id>"`. Rephrase the search once when results are weak.                                                                                         | The handoff lists each applied guide ID, relevant fallback, and required quote. If no guide matches after the prescribed search, record the checked capability and that no guide applied.                                                                                                                                                                |
| Impeccable context and review | Invoke `/impeccable <appropriate-command> <target>`; from a shell-enabled agent, run `~/.claude/skills/impeccable/scripts/impeccable context --target <target>` once per UI session before following the selected Impeccable command. Use `audit` for technical UI quality and `polish` for the final visual pass. | The matching surface contract was followed, hook/detector findings were fixed or recorded as a narrow sanctioned exception, 320px, 390px, 768px tablet, and 1440px renders were inspected, and the handoff includes screenshots plus the final review result. `/impeccable hooks status` must show the project detector active when UI files are edited. |
| Runtime/browser proof         | Invoke `/run` or follow the committed project run instructions, then drive the changed journey in a real browser.                                                                                                                                                                                                  | The handoff records the URL, viewport, interaction path, visible result, console errors, keyboard behavior, and failure/recovery checks. A build alone does not pass.                                                                                                                                                                                    |
| Ponytail phase review         | Invoke `/ponytail-review branch` after implementation and before final handoff. Ponytail must be the reviewed official `DietrichGebert/ponytail` plugin at project baseline `v5.3.3`.                                                                                                                              | Every finding includes a concrete failure case and minimal fix. Confirmed in-scope findings are fixed and retested; plan/dependency defects are recorded and escalated rather than hidden in implementation. The final verdict is `ship` only when no Must-fix finding remains.                                                                          |
| Ask Matt                      | Only the user may invoke `/mattpocock-skills:ask-matt`.                                                                                                                                                                                                                                                            | No automatic invocation. If the user invokes it, record relevant advice and independently verify it against project authority and tests.                                                                                                                                                                                                                 |

Angle-bracket values in this table are substitution instructions, not repository values. Never paste a secret as a target, query, or command argument.

## 6. Roles and ownership

### Project lead

The project lead owns:

- architecture and module boundaries;
- staff identity, MFA, permissions, private links, and security;
- availability locking and schedule consistency;
- pricing and policy interpretation;
- core booking transactions and lifecycle;
- Rent eligibility and policy-sensitive behavior;
- proposals, payments, refunds, webhooks, and reconciliation;
- privacy, retention, incidents, deployment, secrets, backups, and launch approval;
- all final PR review and merges.

### Guided maintainer

The guided maintainer may own only the phases explicitly marked **Guided maintainer**. Suitable work is pattern-based catalog administration, verified-data public UI, service expansion after the core pattern exists, tests, accessibility fixes, and documentation.

The guided maintainer must stop and request lead review before changing:

- a migration after it has run in any shared or production-like environment;
- booking lifecycle or state transitions;
- overlap, hold, expiry, or locking behavior;
- pricing formulas, money, exchange, deposit, refund, or settlement rules;
- authentication, authorization, tokens, rate limits, uploads, exports, or retention;
- provider contracts, webhook verification, production configuration, or deployment;
- client-owned facts or any rule not explicitly approved.

A guided phase with a schema migration requires the lead to review the migration and rollback path before the branch is pushed for final PR review.

## 7. Branch, PR, and merge protocol

### Starting a phase

1. Confirm every prerequisite phase is merged into `main`.
2. Confirm every named client/provider/hosting gate is satisfied or that the phase explicitly permits a safe disabled adapter.
3. Ensure the working tree has no unrelated changes. Do not discard another maintainer's work.
4. Update local `main` from the remote.
5. Create the exact branch named in the phase.
6. Copy the phase prompt into a fresh AI session.
7. Keep one phase per branch. Do not add opportunistic refactors or fixes.

Never branch from another unmerged phase branch. If two phases are independent, each starts from the same current `main`; any later conflict is resolved after one prerequisite merges and the other branch updates from `main`.

### While working

- Commit only phase-owned files and behavior.
- Use small Conventional Commit messages containing project content only.
- Never add generated-by text, session URLs, watermarks, signatures, or co-author trailers.
- Do not push, open a PR, run production migrations, or deploy unless the project lead explicitly authorizes that action.
- Record external facts with owner, source, approval status, effective date, and evidence location.
- Keep production integrations disabled until their phase gate passes.

### Pull request body

Every PR must contain:

- phase number and outcome;
- prerequisite phase commits or merged PRs;
- scope implemented and non-scope preserved;
- changed modules, schema, configuration, and public surfaces;
- decisions made and exact source/approval evidence;
- migrations and rollback compatibility;
- security/privacy impact;
- automated commands and results;
- manual/runtime paths exercised;
- desktop/mobile screenshots for UI work;
- remaining blockers and client/provider inputs;
- deployment or operational notes;
- rollback instructions.

### Review-fix loop

When CI or the lead reports a problem:

1. Reproduce the exact finding on the existing phase branch.
2. Determine whether it is in phase scope. If not, report it without bundling it.
3. State the root cause before editing.
4. Add or strengthen a regression test when behavior was wrong.
5. Make the narrowest correction.
6. Re-run the failing check, targeted tests, and full phase gates.
7. Update the PR evidence; do not erase prior discussion.
8. Request re-review. The lead alone decides when to merge.

## 8. Definition of ready and definition of done

Phase status normally moves only through `Waiting → Ready → In progress → In review → Complete`, with `Blocked` available from any non-complete state. Phase 18 alone inserts `Prepared` after the 18A PR merges and before 18B production execution: `In review → Prepared → Complete`. `Ready` means prerequisites are satisfied; it is not authorization to start. A phase PR sets its row to `In review`. Phase 18 becomes `Prepared` only when 18A is merged and the retained protected-`main` artifact is verified. Only the merge owner, merge automation, or an immediate lead-owned follow-up commit may set a normal phase `Complete`; Phase 18 becomes `Complete` only through the post-observation lead-controlled status update. Every completion record names the merged PR/commit. A blocked phase records the failed gate and owner; merged preparatory work does not make the blocked gate complete.

A phase is **ready** only when:

- all prerequisites are merged;
- required client/provider inputs are verified at the level named by the phase;
- the assigned owner is allowed to make every planned change;
- no conflicting uncommitted work exists;
- acceptance criteria and stop conditions are understood.

A phase is **done** only when:

- the independently usable outcome works without a later phase;
- required tests pass locally and in CI;
- runtime/manual evidence exists for changed journeys;
- failure, empty, stale, expired, permission-denied, and recovery states relevant to the phase are covered;
- security, accessibility, privacy, and rollback effects are addressed;
- authoritative docs and decision records match behavior;
- no invented or unapproved production data is present;
- the project lead approves and merges the PR.

## 9. Decision and evidence recording

Use the existing authority structure rather than creating informal notes:

- Client-requested product changes: add a unique `CHG-###` row to section 8 of `docs/ux/08-acceptance-and-signoff.md`, including request, reason, affected requirements, authorized approver/role, decision, approval date, affected revision, non-secret evidence reference, and applied revision. A request message is evidence of a request, not approval.
- Client-owned values/assets: update `docs/ux/07-client-input-checklist.md` with status, owner, source, approver/date, version, conditions, and effective date.
- Multi-component technical decisions: run full EGM and store the resolved design under `docs/designs/`; update the applicable planning document after approval.
- Public design-system changes: update `DESIGN.md`, `.impeccable/design.json`, and affected `.impeccable/surfaces/` contract.
- Operational/deployment decisions: update `docs/planning/05-cpanel-deployment-and-operations.md` and the phase PR with evidence, without committing secrets or account paths.
- Phase progress: the phase PR updates its row to `In review`; after merge, the merge owner/automation or an immediate lead-owned follow-up commit records `Complete` plus the merged PR/commit. Phase 18 instead records `Prepared` after 18A merge and protected-`main` artifact verification, then `Complete` only after 18B observation and exit approval. Never mark an unmerged normal phase or an unobserved Phase 18 complete.

A PR description is evidence for review, but it is not the sole durable source for a product, architecture, security, or operational decision.

## 10. Dependency-ready queue

```text
00 Foundation and public shell (complete)
├── 01 Hosting, CI, release and rollback foundation
└── 02 Staff identity, access and audit
    └── 03 Catalog, fleet and administration
        ├── 04 Public content and fleet surfaces
        ├── 05 Availability and schedule engine
        └── 06 Pricing, policy and estimates
             └──────────────┬───────────────────────────────
                            07 Core Airport Pickup slice
                            ├── 08 Airport Transfer expansion
                            ├── 09 Ride expansion
                            ├── 10 Rent expansion
                            └── 11 Outbox and notifications
                                 └──────────┬───────────────
                     08 + 09 + 10 + 11 ────┘
                                            12 Proposals and guest control
                                            ├── 13 Payments and financial documents
                                            └── 14 Calendar and driver operations
                                                 └──────┬──────┘
                                                        15 Rent field operations,
                                                           incidents and privacy
                                                        16 Client content and
                                                           provider activation
                                                        17 Launch hardening
01 + merged 17 ────────────────────────────────────────18A go-live preparation PR
                                                        │
                                                        ├── protected-main production artifact
                                                        └── 18B authorized cPanel production run
                                                            Maintenance lane
```

### Status table

| Phase | Branch                                  | Owner             | Prerequisites                                                                                        | Status                              |
| ----- | --------------------------------------- | ----------------- | ---------------------------------------------------------------------------------------------------- | ----------------------------------- |
| 00    | Completed baseline on `main`            | Lead              | None                                                                                                 | Complete                            |
| 01    | `phase/01-hosting-ci-release`           | Lead              | 00                                                                                                   | Blocked — hosting (deployment disabled; owner: client hosting owner) |
| 02    | `phase/02-identity-access-audit`        | Lead              | 00; approved security/permission matrix before coding                                                | Ready for design only               |
| 03    | `phase/03-catalog-fleet-admin`          | Guided maintainer | 02; lead-approved Catalog EGM design                                                                 | Waiting                             |
| 04    | `phase/04-public-content-fleet`         | Guided maintainer | 03                                                                                                   | Waiting                             |
| 05    | `phase/05-availability-schedule`        | Lead              | 03; approved timing/override/driver-unavailability policies; concurrency proof on both supported DBs | Waiting                             |
| 06    | `phase/06-pricing-policy-estimates`     | Lead              | 03 plus approved pricing inputs                                                                      | Waiting                             |
| 07    | `phase/07-airport-pickup-core`          | Lead              | 04, 05, 06; verified eligible fleet/support/Airport Pickup/pending-request inputs                    | Waiting                             |
| 08    | `phase/08-airport-transfer`             | Guided maintainer | 07 plus verified eligible fleet and Airport Transfer inputs                                          | Waiting                             |
| 09    | `phase/09-ride`                         | Guided maintainer | 07 plus Phase 05 multi-interval capability, verified eligible fleet, and Ride inputs                 | Waiting                             |
| 10    | `phase/10-rent`                         | Lead              | 07 plus verified eligible fleet and Rent/legal/insurer inputs                                        | Waiting                             |
| 11    | `phase/11-outbox-notifications`         | Lead              | 07 plus approved messaging accounts for live adapters                                                | Waiting                             |
| 12    | `phase/12-proposals-guest-control`      | Lead              | 08, 09, 10, 11 plus proposal/change policies                                                         | Waiting                             |
| 13    | `phase/13-payments-documents`           | Lead              | 12 plus approved merchant/payment policies                                                           | Waiting                             |
| 14    | `phase/14-calendar-driver-operations`   | Lead              | 12 plus approved driver/replacement/token/offline/escalation policy package                          | Waiting                             |
| 15    | `phase/15-rent-field-incidents-privacy` | Lead              | 10, 12, 13, 14; approved retention/incident rules and supported private-upload security pipeline     | Waiting                             |
| 16    | `phase/16-client-provider-activation`   | Lead              | 01, 04, 08–15 plus approved client/provider inputs                                                   | Waiting                             |
| 17    | `phase/17-launch-hardening`             | Lead              | 16 plus numeric owner-approved performance/query/page-weight budgets                                 | Waiting                             |
| 18    | `phase/18-go-live-preparation`          | Lead              | 01 accepted-host gate, merged 17, protected-main artifact, DNS/TLS and deployment/rollback approval  | Waiting                             |

Phases 01 and 02 may run in parallel only after separate lead authorization as pre-client-approval technical groundwork; neither may freeze client-owned product behavior. Phase 02 must resolve and obtain lead approval for its security matrix before code. Phases 04, 05, and 06 may run in parallel after Phase 03 and their named input gates, although only two maintainers can actively own two branches at once. Phases 08, 09, 10, and 11 may run in any order after Phase 07 and their named gates. Phases 13 and 14 may run in parallel after Phase 12.

## 11. Verification commands used throughout

Run the full repository gate before every PR handoff:

```sh
./scripts/check-environment
composer validate --strict
composer verify
npm run format:check
npm run build
composer audit --locked
npm audit --omit=dev
```

Use `npm ci --ignore-scripts`, not `npm install`, for reproducible dependency installation. The development-only advisory recorded in `docs/development/environment.md` must be rechecked after lockfile changes; do not use `npm audit fix --force`.

Add phase-specific tests rather than relying only on the full gate. Schema phases also prove a clean migration from an empty database and a backward-compatible upgrade from the previous merged phase. UI phases additionally run the Impeccable detector, launch the application, inspect 320px, 390px, 768px tablet, and 1440px renders, and exercise keyboard, popover/select, validation, failure, and reduced-motion states.

---

# Phase Execution Guides and Standalone AI Prompts

## Phase 00 — Foundation and public shell

**Status:** Complete baseline; do not create a new branch or rebuild it.  
**Owner:** Project lead.  
**Outcome already on `main`:** Laravel 13 foundation, shared tooling and environment checks, baseline CI, MySQL-compatible migrations, four service routes, responsive public shell, Modern Web Guidance fallbacks, Impeccable design direction, and initial tests.

### Preserve

- Existing public home and service routes remain available.
- `App\Domain\Booking\ServiceType` remains the single four-service vocabulary until an approved domain design changes it.
- The Cloud/Navy/Amber visual system and current self-hosted Inter baseline remain intact until the approved CHG-002 migration in Phase 04 replaces Inter with Allo 1.0.3 across public/customer surfaces; never ship a mixed-font transition.
- Current MySQL development defaults, PHP 8.3 compatibility, Node 26, npm 11, and lockfiles remain intact.

### Baseline verification

Run the full commands in section 11 and verify home plus every service route returns successfully. Any baseline defect discovered while starting another phase must be classified as a separate bug unless it directly blocks the selected phase.

---

## Phase 01 — Hosting, CI, release and rollback foundation

**Branch:** `phase/01-hosting-ci-release`  
**Owner:** Project lead  
**Prerequisite:** Phase 00  
**Risk:** High; infrastructure, secrets boundaries, migrations, and recovery

### Independently usable outcome

The repository can produce a checksum-protected immutable production artifact without requiring Composer or Node on production, and the actual cPanel account receives a recorded capability verdict. Phase 01 has two valid review outcomes:

- **Complete — host accepted:** artifact checks pass, an approved PII-scrubbed error reporter is active for production-like staging, and staging activation, health checks, and previous-release rollback are rehearsed successfully.
- **Blocked — hosting:** artifact/checksum and capability-assessment work may merge, but deployment remains disabled, the phase status stays `Blocked`, and Phase 18 is not ready until an accepted host passes the rehearsal.

A blocked-host verdict never weakens the application or counts as accepted hosting.

### Scope

- Verify cPanel PHP web and CLI use the same major/minor version; required extensions exist; MariaDB 10.11+ or MySQL 8.0 uses InnoDB with transactions; and document-root control, filesystem layout, one-minute cron, Terminal/SSH or cPanel Git deployment tasks, symlinks/atomic activation, TLS, limits, backup access, and rollback capabilities pass.
- Approve and configure PII-scrubbed error reporting before any production-like staging rehearsal; otherwise keep the rehearsal explicitly non-production-like and do not mark the host accepted.
- Pin/verify the official Ponytail plugin baseline `DietrichGebert/ponytail` `v5.3.3` for both maintainers and extend the shared environment check so phase reviews cannot silently run a different or missing reviewer.
- Extend GitHub Actions to package production PHP dependencies and compiled assets into an immutable artifact after verification jobs pass.
- Exclude `.env`, tests not needed at runtime, local caches, Git metadata, and developer-only files from the release artifact.
- Generate and verify a cryptographic checksum.
- Add production-environment deployment workflow structure with no PR access to deployment secrets, a manual dispatch/approval boundary, deployment concurrency lock, and disabled activation until hosting gates pass.
- Establish release-directory, shared `.env`/storage, health-check, activation, observation, and rollback scripts or runbook commands consistent with `docs/planning/05-cpanel-deployment-and-operations.md`.
- Add Playwright/axe infrastructure only to the extent needed to smoke-test the current public shell; later phases add customer journeys.
- Record the hosting capability verdict without account names, usernames, absolute private paths, or credentials.

### Non-scope

- No production deployment.
- No domain, DNS, provider, payment, messaging, or real customer data activation.
- No web-accessible deployment endpoint.
- No unsafe public-root relocation or synchronous provider workaround.

### Decision and stop gates

Stop and mark hosting blocked if web and CLI PHP do not use the same major/minor version; any required extension is missing; the database/version is unsupported; InnoDB or transactions are unavailable; cron cannot run every minute; the domain cannot point to `current/public`; the application cannot live outside the public root; deployment tasks cannot run reviewed PHP commands; atomic activation is unavailable; or rollback cannot preserve the prior release. A file-upload-only account does not pass. Ask the technical/privacy owner before any production-like staging rehearsal without approved PII-scrubbed error reporting.

### Required proof

- Existing CI remains green on PHP 8.3 and 8.4 and Node 26.
- Artifact contains built assets and production dependencies and contains no secret/local files.
- Checksum verification fails after deliberate test corruption and succeeds for the untouched artifact.
- Two simultaneous deploy attempts cannot activate concurrently.
- Deployment credentials are environment-scoped and unavailable to pull-request jobs.
- For **Complete — host accepted**, approved scrubbed error reporting is verified and staging rehearsal proves activation, health checks, maintenance exit, and previous-release rollback.
- For **Blocked — hosting**, the failed capability and owner are recorded, deployment stays disabled, and the roadmap status remains `Blocked` until the accepted-host proof is added.

### Standalone AI prompt

```text
Implement Spinellanka Phase 01 on branch phase/01-hosting-ci-release. Act as the lead infrastructure engineer. Do not commit, push, deploy, or request secrets unless the project lead explicitly authorizes it.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 01, PRODUCT.md, docs/planning/01-technology-stack.md, docs/planning/02-system-architecture.md, docs/planning/04-security-and-privacy.md, docs/planning/05-cpanel-deployment-and-operations.md, docs/planning/07-delivery-roadmap-and-testing.md, docs/development/environment.md, .github/workflows/verify.yml, composer.json, package.json, scripts/bootstrap, and scripts/check-environment. Inspect configuration and scripts directly. Query Graft before reading application source.

First verify that Phase 00 is on main and the worktree contains no unrelated changes. Run the full EGM workflow because release, CI, hosting, rollback, and operations exchange state. Record the resolved design in docs/designs/. Do not use Ask Matt unless the user invokes it.

Build only: a cPanel capability assessment, immutable release-artifact workflow, checksum verification, environment-scoped manual deployment boundary, deployment lock, staging activation/release-directory path, health/smoke checks, and rollback rehearsal. Production must run compiled assets and PHP only. Keep production secrets out of PR jobs and repository files. Do not create a public deploy endpoint and do not weaken Laravel's public-root, CLI, cron, queue, or debug requirements.

Ask the hosting owner for factual capability evidence only when required. Record the verdict in docs/planning/05-cpanel-deployment-and-operations.md without secrets. Require exact web/CLI PHP major-minor equality, every required extension, a supported transactional InnoDB database, one-minute cron, current/public document-root control, protected shared files, atomic activation and rollback. Approve PII-scrubbed error reporting before a production-like staging rehearsal. On failure, set Phase 01 to Blocked, keep deployment disabled, identify the owner, and recommend a hosting upgrade; do not improvise.

Add focused workflow/script tests where practical. Prove artifact exclusions, checksum failure/success, secret isolation, concurrency locking, and Ponytail version/environment verification. For an accepted host, also prove scrubbed error reporting, staging activation, maintenance exit, health checks, and rollback. For a rejected host, prove deployment remains disabled and report the blocked gate. Run ./scripts/check-environment, composer validate --strict, composer verify, npm run format:check, npm run build, composer audit --locked, and npm audit --omit=dev. Report every command and result.

Prepare a PR handoff with scope, hosting verdict, workflow permissions, secret names without values, artifact contents/exclusions, rehearsal evidence, rollback, remaining hosting actions, and risks. For review findings, reproduce each issue, fix only Phase 01 scope, rerun the failing check plus full gates, update evidence, and request re-review.
```

---

## Phase 02 — Staff identity, access and audit

**Branch:** `phase/02-identity-access-audit`  
**Owner:** Project lead  
**Prerequisite:** Phase 00; before coding, a lead-approved resolved EGM design must define canonical permission keys, Owner/Admin and Staff grants, action/query enforcement, idle/session values, MFA enrollment/recovery, sensitive-action re-authentication, revocation, and first-owner provisioning  
**Risk:** High; authentication, authorization, audit, and sensitive operations

### Independently usable outcome

An authenticated Filament staff shell supports individual active users, mandatory TOTP MFA, capability-level roles/permissions, policy-enforced access, session revocation, and append-oriented audit events. It does not yet expose unbuilt booking operations.

### Scope

- Configure Filament 5 staff panel and individual staff login.
- Implement active/inactive users, secure password behavior, MFA enrollment/recovery, login throttling, session rotation/revocation, idle policy, and neutral recovery behavior.
- Define Owner/Admin and Staff capability permissions from the security plan without hard-coding broad role checks into UI classes.
- Add policy and query-scope enforcement for user/role/audit administration.
- Add append-only audit foundation and security-event capture.
- Require re-authentication for role changes, exports, large refunds, retention changes, and private-link regeneration through a reusable application boundary, even when some actions arrive later.
- Add `noindex, nofollow`, secure headers/cookies, and production-safe debug expectations for staff routes.
- Add safe first-owner provisioning instructions that never commit a default production account or password.

### Non-scope

- No customer accounts.
- No guest or driver links yet.
- No fleet, booking, pricing, payment, export, or deployment features.
- No shared administrator credentials or production seeds.

### Decision and stop gates

Stop for lead approval before changing the permission vocabulary, MFA method, session security policy, or first-owner provisioning method. Stop if a Filament or package capability is unclear; verify official Laravel/Filament/Spatie behavior rather than guessing.

### Required proof

- Active authorized user can log in and complete MFA.
- Inactive, revoked, unauthenticated, and insufficient-permission users fail safely.
- Role/permission changes require authorization and generate audit events.
- Hidden navigation is not the only enforcement; direct routes/actions/queries are denied.
- No shared/default account exists after setup.
- Staff pages are non-indexable and production security settings are tested.

### Standalone AI prompt

```text
Implement Spinellanka Phase 02 on branch phase/02-identity-access-audit. This is lead-owned security work. Do not commit, push, seed a production user, or expose credentials without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 02, PRODUCT.md, docs/planning/01-technology-stack.md, docs/planning/02-system-architecture.md, docs/planning/03-domain-and-data-model.md identity/audit sections, docs/planning/04-security-and-privacy.md, and docs/planning/07-delivery-roadmap-and-testing.md. Query Graft before application-source reads. Consult current official Laravel 13, Filament 5, and Spatie Permission documentation for security behavior. First run full EGM and obtain lead approval for a concrete security matrix containing canonical permission keys, Owner/Admin and Staff default grants, every action/query boundary, idle/session values, MFA enrollment/recovery, sensitive-action re-authentication, revocation, and first-owner provisioning. Do not write implementation code until that resolved artifact is approved.

Implement only the Filament staff shell, individual active users, mandatory TOTP MFA, login/session safeguards, capability permissions, policies/query scoping, re-authentication boundary, session revocation, and append-oriented audit foundation. Keep page/action classes thin and move security/audit rules into services and policies. Do not create customer accounts, shared admins, default production passwords, or unrequested booking features.

Define tests before considering work complete. Cover successful MFA login, throttling, inactive/revoked users, session rotation, role and record authorization, direct route/action denial, query isolation, re-authentication, audit attribution, and noindex/security headers. Verify navigation hiding is not treated as authorization.

Stop and ask the project lead before changing permission names or security policy. Record approved architecture changes through the full EGM artifact and applicable planning document. Never infer a security decision from convenience.

Run targeted tests and the full repository gate from roadmap.md section 11. Launch the staff shell and exercise login, MFA, denied access, logout, and session revocation. Prepare a PR handoff listing permissions, policy coverage, provisioning method, audit events, migrations, rollback compatibility, security evidence, and remaining risks. Apply review feedback only after reproducing it; add a regression test, make the narrowest fix, rerun all security and full gates, and request re-review.
```

---

## Phase 03 — Catalog, fleet and administration

**Branch:** `phase/03-catalog-fleet-admin`  
**Owner:** Guided maintainer, with lead review of schema and authorization  
**Prerequisite:** Phase 02 plus a lead-owned, approved resolved EGM artifact defining Catalog tables/constraints, media/private-public boundary, permission mapping, audit events, and optional import contract  
**Risk:** Medium; schema and verified business data

### Independently usable outcome

Authorized staff can maintain exactly identified vehicles, verified media metadata, drivers, effective-dated vehicle-driver assignments, service eligibility, extras, compatibility, active/maintenance status, and turnaround defaults. Empty public data remains empty; no production fleet record is fabricated.

### Scope

- Create explicit Catalog module records described in the domain model.
- Use foreign keys, enums/value objects, effective dates, status fields, and indexes appropriate to actual queries.
- Build policy-protected Filament resources and application services.
- Keep public/internal fields separate and record verification/approval metadata where needed.
- Store media privately or through the approved storage boundary; include factual alt text and usage-right metadata.
- Provide development/test factories with unmistakably fictional test-only values. Do not seed them in production.
- Add an import path only if an authoritative client source exists and the lead approves its mapping; otherwise provide safe admin entry and validation.
- Prepare Catalog interfaces needed later by Availability, Booking, and public pages without implementing those modules.

### Non-scope

- No booking, schedule-block, estimate, public fleet profile, provider, or real fleet import.
- No invented vehicle specs, capacities, registration details, driver facts, photos, claims, or eligibility.
- No lifecycle, pricing, payment, security-model, or deployment changes.

### Decision and stop gates

Stop before choosing a field meaning absent from `docs/ux/07-client-input-checklist.md`. Stop if a migration would destroy/rename existing shared data, if a permission is missing, or if a requested field crosses Catalog ownership. The lead must approve schema, migration rollback compatibility, authorization, and import behavior before final handoff.

### Required proof

- Clean database migration and upgrade from Phase 02 both pass.
- Unauthorized users cannot view or change catalog records.
- Effective-dated driver assignments cannot create invalid overlapping permanent ties.
- Service eligibility and extra compatibility reject invalid combinations.
- Media validation rejects unsafe files and public access bypass.
- Empty production-like state displays no fake fleet.

### Standalone AI prompt

```text
Implement Spinellanka Phase 03 on branch phase/03-catalog-fleet-admin. You are the guided maintainer. Do not make architecture, security, booking, availability, pricing, payment, or deployment decisions. Stop and ask the project lead whenever this prompt requires lead approval.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 03, PRODUCT.md, the lead-approved resolved Catalog EGM artifact, docs/planning/02-system-architecture.md Catalog boundary, docs/planning/03-domain-and-data-model.md Fleet section, docs/planning/04-security-and-privacy.md authorization/upload rules, docs/ux/07-client-input-checklist.md sections 1–3 and 12, and docs/ux/08-acceptance-and-signoff.md. Query Graft before reading application source. Inspect Phase 02 policies and audit patterns and implement the approved Catalog design exactly. If the artifact is absent, unresolved, or conflicts with current authority, stop and return the branch to the lead; the guided maintainer does not design the schema or security boundary.

Implement exact-vehicle catalog, media metadata, drivers, effective-dated vehicle-driver assignments, service eligibility, extras, compatibility, active/maintenance state, and turnaround defaults through thin Filament resources and domain/application services. Use explicit relational tables, foreign keys, indexes, authorization, validation, and audit events. Keep verified public fields distinct from internal fields. Factories may use clearly fictional test-only data; production seeders must not create fleet, driver, claim, or image facts.

Do not build booking, availability schedule blocks, pricing, public fleet pages, provider adapters, or imports without an approved source. Stop if any field meaning, permission, data source, destructive migration, or module owner is unclear. Before final handoff, ask the lead to review every migration, model relationship, policy, audit event, and rollback path.

Test clean and upgrade migrations, CRUD authorization, invalid combinations, effective-date overlap behavior, media validation/private access, audit attribution, and empty production-like state. Run the full repository gate. Launch the Filament resources and exercise create/edit/disable flows with local fictional data, then remove any local-only record from committed seeders.

Prepare a PR handoff with schema diagram/relationships, migration and rollback notes, permissions, validation, test evidence, manual paths, and a declaration that no real or invented production fleet facts were added. For review findings, reproduce them, stay inside Catalog scope, add a regression test, rerun targeted and full gates, and request re-review.
```

---

## Phase 04 — Public content and fleet surfaces

**Branch:** `phase/04-public-content-fleet`  
**Owner:** Guided maintainer  
**Prerequisite:** Phase 03  
**Risk:** Medium; public claims, accessibility, and design-system consistency

### Independently usable outcome

The public site offers accessible service, fleet, process, help, contact, and legal shells. Verified catalog records can appear; unavailable facts and images remain visibly absent. Booking remains disabled and the contact/WhatsApp/phone fallback is honest.

### Scope

- Expand server-rendered public routes and navigation for approved information architecture.
- Render verified fleet records and factual empty states from Catalog.
- Add service explanations, how-it-works, FAQ/help, contact/support, and legal-page shells using approved copy only.
- Preserve the existing four-service chooser and clear advance-booking/pending-confirmation language.
- For each service, keep its booking CTA disabled or routed to the approved inquiry fallback until that service phase is merged: Airport Pickup—07, Airport Transfer—08, Ride—09, Rent—10. Phase 07 never enables the other three services.
- Add SEO metadata only from verified content; exclude private/staff areas.
- Replace the public/customer Inter family with self-hosted official Allo 1.0.3 assets under SIL OFL 1.1. Use the upright variable WOFF2, load only used obliques, retain the license/copyright notice, preserve system fallbacks, and update `DESIGN.md`, `.impeccable/design.json`, and affected surface contracts from the rendered shipped result.
- Preserve Cloud/Navy/Amber design tokens, the one-family rule, functional corners, no decorative shadows/gradients/pills, keyboard access, 44px targets, 3px focus ring, reduced motion, and non-JavaScript access.
- Add responsive and accessibility tests plus current public-shell Playwright/axe coverage.

### Non-scope

- No booking form, availability, price, account, review/testimonial, live map, stock image, or fabricated trust claim.
- No redesign outside `DESIGN.md` and the approved Impeccable direction.
- No legal wording drafted by the maintainer or AI.

### Decision and stop gates

Stop when required copy, contact details, legal text, fleet facts, imagery, alt text, or proof is unverified. Render a truthful unavailable/pending state or omit the surface. Any design-system change requires lead approval, Impeccable review, and updated design records. For Allo, stop if the asset is not from official `joshpuckett/allo` release/package version 1.0.3, the OFL/copyright notice is absent, or rendered fallback/layout-shift evidence is missing.

### Required proof

- All pages work without client JavaScript for essential content/navigation.
- Empty catalog shows no fake cards or broken image placeholders.
- Verified records render only approved fields.
- Keyboard, zoom, mobile/desktop, reduced-motion, and screen-reader semantics meet the baseline.
- Each service CTA remains disabled/inquiry-only until its own merged phase (07/08/09/10), and no CTA implies immediate or confirmed service.
- Public metadata contains no unverified claim.
- Allo 1.0.3 is self-hosted with no third-party font request, its OFL/copyright notice ships, only required WOFF2 assets load, fallback remains readable, and rendered 320px/390px/768px/1440px layouts show no font-caused clipping or material layout shift.

### Standalone AI prompt

```text
Implement Spinellanka Phase 04 on branch phase/04-public-content-fleet. You are the guided maintainer. Preserve product truth and the existing design system. Do not invent or rewrite client-owned facts, legal text, support promises, reviews, fleet details, or imagery.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 04, PRODUCT.md, DESIGN.md, .impeccable/design.json, applicable .impeccable/surfaces/ files, docs/planning/06-production-design-direction.md, docs/planning/07-delivery-roadmap-and-testing.md, docs/ux/07-client-input-checklist.md sections 1–3, 13–16, and docs/ux/08-acceptance-and-signoff.md. Query Graft before application-source reads.

Before any HTML, CSS, browser JavaScript, form, accessibility, performance, or web-platform edit, run Modern Web Guidance searches/retrieval for each relevant capability and apply required fallbacks. Run Impeccable for all public UI work. Use full EGM only if the lead approves a multi-component architecture change. Ask Matt remains user-invoked only.

Build server-rendered service, fleet, how-it-works, help/FAQ, contact/support, and legal shells. Render only verified Catalog fields. Missing images or facts stay absent with a calm factual empty state, never stock content. Keep each service inquiry fallback in place until its own merged implementation: Airport Pickup Phase 07, Airport Transfer Phase 08, Ride Phase 09, and Rent Phase 10. Phase 07 does not enable all services. Preserve exact-vehicle and pending-confirmation language, semantic structure, keyboard use, 3px focus, 44px targets, reduced motion, progressive enhancement, and the approved Cloud/Navy/Amber system. Implement CHG-002 as a one-family migration from Inter to official self-hosted Allo 1.0.3: use the variable upright WOFF2, include only used obliques, retain OFL/copyright files, keep system fallbacks, prevent third-party font requests, and measure fallback/layout shift rather than guessing metric overrides. Update DESIGN.md, .impeccable/design.json, and affected surface contracts from the rendered result. Do not redesign unrelated pages.

Stop and ask the lead when copy or design authority conflicts. Do not draft legal wording. Record approved client changes in docs/ux/08 and verified inputs in docs/ux/07. Update DESIGN.md, .impeccable/design.json, and surface contracts only when an approved shipped design change requires it.

Add feature, accessibility, Playwright, and axe tests for navigation, empty/verified catalog, mobile/desktop layout, no-JavaScript essential content, keyboard paths, and noindex boundaries. Run the full repository gate and Impeccable detector. Launch the app, inspect 320px, 390px, 768px tablet, and 1440px renders, test keyboard, popover/select, zoom, reduced motion, and capture evidence. Inspect network/font loading, fallback rendering, weight coverage, clipping/overflow, and layout shift with Allo at every required viewport.

Prepare a PR handoff naming applied Modern Web Guidance guides, Impeccable result, screenshots, content sources, omitted unverified inputs, tests, and unchanged non-scope. For review feedback, reproduce the finding, make the smallest Phase 04 correction, rerun targeted/full/UI checks, update evidence, and request re-review.
```

---

## Phase 05 — Availability and schedule engine

**Branch:** `phase/05-availability-schedule`  
**Owner:** Project lead  
**Prerequisite:** Phase 03 plus approved turnaround/buffer, manual/maintenance-block, override-authority, pending/release/expiry, and temporary driver-unavailability policies required by enabled operations; the fixed 15-minute checkout hold is the sole preset exception  
**Risk:** Critical; concurrency and operational authority

### Independently usable outcome

Authorized staff can create maintenance/manual operational blocks, inspect a five-vehicle schedule query model, and rely on one transactionally correct overlap engine for every future blocking write. Checkout holds can be created/expired through domain services and tests, but no public booking flow is exposed yet.

### Scope

- Implement `vehicle_schedule_blocks` and `booking_holds` with required kinds, blocking state, expiry, actor, reason, source, local schedule fields, and indexes.
- Implement overlap semantics, preparation/turnaround boundaries, stale-hold expiry, maintenance/manual blocks, and permissioned override audit.
- For every blocking write: transaction, lock exact vehicle row, expire stale holds, query overlap, reject/alternative/authorized override, write block, commit.
- Provide one reusable all-or-nothing multi-interval command for a single exact vehicle. It locks deterministically once, checks every outbound/return interval and buffer, creates all blocks or none, and releases/expires the group atomically; this is the required Phase 09 interface.
- Represent temporary unavailability of the exact vehicle's effective permanent driver as an authoritative chauffeured-service block unless an already approved replacement satisfies the recorded policy. Staff entry, overlap, release, and audit use the same schedule service.
- Add idempotent scheduler commands for expiry and cleanup.
- Add staff-only block management and schedule query surfaces sufficient to operate before full calendar Phase 14.
- Keep all business schedule authority in `Asia/Colombo`; store infrastructure/audit timestamps in UTC.
- Add high-contention and race tests in CI against both supported engines, MariaDB 10.11 and MySQL 8.0; rerun on the selected accepted-host engine before Phase 05 becomes Complete.

### Non-scope

- No public search/results page, pricing, booking submission, proposal, payment, messaging, or full calendar.
- No Redis/distributed locks.
- No silent conflict override or direct schedule writes from UI classes.

### Decision and stop gates

Do not start until the named policy inputs are approved. Stop before defining a buffer, operational override, pending-request duration, release/expiry rule, driver-unavailability block, or replacement exception without verified policy. The fixed 15-minute checkout hold is the only preset duration. Any exception requires named permission, reason, and audit event. Stop if concurrency tests cannot run on both MariaDB 10.11 and MySQL 8.0 or if the multi-interval command cannot guarantee all-or-nothing behavior.

### Required proof

- Parallel exact-vehicle requests serialize and only one conflicting write succeeds.
- Non-overlapping windows succeed.
- Boundaries, buffers, stale expiry, manual/maintenance blocks, and timezone behavior are deterministic.
- Hold-expiry racing submission cannot release a valid submitted block or double-book.
- Unauthorized override fails; authorized override records actor/reason/audit.
- A failed transaction leaves no partial hold/block.
- A multi-interval request creates all legs or none; one conflicting return leg leaves no outbound block, and grouped expiry/release is atomic.
- Temporary permanent-driver unavailability removes the tied vehicle from chauffeured availability unless an approved replacement path is already satisfied and audited.
- The concurrency suite passes on MariaDB 10.11 and MySQL 8.0 and later on the accepted host engine.

### Standalone AI prompt

```text
Implement Spinellanka Phase 05 on branch phase/05-availability-schedule. This is lead-owned critical concurrency work. Do not commit, push, or change client policy without explicit lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 05, PRODUCT.md, docs/planning/02-system-architecture.md exact-vehicle concurrency section, docs/planning/03-domain-and-data-model.md Availability and critical invariants, docs/planning/04-security-and-privacy.md, docs/planning/07-delivery-roadmap-and-testing.md concurrency tests, docs/ux/07-client-input-checklist.md timing/buffer sections, and docs/ux/08-acceptance-and-signoff.md. Query Graft before application-source reads. Run full EGM because Catalog, Availability, audit, scheduler, database transactions, and staff operations interact.

Implement explicit schedule-block and hold records, indexed overlap queries, Asia/Colombo schedule values, stale expiry, maintenance/manual blocks, temporary permanent-driver-unavailability blocks for chauffeured service, permissioned approved replacement/override behavior, idempotent scheduled cleanup, and a reusable transactional service. Every blocking write must begin a transaction, lock the exact vehicle row, expire stale holds for it, query overlapping blocking intervals including buffers, reject or explicitly authorize an override, write the authoritative block, and commit. Add an all-or-nothing multi-interval command that checks and creates every outbound/return leg under the same deterministic vehicle lock and leaves no partial block when any leg conflicts. UI/action classes call these services and never write blocks directly.

The 15-minute checkout hold is fixed. Require approved values for every other buffer, pending duration, release/expiry rule, driver-unavailability block, replacement, or override. Require named permission, reason, and audit for exceptions. Do not add Redis, public booking, pricing, proposals, or a full calendar.

Write deterministic unit/feature tests and real concurrency tests on MariaDB 10.11 and MySQL 8.0. Cover non-overlap, overlap, exact boundaries, buffers, expiry, submission race, two staff writers, rollback after failure, unauthorized/authorized override, timezone serialization, indexes/query behavior, grouped multi-interval all-or-none creation/release/expiry, one-leg conflict, driver-unavailability block, and approved replacement. Prove each race repeatedly, then rerun on the accepted host engine before completion.

Run targeted concurrency tests and the full repository gate. Launch the staff block surface and verify maintenance/manual block create, denial, conflict, expiry, and audit paths. Prepare a PR handoff with transaction sequence, schema/indexes, race-test method/results, policy inputs, permissions, scheduler behavior, rollback compatibility, and unresolved rules. Review fixes require reproduction and a red/green regression test before the narrow correction and full rerun.
```

---

## Phase 06 — Pricing, policy and immutable estimates

**Branch:** `phase/06-pricing-policy-estimates`  
**Owner:** Project lead  
**Prerequisite:** Phase 03 and approved client pricing examples/policies sufficient for every enabled service  
**Risk:** Critical; financial correctness and client-owned rules

### Independently usable outcome

Authorized staff can maintain effective-dated, service/vehicle-scoped pricing rules and an audited manual USD/LKR approximation basis. Domain services can produce immutable itemized estimate snapshots from approved rules and test examples. No unapproved service is price-enabled; transparent custom quote remains the safe fallback.

### Scope

- Model effective-dated pricing rules, components, units, scope, priority, active dates, and audit metadata without an opaque expression language.
- Implement integer-minor-unit Money behavior and ISO currencies.
- Implement immutable estimates/items with assumptions and source rule versions.
- Implement USD primary and approximate LKR display using staff-entered value, source note, effective time, and actor.
- Separate refundable deposits from service totals.
- Implement permissioned adjustments/overrides only when approved, with reason and audit.
- Validate approved example calculations for all enabled services and edge cases.
- Return an explicit unpriceable/custom-quote result when route/rule input is unavailable; never guess.

### Non-scope

- No final proposal, payment, refund, invoice, live exchange API, or public booking workflow.
- No floating-point money.
- No invented rates, taxes, fees, rounding, discounts, deposits, waiting, no-show, or cancellation terms.

### Decision and stop gates

Do not start calculation implementation until the client supplies owner/source/approval and at least one normal plus one edge-case example for each enabled service. Stop on ambiguous precedence, rounding, settlement, tax, deposit, override, or custom-quote behavior. Product/finance owner decides; the lead translates only approved rules.

### Required proof

- Every approved example matches exactly in integer minor units.
- Effective-date and vehicle/service scope select one deterministic rule set.
- Estimates are immutable and trace their rules, exchange basis, assumptions, and items.
- Missing/expired/ambiguous rules fail to custom quote rather than produce a number.
- Unauthorized override fails; authorized override is attributed and audited.
- Approximate LKR is labeled and never becomes schedule/payment authority.

### Standalone AI prompt

```text
Implement Spinellanka Phase 06 on branch phase/06-pricing-policy-estimates. This is lead-owned financial-domain work. Do not begin calculations until approved pricing inputs and test examples exist. Do not commit, push, or infer business values.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 06, PRODUCT.md, docs/planning/02-system-architecture.md Pricing boundary/degradation rules, docs/planning/03-domain-and-data-model.md Pricing/proposals and critical invariants, docs/planning/04-security-and-privacy.md, docs/ux/07-client-input-checklist.md sections 4–12, and docs/ux/08-acceptance-and-signoff.md. Query Graft before application-source reads. Run full EGM because Catalog, Pricing, audit, policy configuration, estimates, and later Booking interfaces interact.

Verify the client provided owner, source, approver/date, effective version, normal example, and edge example for each enabled service. If any rate, tax, fee, rounding, settlement, exchange, deposit, discount, waiting, no-show, cancellation, or override rule is ambiguous, stop and list the exact decisions for the product/finance owner. Never copy competitor values or create plausible numbers.

Implement effective-dated service/vehicle pricing rules, explicit components/units, integer-minor-unit Money values with ISO currency, immutable itemized estimates, source rule versions, assumptions, separate refundable deposits, audited manual USD/LKR approximation basis, and permissioned approved adjustments. Prefer explicit code and relational records over an opaque formula language. Missing, expired, or ambiguous rules return a transparent custom-quote result.

Do not implement proposals, payment, refunds, financial documents, live exchange APIs, or public booking. Test every approved example exactly plus scope precedence, effective dates, rounding, missing/ambiguous rules, immutability, exchange labeling, override permission/audit, and serialization. Use property/edge tests where they add proof without obscuring examples.

Run targeted pricing tests and the full repository gate. Exercise the staff pricing configuration and estimate service locally with approved fixtures that are safe for the repository. Prepare a PR handoff with rule vocabulary, source approvals, example-to-test mapping, Money behavior, schema/migrations, custom-quote degradation, permissions, and rollback. Review fixes must preserve approved examples; reproduce, add a regression test, make the smallest correction, and rerun all pricing/full gates.
```

---

## Phase 07 — Core Airport Pickup booking slice

**Branch:** `phase/07-airport-pickup-core`  
**Owner:** Project lead  
**Prerequisites:** Phases 04, 05, and 06; at least one verified Airport Pickup-eligible exact vehicle with approved capacities/driver availability/public facts; approved support contact; verified Airport Pickup inputs including pending-request expiry/release; approved map/place/route sandbox or safe manual/custom-quote path  
**Risk:** Critical; first end-to-end booking transaction and private guest access

### Independently usable outcome

A traveler can complete an Airport Pickup request on mobile or desktop: enter service-specific criteria, preserve supported location text/pin, see suitable verified exact vehicles, receive an approved estimate or custom-quote explanation, hold one vehicle for 15 minutes, provide required international WhatsApp/mobile plus optional email and separate booker/lead-traveler details, review and accept required policies/privacy separately from optional unchecked marketing consent, submit a pending request, receive an on-screen reference/private under-review link, and recover safely from stale availability or expiry. Staff can review the request, enter a manual booking through the same schedule transaction, and—under approved policy—manually confirm an unchanged request while recording actor, time, policy version, availability recheck, and manual customer-communication outcome. No traveler submission is labeled confirmed.

### Scope

- Establish the reusable Booking module aggregate and application-service boundaries.
- Add booking, contact, policy-acceptance, consent, Airport Pickup detail, draft/session, public-reference, and guest-token records required by the domain model. Require international WhatsApp/mobile, keep email optional, separate booker and lead-traveler purposes, collect minimum necessary data, and keep optional marketing consent independent and unchecked by default.
- Implement service criteria for airport, flight/arrival, destination, meet-and-greet/name-board context, passengers, luggage, assistance, and approved waiting/no-show information.
- Put place search and route estimation behind the approved provider-neutral contracts; preserve typed text/pin and offer transparent staff-reviewed custom quote on provider/pricing failure.
- Query Catalog suitability and Availability without duplicating their rules.
- Create and release the exact 15-minute checkout hold only through Availability. Do not refresh or extend its expiry unless a later approved policy defines the hard maximum and atomic switching behavior.
- Use the immutable estimate from Pricing; never recalculate a historical estimate in a page class.
- Convert the valid hold to a submitted-request block atomically while creating the booking, policy/contact snapshots, audit event, private access token, and later-outbox-ready domain event.
- Implement a private under-review page and privacy-safe invalid/expired/revoked token shell.
- Add staff inbox/review and manual-booking entry sufficient for the pending workflow.
- Under an approved manual-confirmation policy, allow an authorized staff member to confirm only an unchanged, currently available pending request through the lifecycle and schedule services; record actor, time, policy version, availability recheck, and manual customer-communication outcome. Reuse this boundary for Phases 08–10.
- Add rate limits, CSRF, validation, noindex/referrer protections, and redacted logs.
- Establish reusable UI/domain patterns for Phases 08–10 without implementing their service-specific fields.

### Non-scope

- No automatic confirmation, proposal, payment, customer account, live flight tracking, live dispatch, automated messages, full calendar, or other three service forms.
- No direct database writes from controllers, Livewire components, or Filament actions.
- No invented airport, waiting, route, price, policy, contact, or support values.

### Decision and stop gates

Verified Airport Pickup policy inputs must cover supported airports/geography, flight requirements, meet-and-greet/name-board behavior, included waiting, delay/no-show handling, fees/inclusions, meeting instructions, manual-confirmation authority/communication, pending-request blocking duration and release behavior, and policy versions. At least one verified eligible exact vehicle plus approved support contact must exist through the Phase 03 admin path. If an input is missing, omit or block the dependent behavior; do not infer it. Stop on any unclear transaction boundary, token policy, suitability rule, contact/consent purpose, or manual-booking/confirmation authority.

### Required proof

- Happy-path request remains pending and blocks the selected exact vehicle.
- Two travelers cannot submit the same vehicle/window.
- Validation, no availability, unsuitable fleet, stale result, expired/revoked hold, provider failure, pricing failure, duplicate submit, and database rollback recover safely.
- Manual booking immediately affects public availability through the same lock service.
- Authorized manual confirmation rechecks current availability, rejects stale/changed requests, uses the lifecycle service, and records actor/time/policy/manual communication; unauthorized confirmation fails.
- International WhatsApp/mobile is required, email remains optional, booker/lead-traveler purposes stay distinct, required privacy/policy acceptance is recorded, and optional marketing consent is independently unchecked.
- Pending-request expiry/release follows the approved duration and cannot release a valid reviewed/confirmed block or be extended through repeated checkout refresh.
- Guest token has at least 256 bits of randomness, only its hash is stored, and invalid/expired/revoked links reveal no record existence.
- Essential form content works server-side; JavaScript is enhancement.
- Mobile/desktop Playwright and axe tests cover the critical path.

### Standalone AI prompt

```text
Implement Spinellanka Phase 07 on branch phase/07-airport-pickup-core. This is the lead-owned first production booking vertical. Do not commit, push, enable production providers, or make policy decisions without explicit lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 07, PRODUCT.md, DESIGN.md, docs/planning/01-technology-stack.md through docs/planning/04-security-and-privacy.md, docs/planning/06-production-design-direction.md, docs/planning/07-delivery-roadmap-and-testing.md, docs/ux/07-client-input-checklist.md Airport Pickup, timing, pricing, contact, legal and support sections, and docs/ux/08-acceptance-and-signoff.md. Query Graft before reading application source. Study merged Catalog, Availability, Pricing, Identity/audit, and public-shell interfaces. Run full EGM because this phase joins all of them.

Before browser code, run Modern Web Guidance for forms, validation, accessibility, date/time input, popovers or other platform features used. Run Impeccable for public workflow UI and preserve DESIGN.md. Ask Matt remains user-invoked only.

Verify every Airport Pickup fact and policy has owner, source, approver/date, version, and effective date, including pending-request duration/release and manual-confirmation authority/communication. Verify at least one approved Airport Pickup-eligible exact vehicle and support contact exist through Catalog. Implement only the Airport Pickup vertical: service-specific criteria, provider-neutral location/route contracts with text/pin preservation and custom-quote degradation, suitability including permanent-driver availability, exact-vehicle results, non-refreshable 15-minute checkout hold, immutable estimate, required international WhatsApp/mobile with optional email, separate booker/lead-traveler snapshots, required policy/privacy acceptance separate from optional unchecked marketing consent, atomic pending submission, random private token with stored hash only, under-review page, staff review inbox, manual booking, and approved manual confirmation through the same schedule/lifecycle services. Keep UI classes thin. Traveler submission must never say confirmed.

Do not build the other service forms, proposals, payments, automatic messages, live flight tracking, full calendar, or customer accounts. Stop if policy, transaction, token, permission, suitability, or provider behavior is unclear. Record client decisions in docs/ux/07 and docs/ux/08 and architecture decisions through EGM.

Test happy path plus every relevant validation, required/optional contact and consent branch, no-result, unsuitable, driver-unavailable, stale, expired pending request, duplicate, provider-failure, custom-quote, unauthorized, token, concurrency, manual-booking, manual-confirmation, and rollback path. Prove manual confirmation rejects stale/changed requests and records authority/communication. Use MySQL/MariaDB for locking proof. Add Playwright/axe coverage on mobile and desktop. Run the full repository gate, Impeccable detector, and real-browser checks at 320px, 390px, 768px tablet, and 1440px including keyboard, zoom, reduced motion, slow/failed network, and JavaScript-degraded behavior.

Prepare a PR handoff mapping approved inputs to behavior/tests, transaction sequence, token/security proof, screenshots, applied web guides, Impeccable result, migrations/rollback, provider degradation, and explicit non-scope. Review findings require reproduction, a regression test, the narrowest Phase 07 fix, full targeted/concurrency/browser/repository reruns, and re-review.
```

---

## Phase 08 — Airport Transfer expansion

**Branch:** `phase/08-airport-transfer`  
**Owner:** Guided maintainer  
**Prerequisites:** Phase 07; at least one verified Airport Transfer-eligible exact vehicle and approved support contact; verified Airport Transfer timing/policy inputs  
**Risk:** Medium; pattern-based workflow with safety-sensitive pickup guidance

### Independently usable outcome

Travelers can submit an Airport Transfer request using the proven core booking path. The form records origin, departure airport, flight/departure context, route/timing guidance, recommended pickup time, any permitted later-time override warning/acceptance, passengers, luggage, assistance, exact vehicle, estimate/custom quote, hold, review, and pending submission.

### Scope

- Add explicit `airport_transfer_details` behavior and validation.
- Reuse Booking, Catalog suitability, Availability, Pricing, location/route, private-link, staff-review, and manual-booking services from Phase 07.
- Calculate pickup guidance only from approved airport-arrival and route-contingency rules and available route data.
- Preserve both recommended and customer-chosen pickup times plus warning acceptance where approved.
- Add service-specific staff review fields and customer summaries.
- Add unit, feature, browser, accessibility, provider-failure, stale/expiry, and concurrency coverage.

### Non-scope

- No automatic flight tracking or guarantee against missed flights.
- No new booking framework, availability query, token system, pricing engine, provider contract, or visual system.
- No policy invention or broad refactor of Phase 07.

### Decision and stop gates

Stop if recommended-arrival buffer, route contingency, latest permitted pickup, unsafe-time warning, staff authority, missed-flight wording, or airport fee treatment is not approved. The guided maintainer cannot decide safety/legal text or change shared lifecycle/concurrency behavior.

### Required proof

- Approved examples produce the expected recommendation and warning.
- A later permitted choice requires recorded warning acceptance.
- An impermissible choice fails with approved guidance and a staff-support path.
- Provider failure preserves entered criteria and offers custom quote.
- Hold, stale result, duplicate submission, pending status, manual booking, and private link behave exactly like Phase 07.

### Standalone AI prompt

```text
Implement Spinellanka Phase 08 on branch phase/08-airport-transfer. You are the guided maintainer. Follow the merged Airport Pickup pattern and stop before changing shared lifecycle, availability, pricing, security, provider contracts, or design-system rules.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 08, PRODUCT.md, DESIGN.md, docs/planning/02-system-architecture.md, docs/planning/03-domain-and-data-model.md airport_transfer_details, docs/planning/04-security-and-privacy.md, docs/ux/07-client-input-checklist.md sections 4, 6, 9, 11, 13 and 14, and docs/ux/08-acceptance-and-signoff.md Airport Transfer decisions. Query Graft before application-source reads. Trace and reuse the Phase 07 Booking, location/route, suitability, Availability, Pricing, token, staff-review, and UI patterns.

Run Modern Web Guidance before form/browser work and Impeccable for public UI. Use full EGM only if the lead authorizes a shared architecture change; otherwise do not redesign the core. Ask Matt only if the user explicitly invokes it.

Verify approved values for airport-arrival buffer, route contingency, latest allowed pickup, unsafe-time warning/acceptance, staff authority, missed-flight wording, and fee inclusion. If any are absent or conflict, stop and ask the lead with exact checklist references.

Implement only Airport Transfer details, validation, recommendation calculation from approved inputs, recommended/chosen time snapshots, warning acceptance, customer review/summary, staff review fields, and tests. Reuse every shared service. Do not add flight tracking, guarantees, new provider contracts, duplicate pricing/availability logic, or broad refactors.

Test approved timing examples, boundaries, timezone display, later permitted and impermissible choices, provider failure/custom quote, validation, suitability, no availability, stale/expired hold, concurrency, pending submission, manual booking, token privacy, and mobile/desktop accessibility. Run the full repository gate, Impeccable detector, and real-browser checks at 320px, 390px, 768px tablet, and 1440px.

Prepare a PR handoff listing reused patterns, approved policy sources, calculations/examples, new schema, screenshots, web guidance, Impeccable result, tests, and unchanged shared behavior. Before final handoff, request lead review of the timing logic and migration. Review fixes must reproduce the finding, stay inside Phase 08, add a regression test, rerun targeted/full/UI gates, and request re-review.
```

---

## Phase 09 — Ride expansion

**Branch:** `phase/09-ride`  
**Owner:** Guided maintainer  
**Prerequisites:** Phase 07 and the merged Phase 05 all-or-nothing multi-interval Availability command; at least one verified Ride-eligible exact vehicle and approved support contact; verified Ride routing/waiting/return policies  
**Risk:** Medium; pattern-based workflow with multi-leg scheduling

### Independently usable outcome

Travelers can request a chauffeured point-to-point or multi-stop Ride with ordered stops, approved waiting information, and an optional separately timed return leg. Selecting destinations produces a provider-neutral map/route preview with an equivalent text itinerary. Suitability, exact vehicle, estimate/custom quote, holds, review, pending submission, staff review, and manual entry use the established core. The active-Ride customer-device marker is delivered later on the private guest surface in Phase 12.

### Scope

- Add explicit `ride_details` and ordered `ride_stops` behavior.
- Support pickup, zero or more approved intermediate stops, final destination, per-stop waiting, and optional return with its own date/time and route.
- Check the exact vehicle across all required blocking legs and buffers through Availability.
- Price only approved route, stop, wait, and return components through Pricing.
- Preserve ordered customer input and immutable submitted summaries.
- Render the selected pickup/stops/final destination on a provider-neutral static/interactive route map with a text itinerary and no-map fallback.
- Expose a reusable route-map presentation boundary for Phase 12 without accepting current-device coordinates, adding tracking state, or changing Booking/Availability/Pricing behavior.
- Add accessible add/remove/reorder interactions with a server-rendered fallback.
- Add staff review/manual-entry fields and complete tests.

### Non-scope

- No live dispatch, open-ended hourly hire, route optimization, active-Ride current-device marker (Phase 12), background geolocation, server-side/customer location history, driver or vehicle tracking, staff location view, route-completion proof, emergency/safety monitoring, or location-driven booking/pricing/payment/availability decision.
- No new tracking provider or map-provider contract outside the approved provider-neutral `PlaceSearch`/`RouteEstimate` boundary; if the approved map capability cannot render the selected route without new procurement, stop and return the decision to the lead/privacy owner.
- No invented stop limits, waiting ranges/fees, same-vehicle rule, return rule, or route-change policy.
- No shared domain refactor without lead approval.

### Decision and stop gates

Stop if included stop count, waiting min/max/pricing, return-leg rules, same-vehicle expectation, route changes, no-show behavior, fee inclusion, approved map provider/cost/privacy terms, or route-map fallback is unapproved. Before the guided maintainer starts, confirm the merged Phase 05 multi-interval command passes its all-or-nothing tests; if it is absent or failing, return the work to the lead and do not modify locking in Phase 09. The route-map boundary must not accept current-device coordinates or create tracking state; Phase 12 owns the separately approved active-Ride browser-location enhancement.

### Required proof

- Stop order and waiting values persist exactly and validate approved boundaries.
- Optional return is a separately timed leg and all required intervals are protected atomically.
- One conflicting leg blocks the exact vehicle and offers safe alternatives/custom assistance.
- JavaScript enhancement cannot lose or reorder submitted data.
- Selected destinations produce a usable route map plus equivalent text itinerary; provider/script failure preserves the itinerary and custom-quote path.
- The Phase 09 map accepts only selected route/destination data, creates no current-device tracking state, and leaves a documented presentation boundary for Phase 12.
- Stale/expired/concurrent/pending/private/staff behavior matches Phase 07.

### Standalone AI prompt

```text
Implement Spinellanka Phase 09 on branch phase/09-ride. You are the guided maintainer. Follow the merged Phase 07 core and do not alter shared lifecycle, locking, pricing, security, provider contracts, or design rules without lead approval.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 09, PRODUCT.md, DESIGN.md, docs/planning/02-system-architecture.md, docs/planning/03-domain-and-data-model.md ride_details/ride_stops, docs/planning/04-security-and-privacy.md, docs/ux/07-client-input-checklist.md sections 4, 7, 9, 11 and 12, and docs/ux/08-acceptance-and-signoff.md Ride decisions. Query Graft before application-source reads and trace Phase 07 patterns.

Run Modern Web Guidance before editing the dynamic stop form, validation, accessibility, HTML/CSS, or client JavaScript. Run Impeccable for public UI. Use full EGM only if the lead approves a shared multi-component change. Ask Matt remains user-invoked only.

Confirm approved stop limits, waiting bounds/pricing, return behavior, same-exact-vehicle rule, route-change/no-show behavior, fee treatment, map provider/cost/privacy terms, and route fallback. Stop with exact questions when any value is absent. Do not invent plausible defaults.

Implement only Ride-specific records, ordered pickup/stops/final destination, per-stop waiting, optional separately timed return, immutable summaries, staff review/manual fields, selected-destination route map, and tests. Reuse provider-neutral location/route, Catalog suitability, Pricing, token, Booking, and the merged Phase 05 all-or-nothing multi-interval Availability command. Do not change locking architecture in this guided phase. Essential form submission, ordering, itinerary, and recovery must survive without JavaScript or map-provider success.

Expose a narrow route-map presentation boundary that Phase 12 can enhance on an active private Ride page. Phase 09 must not request browser geolocation, accept current-device coordinates, create tracking state, or imply live dispatch/vehicle tracking.

Test ordering, add/remove/reorder, validation boundaries, return/no-return, all-leg availability, one-leg conflict, price/custom quote, route-map/text-itinerary parity, map-provider/script failure, and proof that Phase 09 creates no current-device geolocation request or tracking state. Also test stale/expired hold, concurrency, duplicate submit, pending status, token privacy, manual booking, keyboard/screen-reader status, reduced motion, and mobile/desktop layouts. Run full repository, Impeccable, Playwright, axe, privacy/network inspection, and real-browser checks.

Prepare a PR handoff mapping policy inputs to tests, reused interfaces, multi-leg transaction proof, map-provider/privacy approval, route/text fallback screenshots, proof that Phase 09 adds no current-device tracking state, the Phase 12 presentation boundary, Modern Web Guidance, Impeccable result, migration/rollback, and non-scope. Ask the lead to review schema, multi-leg availability, and the map boundary before final handoff. For feedback, reproduce, add regression coverage, make only a Phase 09 fix, rerun targeted/full/UI/privacy gates, and request re-review.
```

---

## Phase 10 — Rent expansion

**Branch:** `phase/10-rent`  
**Owner:** Project lead  
**Prerequisites:** Phase 07; at least one verified Rent-eligible exact vehicle and approved support contact; verified Rent eligibility, legal, insurance, timing, deposit, and operational inputs  
**Risk:** Critical; eligibility, legal/policy, schedule duration, and self-drive behavior

### Independently usable outcome

Travelers can request one exact self-drive vehicle for a selected pickup/return period. The workflow clearly states no driver, calculates approved 24-hour periods, collects only approved eligibility context, records additional-driver and expected-distance context, preserves delivery/collection information, checks availability and estimate/custom quote, and submits pending staff review. Original identity/licence documents are checked later in person and are never uploaded.

### Scope

- Add explicit `rent_details` behavior and validation.
- Implement approved 24-hour period, extra-hour, pickup/return, delivery/collection, eligibility-question, expected-distance, and additional-driver rules.
- Ensure Rent never creates or displays a driver assignment.
- Reuse exact-vehicle Availability and approved Pricing rules for full rental/handover/buffer windows.
- Collect minimum necessary eligibility answers; avoid identity-document files or excessive identifiers.
- Display approved deposit, insurance, mileage, fuel, restriction, and next-step information only when verified.
- Add staff review/manual request fields and defer physical handover to Phase 15.
- Add complete domain, authorization, privacy, browser, and accessibility tests.

### Non-scope

- No document upload, automated identity verification, licence approval guarantee, handover/return condition workflow, incident handling, payment collection, or extension completion.
- No driver assignment.
- No invented legal, insurance, eligibility, deposit, distance, fuel, late, damage, or restricted-use rule.

### Decision and stop gates

Do not start until the client legal/insurance owner approves all Phase 10-dependent entries in checklist section 8 and relevant pricing/policy sections, including original-document inspection requirements. Stop if the application would need to store an identity document or if a requested eligibility rule lacks legal/insurer ownership.

### Required proof

- Approved duration examples and boundary times calculate exactly in `Asia/Colombo`.
- Rent produces no driver assignment or chauffeured copy.
- Ineligible or incomplete answers route to approved staff review/denial behavior without unsupported legal claims.
- No identity-document upload or raw document copy exists.
- Exact vehicle is blocked for the entire approved window, including handover/return/buffers.
- Pending/private/stale/concurrency/manual behavior matches core booking.

### Standalone AI prompt

```text
Implement Spinellanka Phase 10 on branch phase/10-rent. This is lead-owned policy-sensitive work. Do not begin until the client legal/insurance owner has approved every required Rent input. Do not commit, push, or infer legal, insurance, eligibility, deposit, duration, or fee rules.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 10, PRODUCT.md, DESIGN.md, docs/planning/02-system-architecture.md, docs/planning/03-domain-and-data-model.md rent_details and invariants, docs/planning/04-security-and-privacy.md data minimization/upload rules, docs/planning/07-delivery-roadmap-and-testing.md, docs/ux/07-client-input-checklist.md sections 4, 8–12 and 14, and docs/ux/08-acceptance-and-signoff.md Rent decisions. Query Graft before source reads. Run full EGM because Rent joins Booking, Availability, Pricing, Catalog, privacy, legal policy, and future Operations.

Run Modern Web Guidance before forms, dates/times, validation, accessibility, or browser code. Run Impeccable for public UI. Ask Matt remains user-invoked only.

Verify owner, source, approver/date, version and effective date for age/licence/IDP rules, original-document checks, additional drivers, deposit, insurance/excess, mileage, fuel, 24-hour/extra-hour timing, return/extension, delivery, restrictions, breakdown, incident, cleaning and agreement terms. Stop on any gap. Do not upload or copy passport/licence documents; record only approved eligibility context now and original inspection outcome later.

Implement only Rent-specific records, approved duration calculation, pickup/return and delivery/collection context, eligibility answers, expected distance, additional driver, self-drive/no-driver behavior, full-window availability, approved estimate/custom quote, review, pending submission, private page, and staff/manual review fields. Defer handover, return, incidents, payments, and completed extension workflow.

Test approved duration examples/boundaries, timezone, eligibility branches, data minimization, no upload, no driver assignment, full-window/buffer conflicts, pricing/custom quote, stale/expired hold, concurrency, duplicate submit, pending status, token privacy, manual entry, mobile/desktop accessibility, and failure recovery. Run full repository, Impeccable, Playwright, axe, and real-browser gates.

Prepare a PR handoff mapping each implemented rule to approval evidence/test, privacy fields, duration proof, availability window, screenshots, web guidance, Impeccable result, migrations/rollback, and deferred field operations. Review fixes require reproduction and regression proof; never change an approved policy example merely to satisfy code.
```

---

## Phase 11 — Transactional outbox and notifications

**Branch:** `phase/11-outbox-notifications`  
**Owner:** Project lead  
**Prerequisite:** Phase 07; approved WhatsApp/email accounts and templates are required only for live adapter activation  
**Risk:** High; transaction durability, privacy, external providers, retries, and idempotency

### Independently usable outcome

Booking state and outbound notification intent commit atomically. Database-queue workers dispatch versioned WhatsApp/email messages through provider-neutral adapters with stable idempotency keys, bounded retry, redacted failure records, and staff-visible status. A local/fake adapter supports complete testing when provider accounts are not yet approved. Message failure never invalidates a booking.

### Scope

- Add `outbox_events` and `message_deliveries` with payload versions, idempotency keys, availability/attempt/final state, safe recipient/template snapshots, and provider references.
- Commit domain changes and outbox events in the same transaction.
- Implement dispatcher/jobs, bounded timeout/retry rules tied to named failure modes, permanent failure state, explicit `delivery_unknown`/reconciliation state for ambiguous provider acceptance, staff retry, and aging/failure visibility.
- Never automatically retry an ambiguous accepted-or-timeout result unless provider idempotency or a status lookup proves the retry safe; otherwise require staff-reviewed reconciliation/retry.
- Define provider-neutral `WhatsAppSender` and `TransactionalMailer` contracts.
- Implement fake/local adapters first; activate Meta WhatsApp Cloud API and Postmark only after approved account/template/domain gates.
- Keep queue payloads minimal and redact secrets/PII from logs and failed-job displays.
- Ensure on-screen reference/private link remains the recovery path.
- Add cPanel-compatible short-lived queue drain/scheduler behavior.

### Non-scope

- No marketing campaigns, inbound WhatsApp bot, arbitrary staff message composer, SMS, customer account, or guarantee of immediate delivery.
- No provider credential in Git or PR jobs.
- No rollback of committed domain state because notification fails.

### Decision and stop gates

Stop before live activation without client-owned Meta Business/number/templates, authenticated sending domain/provider approval, privacy/cost approval, support process, and secret storage. Stop if a template includes internal notes or an unapproved promise. Retry behavior must map to documented provider failure classes; do not retry every error indefinitely.

### Required proof

- Domain state plus outbox commit together; injected rollback leaves neither.
- Duplicate dispatch/idempotency cannot send a duplicate customer event where the provider supports prevention.
- Temporary failure retries within bounds; permanent failure becomes visible without changing booking state; ambiguous provider acceptance becomes `delivery_unknown` and is not auto-retried without proven idempotency/status reconciliation.
- Queue delay does not lose the event.
- Logs/jobs contain no secret and minimum necessary PII.
- Cron-style short worker drains safely and cannot overlap.

### Standalone AI prompt

```text
Implement Spinellanka Phase 11 on branch phase/11-outbox-notifications. This is lead-owned integration/durability work. Do not activate production providers, request credentials in chat, commit secrets, or promise delivery behavior without approved accounts and templates.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 11, PRODUCT.md, docs/planning/01-technology-stack.md provider gates, docs/planning/02-system-architecture.md transactional outbox/cPanel/degradation sections, docs/planning/03-domain-and-data-model.md Messaging/integrations, docs/planning/04-security-and-privacy.md Integration/queue security, docs/planning/05-cpanel-deployment-and-operations.md cron/queue, docs/ux/07-client-input-checklist.md notification/support and integration sections, and docs/ux/08-acceptance-and-signoff.md. Query Graft before source reads. Run full EGM because Booking transactions, queue, staff operations, providers, privacy, and cPanel execution interact.

Implement outbox events and message deliveries, same-transaction event recording, stable idempotency, payload versions, minimal data, dispatcher/jobs, bounded timeout/retries tied to named failures, permanent failure, explicit delivery_unknown/reconciliation for ambiguous acceptance, staff visibility/manual retry, and cPanel-compatible short-lived queue drain. Define provider-neutral WhatsAppSender and TransactionalMailer interfaces. Use fake/local adapters for deterministic tests. Add Meta/Postmark adapters only after verifying current official APIs and approved client accounts/templates/domain; keep them disabled without secrets. Never auto-retry an ambiguous accepted-or-timeout send unless provider idempotency or status lookup proves safety.

Never roll back booking/proposal/payment state for a message failure. Never place internal notes, secrets, excessive customer data, or unapproved support promises in payloads/logs/templates. Stop on missing template approval, privacy/cost decision, failure classification, or secret-store path.

Test transaction rollback, duplicate dispatch, idempotency, temporary/permanent errors, provider-accepted-then-client-timeout ambiguity, delivery_unknown reconciliation, safe versus forbidden retry, delayed queue, worker overlap, redaction, unauthorized retry, provider payload/status mapping, and fake-adapter customer journeys. Exercise queue and scheduler locally. Run the full repository gate and any provider sandbox contract suite without exposing credentials.

Prepare a PR handoff with event/template registry, adapter contracts, transaction proof, retry matrix, redaction evidence, staff failure UI, cron behavior, provider gates, secret names without values, and rollback. Review fixes require reproducing the exact failure, adding a regression test, correcting the narrow outbox/adapter layer, and rerunning integration plus full gates.
```

---

## Phase 12 — Versioned proposals and private guest control

**Branch:** `phase/12-proposals-guest-control`  
**Owner:** Project lead  
**Prerequisites:** Phases 08, 09, 10, and 11; approved proposal, change, cancellation, expiry, replacement, and extension policies  
**Risk:** Critical; lifecycle, private authorization, versioning, and accepted-booking preservation

### Independently usable outcome

Staff can review any service request, create and send immutable versioned proposals, explain changes, set approved deadlines/payment choices, and record staff-reviewed decisions. A traveler can use the private link to inspect the current state, compare the current proposal, accept, decline, request a change/cancellation, or request a Rent extension. Superseded/expired proposals cannot confirm. Bookings requiring online payment stop safely at Awaiting payment until Phase 13; approved pay-later/manual paths follow explicit policy. An accepted booking remains active while a revision is pending.

### Scope

- Implement explicit booking transition services for approved lifecycle states.
- Add immutable proposal versions/items, complete service/vehicle/schedule/terms/amount snapshot, change comparison, response events, deadlines, and current-version pointer.
- Add proposal schedule holds/expiry through Availability.
- Enhance the private guest page with status, next action, details, proposal comparison, approved payment choice display, documents placeholders only where real, requests, and support.
- Implement accept/decline/change/cancel and Rent extension request boundaries according to approved policies.
- Keep the accepted booking authoritative during revision; never mutate a sent proposal.
- Rotate/revoke private tokens after suspected disclosure or sensitive change; preserve privacy-safe invalid shell.
- Record lifecycle and material actions in audit/outbox.
- Add staff proposal comparison/review actions with permission and re-authentication where required.

### Non-scope

- No hosted payment session/webhook, automatic financial settlement, refund execution, final invoice, driver operations, handover, or incident flow.
- No silent replacement vehicle/time/route/price change.
- No confirmation from an expired/superseded proposal or unverified browser state.

### Decision and stop gates

Stop until policy owners approve proposal expiry, payment choices, cancellation/change cutoffs and fees, replacement acceptance, Rent extension behavior, support process, and policy versions. Stop on any transition not defined by the approved lifecycle. A change to an accepted booking requires a pending revision branch, not in-place mutation.

### Required proof

- Only current unexpired proposal can be accepted.
- Duplicate/racing responses are idempotent and produce one transition.
- Proposal edit creates a new immutable version and correct comparison.
- Original accepted booking remains active during pending revision or extension.
- Expiry releases only its own hold safely.
- Private links enforce scope, expiry, revocation, rotation, noindex, no-referrer, and neutral invalid response.
- Message failure leaves proposal/lifecycle committed and visible.

### Standalone AI prompt

```text
Implement Spinellanka Phase 12 on branch phase/12-proposals-guest-control. This is lead-owned lifecycle and private-access work. Do not commit, push, or invent proposal, payment-choice, cancellation, replacement, or extension policy.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 12, PRODUCT.md, DESIGN.md, docs/planning/02-system-architecture.md request path/degradation, docs/planning/03-domain-and-data-model.md lifecycle/pricing/proposals/invariants, docs/planning/04-security-and-privacy.md private links/authorization, docs/planning/07-delivery-roadmap-and-testing.md Phase 3, docs/ux/07-client-input-checklist.md payment, cancellation/change, Rent and support sections, and docs/ux/08-acceptance-and-signoff.md. Query Graft before source reads. Run full EGM because Booking, Availability, Pricing, Messaging, Identity, audit, staff and guest surfaces interact.

Run Modern Web Guidance before private-page forms, status announcements, dialogs, deadlines, accessibility, or client-side behavior. Run Impeccable for customer-facing UI. Ask Matt remains user-invoked only.

Verify approved policy versions for proposal expiry, full/deposit/pay-later availability, cancellation/change cutoffs/fees, replacement acceptance, Rent extension, support, and accepted-booking preservation. Stop on any missing transition or unclear payment precondition.

Implement explicit transition services; immutable versioned proposals/items and complete snapshots; comparison to request/previous version; proposal holds/deadlines; staff send/revise actions; guest current-state/details/proposal/next-action UI; accept, decline, change, cancel and Rent extension requests; token rotation/revocation; audit and outbox. A sent proposal is never edited. Only the current unexpired proposal can advance. Keep the prior accepted booking active while a revision is pending. Online-payment-required acceptance stops at Awaiting payment until Phase 13.

Do not implement hosted checkout, webhook settlement, refund execution, final invoices, driver operations, handover, or incidents. Test lifecycle matrix, current/superseded/expired proposals, duplicate/racing responses, expiry/hold release, revision preservation, permission/re-authentication, token enumeration/expiry/revocation/rotation, message failure, slow network, and every guest status/error/recovery state. Add Playwright/axe mobile/desktop journeys and run full repository/Impeccable/real-browser gates.

Prepare a PR handoff with lifecycle matrix, policy evidence, proposal snapshot/version design, race tests, token/security proof, screenshots, web guidance, Impeccable result, migrations/rollback, and payment boundary. Review fixes require exact reproduction, a regression test, narrow service-layer correction, all lifecycle/concurrency/private/browser/full gates, and re-review.
```

---

## Phase 13 — Hosted payments and financial documents

**Branch:** `phase/13-payments-documents`  
**Owner:** Project lead  
**Prerequisites:** Phase 12; signed-off merchant/gateway choice; approved settlement, deposit, pay-later, offline-payment, refund, and document rules  
**Risk:** Critical; money, provider webhooks, idempotency, reconciliation, and legal documents

### Independently usable outcome

The current accepted proposal can create a provider-hosted payment session for an approved full or deposit amount. Verified, idempotent webhooks update an append-oriented online/offline transaction ledger and only then advance eligible bookings. Browser returns show pending/success/failure context but never prove payment. Authorized staff can record approved offline payments, reconcile discrepancies, issue controlled refunds, and produce versioned estimate/proposal/receipt/final-invoice records.

### Scope

- Define provider-neutral `HostedPayment` and `PaymentWebhookVerifier` contracts.
- Integrate only the client/bank-approved Sri Lankan merchant gateway after sandbox verification.
- Add payment transactions, verified provider events, webhook receipts, refunds, balances, reconciliation states, and financial-document metadata.
- Generate stable idempotency keys and enforce unique provider event/transaction identifiers.
- Verify webhook signature, timestamp/replay, merchant account, proposal version, booking, amount, currency, and current state before any settlement transition.
- Treat browser return parameters as display context and reconcile independently.
- Support approved full, deposit, pay-later, and offline methods per proposal/policy.
- Use compensating ledger/refund records rather than rewriting settled history.
- Add permission, re-authentication, approval, reason, audit, outbox, noindex, rate/body limits, and payload-retention controls.
- Produce approved financial document versions without inventing tax, numbering, or legal formats.
- Add scheduled reconciliation and staff-visible pending/mismatch/failure states.

### Non-scope

- No raw card number, CVC, authentication data, card form, unapproved gateway, cryptocurrency, saved customer wallet, or automatic accounting sync.
- No settlement change from a browser return.
- No silent amount/currency correction or destructive ledger edit.

### Decision and stop gates

Do not begin provider implementation before client finance/legal approves the merchant, contract, supported currencies/methods, settlement, fees, 3-D Secure, deposits, refunds, deadlines, pay-later/offline rules, invoice/receipt format, and webhook quality. Stop if official signature verification or event-idempotency semantics cannot be proven. A provider that cannot meet the security/invariant requirements is rejected.

### Required proof

- Valid signed sandbox event with matching proposal/amount/currency advances exactly once.
- Duplicate, delayed, replayed, unsigned, wrong-account, wrong-proposal, wrong-amount, and wrong-currency events do not double-charge or confirm.
- Browser return without webhook remains pending.
- Offline and online payments share one append-oriented history with authorization/audit.
- Refund permissions, approvals, partial/full amounts, duplicate protection, and reconciliation work.
- Secrets and prohibited card data never appear in storage, logs, jobs, documents, screenshots, or tests.
- Financial states explain amount, currency, balance, deadline, and support path.

### Standalone AI prompt

```text
Implement Spinellanka Phase 13 on branch phase/13-payments-documents. This is lead-owned payment/security work. Do not start provider code until the client finance/legal owner and technical lead approve the actual merchant gateway and policies. Never request credentials in chat or commit/publish secrets. Do not commit, push, or make an external sandbox/production change without explicit lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 13, PRODUCT.md, docs/planning/01-technology-stack.md payment gate, docs/planning/02-system-architecture.md Payments/integration/degradation, docs/planning/03-domain-and-data-model.md Payments/documents/invariants, docs/planning/04-security-and-privacy.md payment/webhook rules, docs/planning/07-delivery-roadmap-and-testing.md Phase 3/concurrency, docs/ux/07-client-input-checklist.md sections 9–11 and 14, and docs/ux/08-acceptance-and-signoff.md. Query Graft before source reads. Run full EGM because Proposals, Payments, webhooks, lifecycle, audit, Messaging, documents, security, and reconciliation interact.

Verify the selected provider against current official documentation and sandbox behavior: merchant eligibility, currencies, methods, settlement, fees, 3-D Secure, full/deposit/refund support, unique events, signatures, retries, webhook origin requirements, and cPanel compatibility. Record approval evidence without credentials. Stop and reject the provider if signature/idempotency or required policy cannot be proven.

Implement provider-neutral HostedPayment and PaymentWebhookVerifier contracts; hosted checkout session; append-oriented online/offline transactions and events; webhook receipts; stable idempotency; signature/timestamp/replay/account/proposal/booking/amount/currency checks; pending browser return; approved full/deposit/pay-later/offline behavior; refunds as compensating records; reconciliation; versioned financial documents; permission/re-authentication/audit/outbox; and safe payload retention. Never accept or store raw card data.

Test valid, duplicate, delayed, replayed, unsigned, malformed, wrong-account, wrong-proposal, wrong-amount, wrong-currency, expired-proposal, provider-timeout, browser-return-before-webhook, offline, partial/full refund, reconciliation, and race paths using the provider sandbox/official fixtures. Prove one transition per unique event. Add browser tests for pending/success/failure/recovery and run full security/repository gates.

Prepare a PR handoff with provider decision evidence, contracts, lifecycle/payment matrix, signature/idempotency proof, sandbox results, secret names without values, ledger/refund/document rules, screenshots, migrations/rollback, reconciliation and incident paths. Review fixes require reproducing the exact provider/domain case, a regression test, the smallest service/adapter correction, all payment/security/browser/full gates, and re-review.
```

---

## Phase 14 — Operations calendar and driver trip access

**Branch:** `phase/14-calendar-driver-operations`  
**Owner:** Project lead  
**Prerequisite:** Phase 12 plus an approved driver/replacement/traveler-approval/private-link/service-day-contact/status-order/offline/escalation policy package with owner, source, version, and effective date  
**Risk:** High; operational schedule authority, assignments, and private driver access

### Independently usable outcome

Authorized staff use a five-vehicle day/week/month calendar and mobile agenda representing all blocking states without color alone. Staff can assign the effective tied driver or an approved replacement to a confirmed chauffeured trip. A driver receives a separate, trip-scoped, expiring private link and can acknowledge, mark en route, arrived, started, completed, or report an issue while seeing only the minimum current-trip data—never prices or internal notes.

### Scope

- Build calendar/agenda query models from authoritative schedule blocks; do not create a second schedule store.
- Represent holds, pending requests/proposals, confirmed, in-progress, Rent, handover/return, buffers, maintenance, and manual blocks with text/icon/status plus color.
- Add operations dashboard alerts for conflicts, deadlines, stale tasks, failed messages, and payment states already available.
- Resolve permanent tied driver by effective date for chauffeured services; support permissioned replacement workflow with traveler-approval boundary where required.
- Add driver assignment and trip records/updates.
- Issue separate 256-bit-or-greater random driver tokens; store hash only; bind to one trip/purpose; expire/revoke/rotate and record use.
- Build privacy-safe driver mobile flow and invalid-link shell with noindex/no-referrer.
- Add offline/interrupted-action recovery without allowing stale completion to bypass server state.
- Audit assignments, replacements, token actions, and trip updates; send outbox events as approved.

### Non-scope

- No GPS/live tracking, public driver profile invention, driver account/app, price/internal-note access, automatic traveler approval, Rent handover/return, or incident case management.
- No duplicate calendar database or direct schedule mutation from the calendar UI.

### Decision and stop gates

Stop if replacement authority/approval, trip data visibility, token lifetime, service-day contacts, update meanings, offline behavior, or issue escalation is unapproved. Drivers remain link users, not staff accounts. Any schedule-changing action must call existing domain services.

### Required proof

- Calendar and agenda match the schedule authority across all block kinds and timezone boundaries.
- Every state is understandable without color.
- Driver sees only one assigned trip and minimum operational contact/location data.
- Driver cannot see price, payment, policy, internal note, other booking, or staff data.
- Invalid/expired/revoked/reassigned tokens reveal no trip existence.
- Duplicate/offline/replayed trip updates are idempotent and follow allowed order.
- Unauthorized replacement or schedule change fails and is audited.

### Standalone AI prompt

```text
Implement Spinellanka Phase 14 on branch phase/14-calendar-driver-operations. This is lead-owned operations/private-access work. Do not commit, push, or decide driver/replacement/token/service-day policy without explicit lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 14, PRODUCT.md, DESIGN.md, docs/planning/02-system-architecture.md Availability/Operations/Identity boundaries, docs/planning/03-domain-and-data-model.md Fleet/Availability/Operations/tokens/invariants, docs/planning/04-security-and-privacy.md driver/private/authorization rules, docs/planning/07-delivery-roadmap-and-testing.md Phase 4, docs/ux/07-client-input-checklist.md driver/support/timing sections, and docs/ux/08-acceptance-and-signoff.md Operations decisions. Query Graft before source reads. Run full EGM because schedule query models, assignments, tokens, driver actions, Messaging, audit, staff and mobile UI interact.

Run Modern Web Guidance before calendar/table/popover, mobile interaction, offline/status, accessibility, HTML/CSS or client JavaScript work. Run Impeccable for calendar, dashboard, and driver UI. Ask Matt remains user-invoked only.

Implement calendar and agenda projections from existing schedule blocks; text/icon/status alternatives to color; operational alerts; effective tied-driver resolution; permissioned replacement request/approval boundary; driver assignment/trip updates; separate random hashed trip token with expiry/revocation/rotation; minimal mobile trip page; invalid-link privacy shell; idempotent status actions; audit and outbox. Never create a second schedule store or let UI write schedule state directly.

Do not add GPS/live tracking, driver accounts/apps, prices/internal notes on driver pages, Rent handover, or incident management. Stop on missing replacement/traveler approval, token lifetime, data visibility, service-day contact, update-order, offline, or escalation decisions.

Test all schedule block kinds, Asia/Colombo boundaries, no-color comprehension, desktop/month/week/day and mobile agenda, authorization, tied/replacement driver, token entropy/hash/scope/expiry/revocation/reassignment, data leakage, allowed/invalid/duplicate/offline updates, message failure, and direct-route attacks. Add Playwright/axe and real mobile checks. Run full repository and Impeccable gates.

Prepare a PR handoff with schedule projection mapping, query/performance evidence, permissions, replacement policy, token/security proof, driver field list, screenshots, web guidance, Impeccable result, tests, migrations/rollback, and deferred Rent/incidents. Review fixes require reproduction, regression coverage, narrow service/query/UI correction, and all operations/security/browser/full reruns.
```

---

## Phase 15 — Rent field operations, incidents, exports and privacy operations

**Branch:** `phase/15-rent-field-incidents-privacy`  
**Owner:** Project lead  
**Prerequisites:** Phases 10, 12, 13, and 14; approved handover, condition, incident, charge/refund, retention, export, and data-request policies; an accepted-host-supported private-upload pipeline with quarantine plus malware scanning, or strict image decode/re-encode with originals never served, and documented failure/download behavior  
**Risk:** Critical; sensitive evidence, field decisions, financial effects, and privacy rights

### Independently usable outcome

Authorized staff can run Rent collection/return checklists, record approved original-document inspection outcomes, capture condition/signature/photo evidence privately, compare return state, block release when checks fail, and process approved exceptions. Staff can manage incidents, substitutions, approved charges/deposit/refund outcomes, permissioned exports, data requests, retention jobs, and complete audit views. Sensitive evidence remains access-controlled and lifecycle-aware.

### Scope

- Add handovers, condition records/attachments, checklist versions, inspection outcomes, signatures, mileage/fuel/accessories/damage facts, comparison, and completion gates.
- Store attachments privately with strict MIME/type/size limits, random names, authorization, safe download headers, expiry/retention class, and audited download. Apply the approved host-supported pipeline: quarantine plus malware scan, or strict image decode/re-encode while originals remain quarantined and are never served. Define scan/decode failure and cleanup behavior.
- Record original identity/licence inspection result only; no document copy.
- Add permissioned exceptions requiring reason, re-authentication where appropriate, and audit.
- Add incidents/actions, severity, factual timeline, involved records, private attachments, communication, owner, resolution, and approved financial impact.
- Connect approved handover charges, deposits, and refunds through Payments rather than editing ledger history.
- Add permissioned exports with purpose, scope, encrypted/access-controlled file, expiry, and audit.
- Add data request, correction, anonymization/deletion, legal-hold, and retention execution only for approved retention classes/periods.
- Add operational alerts and mobile field flows with interrupted/offline-safe resumption.

### Non-scope

- No passport/licence image, biometric/automated identity check, public incident details, unrestricted export, invented retention period, silent deletion, unapproved damage charge, or direct payment-record edit.
- No live vehicle tracking or insurance/legal decision by software.

### Decision and stop gates

Do not start sensitive evidence/retention execution until legal/insurance/privacy owners approve checklist, inspection, signatures, photo consent, damage/incident process, charge/refund authority, retention by data class, legal hold, request verification, and deletion/anonymization behavior. Before uploads, approve and prove the accepted host can run the chosen quarantine/malware-scan or strict decode/re-encode pipeline, with originals never publicly served, safe download headers, and spoof/polyglot tests. Stop if private storage/download controls or the chosen processing control cannot run safely.

### Required proof

- Rent release is blocked until required checks pass or a permitted exception is attributed/audited.
- Identity-document images cannot be uploaded or retained.
- Attachments reject disallowed type/size, spoofed MIME, malformed/polyglot content, and cannot be fetched without permission.
- The selected quarantine/malware-scan or decode/re-encode pipeline keeps originals non-public, fails closed, records processing state, safely removes rejected/expired files, and serves only approved derivatives with safe download headers.
- Return comparison and approved charge/refund create traceable domain/ledger records.
- Incident visibility is permission-limited.
- Export scope, expiry, re-authentication, and audit work.
- Retention/data-request jobs are idempotent, honor legal holds, and never delete outside approved classes.
- Mobile interruption/retry does not duplicate signatures, evidence, charges, or transitions.

### Standalone AI prompt

```text
Implement Spinellanka Phase 15 on branch phase/15-rent-field-incidents-privacy. This is lead-owned sensitive-data, field-operations, financial, and privacy work. Do not begin until legal, insurance, finance, and privacy owners approve the required policies. Do not commit, push, or use real identity/incident/customer data in development.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 15, PRODUCT.md, docs/planning/02-system-architecture.md Operations/Audit/privacy, docs/planning/03-domain-and-data-model.md Operations/retention/invariants, docs/planning/04-security-and-privacy.md uploads/privacy/audit, docs/planning/07-delivery-roadmap-and-testing.md Phase 4, docs/ux/07-client-input-checklist.md Rent, payment/refund, legal/privacy/retention and support sections, and docs/ux/08-acceptance-and-signoff.md. Query Graft before source reads. Run full EGM because handover, private files, incidents, Payments, permissions, audit, exports, retention and field UI interact.

Run Modern Web Guidance before upload, signature, mobile/offline, forms, validation, accessibility, HTML/CSS, or client JavaScript work. Run Impeccable for staff/mobile field UI. Ask Matt remains user-invoked only.

Verify approved checklist versions, inspection outcome fields, signature/photo consent, condition/damage process, incident escalation, charge/deposit/refund authority, export purpose/scope, data-request verification, retention periods by class, legal hold, deletion/anonymization, support process, and the accepted host-supported private-upload control. Stop on any gap or if private storage, quarantine/processing, safe download, failure cleanup, or retention enforcement cannot be proven.

Implement Rent collection/return handovers, condition comparisons, private attachments, signatures, original-document inspection result only, release gates, permissioned audited exceptions, incidents/actions, approved financial effects through Payments, expiring audited exports, data requests, legal holds, and idempotent retention execution. For every attachment, enforce allowlisted MIME/type/size, random names, private quarantine, authorization, processing status, safe download headers, expiry, audited access, and fail-closed cleanup. Use the approved pipeline: quarantine plus malware scan, or strict image decode/re-encode while originals remain quarantined and are never served. Never accept passport/licence images or directly edit settled ledger history.

Test blocked/success/exception handover, forbidden identity upload, MIME/type/size mismatch, spoofed extension/content, malformed and polyglot payloads, scanner/decode failure, quarantined/original access denial, derivative authorization and headers, cleanup/expiry, interrupted duplicate actions, condition comparison, incident permissions, charge/refund trace, export authorization/expiry, request verification, retention boundaries, legal holds, audit immutability, and mobile/desktop accessibility. Run full security/repository/Impeccable/browser gates and inspect private storage/web exposure.

Prepare a PR handoff mapping every sensitive field/action to approved purpose/retention/permission, upload controls, field screenshots, web guidance, Impeccable result, payment/audit links, migration/rollback, tests, and incident/recovery runbook. Review fixes require safe reproduction with synthetic data, regression coverage, narrow correction, all privacy/security/field/full reruns, and re-review.
```

---

## Phase 16 — Verified client content and provider activation

**Branch:** `phase/16-client-provider-activation`  
**Owner:** Project lead; guided maintainer may assist with approved content entry and UI checks  
**Prerequisites:** Phases 01, 04, and 08–15; the minimum verified records used by earlier phases already exist; all launch-dependent client checklist entries are approved; provider sandbox accounts are available  
**Risk:** High; real business data, external accounts, legal/public claims, private operational data, and production-like configuration

### Independently usable outcome

A production-like staging environment contains a final reconciled set of the client's approved five-vehicle catalog, permitted imagery, rates, policy versions, legal/privacy/retention text, support details, message templates, financial-document details, and provider sandbox integrations. Every visible fact has owner/source/approval/effective date. A versioned, idempotent, auditable configuration package can reproduce and roll back the approved non-secret configuration. Traveler, payment, and incident test records remain synthetic; production credentials remain absent and production deployment remains disabled.

### Scope

- Treat this phase as final launch reconciliation and provider activation, not the first creation of usable fleet/rate/service data required by Phases 03–15.
- Reconcile every launch-blocking entry in `docs/ux/07-client-input-checklist.md` with owner, source, verification, approval, date, version, conditions, and effective date.
- Classify staging data before import: approved public/business configuration is allowed; traveler, payment, and incident data must be synthetic; private driver operational PII stays excluded unless a named owner has approved access, purpose/consent, encryption, retention, backup, and deletion controls.
- Import or enter approved fleet/driver/content/rate/policy/legal/support data through audited application paths.
- Implement a versioned, idempotent, auditable configuration export/import contract with schema version, source revision, record counts, checksums, explicit secret/PII exclusions, dry-run/validation, applied-by/time, and rollback to the prior approved configuration version.
- Validate image rights, current subject, alt text, format, dimensions, privacy, and storage.
- Activate approved map/place/route, WhatsApp, email, payment sandbox, error-reporting, and off-host-backup adapters in staging.
- Verify domain/origin restrictions, webhook signatures, templates, sender identity, quotas, cost limits, privacy terms, and failure degradation.
- Add consent-aware analytics only if the product/privacy owner approves it; omission does not block core launch.
- Replace truthful pending shells only where approved content exists.
- Run representative approved calculations and end-to-end staging journeys for all four services.
- Record content/configuration/provider versions and activation evidence without secrets or private data.

### Non-scope

- No production credential, real payment, live customer message, real traveler/payment/incident record, public claim without evidence, stock/manufacturer image without rights, copied competitor content, or production deployment.
- No private driver operational PII unless the complete named-control package is approved and proven.
- No redesign or product-policy change hidden inside content entry, and no direct unaudited database import.

### Decision and stop gates

Any missing fleet fact, rate, policy, legal text, support process, evidence, provider approval, domain control, merchant/template approval, backup destination, retention rule, staging-data classification, or configuration rollback rule blocks its dependent staging/launch behavior. Omit or keep it disabled; never fill the gap. Private driver operational PII remains excluded unless named access, purpose/consent, encryption, retention, backup, and deletion owners approve and verify every control. Escalate conflicts to the authorized client owner and record accepted changes through the decision register.

### Required proof

- A source/approval record exists for every production-visible fact and asset.
- Exactly five approved vehicles are reconciled; their import counts/checksums match and no test fixture leaks.
- Configuration import is schema-versioned, idempotent, dry-run validated, audited, excludes secrets/PII, detects drift, and can restore the prior approved version.
- A staging-data inventory proves public/business data is approved, traveler/payment/incident data is synthetic, and unapproved private driver PII is absent.
- Every approved pricing example matches staging results.
- All provider sandbox contracts, signatures, domain restrictions, retries, and degradation paths pass.
- Four complete service journeys pass with approved business configuration and synthetic people, payments, and incidents.
- Legal/support/message/document content matches approved versions.
- No production secret/data or real charge/message is used.

### Standalone AI prompt

```text
Implement Spinellanka Phase 16 on branch phase/16-client-provider-activation. Act as the lead release integrator. The guided maintainer may enter approved content but may not decide, reinterpret, import private operational data, or activate a provider. Do not commit, push, send a real customer message, make a real charge, or change an external account without explicit lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 16, PRODUCT.md, DESIGN.md, all docs/planning files, all of docs/ux/07-client-input-checklist.md, docs/ux/08-acceptance-and-signoff.md, prior phase configuration/import decisions, provider contracts/tests, and deployment/security configuration. Query Graft before source reads. Run full EGM for any unresolved cross-module activation or configuration-transfer design. Run Modern Web Guidance and Impeccable for affected public content/UI. Ask Matt remains user-invoked only.

Treat Phase 16 as final reconciliation and activation, not the first source of usable fleet/rate/service configuration. Create a launch-input matrix and a staging-data inventory. For every value or asset require owner, source, Verified/Approved status, approver/date, version, conditions, and effective date. Allow approved public/business configuration. Require synthetic traveler, payment, and incident records. Exclude private driver operational PII unless a named owner approves access, purpose/consent, encryption, retention, backup, and deletion controls. Stop on any gap; never copy competitor content or use plausible filler.

Build or complete a versioned, idempotent, auditable configuration export/import path through application services. Include schema version, source revision, dry-run validation, record counts/checksums, explicit secret/PII exclusions, drift/error handling, actor/time, and rollback to the prior approved configuration version. Verify exactly five approved vehicle records without duplicates. Test repeat import, partial/corrupt package rejection, schema mismatch, checksum mismatch, excluded-field leakage, rollback, and audit history. Never use direct unaudited database writes.

Activate only approved sandbox adapters for maps/routes, WhatsApp, email, payments, error reporting, and off-host backups. Verify current official setup, domain/origin restrictions, signatures, templates, sender identity, quotas/cost controls, privacy terms, secret storage, retries, and safe degradation. Add analytics only with explicit product/privacy approval.

Run approved pricing examples and all four end-to-end service journeys through request, proposal, sandbox payment where applicable, operations, and field flows. Verify content, legal/support versions, imagery rights/alt text, messages, documents, and data-class exclusions. Run full repository, security, provider contract, Playwright/axe, Impeccable, mobile/desktop, slow/failure, import/rollback, and backup checks.

Prepare a PR handoff containing the input matrix, staging-data inventory, source/approval references, configuration schema/version, import counts/checksums without private data, exclusion and rollback proof, provider sandbox evidence, secret names without values, screenshots, calculations, omitted/blocked items, tests, and production activation checklist. Review fixes must preserve source truth: reproduce, correct the narrow data/configuration/adapter issue, rerun affected journeys and full gates, then request re-review.
```

---

## Phase 17 — Launch hardening, recovery and operational handoff

**Branch:** `phase/17-launch-hardening`  
**Owner:** Project lead  
**Prerequisites:** Phase 16; owner-approved numeric mobile performance, page-weight, asset-size, and query budgets; an approved observation-window policy  
**Risk:** Critical; release acceptance, security, accessibility, recovery, and operations

### Independently usable outcome

The merged application source is approved as a production-candidate source state. It passes accessibility, security, privacy, previously approved performance/query/asset budgets, reliability, migration, backup/restore, and rollback gates. Named staff are trained on documented operating, support, escalation, incident, reconciliation, and recovery procedures. After this phase merges, protected-`main` CI builds and retains the exact candidate artifact/checksum used by Phase 18 preparation; no artifact from the Phase 17 feature branch is eligible for production.

### Scope

- Complete a WCAG 2.2 AA automated and manual audit across public, guest, staff, driver, and field journeys.
- Test keyboard, screen reader, zoom/reflow, contrast, target size, status/error announcements, reduced motion, and no-color states.
- Verify previously approved numeric mobile performance, page-weight, asset-size, and query budgets under the approved throttling profile and production-like data; do not invent or loosen a budget during hardening.
- Conduct an authorized security review/penetration test of public, guest, driver, staff, upload, export, webhook, and payment surfaces; resolve or formally accept findings through an authorized owner.
- Verify HTTPS/security headers/cookies, trusted hosts/proxies, debug off, directory exposure, rate limits, secret redaction, token rules, and least privilege.
- Rehearse empty-to-current and previous-release-to-current migrations using expand/contract compatibility.
- Demonstrate backup integrity and restore into non-production; reconcile accepted booking/payment evidence in the incident procedure.
- Rehearse application rollback with the post-migration schema.
- Validate scheduler, short queue drain, expiry, reconciliation, retention, backup, and monitoring alerts.
- Finalize staff training, SOPs, support/escalation, launch communication, incident, payment reconciliation, data-request, and rollback runbooks.
- Record the approved production observation window: duration, monitored signals, numeric thresholds, check cadence, rollback owner, incident channel, and authorized exit approver.
- Merge this phase before artifact selection. Protected-`main` CI then builds, checksums, signs/attests where supported, and retains the exact candidate artifact. Record its source commit; never freeze a feature-branch artifact.

### Non-scope

- No production deployment, DNS cutover, real payment/message, new feature, redesign, broad refactor, or unresolved medium/high security bypass.
- No launch acceptance based only on automated tests.
- No new performance target chosen after measurement and no production artifact built from `phase/17-launch-hardening`.

### Decision and stop gates

Do not start until authorized owners approve numeric mobile performance, page-weight, asset-size, and query budgets plus the observation-window duration, signals, thresholds, cadence, rollback owner, and exit approver. Stop release for any launch-blocking accessibility/security defect, failed approved budget, missing client input, failed restore/rollback, unprotected route/file, broken critical journey, unresolved payment/reconciliation invariant, absent scheduler/queue/backup evidence, or unnamed operational owner. Risk acceptance requires the authorized owner, scope, reason, compensating control, expiry/review date, and evidence. A feature-branch artifact is never eligible for Phase 18.

### Required proof

- All ten minimum browser journeys in the approved test roadmap pass at required viewports and failure states.
- WCAG audit has no launch-blocking defect.
- Penetration-test findings are fixed or explicitly accepted by an authorized owner.
- Critical pages meet each previously approved numeric mobile performance, page-weight, asset-size, and query budget.
- Backup restore and application rollback are demonstrated, timed, and documented.
- Production-like scheduler/queue/monitoring alerts are observed.
- Staff can execute representative request, proposal, payment, incident, data-request, backup, and rollback drills.
- The observation policy names duration, signals, thresholds, cadence, rollback owner, incident channel, and exit approver.
- After merge, protected-`main` CI produces a retained candidate artifact whose checksum and source commit match the merged Phase 17 state; no feature-branch artifact is accepted.

### Standalone AI prompt

```text
Implement Spinellanka Phase 17 on branch phase/17-launch-hardening. Act as the lead release engineer. This phase fixes launch blockers and proves operations; it does not add features, deploy production, or select a feature-branch artifact. Do not commit, push, run an external penetration test, alter hosting/provider accounts, or accept risk without explicit lead/owner authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 17, PRODUCT.md, DESIGN.md, every docs/planning file, docs/ux/07-client-input-checklist.md, docs/ux/08-acceptance-and-signoff.md, approved numeric performance/query/asset budgets, the approved observation-window policy, all operations/runbooks, CI/deployment workflows, and provider test evidence. Query Graft before source reads. Use full EGM only for a required multi-component remediation. Run Modern Web Guidance and Impeccable before public/browser fixes. Ask Matt remains user-invoked only.

Before work, prove the authorized owners approved numeric mobile performance, page-weight, asset-size, and query budgets. Also prove approval of observation duration, monitored signals, numeric thresholds, check cadence, rollback owner, incident channel, and exit approver. Stop if any value or owner is absent. Do not derive a passing target from current measurements or weaken a target to make a result pass.

Create a release-gate matrix covering all accepted product journeys, WCAG 2.2 AA, security/privacy, every approved performance/query/asset budget, provider failure, migration compatibility, scheduler/queue, monitoring, backup/restore, rollback, staff training, client inputs, and observation criteria. Every row needs a command/path, owner, numeric or binary threshold, evidence, and pass/fail result.

Run automated axe plus manual keyboard, screen-reader, zoom, contrast, target, status, and reduced-motion checks. Run authorized security tests for public, private-token, staff, driver, upload, export, webhook, and payment boundaries. Verify production-like headers/cookies/debug/directory protection, least privilege, rate limits, and redaction. Measure the approved budgets using the approved device/network/data profile. Rehearse empty/current upgrade migrations, backup restore, prior-release application rollback, scheduler/queue/expiry/reconciliation/retention, and alerts. Conduct staff drills with synthetic data.

Fix only proven launch blockers. Record authorized risk acceptance with owner, scope, reason, compensating control, expiry, and review date. Stop on any unresolved launch blocker, failed budget/restore/rollback, missing owner/input, or unsafe production assumption. Do not freeze or nominate an artifact from this feature branch.

Run the complete repository, browser, provider, security, accessibility, performance, and recovery suite. Prepare a PR handoff with the gate matrix, measurements, screenshots/reports, findings and dispositions, restore/rollback timings, training evidence, approved observation policy, remaining accepted risks, and exact Phase 18 preparation checklist. After lead-approved merge, require protected-main CI to build/checksum and retain the candidate artifact and record its exact merged source commit. Review fixes require reproducing the failed gate, narrow remediation, regression evidence, full affected and release-gate reruns, and re-review.
```

---

## Phase 18 — Go-live preparation and authorized cPanel production run

**Preparation branch (18A):** `phase/18-go-live-preparation`  
**Production run (18B):** no feature branch and no code changes; deploy only the retained protected-`main` artifact created after 18A merges  
**Owner:** Project lead  
**Prerequisites:** Phase 01 accepted-host gate; merged Phase 17; protected-`main` candidate evidence; passed deployment/rollback rehearsal; approved production configuration; approved DNS/TLS cutover or prerequisite state; explicit preparation, merge, and production authorizations  
**Risk:** Critical and outward-facing; production data, secrets, migrations, DNS, TLS, providers, staff access, and customer traffic

### Independently usable outcome

The exact immutable artifact built from the reviewed Phase 18A merge commit on protected `main` is installed on the accepted cPanel environment. Approved production configuration is imported before public activation. The domain serves only Laravel `public/`; named least-privilege staff accounts use MFA; database migrations, cron, queue, backups, monitoring, DNS/TLS, and provider origins work; focused smoke tests pass; maintenance mode is cleared; and the prior release remains immediately rollbackable throughout the objectively defined observation window.

### Two-stage boundary

1. **18A — preparation PR:** Use `phase/18-go-live-preparation` only for reviewed deployment automation, configuration-contract, runbook, DNS/TLS plan, account-provisioning plan, and non-secret readiness evidence. Run all gates, review, and merge it to protected `main`. No production action occurs from this branch.
2. **Artifact creation:** After 18A merges, protected-`main` CI builds once, verifies, checksums, and retains the exact production artifact and dependency/SBOM manifest. Record the merge commit and artifact identity, then set the phase status to `Prepared`; it is not `Complete`.
3. **18B — authorized production run:** Make no code change and create no deployment feature branch. An authorized operator deploys only that pinned artifact through the rehearsed locked path. Any required code correction returns to a reviewed PR and creates a new protected-`main` artifact.

Never deploy an artifact built from `phase/18-go-live-preparation`, any legacy go-live branch, a hotfix branch, a local checkout, or any arbitrary branch.

### Accepted deployment paths

1. **Preferred:** GitHub Actions builds and verifies the immutable artifact; a restricted SSH key uploads and activates it.
2. **Acceptable after rehearsal:** GitHub Actions builds/verifies/transports the artifact; cPanel Git deployment tasks in committed `.cpanel.yml` or an equivalent restricted server-side action execute the same reviewed PHP/activation sequence even if interactive Terminal is unavailable.
3. **Transport only:** SFTP may upload the immutable artifact only when a verified restricted cPanel action can run checksum verification, links, migrations, optimization, atomic activation, health checks, and rollback.

File Manager/FTP-only hosting with no safe PHP CLI/cron/document-root/activation path is rejected. Do not add a public web deploy endpoint.

### Scope

#### 18A — reviewed preparation

- Reconfirm that every Phase 01 hosting capability and exact PHP web/CLI version requirement still holds.
- Define the GitHub production environment, minimum secrets, protected-`main` restriction, separate manual approval where the repository plan supports it, and deployment concurrency lock.
- If required-reviewer environment protection is unavailable for the private repository plan, retain protected `main`, manual `workflow_dispatch`, and explicit lead approval recorded in the run.
- Approve a DNS/TLS prerequisite or cutover plan covering current/target records, ownership, TTL/preflight timing, certificate issuance and installation order, canonical-host redirects, provider origin/webhook timing, propagation checks, monitoring, and record/certificate rollback.
- Validate the Phase 16 production configuration package/import contract, exact approved version/checksum, secret/PII exclusions, dry-run output, rollback version, and import owner.
- Define named production staff provisioning: unique accounts, least-privilege roles, MFA enrollment, recovery-code handoff, sensitive-session revocation, and removal/disablement of test, default, shared, or temporary accounts before public activation.
- Freeze objective observation criteria already approved in Phase 17: duration, signals, thresholds, check cadence, rollback owner, incident channel, and exit approver.
- Finalize a step-by-step deployment/rollback runbook, including maintenance-mode cleanup on every success, failure, and rollback path.
- Merge 18A, then require protected-`main` CI to build/checksum and retain the deployable artifact. Do not build or nominate the production artifact before merge.

#### 18B — authorized production run

- Verify the pinned artifact source commit/checksum and acquire the deployment concurrency lock.
- Back up the current database/shared data and verify backup readability before migration.
- Create a new release directory, verify checksum, link protected shared `.env`/storage, run backward-compatible migrations, clear/cache/optimize, and atomically switch `current`.
- Enter maintenance mode only when the approved runbook requires it. If entered, run `php artisan up` on the active release before public smoke tests. On every failure/rollback path, restore the previous `current` pointer when needed, run `php artisan up` on the restored active release in a finally/cleanup step, and verify that no maintenance response remains.
- Import the exact approved production configuration through the audited idempotent contract before public activation; verify counts/checksums, exclusions, actor/time, and rollback readiness.
- Provision named staff accounts, complete MFA/recovery handoff through a private channel, verify least privilege, revoke setup sessions, and remove/disable every test/default/shared/temporary account before public activation.
- Execute the approved DNS/TLS order; verify certificate chain/renewal, canonical redirects, propagation, provider origins/webhooks, and rollback controls.
- Verify `/up`, home, one public service, guest invalid shell, staff login/MFA shell, driver invalid shell, payment-return pending shell, scheduler, queue drain, storage permissions, logs, backups, monitoring, headers, TLS, debug off, and directory denial.
- Enable production provider origins/webhooks/templates only in the approved order and perform non-destructive verification.
- Run the focused go-live smoke suite with synthetic/non-charge checks where possible.
- Observe errors, queue, messages, payments, cron, disk/inodes, database, SSL, and backups at the approved cadence for the full window. Exit only when every signal stays within threshold and the named exit approver records acceptance.
- Roll back the application pointer immediately on a release failure; follow incident/restore/reconciliation procedure for data corruption.

### Non-scope

- No build on production, feature-branch deployment, direct edit in `current`, unreviewed hotfix, destructive migration cleanup, public debug mode, test/default/shared account, real charge without separately approved transaction, or deletion of the previous release during observation.
- No improvised DNS, TLS, configuration, account, provider, or maintenance step outside the reviewed runbook.

### Decision and stop gates

18A preparation, 18A merge, artifact promotion, and 18B execution require separate explicit lead authorizations. Every production-changing step requires current authorization. Stop before DNS/TLS change, deployment, migration, production-configuration import, account provisioning, provider-production switch, real-message/payment check, or rollback if approval is absent. Stop and roll back on failed checksum, backup, migration, configuration import, account/MFA control, health, TLS/header, scheduler/queue, provider signature, critical smoke, maintenance cleanup, or unexplained error-rate gate. Application rollback cannot repair destructive data damage; use verified backup plus audit/provider reconciliation under incident command.

### Required proof

- Production serves the exact protected-`main` 18A merge commit and retained artifact checksum; no branch/local artifact is used.
- The approved production configuration version imports idempotently with matching counts/checksums, no excluded secret/PII fields, an audit record, and a tested rollback version.
- `.env`, source-private files, storage, logs, backups, Git, and Composer metadata are not web-accessible.
- `APP_DEBUG=false`, HTTPS, certificate/renewal, canonical redirects, cookies, headers, trusted hosts/proxies, noindex boundaries, and dedicated DB user are correct.
- Named staff accounts have least privilege and MFA/recovery handoff; setup sessions are revoked and test/default/shared/temporary accounts are absent or disabled.
- If maintenance mode was used, it was cleared before public smoke; success and rollback cleanup prove the active release cannot remain accidentally down.
- Migrations, scheduler, short queue drain, expiry, reconciliation, backup, restore alert, monitoring, and DNS/TLS propagation are observed.
- Public, guest, driver, staff, payment-return, and provider-webhook boundaries pass focused checks.
- Previous release can still run against the migrated schema and rollback remains rehearsed.
- The full observation duration completes with every approved signal within threshold; the named exit approver records acceptance.

### Standalone AI prompt

```text
Execute Spinellanka Phase 18 as two strictly separated stages. First prepare and review 18A on branch phase/18-go-live-preparation. After it merges, allow protected-main CI to create the pinned artifact. Then run 18B with no branch and no code changes. Act as the deployment operator only after the project lead explicitly authorizes each outward-facing action. Never infer authorization from this roadmap. Do not commit, push, merge, change DNS/TLS, deploy, migrate, import production configuration, provision production accounts, switch providers, send a real message, make a real charge, or roll back without current explicit approval for that action.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 18, PRODUCT.md, docs/planning/01-technology-stack.md, docs/planning/02-system-architecture.md cPanel/degradation/rollback, docs/planning/04-security-and-privacy.md, all of docs/planning/05-cpanel-deployment-and-operations.md, docs/planning/07-delivery-roadmap-and-testing.md release strategy, Phase 01 hosting verdict/rehearsal, Phase 16 configuration import evidence, Phase 17 release-gate and observation matrices, the approved DNS/TLS plan, and the candidate artifact manifest/checksum. Query Graft before any application-source investigation. Do not redesign architecture during go-live.

For 18A, confirm the accepted hosting path: restricted SSH activation, or verified cPanel Git/restricted deployment tasks capable of the exact server-side PHP and atomic activation sequence. SFTP is transport only. If only File Manager/FTP is available, stop and require a hosting upgrade. Never create a public deploy endpoint or expose the full Laravel application under the web root.

On 18A, finalize and test only reviewed deployment automation, configuration-contract/runbook, DNS/TLS order and rollback, named staff/MFA/recovery provisioning, maintenance cleanup, provider activation, objective observation criteria, and secret-free evidence. Run all applicable gates and Ponytail review. Prepare a PR handoff with scope/non-scope, host verdict, configuration and account controls, DNS/TLS order, artifact-build boundary, tests, rehearsal evidence, stop gates, and rollback. Merge through protected main only after approval. Do not deploy or nominate a branch artifact. After merge, require protected-main CI to build once, verify, checksum, and retain the exact production artifact and dependency/SBOM manifest; record Phase 18 as Prepared, not Complete. If code or deployment automation changes after that build, invalidate it and repeat review/merge/build.

Before every 18B external action, state the action, effect, rollback, and required approval. After approval, verify the pinned protected-main artifact, GitHub production protection, deployment lock, secret isolation, approved DNS/TLS order, production configuration checksum, account plan, and backup readability. Upload to a new release, verify checksum, link shared protected .env/storage, enter maintenance only if the runbook requires it, run backward-compatible migrations, optimize, atomically switch current, and import the approved configuration before public activation.

If maintenance mode was entered, run php artisan up on the active release before public smoke tests. Put a finally/cleanup action on every failure path: restore the prior current pointer when required, run php artisan up on the restored active release, and verify the maintenance response is gone. Provision unique staff accounts, complete private MFA/recovery handoff, verify least privilege, revoke setup sessions, and remove/disable test/default/shared/temporary accounts before public activation. Execute DNS/TLS and provider activation only in the approved order.

Stop and roll back on any failed checksum, backup, migration, configuration import, account/MFA gate, /up, critical route, TLS/header boundary, scheduler/queue, webhook signature, smoke test, maintenance cleanup, or unexplained production error. Do not attempt destructive data repair. Use the approved incident, restore, and payment/booking reconciliation procedure.

Record commands with secret values redacted, separate approvals, timestamps in Asia/Colombo and UTC, protected-main commit/artifact/checksum, release identifier, migration/configuration import results, staff/MFA control evidence without recovery secrets, DNS/TLS evidence, smoke results, provider verification, monitoring, observation measurements, owner, and rollback readiness. Exit only after the full approved duration passes, every signal remains within its numeric/binary threshold, and the named exit approver accepts it.

Prepare the final handoff with production URL checks, exact artifact, release identifier without account secrets, configuration version/count/checksum, staff-access control status, migration/cron/queue/backup/monitoring state, DNS/TLS state, smoke results, active providers, observation evidence, accepted risks, support ownership, and rollback instructions. After observation, a lead-controlled protected-main status update marks Phase 18 Complete. Any review or incident fix uses the post-launch protocol below, never a live-server edit or branch artifact.
```

---

# Per-Phase Standalone Review-and-Fix Prompts

Use the matching prompt after implementation and before final PR handoff. Each review must examine both the implementation and the phase plan. A plan defect is not permission to improvise product, legal, finance, privacy, security, architecture, or design behavior; record it, obtain the authorized decision, update the durable authority, then resume.

## Phase 00 review-and-fix prompt

```text
Review the completed Spinellanka Phase 00 foundation/public shell against its merged baseline and current main. This is a retrospective baseline review, not permission to rebuild Phase 00. Do not commit, push, merge, deploy, or create a repair branch without explicit project-lead authorization. If no authorized repair branch exists, report findings only; after a narrow repair branch is authorized, run the full fix and review loop below.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 00, PRODUCT.md, DESIGN.md, docs/planning/01-technology-stack.md, docs/planning/02-system-architecture.md, docs/planning/04-security-and-privacy.md, docs/planning/06-production-design-direction.md, docs/planning/07-delivery-roadmap-and-testing.md, docs/ux/08-acceptance-and-signoff.md, the exact Phase 00 merge/diff, and its tests. Query Graft before application-source reads. On an authorized repair branch invoke /ponytail-review branch and /code-review. Use /egm-review if the implementation or roadmap conflicts across components. Run Modern Web Guidance and Impeccable audit/polish for public-shell findings; Ask Matt remains user-invoked only.

Review both code and plan for concrete failures: Laravel/PHP/Node/tool version drift; broken home or four service routes; duplicate service vocabulary; invented facts; incorrect Cloud/Navy/Amber/Inter rules; responsive, keyboard, focus, reduced-motion, or console defects; test/CI gaps; and Phase 00 assumptions that make a later phase unsafe. Classify findings as Must fix, Should fix, or Nice to have. For each give a reproducible failure scenario, exact file/line or roadmap section, consequence, and smallest safe correction. Reject style-only churn and speculative findings.

Fix only confirmed in-scope defects after authorization. Stop for any product, client, security, architecture, legal, finance, privacy, or design decision. Add regression coverage, run targeted tests, the full repository gate, real-browser checks at 320px, 390px, 768px, and 1440px, keyboard/reduced-motion checks, Impeccable detector, and rerun Ponytail/code review. Finish with a ship or fix-required verdict, unresolved plan defects and owners, commands/results, runtime evidence, and rollback impact. Ship only when no Must-fix finding remains.
```

## Phase 01 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 01 on branch phase/01-hosting-ci-release. Do not commit, push, merge, deploy, change hosting/GitHub settings, or use a production secret without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 01, PRODUCT.md, docs/planning/01-technology-stack.md, docs/planning/02-system-architecture.md deployment/degradation rules, docs/planning/04-security-and-privacy.md, all of docs/planning/05-cpanel-deployment-and-operations.md, docs/planning/07-delivery-roadmap-and-testing.md, docs/development/environment.md, the Phase 01 PR/diff, hosting evidence, workflows/scripts, and tests. Query Graft before application-source reads. Invoke /ponytail-review branch and /code-review. Use /egm-review to compare any multi-component release design with the resolved EGM artifact. Use Modern Web Guidance/Impeccable only if browser smoke/UI code changed. Ask Matt remains user-invoked only.

Review implementation and plan for concrete failures: false host acceptance; PHP web/CLI mismatch; missing extensions/DB transactions/cron/queue/document-root controls; unsafe FTP-only activation; branch-built or mutable artifacts; checksum/exclusion/SBOM gaps; leaked secrets; concurrent deploys; destructive or backward-incompatible migration steps; failed atomic switch/rollback; missing staging rehearsal; missing Ponytail v5.3.3 verification; and a roadmap gate that can mark hosting complete while blocked. Report Must fix, Should fix, and Nice to have. Every finding needs a failure scenario, exact location, consequence, and minimal correction.

Fix confirmed in-scope issues only. Stop for hosting purchase, secret, security-risk acceptance, deployment architecture, or external-account decisions. Add regression/contract tests and rerun the failing check, artifact inspection, staging activation/rollback rehearsal, PHP 8.3/8.4 and frontend CI, audits, and the full repository gate. Rerun Ponytail/code review. Finish with ship, fix required, or Blocked—hosting verdict; list plan defects/owners, evidence, skipped gates, and rollback proof. Ship only when no Must-fix finding remains and the accepted-host gate is genuinely satisfied.
```

## Phase 02 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 02 on branch phase/02-identity-access-audit. Do not commit, push, merge, provision production users, or alter external identity settings without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 02, PRODUCT.md, docs/planning/02-system-architecture.md identity/audit boundaries, docs/planning/03-domain-and-data-model.md staff/audit models, docs/planning/04-security-and-privacy.md, the approved Phase 02 EGM security matrix, docs/ux/08-acceptance-and-signoff.md, the PR/diff, migrations, policies, tests, and first-owner runbook. Query Graft before source reads and trace every changed authorization symbol. Invoke /ponytail-review branch and /code-review. Invoke /egm-review against the approved security matrix for cross-component drift. Use Modern Web Guidance/Impeccable for staff forms or browser behavior. Ask Matt remains user-invoked only.

Review implementation and plan for permission-key/grant drift; shared/default admin accounts; MFA enrollment/recovery bypass; missing re-authentication; weak session/idle/revocation behavior; route/query/action/job/export authorization gaps; tenantless record leakage; unsafe first-owner bootstrap; mutable/incomplete audit events; secret/PII logging; missing rate limits; migration rollback risk; and security-matrix omissions. Classify Must fix, Should fix, and Nice to have with a concrete failure scenario, exact location, consequence, and smallest safe fix.

Fix only confirmed in-scope security defects. Stop for permission grants, MFA/session values, recovery policy, risk acceptance, or product/privacy decisions not already approved. Add denial-first regression tests for every affected route/query/action/job/export plus MFA, recovery, revocation, audit immutability, bootstrap replay, and session boundaries. Run targeted security tests, migrations from empty/previous schema, browser accessibility where changed, full repository gates, then Ponytail/code review again. Finish with ship or fix-required verdict and explicit unresolved plan defects/decision owners. Ship only with zero Must-fix findings.
```

## Phase 03 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 03 on branch phase/03-catalog-fleet-admin. Do not commit, push, merge, import client data, or reinterpret fleet facts without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 03, PRODUCT.md, docs/planning/02-system-architecture.md Catalog boundaries, docs/planning/03-domain-and-data-model.md Catalog records, docs/planning/04-security-and-privacy.md media/admin controls, docs/ux/07-client-input-checklist.md fleet/driver sections, docs/ux/08-acceptance-and-signoff.md, the lead-approved resolved Catalog EGM design, PR/diff, migrations, import contract, and tests. Query Graft first. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the Catalog design. Use Modern Web Guidance and Impeccable for Filament/forms/media UI. Ask Matt remains user-invoked only.

Review implementation and plan for invented fleet/driver data; schema/constraint mismatch; duplicate or unstable exact-vehicle identity; service-eligibility drift; unsafe public/private media boundary; unscoped admin queries/actions; missing permission/audit events; non-idempotent or unaudited import; direct database bypass; irreversible migration; stale/disabled vehicle behavior; and assumptions that break availability/pricing phases. Classify Must fix, Should fix, and Nice to have. Every finding needs a failure scenario, exact location, consequence, and minimal fix.

Fix confirmed phase-owned defects only. Stop for client facts, eligibility, driver tie, media rights, privacy, permission, or architecture decisions. Add regression tests for constraints, duplicate/repeat import, invalid/partial package rejection, authorization denial, audit, inactive/maintenance states, private media access, clean/upgrade migration, and rollback compatibility. Run targeted tests, Filament/browser/accessibility checks, full gates, and rerun Ponytail/code review. Finish with ship or fix-required verdict, plan defects and owners, evidence, and schema rollback impact. Ship only with no Must-fix finding.
```

## Phase 04 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 04 on branch phase/04-public-content-fleet. Do not commit, push, merge, publish an unverified fact/asset, or change the design system without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 04, PRODUCT.md, DESIGN.md, .impeccable/design.json, affected .impeccable/surfaces contracts, docs/planning/06-production-design-direction.md, docs/ux/07-client-input-checklist.md content/fleet/imagery/proof sections, docs/ux/08-acceptance-and-signoff.md, PR/diff, screenshots, and tests. Query Graft first. Invoke /ponytail-review branch and /code-review. Use /egm-review if public surfaces conflict with Catalog/service architecture. Run Modern Web Guidance searches/retrieval and Impeccable context, audit, and polish. Ask Matt remains user-invoked only.

Review code and plan for fabricated claims/assets; private data exposure; wrong exact-vehicle detail/eligibility; CTA activation before Phases 07/08/09/10; inaccessible or broken responsive layout; missing alt text provenance; image/LCP/CLS waste; improper headings/landmarks/focus; unsupported browser behavior without fallback; mixed Inter/Allo output; unofficial or wrong-version font assets; absent OFL/copyright notice; third-party font requests; unused oblique payloads; unreadable fallback or font-caused layout shift/clipping; design-token drift; stale surface/DESIGN records; and missing empty/pending states. Report Must fix, Should fix, and Nice to have with concrete failure, exact location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for content approval, image rights, service eligibility, CTA/product behavior, font source/license, or design-direction decisions. Add regression/browser coverage. Run targeted tests, Modern Web Guidance compliance check, Impeccable detector/audit/polish, real-browser journeys at 320px/390px/768px/1440px, keyboard, 200% zoom, screen reader, reduced motion, slow/failure and console checks, network/font payload inspection, fallback rendering, and layout-shift/clipping review, plus full repository gates. Rerun Ponytail/code review. Finish with ship/fix-required verdict, applied guide IDs, screenshots, Allo source/version/license evidence, updated design records, unresolved plan defects/owners, and no-fabrication evidence.
```

## Phase 05 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 05 on branch phase/05-availability-schedule. Do not commit, push, merge, change timing/override policy, or run destructive shared-database tests without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 05, PRODUCT.md, docs/planning/02-system-architecture.md locking/degradation rules, docs/planning/03-domain-and-data-model.md schedule/hold invariants, docs/planning/04-security-and-privacy.md, approved timing/override/expiry/driver-unavailability decisions, resolved EGM artifact, PR/diff, migrations, and concurrency tests. Query Graft first and trace all schedule-write callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the availability design. Use Modern Web Guidance only for affected date/time/form/browser behavior. Ask Matt remains user-invoked only.

Review implementation and plan for writes outside one transaction; missing exact-vehicle row lock; wrong stale-hold expiry order; overlap boundary/timezone errors; partial multi-interval Ride blocks; race-created double bookings; inconsistent manual/maintenance/handover/offline blocks; unauthorized override; missing actor/reason/audit; permanent-driver unavailability not blocking the tied chauffeured vehicle; unsafe deadlock retry; weak DB indexes/constraints; and tests that use SQLite or only one engine. Classify Must fix, Should fix, and Nice to have with concrete interleaving/failure, exact location, consequence, and minimal correction.

Fix only confirmed in-scope defects. Stop for notice/buffer/expiry/override/replacement policy or schema architecture decisions. Add deterministic regression tests for boundary, stale, duplicate, all-or-nothing multi-interval, driver-unavailability, deadlock/retry, and concurrent-writer behavior. Prove on MariaDB 10.11 and MySQL 8.0, then on the accepted hosting engine when available. Run clean/upgrade migration, targeted and full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, lock-order evidence, DB matrix, unresolved plan defects/owners, and rollback impact. No Must-fix finding may remain.
```

## Phase 06 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 06 on branch phase/06-pricing-policy-estimates. Do not commit, push, merge, or choose/alter a rate, tax, fee, currency, exchange, discount, deposit, or override rule without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 06, PRODUCT.md, docs/planning/02-system-architecture.md Pricing boundary, docs/planning/03-domain-and-data-model.md money/snapshot rules, docs/planning/04-security-and-privacy.md finance/audit controls, docs/ux/07-client-input-checklist.md pricing/payment/policy sections, approved examples/edge cases, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace pricing callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the approved Pricing design. Use Modern Web Guidance/Impeccable for affected estimate forms/displays. Ask Matt remains user-invoked only.

Review code and plan for floating-point money; missing ISO currency; unlabelled approximate LKR; silent exchange-rate changes; mutable historical estimates; incomplete rule/version/component snapshots; wrong rounding/tax/fee order; service/vehicle eligibility bypass; unauthorized discounts/overrides; stale estimate reuse; non-deterministic calculation; missing explanation; PII in snapshots/audit; and incomplete approved examples. Report Must fix, Should fix, and Nice to have with exact input-to-wrong-output scenario, location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for any unapproved financial/policy value or owner decision. Add regression tests for every approved example and edge, integer overflow/boundaries, rounding, currency, rate-version changes, stale estimates, authorization, immutable history, repeat calculation, and migration compatibility. Run targeted pricing tests, browser/accessibility checks where changed, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, example matrix, unresolved plan defects/owners, and financial/rollback impact. Ship only with no Must-fix finding.
```

## Phase 07 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 07 on branch phase/07-airport-pickup-core. Do not commit, push, merge, contact a traveler, or decide booking/policy behavior without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 07, PRODUCT.md, applicable Booking/Availability/Pricing sections in docs/planning/02-system-architecture.md and 03-domain-and-data-model.md, docs/planning/04-security-and-privacy.md, docs/ux/07-client-input-checklist.md Airport Pickup/contact/consent/support sections, docs/ux/08-acceptance-and-signoff.md, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace lifecycle/hold/schedule callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the cross-module design. Run Modern Web Guidance and Impeccable for the booking UI. Ask Matt remains user-invoked only.

Review implementation and plan for wrong exact-vehicle eligibility; missing 15-minute hold; hold refresh or release errors; pending-request expiry gaps; submission shown as confirmed; optional email treated as required or WhatsApp/mobile omitted; booker/traveler fields conflated; required acceptance mixed with optional prechecked marketing; timezone/flight/buffer errors; partial Booking/Availability/Pricing commits; duplicate submission; stale estimate; unauthorized manual confirmation; confirmation without availability recheck; missing actor/time/policy/manual-contact outcome; and leaked private data. Classify Must fix, Should fix, and Nice to have with failure scenario, exact location, consequence, and minimal correction.

Fix only confirmed in-scope defects. Stop for flight/wait/no-show, pending expiry, confirmation authority, consent copy, support, fleet, or pricing decisions. Add regression tests for happy, error, stale, expired, duplicate, concurrent, and permission paths plus manual-confirmation audit and rollback. Run targeted domain tests, critical Playwright/axe journey, mobile/desktop/keyboard/slow/failure checks, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, lifecycle evidence, unresolved plan defects/owners, and migration/rollback impact. No Must-fix finding may remain.
```

## Phase 08 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 08 on branch phase/08-airport-transfer. Do not commit, push, merge, or choose transfer policy/rates/fleet facts without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 08, PRODUCT.md, the approved Phase 07 pattern, applicable architecture/data/security docs, docs/ux/07-client-input-checklist.md Airport Transfer/geography/timing/pricing/support sections, docs/ux/08-acceptance-and-signoff.md, PR/diff, migrations, and tests. Query Graft first and compare changed symbols with Phase 07 reuse. Invoke /ponytail-review branch and /code-review. Use /egm-review if the phase changes shared lifecycle/interfaces instead of extending the approved pattern. Run Modern Web Guidance and Impeccable for UI. Ask Matt remains user-invoked only.

Review code and plan for duplicated/divergent core booking logic; wrong exact-vehicle eligibility; departure/airport/timezone or recommended-arrival calculation errors; unsafe-time warning/acceptance gaps; hidden policy/rate assumptions; stale hold/estimate handling; pending status errors; contact/consent drift; authorization/audit gaps; missing custom-quote/failure state; and CTA activation mismatch. Report Must fix, Should fix, and Nice to have with concrete failure, exact location, consequence, and smallest safe fix.

Fix confirmed Phase 08 defects only. Stop for supported airports/routes, buffers, liability wording, timing authority, rates, or shared lifecycle changes. Add transfer-specific boundary/error tests while reusing shared services. Run targeted tests, all Phase 07 regression tests, transfer Playwright/axe journey, responsive/keyboard/slow/failure checks, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, reuse/divergence evidence, plan defects/owners, and rollback impact.
```

## Phase 09 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 09 on branch phase/09-ride. Do not commit, push, merge, or redesign availability locking, Ride policy, rates, or fleet eligibility without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 09, PRODUCT.md, the approved Phase 07 pattern, Phase 05 multi-interval design/tests, applicable architecture/data/security docs, docs/ux/07-client-input-checklist.md Ride/geography/timing/pricing/support sections, docs/ux/08-acceptance-and-signoff.md, PR/diff, migrations, and tests. Query Graft first and trace shared multi-interval callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review if shared availability/lifecycle drift exists. Run Modern Web Guidance and Impeccable for UI. Ask Matt remains user-invoked only.

Review code and plan for outbound/return partial commits; wrong overlap or ordering; same-exact-vehicle failure; lock redesign outside Phase 05; duplicate core booking logic; stop/wait/route-change assumptions; timezone/duration boundaries; stale hold/estimate; pending status/contact/consent drift; custom-quote/failure gaps; route map not matching ordered destinations; JavaScript/provider failure losing the itinerary; geolocation starting without explicit action; coordinates entering Livewire/fetch/provider/analytics/error-reporting/URL/form/storage/service-worker/log/queue/database boundaries; watch cleanup leaks; denial/unsupported/inaccurate states blocking the Ride page; location presented as vehicle tracking or trip proof; and concurrency tests that cannot prove all-or-nothing behavior. Classify Must fix, Should fix, and Nice to have with exact input/interleaving or privacy failure, location, consequence, and minimal fix.

Fix confirmed Ride-owned defects only. Stop for wait/stop/return/same-vehicle/route policy, rates, map-provider/privacy terms, location copy, or Availability redesign. Add single-leg, two-leg, one-conflicting-leg atomic rollback, duplicate, stale, concurrent, ordering, route-map/text-fallback, provider/script failure, explicit permission, granted/denied/unsupported/inaccurate/interrupted location, watch cleanup, zero-coordinate-egress, permission, and UI regression tests. Run the Phase 05 concurrency matrix on MariaDB/MySQL, Phase 07 regressions, Ride Playwright/axe, privacy/network/storage inspection, responsive/keyboard/reduced-motion checks, full gates, then Ponytail/code review again. Finish with ship/fix-required verdict, transaction and location-boundary evidence, unresolved plan defects/owners, and rollback impact.
```

## Phase 10 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 10 on branch phase/10-rent. Do not commit, push, merge, or decide Rent eligibility, legal, insurer, deposit, handover, rate, or document policy without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 10, PRODUCT.md, approved Phase 07 pattern, applicable architecture/data/security docs, docs/ux/07-client-input-checklist.md Rent eligibility/terms/pricing/payment/legal/support sections, docs/ux/08-acceptance-and-signoff.md, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace Rent lifecycle, availability, pricing, and authorization callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the approved cross-module Rent design. Run Modern Web Guidance and Impeccable for UI. Ask Matt remains user-invoked only.

Review implementation and plan for Rent treated as chauffeured; unapproved eligibility/legal claims; identity-document upload; missing original-document inspection outcome path; exact-vehicle/24-hour/buffer errors; deposit/insurance/mileage/fuel assumptions; stale hold/estimate; pending status/contact/consent drift; incomplete immutable snapshots; unauthorized exception; and later handover/incident needs made impossible by schema/lifecycle. Report Must fix, Should fix, and Nice to have with concrete failure, location, consequence, and minimal correction.

Fix confirmed in-scope defects only. Stop for any client/legal/insurer/finance policy or lifecycle decision. Add eligibility boundary, no-document-upload, exact-vehicle, duration, stale/duplicate/concurrent, permission, snapshot, migration, and UI tests. Run shared Phase 07 regressions, Rent Playwright/axe, responsive/keyboard/error checks, security upload denial, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, policy-source mapping, plan defects/owners, and rollback impact.
```

## Phase 11 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 11 on branch phase/11-outbox-notifications. Do not commit, push, merge, send a live message, or alter provider accounts/templates without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 11, PRODUCT.md, Messaging/outbox/degradation sections in architecture/data/security docs, docs/ux/07-client-input-checklist.md notifications/support/provider sections, docs/ux/08-acceptance-and-signoff.md, provider contracts, resolved EGM design, PR/diff, migrations, jobs, and tests. Query Graft first and trace transaction/outbox/job callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the outbox design. Use Modern Web Guidance/Impeccable only for affected status/admin UI. Ask Matt remains user-invoked only.

Review implementation and plan for outbox writes outside business transactions; message failure rolling back business state; duplicate send; unsafe retry after provider-accepted/client-timeout ambiguity; missing delivery_unknown/reconciliation state; weak idempotency/status lookup; wrong template/version/contact/consent use; PII/secret logs; unbounded retry; queue starvation; manual replay without authorization/audit; and provider-specific logic leaking into domain code. Classify Must fix, Should fix, and Nice to have with timeline/failure scenario, exact location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for channel/template/support/provider/cost/privacy decisions. Add regression tests for transaction rollback, commit-then-send, duplicate worker, timeout ambiguity, delivery_unknown, safe reconciliation, retry exhaustion, provider outage, redaction, authorization, short-worker, and migration behavior. Use fakes/sandboxes only. Run targeted queue/contract tests, scheduler/worker rehearsal, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, delivery-state evidence, plan defects/owners, and recovery/rollback impact.
```

## Phase 12 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 12 on branch phase/12-proposals-guest-control. Do not commit, push, merge, contact a guest, or decide proposal/change/cancellation policy without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 12, PRODUCT.md, proposal/private-link/lifecycle sections in architecture/data/security docs, docs/ux/07-client-input-checklist.md change/cancellation/proposal/support sections, docs/ux/08-acceptance-and-signoff.md, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace proposal, booking, token, and availability callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the cross-module design. Run Modern Web Guidance and Impeccable for guest UI. Ask Matt remains user-invoked only.

Review implementation and plan for accepted booking overwritten by pending revision; mutable proposal versions; token entropy/hash/scope/expiry/revocation flaws; raw token storage/logging; link enumeration/referrer/index leakage; unauthorized guest actions; acceptance races; stale price/availability; proposal expiry failing to release correct blocks; duplicate acceptance; incorrect cancellation/change state; and inaccessible/ambiguous guest status. Report Must fix, Should fix, and Nice to have with concrete race/attack scenario, exact location, consequence, and minimal correction.

Fix confirmed in-scope defects only. Stop for proposal expiry, change/cancel authority, repricing, refund, or communication decisions. Add regression tests for version immutability, accepted-authority preservation, token attacks, expiry/revocation, race/idempotency, stale availability/price, permission, noindex/referrer, migration, and browser behavior. Run targeted lifecycle/concurrency/security tests, guest Playwright/axe and viewport/keyboard/failure checks, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, race/token evidence, plan defects/owners, and rollback impact.
```

## Phase 13 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 13 on branch phase/13-payments-documents. Do not commit, push, merge, make a real charge/refund, or alter merchant/provider settings without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 13, PRODUCT.md, Payments/ledger/webhook/reconciliation sections in architecture/data/security docs, docs/ux/07-client-input-checklist.md payment/refund/pricing/legal/document sections, docs/ux/08-acceptance-and-signoff.md, approved merchant/provider contracts, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace payment, proposal, booking, webhook, and outbox callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the Payments design. Run Modern Web Guidance/Impeccable for hosted-checkout return and financial-document UI. Ask Matt remains user-invoked only.

Review implementation and plan for browser return treated as payment proof; unsigned/replayed webhook; amount/currency/reference mismatch; non-idempotent settlement/refund; mutable ledger; double allocation; incorrect deposit/balance; proposal/payment expiry race; refund without authority; secrets/card data in storage/logs; provider-specific domain coupling; missing delivery/reconciliation state; duplicate document numbers; and inaccurate legal/financial documents. Classify Must fix, Should fix, and Nice to have with concrete event sequence, exact location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for payment methods, deposit/full/pay-later policy, refund authority, settlement currency, numbering/legal format, or provider decisions. Add regression tests for signature/replay/idempotency, out-of-order/duplicate webhooks, mismatch, timeout, return-pending, concurrent settlement/refund, immutable ledger, reconciliation, authorization, redaction, documents, migration, and rollback. Use sandbox/fakes only. Run provider contracts, security/concurrency tests, hosted-return browser checks, full gates, then Ponytail/code review again. Finish with ship/fix-required verdict, financial invariant evidence, plan defects/owners, and reconciliation/rollback impact.
```

## Phase 14 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 14 on branch phase/14-calendar-driver-operations. Do not commit, push, merge, contact a driver, or choose replacement/link/service-day/offline/escalation policy without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 14, PRODUCT.md, Operations/Availability/private-link sections in architecture/data/security docs, docs/ux/07-client-input-checklist.md driver/support/operations sections, docs/ux/08-acceptance-and-signoff.md, approved policy package, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace calendar, schedule, driver-token, and action callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the Operations design. Run Modern Web Guidance and Impeccable for calendar/driver/mobile UI. Ask Matt remains user-invoked only.

Review implementation and plan for calendar missing online/manual/maintenance/handover/offline blocks; timezone/day-boundary errors; unauthorized cross-record visibility; driver token scope/entropy/hash/expiry/revocation flaws; raw tokens/referrer/index leakage; replacement bypassing approval/availability; stale link action; duplicate/offline action; service-day escalation gaps; private traveler data overexposure; and inaccessible mobile states. Report Must fix, Should fix, and Nice to have with concrete failure/attack, exact location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for replacement, link expiry, offline, service-day contact/escalation, or driver-data decisions. Add regression tests for calendar completeness, timezone, authorization, token attack/revocation, replacement conflict, stale/duplicate/offline resumption, audit, noindex/referrer, migration, and browser behavior. Run targeted lifecycle/security tests, mobile/desktop/keyboard/slow/offline checks, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, visibility/token evidence, plan defects/owners, and rollback impact.
```

## Phase 15 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 15 on branch phase/15-rent-field-incidents-privacy. Do not commit, push, merge, use real sensitive data, or decide legal/insurance/finance/privacy policy without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 15, PRODUCT.md, Operations/Payments/Audit/privacy/retention sections in architecture/data/security docs, docs/ux/07-client-input-checklist.md Rent/payment/legal/privacy/support sections, docs/ux/08-acceptance-and-signoff.md, approved upload-pipeline and policy decisions, resolved EGM design, PR/diff, migrations, and tests. Query Graft first and trace file, handover, incident, export, retention, and payment callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against the sensitive-data design. Run Modern Web Guidance and Impeccable for field/upload/signature/mobile UI. Ask Matt remains user-invoked only.

Review implementation and plan for identity-document image acceptance; public/original file exposure; spoof/polyglot bypass; scanner/decode fail-open; unsafe headers/names; missing quarantine state/cleanup; authorization/audit gaps; release without checklist/exception; duplicate signature/evidence/charge; direct ledger edits; incident overexposure; unrestricted export; invented retention; legal-hold violation; non-idempotent deletion/anonymization; and interrupted mobile corruption. Classify Must fix, Should fix, and Nice to have with attack/failure scenario, exact location, consequence, and minimal correction.

Fix confirmed in-scope defects only. Stop for checklist/consent/damage/incident/charge/refund/export/retention/legal-hold decisions or unsupported hosting controls. Add regression tests for forbidden identity uploads, MIME/type/size, extension/content mismatch, malformed/polyglot content, scanner/decode failure, original/quarantine denial, derivative/header/authorization, cleanup/expiry, handover/exception, duplicate/interruption, incident/export, retention/legal-hold, payment trace, migration, and browser behavior. Use synthetic data. Run security/storage inspection, mobile/desktop/accessibility checks, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, upload/retention proof, plan defects/owners, and rollback impact.
```

## Phase 16 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 16 on branch phase/16-client-provider-activation. Do not commit, push, merge, send a live message, make a real charge, import unapproved private data, or alter external accounts without explicit project-lead authorization.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 16, PRODUCT.md, DESIGN.md, all planning docs, all of docs/ux/07-client-input-checklist.md, docs/ux/08-acceptance-and-signoff.md, prior configuration/import decisions, provider contracts, PR/diff, configuration packages/manifests, and tests. Query Graft first and trace import/provider/public-content callers. Invoke /ponytail-review branch and /code-review; invoke /egm-review against any cross-module activation design. Run Modern Web Guidance and Impeccable for changed public UI/content. Ask Matt remains user-invoked only.

Review implementation and plan for unapproved or conflicting facts; Phase 16 incorrectly becoming the first usable data source; real traveler/payment/incident data in staging; private driver PII without named controls; secret/PII export; non-versioned/non-idempotent/direct DB import; schema/count/checksum drift; partial import; missing rollback/audit; duplicate vehicles; wrong imagery rights/alt text; provider origin/signature/template/quota/privacy errors; unsafe degradation; and analytics without consent. Report Must fix, Should fix, and Nice to have with concrete failure, exact location, consequence, and minimal fix.

Fix confirmed in-scope defects only. Stop for client facts, legal/privacy, provider/account, cost, production credential, or data-class decisions. Add regression tests for repeat/partial/corrupt/schema/checksum/exclusion/rollback/import-audit behavior, data-inventory checks, provider contract/failure paths, approved pricing examples, and four synthetic end-to-end journeys. Run UI/accessibility checks where changed, backup/degradation tests, full gates, and rerun Ponytail/code review. Finish with ship/fix-required verdict, source/input matrix, data inventory, import proof, plan defects/owners, and production activation blockers.
```

## Phase 17 review-and-fix prompt

```text
Review and, where authorized, fix Spinellanka Phase 17 on branch phase/17-launch-hardening. Do not commit, push, merge, run an external penetration test, accept risk, alter accounts, or deploy without explicit project-lead/authorized-owner approval.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 17, PRODUCT.md, DESIGN.md, every planning doc, docs/ux/07-client-input-checklist.md, docs/ux/08-acceptance-and-signoff.md, approved numeric mobile performance/page-weight/asset/query budgets, approved observation policy, all runbooks, PR/diff, CI evidence, and tests. Query Graft first and trace every remediated shared symbol. Invoke /ponytail-review branch and /code-review; invoke /egm-review for multi-component remediation/plan drift. Run Modern Web Guidance and Impeccable audit/polish for browser/UI findings. Ask Matt remains user-invoked only.

Review implementation and plan for targets invented or loosened after measurement; incomplete journey/WCAG/security scope; unaccepted medium/high finding; false header/cookie/proxy/debug/directory proof; migration incompatibility; unreadable backup or unproven restore; rollback against incompatible schema; missing scheduler/queue/monitoring evidence; untrained/unnamed owners; vague observation duration/signals/thresholds/cadence/exit; feature-branch artifact nomination; and gates that can pass with skipped evidence. Classify Must fix, Should fix, and Nice to have with reproducible failure, exact location, consequence, and minimal fix.

Fix only proven launch blockers in scope. Stop for risk acceptance, budget changes, product/legal/privacy/security decisions, or external testing/account actions. Add regression proof and rerun the failed gate, all affected journeys, WCAG manual/automated checks, authorized security tests, approved numeric budgets, migration/restore/rollback, scheduler/queue/monitoring, staff drills, and full repository gates. Rerun Ponytail/code review. Finish with ship/fix-required verdict, gate matrix, plan defects/owners, accepted risks, and observation criteria. After authorized merge, verify protected-main CI—not the feature branch—builds/checksums and retains the candidate artifact.
```

## Phase 18 review-and-fix prompt

```text
Review Spinellanka Phase 18A on branch phase/18-go-live-preparation and, only where authorized, fix preparation defects before merge. Separately review the 18B production run record after execution. Do not commit, push, merge, build/promote an artifact, change DNS/TLS, deploy, migrate, import production configuration, provision accounts, switch providers, send a real message, make a real charge, or roll back without explicit authorization for that exact action.

Read CLAUDE.md, roadmap.md sections 1–11 and Phase 18, PRODUCT.md, deployment/security planning docs, Phase 01 host verdict, Phase 16 configuration evidence, Phase 17 gate/observation matrices, approved DNS/TLS and staff-account plans, 18A PR/diff, protected-main artifact manifest, and—when reviewing 18B—the redacted deployment/observation record. Query Graft before source investigation. Invoke /ponytail-review branch and /code-review on 18A. Invoke /egm-review if preparation diverges from resolved deployment architecture. Use Modern Web Guidance/Impeccable only for changed browser surfaces. Ask Matt remains user-invoked only.

Review plan and implementation for production action from 18A; artifact built from any feature/hotfix/local branch; missing protected-main commit/checksum; mutable artifact; unsafe FTP/File Manager/public endpoint; absent deploy lock; secret leakage; unverified backup; incompatible migration; config import without version/count/checksum/exclusions/audit/rollback; test/default/shared staff accounts; missing MFA/recovery/session revocation; incomplete DNS/TLS order/redirect/propagation/rollback; maintenance not cleared on success/failure/rollback; provider activation out of order; smoke gaps; or subjective observation exit. Report Must fix, Should fix, and Nice to have with exact failure scenario, location/record step, consequence, and minimal correction.

Fix only 18A preparation defects on its branch. Any post-merge code/automation defect requires a new reviewed PR and a newly built protected-main artifact; never patch 18B or the server. Stop for every production/secret/account/DNS/provider/risk decision. Add regression tests and rehearse contract, artifact, migration, configuration, account, maintenance-cleanup, DNS/TLS, smoke, lock, rollback, and observation checks. Run full gates, staging deployment/rollback, and rerun Ponytail/code review before 18A handoff.

For 18B, verify separate approvals, pinned artifact, commands/results, maintenance cleanup, configuration import, staff/MFA controls, DNS/TLS/provider state, smoke, thresholds, full observation duration, rollback readiness, and exit-approver acceptance. Finish with ship, fix required, blocked, or rollback verdict. Phase 18 is Complete only after no Must-fix finding remains, the objective observation window passes, and a lead-controlled protected-main status update records the result.
```

# Local Setup and Testing Guide for the Second Maintainer

## 12. Supported local environments

Use one of these environments:

- macOS with PHP 8.4 preferred, Composer 2, MySQL 8 or MariaDB 10.11, and NVM.
- Linux with PHP 8.4 preferred or PHP 8.3, Composer 2, MySQL 8 or MariaDB 10.11, and NVM.
- Windows through WSL2 Ubuntu. Keep the repository, PHP, Composer, Node, and database inside WSL for consistent permissions and paths. This remains the verified Windows baseline.
- Native Windows with XAMPP is conditional, not automatically supported. It may be used only when XAMPP's actual CLI PHP is 8.3+ with every required extension and its database is MySQL 8.0 or MariaDB 10.11+ with InnoDB/transactions. The exact binaries used by Composer, Artisan, Apache, and database clients must pass the same project checks. If any gate fails, use WSL2; never lower Composer/platform requirements, switch to SQLite, mix hidden PHP installations, or bypass `scripts/check-environment`.

Do not use Docker as a substitute unless the project lead approves and commits a shared Docker environment. Both maintainers should use the locked runtime and dependency graph rather than separate ad hoc environments.

## 13. Required software

Install and verify:

| Tool                | Required version/policy                                                                                                              |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Git                 | Current supported release                                                                                                            |
| PHP                 | 8.4 preferred; 8.3 minimum                                                                                                           |
| PHP extensions      | Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO MySQL, Session, Tokenizer, XML; Intl and Zip recommended |
| Composer            | 2.x                                                                                                                                  |
| MySQL/MariaDB       | MySQL 8.0 or MariaDB 10.11                                                                                                           |
| NVM                 | Current supported release                                                                                                            |
| Node                | 26.x from `.nvmrc`                                                                                                                   |
| npm                 | 11.x from `packageManager`/engines                                                                                                   |
| Bun                 | Optional 1.3.5 script runner; never lockfile owner                                                                                   |
| Claude Code         | 2.1.327 or newer                                                                                                                     |
| Graft               | 0.16.0                                                                                                                               |
| Impeccable          | 4.9.1 project installation                                                                                                           |
| Ponytail            | Official `DietrichGebert/ponytail` plugin, v5.3.3                                                                                    |
| EGM                 | 0.1.1 at committed marketplace ref                                                                                                   |
| Matt Pocock skills  | Verified plugin 1.3.1                                                                                                                |
| Modern Web Guidance | Repository-locked commit                                                                                                             |
| Caveman             | Shared source recorded in environment guide                                                                                          |

### macOS notes

Use Homebrew or another trusted package manager for PHP, Composer, and MySQL/MariaDB. Use NVM for Node rather than installing a second unmanaged Node version. Ensure the selected PHP CLI is the one returned by `which php`, then verify extensions:

```sh
php -v
php -m
composer --version
mysql --version
```

Start the local database using the service mechanism provided by the package manager. Do not run two MySQL/MariaDB servers on port 3306.

### Linux/WSL2 notes

Use the distribution's supported PHP repository. Install the CLI plus cURL, Mbstring, XML/DOM, MySQL/PDO, Intl, and Zip extensions matching the selected PHP minor version. Install Composer 2 from its official source or trusted distribution package. Enable/start MySQL or MariaDB, then verify:

```sh
php -v
php -m
composer --version
mysql --version
```

If the distribution offers only PHP 8.3, use it; CI tests both 8.3 and 8.4. Do not mix extension packages from a different PHP minor version.

## 14. Install the shared AI/tooling environment

Install NVM from its official project instructions, then install the repository Node version:

```sh
nvm install 26
nvm use 26
node --version
npm --version
```

Install only machine-level shared tools before cloning:

```sh
npm install --global @nanonets/graft@0.16.0
npx skills add juliusbrussee/caveman@caveman -g -y
npm install --global @caveman-ai/cli@2.1.0
```

Install/sign in to Claude Code through Anthropic's official instructions. Project-scoped installers must run only after cloning and confirming the repository root in section 15. Repository settings enable the pinned EGM marketplace source, Matt Pocock plugin, Modern Web Guidance skill, and Graft MCP/hooks. Do not replace committed `.claude/settings.json`, `.mcp.json`, `skills-lock.json`, or vendored skill files with personal copies.

Ask Matt remains manual: `/mattpocock-skills:ask-matt`. Do not invoke it automatically. If `scripts/check-environment` reports Matt, Caveman, Ponytail, or another tool drift, stop and align with the lead before working; user-level installers do not provide a complete committed content lock.

## 15. Clone and prepare the repository

Clone the canonical remote, enter it, and prove the current directory is the repository root before any project-scoped installer:

```sh
git clone https://github.com/HMAHD/Spinellanka.git
cd Spinellanka
git rev-parse --show-toplevel
git remote -v
git status --short --branch
nvm install
nvm use
```

The `git rev-parse --show-toplevel` output must be the directory just entered. Expected branch for first checkout is `main`, and the working tree must be clean. Do not start feature work directly on `main`.

Install/align Impeccable from the verified repository root:

```sh
npx -y impeccable@4.9.1 install --providers=claude --scope=project
git diff --exit-code
test -z "$(git status --porcelain)" || {
  git status --short
  printf '%s\n' 'STOP: project installer changed the clean checkout.' >&2
  exit 1
}
```

The installer must leave no unreviewed repository diff. If it changes committed files, stop, preserve the diff, and ask the lead whether the shared installation baseline needs a dedicated reviewed tooling change.

Inside Claude Code, install the official Ponytail plugin and verify the installed release is exactly v5.3.3:

```text
/plugin marketplace add DietrichGebert/ponytail
/plugin install ponytail@ponytail
```

Use `/plugin` to inspect the installed source/version. Stop if the source is not `DietrichGebert/ponytail` or the release differs from v5.3.3. Phase 01 must add the shared environment check that verifies this pin for both maintainers; do not silently accept a later release.

Run environment validation before installing project dependencies:

```sh
./scripts/check-environment
```

Resolve every failure rather than bypassing the script. Typical failures mean the wrong PHP/Node/npm/Claude/Graft version or a missing shared skill/plugin.

## 16. Create the local database

Use a local-only database and a dedicated local user. The following SQL is entered in an interactive MySQL/MariaDB administrative session; choose a unique local password and save it in the local password manager, not this repository:

```sql
CREATE DATABASE spinellanka
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'spinellanka_local'@'127.0.0.1'
  IDENTIFIED BY 'use-a-unique-local-password-from-your-password-manager';

GRANT ALL PRIVILEGES ON spinellanka.*
  TO 'spinellanka_local'@'127.0.0.1';

FLUSH PRIVILEGES;
```

If `spinellanka` or `spinellanka_local` already exists, inspect it before changing or deleting anything. Never point a local `.env` at production or another maintainer's shared database.

## 17. Prepare the application

Install dependencies and create a new local environment only when `.env` does not already exist:

```sh
if [ -e .env ]; then
  printf '%s\n' 'STOP: .env already exists. Inspect it; never overwrite it or regenerate APP_KEY.' >&2
  exit 1
fi

composer install --no-interaction --prefer-dist
cp .env.example .env

if grep -Eq '^APP_KEY=[[:space:]]*$' .env; then
  php artisan key:generate
else
  printf '%s\n' 'STOP: new .env does not have an empty APP_KEY. Inspect before continuing.' >&2
  exit 1
fi

npm ci --ignore-scripts
```

If `.env` already exists, do not run the block. Inspect its non-secret environment/database targets and preserve its existing `APP_KEY`; run only dependency installation commands that are needed. Never overwrite `.env` or generate a replacement key as a convenience.

Edit only local `.env` values for the database:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spinellanka
DB_USERNAME=spinellanka_local
DB_PASSWORD=use-the-password-created-in-the-local-database-step
```

Keep database-backed queue, cache, and session drivers from `.env.example` unless the current repository explicitly changes them. Never copy another maintainer's `.env` or `APP_KEY`. Never commit `.env`.

Run migrations and build assets:

```sh
php artisan config:clear
php artisan migrate
npm run build
```

For a first checkout only, `./scripts/bootstrap` performs environment checks, dependency installation, conditional `.env` copy, unconditional key generation, forced migration, `npm ci --ignore-scripts`, and build. Run it only after `test ! -e .env` succeeds. Never invoke it when `.env` exists: its key-generation step can replace `APP_KEY` and invalidate encrypted values or sessions. It must never run against staging or production. The manual steps above are safer when setup state is uncertain.

## 18. Start and use the local application

Start Laravel, the database queue listener, and Vite together:

```sh
composer dev
```

Expected local URL:

```text
http://127.0.0.1:8000
```

For scheduled-work testing, open another terminal in the repository and run:

```sh
php artisan schedule:work
```

Stop it when the test ends. Production does not use `schedule:work`; cPanel cron runs Laravel Scheduler every minute and starts a short-lived queue drain as defined in the deployment plan.

Initial smoke checks:

```sh
php artisan about
php artisan migrate:status
curl --fail --silent --show-error http://127.0.0.1:8000/up
curl --fail --silent --show-error http://127.0.0.1:8000/
curl --fail --silent --show-error http://127.0.0.1:8000/services/ride
```

Also open the application in a real browser. Verify home, each service route, mobile navigation, keyboard focus, reduced motion, and no console errors.

## 19. Testing during development

Run the smallest relevant test while editing, then the full gate before handoff.

Common commands:

```sh
php artisan test
php artisan test --filter=ServiceTypeTest
composer lint
composer analyse
composer verify
npm run format:check
npm run build
```

When a phase adds Playwright/axe, use the exact committed npm scripts introduced by that merged phase. Do not invent a local-only command or use globally installed browser packages.

Before every PR:

```sh
./scripts/check-environment
composer validate --strict
composer verify
npm run format:check
npm run build
composer audit --locked
npm audit --omit=dev
```

For schema changes, additionally prove:

1. migration from a clean local test database;
2. upgrade from the previous merged schema with representative synthetic data;
3. the previous release remains compatible during the rollback window;
4. no destructive cleanup is bundled into the same release as its replacement.

For UI changes, additionally run required Modern Web Guidance and Impeccable workflows, start the application, inspect 320px, 390px, 768px tablet, and 1440px, use keyboard-only navigation, test 200% zoom/reflow, reduced motion, validation/error states, slow/failed network where relevant, and review browser console errors.

## 20. Safe local database reset

`migrate:fresh` destroys every table in the effective database connection. Never trust `.env` text alone because cached configuration, shell environment, a forwarded port, or a different connection can override it.

Run this preflight from the repository root:

```sh
php artisan config:clear
php artisan env
php artisan tinker --execute='$name = config("database.default"); dump(["environment" => app()->environment(), "driver" => $name, "host" => config("database.connections.$name.host"), "port" => config("database.connections.$name.port"), "database" => config("database.connections.$name.database")]);'

if command -v lsof >/dev/null 2>&1; then
  lsof -nP -iTCP:3306 -sTCP:LISTEN
elif command -v ss >/dev/null 2>&1; then
  ss -ltnp '( sport = :3306 )'
else
  printf '%s\n' 'STOP: cannot inspect the local port owner.' >&2
  exit 1
fi
```

Continue only when all facts are visible and proven:

1. Laravel's effective environment is `local`.
2. The effective driver is `mysql`.
3. The effective host is `127.0.0.1`, port is `3306`, and database is the disposable local `spinellanka` database.
4. The port owner is the intended local MySQL/MariaDB process, not `ssh`, a tunnel, proxy, container mapping, or forward to another machine.
5. No shell-level environment override changes the printed values.

If any value is missing, surprising, forwarded, or cannot be proven local, stop. Do not run a destructive command.

Type the exact disposable database name before reset:

```sh
printf '%s' 'Type the disposable database name to confirm reset: '
IFS= read -r confirmed_database
if [ "$confirmed_database" != 'spinellanka' ]; then
  printf '%s\n' 'STOP: database name did not match.' >&2
  exit 1
fi
php artisan migrate:fresh
```

Never use `migrate:fresh`, `db:wipe`, destructive SQL, or a forced reset against staging or production. Production migration rollback follows release compatibility and restore procedures, not local reset commands.

## 21. Daily Git workflow for the guided maintainer

1. Finish or preserve current work without discarding it. If the tree is not clean, stop and ask the lead how to preserve it; do not stash, reset, clean, or overwrite another maintainer's work without approval.
2. Confirm the selected phase is `Ready` in section 10 and copy its exact branch name.
3. Run the safe preflight. Set `PHASE_BRANCH` to that exact section-10 value before running the final command:

    ```sh
    git status --short --branch
    test -z "$(git status --porcelain)" || {
      printf '%s\n' 'STOP: working tree is not clean.' >&2
      exit 1
    }

    git fetch --prune origin
    git switch main
    git pull --ff-only origin main

    test -z "$(git status --porcelain)" || {
      printf '%s\n' 'STOP: main is not clean after update.' >&2
      exit 1
    }

    printf '%s' 'Paste the exact Ready phase branch from section 10: '
    IFS= read -r PHASE_BRANCH
    case "$PHASE_BRANCH" in
      phase/01-hosting-ci-release|phase/02-identity-access-audit|phase/03-catalog-fleet-admin|phase/04-public-content-fleet|phase/05-availability-schedule|phase/06-pricing-policy-estimates|phase/07-airport-pickup-core|phase/08-airport-transfer|phase/09-ride|phase/10-rent|phase/11-outbox-notifications|phase/12-proposals-guest-control|phase/13-payments-documents|phase/14-calendar-driver-operations|phase/15-rent-field-incidents-privacy|phase/16-client-provider-activation|phase/17-launch-hardening|phase/18-go-live-preparation)
        ;;
      *)
        printf '%s\n' 'STOP: branch name is not an exact roadmap phase branch.' >&2
        exit 1
        ;;
    esac
    git switch -c "$PHASE_BRANCH"
    ```

4. Verify the new branch name and base commit, then copy that phase's standalone implementation prompt into a new AI session.
5. Keep changes inside the phase. Review `git diff` and ensure no `.env`, secret, database dump, screenshot with private data, build cache, or unrelated file is included.
6. Run targeted tests, Ponytail phase review, applicable runtime/UI/security checks, and the full gate.
7. Obtain explicit lead authorization for each outward Git action separately:
    - authorization to create commits, when the phase handoff requires committed work;
    - authorization to push the branch;
    - authorization to open the PR.
      Permission for one action does not authorize either later action.
8. Open the authorized PR to `main`; never merge it unless the lead explicitly assigns that exact action.
9. Address review through the phase-specific review-and-fix prompt and documented review-fix loop.
10. After the lead merges, delete the phase branch only when authorized and update local `main` with the same clean-worktree preflight.

Do not force-push after review without explicit lead authorization. Do not resolve merge conflicts by choosing an entire side blindly; preserve both merged product behavior and phase intent, then rerun all affected tests.

## 22. Local troubleshooting

### Wrong Node/npm version

```sh
nvm install
nvm use
node --version
npm --version
./scripts/check-environment
```

### PHP extension missing

Read the exact missing extension from Composer or `php -m`. Install the matching extension package for the active PHP minor version, restart the PHP service if one is used, then rerun `composer install` and the environment check.

### MySQL connection refused

Verify the database service is running, port 3306 is listening locally, the local `.env` uses `127.0.0.1`, and the user/database/grant exist. Do not switch to SQLite merely to hide a MySQL-specific failure in concurrency or migration work.

### Application key or cached configuration error

```sh
php artisan config:clear
php artisan cache:clear
```

Generate a key only for a new local `.env`. Replacing an existing `APP_KEY` can invalidate encrypted values and sessions.

### Writable directory error

Ensure the current OS user can write `storage/` and `bootstrap/cache/`. Do not make the project world-writable and do not copy production ownership commands into local setup.

### Queue work appears stuck

Confirm `composer dev` still runs the queue listener, inspect failed jobs through Laravel commands/UI available in the current phase, and run the smallest synthetic job. Do not delete failed production jobs from a local instruction.

### Frontend build or stale asset problem

```sh
npm ci --ignore-scripts
npm run build
```

With `composer dev` active, verify Vite is running and hard-refresh the browser. Do not edit compiled output under `public/build` directly.

### Agent skill/version drift

```sh
./scripts/check-environment
```

Stop and align with `docs/development/environment.md`. Do not upgrade one maintainer's plugin or lockfile during a phase unless dependency/tooling upgrade is the approved scope.

### Standalone AI prompt — guided maintainer setup with XAMPP

Copy this entire prompt into a fresh Claude Code session on the guided maintainer's Windows computer. The prompt is capability-gated: it uses XAMPP only when its real PHP and database satisfy the project. It never weakens the project to fit an older XAMPP bundle.

```text
Set up the Spinellanka repository for a guided maintainer on Windows. The maintainer currently uses XAMPP. Act as a patient setup operator: explain one short step at a time, run every non-administrative check you can, and ask the maintainer only for actions that require Windows Administrator approval, passwords, browser sign-in, or a GUI. Never ask them to paste a password, token, .env file, APP_KEY, database dump, or private path into chat. Redact username-specific paths in the final report.

This is environment setup only. Do not edit application behavior, product/design/planning documents, dependency constraints, lockfiles, migrations, or shared tool versions. Do not commit, push, create a phase branch/PR, reset/clean/stash work, or change a remote. Never lower PHP/database requirements, change Composer platform settings, switch tests to SQLite, skip scripts/check-environment, expose the Laravel repository root through Apache, or use a different skill/plugin version to make a check pass.

Read the repository CLAUDE.md, roadmap.md sections 1–11 and 12–22, docs/development/environment.md, composer.json, package.json, .nvmrc, .env.example, scripts/check-environment, scripts/bootstrap, .claude/settings.json, .mcp.json, and skills-lock.json. These files override generic XAMPP tutorials. Use direct file tools for these configuration/docs files. Query Graft before any application-source read; setup should not require application-source edits. Ask Matt remains user-invoked only and must never be called automatically.

1. Protect existing work and discover the machine

Before installing or changing anything, ask where XAMPP is installed and whether this is a fresh clone. Run read-only PowerShell checks and show a redacted summary:

$PSVersionTable.PSVersion
Get-Command git, php, composer, node, npm, claude, graft -ErrorAction SilentlyContinue | Select-Object Name, Source
where.exe php
where.exe mysql
where.exe node
where.exe npm
where.exe composer

Do not assume C:\xampp. Set $XamppRoot to the path the maintainer confirms, then run:

& "$XamppRoot\php\php.exe" -v
& "$XamppRoot\php\php.exe" --ini
& "$XamppRoot\php\php.exe" -m
& "$XamppRoot\mysql\bin\mysql.exe" --version

Inspect the actual output. XAMPP passes only if:
- CLI PHP is 8.4 preferred or at least 8.3 and uses PHP 8.3-compatible syntax;
- Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO MySQL, Session, Tokenizer, and XML are loaded; Intl and Zip are strongly recommended;
- the database is MySQL 8.0 or MariaDB 10.11 or newer, uses InnoDB, and supports transactions;
- Composer is 2.x;
- Node is 26.x and npm is 11.x;
- Git and Claude Code 2.1.327 or newer are available.

If XAMPP's PHP or database fails, stop. Do not patch composer.json, use XAMPP's older database, or hide the mismatch. Recommend the verified WSL2 setup from roadmap section 12. XAMPP may remain installed for unrelated projects, but it does not become Spinellanka's runtime. If only Apache is needed from XAMPP, a separately supported PHP/database arrangement requires lead approval; do not mix binaries silently.

2. Align Windows runtimes

Use nvm-windows for Node 26 if needed. Never install a second unmanaged Node over an active nvm-windows installation. After the maintainer approves any installer/Administrator prompt, verify in a new PowerShell window:

nvm install 26
nvm use 26
node --version
npm --version

Install Composer 2 against the same XAMPP php.exe only after XAMPP passes. Confirm `where.exe php` and Composer diagnostics point to that same PHP installation. If Apache will be used later, its PHP module must use the same PHP major/minor as CLI; otherwise stop.

Install/sign in to Claude Code through official Anthropic instructions. Never ask for or copy its credential. Install the pinned machine-level tools under Node 26 only after approval:

npm install --global @nanonets/graft@0.16.0
npx skills add juliusbrussee/caveman@caveman -g -y
npm install --global @caveman-ai/cli@2.1.0

Verify `graft --version` is exactly 0.16.0. Caveman is available only when the user invokes it; repository prose remains normal English.

3. Clone safely and load project tools

Clone the canonical repository into a normal user-owned development folder, not C:\xampp\htdocs, OneDrive, a synchronized folder, or an Administrator-owned directory. Enter the clone and prove the root/clean state:

git clone https://github.com/HMAHD/Spinellanka.git
cd Spinellanka
git rev-parse --show-toplevel
git remote -v
git status --short --branch

Expected branch is main and the fresh tree is clean. Stop if the remote is unexpected or any file is changed/untracked. Do not delete it.

Use nvm-windows to select Node 26, then install/align Impeccable from the verified repository root:

npx -y impeccable@4.9.1 install --providers=claude --scope=project
git diff --exit-code
git status --short

Stop if the project installer changes the clean checkout. Preserve and report the diff; do not accept it automatically.

Inside Claude Code, add the official Ponytail marketplace/plugin and verify exactly v5.3.3:

/plugin marketplace add DietrichGebert/ponytail
/plugin install ponytail@ponytail

Use /plugin to verify the source is DietrichGebert/ponytail and version is v5.3.3. Open the repository in Claude Code so committed settings load pinned EGM 0.1.1, Matt Pocock skills 1.3.1, Modern Web Guidance at the locked commit, Graft MCP/hooks, and project instructions. Do not replace committed settings or skill files. Verify:
- Graft is 0.16.0 and responds before source work;
- Impeccable is 4.9.1;
- Ponytail is official v5.3.3;
- EGM is 0.1.1 at the committed marketplace ref;
- Matt Pocock skills are 1.3.1, with Ask Matt manual only;
- Modern Web Guidance matches skills-lock.json;
- Caveman and its CLI match the shared environment guide.

Use Git Bash for repository Bash scripts. In that Git Bash session, put the confirmed XAMPP PHP/MySQL directories before other PHP/MySQL entries for this session only; adapt the drive/path from $XamppRoot rather than assuming /c/xampp:

export PATH="/<drive>/<xampp-path>/php:/<drive>/<xampp-path>/mysql/bin:$PATH"
hash -r
which php
php -v
php -m
mysql --version
node --version
npm --version
composer --version
claude --version
graft --version
./scripts/check-environment

The angle-bracket path is an instruction to translate the confirmed Windows path into Git Bash syntax. Do not paste it literally. Stop on every failure. Do not edit the check script to pass the machine.

4. Create a disposable local database

Start only XAMPP's database service after it has passed the version gate. Keep Apache stopped for the preferred baseline run. Confirm port 3306 belongs to the intended local MySQL/MariaDB process and is not an SSH tunnel, proxy, Docker forward, or another server. Use XAMPP Shell, PowerShell with the full mysql.exe path, or phpMyAdmin only after verifying it targets 127.0.0.1:3306.

Create a local-only database/user with a password entered privately by the maintainer. Never put the real password in chat or Git:

CREATE DATABASE spinellanka
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER 'spinellanka_local'@'127.0.0.1'
  IDENTIFIED BY '<enter-private-local-password>';
GRANT ALL PRIVILEGES ON spinellanka.*
  TO 'spinellanka_local'@'127.0.0.1';
FLUSH PRIVILEGES;

The password token above is not a value to paste. Ask the maintainer to type their private local password directly into the SQL client. If the database or user exists, inspect it; never drop or overwrite it automatically. Verify InnoDB and a transaction with a disposable test table, then remove only that test table.

5. Install the application without overwriting secrets

In PowerShell from the clean repository root, stop immediately if .env exists. Inspect it with the maintainer; never overwrite it or regenerate APP_KEY. For a fresh checkout only:

composer install --no-interaction --prefer-dist
Copy-Item .env.example .env

Confirm APP_KEY is empty before running:

php artisan key:generate
npm ci --ignore-scripts

Set only these local values, with the password entered locally and never printed in the report:

APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spinellanka
DB_USERNAME=spinellanka_local
DB_PASSWORD=<private-local-password>

Keep the repository's database-backed queue, cache, and session settings. Run:

php artisan config:clear
php artisan migrate
npm run build
php artisan about
php artisan migrate:status

Do not use scripts/bootstrap when .env exists. Do not run migrate:fresh, db:wipe, or destructive SQL during setup.

6. Run the project in the supported XAMPP-compatible mode

Preferred mode: use XAMPP only for the accepted local database. Keep XAMPP Apache stopped and run the Laravel application, queue listener, and Vite together from the repository:

composer dev

Open http://127.0.0.1:8000. This avoids exposing the repository through htdocs and matches the project's normal development command.

If the maintainer explicitly requires XAMPP Apache, first make the preferred mode pass. Then ask for lead approval before changing Windows hosts or Apache configuration. Configure a named local virtual host whose DocumentRoot is exactly <repository>\public, never the repository root. Enable rewrite, deny directory listing, and keep .env, storage, vendor metadata, Git, and source files outside web access. Verify Apache's PHP major/minor equals the CLI. Continue running the queue listener and Vite in separate terminals. Never use http://localhost/Spinellanka or copy the repository into htdocs as a shortcut.

7. Verify as a user and as a maintainer

With composer dev running, execute:

curl.exe --fail --silent --show-error http://127.0.0.1:8000/up
curl.exe --fail --silent --show-error http://127.0.0.1:8000/
curl.exe --fail --silent --show-error http://127.0.0.1:8000/services/ride

Open the app in a real browser. Verify home and all four service routes, mobile navigation, keyboard focus, reduced motion, and no console errors. Use fictional local data only.

Run the full local gate in Git Bash under Node 26, with the previously verified XAMPP PHP/MySQL paths active:

./scripts/check-environment
composer validate --strict
composer verify
npm run format:check
npm run build
composer audit --locked
npm audit --omit=dev

Report each pass/failure exactly. A setup is Complete only when every environment gate, migration, build, smoke route, browser check, and full verification command passes. If XAMPP fails a capability, the status is Blocked—XAMPP and the next step is WSL2; never call it complete with a workaround.

8. Final handoff

Return a concise report containing redacted binary paths, versions, XAMPP capability verdict, database engine/version/port without credentials, selected run mode, skill/plugin versions, environment-check result, migration/build/test results, URLs checked, browser result, and any blocker. Confirm git status is still clean and that no .env, secret, generated database, or personal path is staged. Do not commit or push. End with the exact commands the maintainer should run each day: start the accepted XAMPP database service, enter the repository, select Node 26, run composer dev, and stop all processes when finished.
```

---

# CI/CD Progression and External Accounts

## 23. CI/CD progression by phase

| Milestone        | Required pipeline capability                                                                                                                                                                                 |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Current baseline | PHP 8.3/8.4 Composer validation, Pint, Larastan, Pest, Composer audit; Node 26 npm lock install, Prettier check, build, production npm audit                                                                 |
| Phase 01         | Playwright/axe public smoke foundation; immutable production artifact; exclusions; checksum; release manifest; staging activation/rollback rehearsal; protected manual production workflow shell             |
| Phase 02–03      | Authorization, MFA, audit and migration tests added to PHP gates                                                                                                                                             |
| Phase 04         | Public responsive/accessibility browser suite required for affected PRs                                                                                                                                      |
| Phase 05         | MySQL/MariaDB concurrency job for blocking schedule writes                                                                                                                                                   |
| Phase 07         | Critical Airport Pickup Playwright/axe journey and provider-failure/custom-quote states                                                                                                                      |
| Phases 08–10     | One critical browser journey per service and all service-specific domain tests                                                                                                                               |
| Phase 11         | Queue/outbox/idempotency/failure contract tests and short-worker rehearsal                                                                                                                                   |
| Phase 12         | Proposal/private-link lifecycle and race browser tests                                                                                                                                                       |
| Phase 13         | Payment sandbox contract, webhook signature/replay/idempotency and reconciliation tests; no secrets on PRs                                                                                                   |
| Phases 14–15     | Driver, calendar, field, upload/export/privacy authorization and mobile journey gates                                                                                                                        |
| Phase 16         | Versioned/idempotent configuration import/rollback, data-class inventory, all provider sandbox contracts, and four full synthetic staging journeys                                                           |
| Phase 17         | Previously approved numeric security/WCAG/performance/asset/query gates, migration, backup/restore, rollback, observation matrix, then retained protected-main candidate artifact                            |
| Phase 18         | 18A reviewed preparation merged first; retained protected-main production artifact only; locked 18B deployment, configuration import, account/MFA, DNS/TLS, maintenance cleanup, smoke, monitoring, rollback |

Protect `main` from direct push. Require PR review and all applicable checks. Keep deployment secrets in the GitHub `production` environment, not repository variables available to PR jobs. Use environment reviewers where the GitHub repository plan supports them; otherwise require protected `main`, manual `workflow_dispatch`, and the lead's recorded approval.

Production deployment never runs from a pull request, fork, arbitrary branch, or unreviewed commit. Production never installs Node dependencies or compiles assets.

## 24. External accounts and secret boundary

| Capability                                       | Account/decision owner                    | Required before                  | Secret purpose                                                                             |
| ------------------------------------------------ | ----------------------------------------- | -------------------------------- | ------------------------------------------------------------------------------------------ |
| Google Maps Places/Routes or approved equivalent | Technical owner with client cost approval | Phase 07 live route integration  | Restricted place/route API credentials and quotas                                          |
| Meta WhatsApp Cloud API                          | Client business owner                     | Phase 11 live WhatsApp adapter   | Business account/number/API credentials and approved templates                             |
| Postmark or approved transactional email         | Client business/technical owner           | Phase 11 live email adapter      | Authenticated sender/domain and server credential                                          |
| Hosted Sri Lankan payment gateway                | Client finance/legal owner                | Phase 13 implementation          | Merchant sandbox/production credentials and webhook verification secret/certificate        |
| Sentry or approved error reporting               | Technical/privacy owner                   | First production-like deployment | DSN/auth with PII scrubbing and approved retention                                         |
| Encrypted off-host backup storage                | Hosting/technical owner                   | Before any production data       | Restricted backup access with retention and restore ownership                              |
| Analytics, if approved                           | Product/privacy owner                     | Phase 16                         | Consent-aware public-only measurement; omission is allowed                                 |
| cPanel deployment                                | Hosting/technical owner                   | Phase 01 rehearsal and Phase 18  | Restricted SSH/SFTP/cPanel deployment credential, host verification and approved base path |
| GitHub production environment                    | Repository owner                          | Phase 18                         | Environment-scoped deployment/provider configuration unavailable to PR jobs                |

Never paste a secret into an AI prompt, issue, PR, test fixture, screenshot, shell history example, or roadmap. Record secret names, owner, rotation date, and storage location—not values.

---

# Post-Launch Maintenance Lane

## 25. Branches and classification

After launch, every request enters one of these classes before code changes:

- **Confirmed bug:** current behavior violates an approved requirement or produces an error.
- **Client-requested product change:** approved behavior or content must change.
- **Verified content/data update:** facts, images, support details, rates, policy versions, or legal text change without redesigning the system.
- **Design change:** hierarchy, component, token, responsive behavior, interaction, or public direction changes.
- **Operational/configuration change:** scheduler, queue, backup, monitoring, hosting, permissions, or provider configuration changes.
- **Provider change:** external API/account/vendor behavior changes.
- **Technical refactor:** behavior should remain unchanged while internal structure changes.
- **Security/production incident:** active confidentiality, integrity, availability, payment, or data risk requires incident control.

Use one short-lived branch:

- `fix/<github-issue-number>-<short-name>` for confirmed bugs;
- `change/<github-issue-number>-<short-name>` for approved changes;
- `hotfix/<github-issue-number>-<short-name>` only for an active production incident authorized by the lead.

A client message alone is not implementation approval when it changes product, legal, financial, privacy, security, architecture, or design-system decisions. Record it in `docs/ux/08-acceptance-and-signoff.md`, identify the authorized owner, and obtain the decision before editing dependent behavior.

## 26. Maintenance decision flow

```text
Request received
  |
  +-- Security/production incident? -- yes --> incident owner + containment + hotfix protocol
  |
  +-- Bug claim? -- yes --> reproduce against approved behavior
  |                         |
  |                         +-- cannot reproduce --> collect safe evidence; no speculative patch
  |                         +-- reproduced --> root cause + regression test + narrow fix
  |
  +-- Requested change? --> authority and impact map
                            |
                            +-- missing owner/fact/approval --> record and stop
                            +-- architecture/design/security change --> required EGM/Impeccable review
                            +-- approved narrow change --> branch + tests + PR
```

For every accepted change, map impact across:

- product requirements and client acceptance;
- module/domain ownership and lifecycle;
- schema/data migration and rollback;
- public/staff/guest/driver UI and accessibility;
- authorization, privacy, retention, tokens, uploads, and audit;
- provider contracts, retries, idempotency, and secrets;
- queue, scheduler, cPanel, backup, monitoring, and deployment;
- automated tests, browser journeys, design records, and operations docs.

## 27. Reusable change-or-bug AI prompt

Copy this complete prompt into a fresh AI session together with the client's request or bug report:

```text
You are handling one post-launch Spinellanka request. Work in the existing Laravel 13 modular monolith. Do not edit code until you classify the request, locate its authority, and—if it is a bug—reproduce it and state one evidence-backed root cause. Do not commit, push, merge, deploy, change an external account, migrate production, or contact a customer/provider unless the project lead explicitly authorizes that exact action.

REQUEST OR BUG REPORT:
Paste the exact request/report and non-secret evidence after this line.

Start by reading CLAUDE.md, roadmap.md sections 1–11 and 25–29, PRODUCT.md, applicable docs/planning files, docs/ux/07-client-input-checklist.md, docs/ux/08-acceptance-and-signoff.md, DESIGN.md for UI work, the exact deployed-commit/release record, and current tests. Client-approved sign-off changes win conflicts. Query Graft before reading or searching application source.

Classify the work as confirmed bug, client product change, verified content/data update, design change, operational/configuration change, provider change, behavior-preserving refactor, or security/production incident. State why. For a bug, define a deterministic reproduction and expected behavior from an authoritative source. If it does not reproduce, add a safe read-only probe or request exact evidence; do not patch a guess. State: “I believe the root cause is [specific file/function/condition] because [evidence].” For an incident, stop normal work and follow the approved incident owner/containment process.

Create an impact map covering product truth; Booking/Catalog/Availability/Pricing/Payments/Messaging/Operations/Identity/Audit ownership; database and existing data; public/guest/staff/driver UI; accessibility; security/privacy/retention; provider contracts and secrets; queue/scheduler/cPanel/deployment; tests and docs. Trace callers/dependents with Graft before changing a shared symbol or interface.

Stop and ask the authorized owner when the request conflicts with approved requirements, lacks a verified fact, changes service/lifecycle/availability/pricing/payment/refund/security/privacy/retention/legal behavior, needs a destructive migration, changes provider commitments, or changes the approved design system. Record a client product change as the next unique CHG entry in docs/ux/08 with request, reason, affected requirements, owner, decision and revision. Record client-owned values in docs/ux/07 with owner/source/approval/date/version/effective date. Run full EGM and store a resolved docs/designs artifact for a multi-component architecture change. Update planning docs after approval. Run Modern Web Guidance before browser/HTML/CSS/client-JS/forms/accessibility/performance work. Run Impeccable for public UI/design-system work and update DESIGN.md plus .impeccable records if the shipped design changes. Ask Matt is user-invoked only.

Choose the smallest independently mergeable fix/change. Preserve exact-vehicle selection, Asia/Colombo schedule authority, 15-minute hold, pending-until-staff-confirmation, no customer account, scoped private links, integer money, hosted payment proof, one authoritative schedule, outbox durability, provider-neutral contracts, and verified-content-only rules. Keep controllers, Livewire components, Filament pages/actions, jobs and webhooks thin; put business rules in domain/application services. Use backward-compatible expand/contract migrations. Do not broaden scope with cleanup or unrelated findings.

Create fix/<issue-number>-<short-name> for a confirmed non-incident bug or change/<issue-number>-<short-name> for an approved change, based on clean current protected main. For an authorized production incident, identify the exact deployed commit from the production release record and create hotfix/<issue-number>-<short-name> from that commit, even when main is ahead; never base an emergency fix on an assumed current main and never edit the live server. Write or update a focused test. For a recurring bug, observe the new regression test fail on unfixed behavior and pass after the fix. Test happy path, validation/errors, boundaries, stale/expired/duplicate/concurrent behavior, permission denial, provider failure, data migration, rollback, and recovery as applicable. A hotfix must receive emergency review, merge through protected main or an explicitly predeclared protected release branch, and produce its deployable artifact from that reviewed merge commit. Lead approval alone never authorizes deployment of a hotfix-branch artifact.

Run targeted tests, then ./scripts/check-environment, composer validate --strict, composer verify, npm run format:check, npm run build, composer audit --locked, and npm audit --omit=dev. Run applicable Playwright/axe/provider/security/concurrency/migration suites. For UI, launch the app, inspect 320px, 390px, 768px tablet, and 1440px, test keyboard, zoom, reduced motion, slow/failure states and console, and capture non-private evidence. For production behavior, verify in staging first.

Before handoff, sweep the exact root-cause pattern across non-generated application code and classify every match as same bug, safe with reason, or separate follow-up. Update authoritative docs and operations material. Prepare a PR summary with classification, authority, reproduction/root cause or approved change record, impact map, scope/non-scope, schema/config/API changes, security/privacy effect, tests and results, runtime evidence, deployment/rollback, remaining blockers, and client/provider inputs. Never add generated-by text, watermarks, session URLs, signatures or co-author trailers.

When review or CI reports a problem, reproduce it on the same branch, confirm it is in scope, add/strengthen regression proof, make the narrowest correction, rerun the failing and full applicable gates, update the PR evidence, and request re-review. The project lead alone merges and deploys.
```

## 28. Reusable review-fix prompt

Use this when a phase/change PR receives review or CI feedback:

```text
Address the following Spinellanka PR review or CI finding on the existing branch. Do not widen the PR and do not commit, push, merge, or deploy without explicit project-lead authorization.

FINDING:
Paste the exact finding and non-secret evidence after this line.

Read CLAUDE.md, the branch's phase or maintenance section in roadmap.md, the PR scope, the changed diff, applicable authority docs, and existing tests. Query Graft before application-source reads and trace shared-symbol dependents before editing.

Reproduce the finding. State the root cause with a specific file/function/condition and evidence. Classify it as in-scope defect, incorrect review assumption, pre-existing unrelated issue, or blocked decision. If it is not in scope, explain and propose a separate issue; do not bundle it. If a product/client/security/design decision is missing, record the decision need and stop.

For an in-scope defect, add or strengthen a regression test, make the smallest fix at the responsible domain/application/interface layer, and preserve all product/security/design invariants. Run the original failing check, targeted tests, all tests affected by the dependency blast radius, and the full repository gate. Run browser/provider/concurrency/security/migration evidence required by the phase. Update the PR handoff with root cause, fix, regression proof, command results, runtime evidence and rollback impact. Request re-review; never mark a finding resolved without evidence.
```

## 29. Emergency production hotfix boundary

An emergency does not authorize an unreviewed live-server edit or deployment from a hotfix branch.

1. Name the incident owner, communication channel, current production release identifier, and exact deployed Git commit.
2. Preserve logs/evidence without exposing customer or secret data.
3. Contain using an existing safe control when possible: disable an integration, revoke a token/account/session, pause a workflow, enter maintenance, or roll back to the last approved release.
4. Create a GitHub issue. Create the authorized `hotfix/` branch from the exact deployed commit, not from current `main` when `main` is ahead of production.
5. Reproduce safely in staging, identify root cause, add regression proof, and make the narrowest fix.
6. Run all feasible security/payment/data/full gates. Record any skipped gate and explicit authorized risk acceptance.
7. Obtain emergency review. Merge through protected `main` when it can safely accept the fix, or through an explicitly predeclared protected release branch when production is behind and a direct main merge would pull unrelated changes. Do not create an ad hoc unprotected release branch during the incident.
8. Build/checksum the immutable artifact only from the reviewed protected merge commit. Pin that artifact in the deployment record. Lead approval alone does not authorize a hotfix-branch artifact.
9. Obtain separate deployment authorization and deploy the pinned artifact through the normal locked path. Apply the same backup, migration, maintenance cleanup, smoke, observation, and rollback controls as Phase 18.
10. Observe and reconcile affected bookings, payments, and messages. Complete a factual incident review.
11. If a protected release branch was used, reconcile the exact fix into protected `main` through a reviewed PR as soon as incident stability permits. Verify no divergence or duplicate migration remains.
12. Follow with a normal PR for non-emergency cleanup; do not expand the hotfix.

---

# Final Completion Criteria

The project is complete for launch only when:

- Phases 01–18 are merged and their status rows are marked Complete.
- Every launch-blocking client input has owner, source, approval, version, and effective date.
- All four services pass approved end-to-end customer, staff, guest, payment, driver, and field journeys as applicable.
- Availability concurrency, proposal lifecycle, payment idempotency, message durability, private authorization, and audit invariants pass.
- WCAG 2.2 AA, performance, security, privacy, backup/restore, rollback, scheduler/queue, provider, and cPanel gates pass.
- Staff training and operational ownership are recorded.
- The exact production artifact is deployed and observed without an unresolved launch blocker.
- The maintenance lane is active for all later changes and bugs.

Completion does not mean future requests bypass review. Every post-launch change follows sections 25–29.
