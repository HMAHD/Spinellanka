# Goldfish Annotated Review Report

**Target:** `design_draft.md` (Full document)
**Status:** resolved

## Prioritized Findings & AI Suggestions

### 1. Health Check Target `/services/airport-pickup` Returns HTTP 404 [egm-codebase-details] - Severity: Blocker
* **Affected Scope:** `design_draft.md` (line 111)
* **Discrepancy Description:** Calling `/services/airport-pickup` with a hyphen evaluates `ServiceType::tryFrom('airport-pickup')` to `null` and triggers `abort(404)`. Approved route is `/services/airport_pickup`.
* **Rationale:** Route and enum definition in `routes/web.php` and `ServiceType.php` uses `airport_pickup`.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Essential correction to prevent automated health check failure.
* **Proposed Edit:**
  * Location: `design_draft.md` Health Verification list
  * Change: Replace `/services/airport-pickup` with `/services/airport_pickup`.

### 2. Health Check Target `/admin/login` Does Not Exist in Phase 01 [egm-implementation-readiness, egm-codebase-details] - Severity: Blocker
* **Affected Scope:** `design_draft.md` (lines 24, 37, 112)
* **Discrepancy Description:** Filament staff login shell is scheduled for Phase 02 (`phase/02-identity-access-audit`). In Phase 01, `/admin/login` returns HTTP 404.
* **Rationale:** Filament provider and admin routes do not exist in Phase 00/01.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Scope Phase 01 health checks strictly to existing routes (`/up`, `/`, `/services/airport_pickup`). Note that `/admin/login` will be added in Phase 02.
* **Proposed Edit:**
  * Location: Health checks in Overview, Goals, and Activation sequence
  * Change: Remove `/admin/login` from Phase 01 activation health checklist.

### 3. Archive Extraction Prior to Checksum Verification [egm-design, egm-implementation-readiness, egm-codebase-details] - Severity: Blocker
* **Affected Scope:** `design_draft.md` (lines 33, 103)
* **Discrepancy Description:** The design unpacks `release.tar.gz` before executing `sha256sum -c`.
* **Rationale:** Violates security ordering and archive integrity principles. Unverified archives must never be unpacked onto the host.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Invert sequence: verify checksum of archive file in staging directory first; unpack only when checksum verification exits 0.
* **Proposed Edit:**
  * Location: Section "Release Activation Sequence", Step 2
  * Change: Verify `sha256sum -c release.tar.gz.sha256` first. Unpack only upon success.

### 4. Non-Atomic Symlink Swap (`ln -sfn`) [egm-design] - Severity: Blocker
* **Affected Scope:** `design_draft.md` (lines 81, 107, 118, 134)
* **Discrepancy Description:** `ln -sfn` is non-atomic on Linux because it calls `unlink()` then `symlink()`, leaving a downtime/race window.
* **Rationale:** POSIX guarantees atomicity via `rename()`.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Use atomic pointer replacement via temporary symlink and `mv -Tf`:
  `ln -sfn "releases/${RELEASE_ID}" "${DEPLOY_PATH}/current.tmp.$$" && mv -Tf "${DEPLOY_PATH}/current.tmp.$$" "${DEPLOY_PATH}/current"`.
* **Proposed Edit:**
  * Location: Activation and rollback symlink commands
  * Change: Use temporary symlink and `mv -Tf`.

### 5. Omission of PII-Scrubbed Error Reporting Gate [egm-design, egm-implementation-readiness, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (missing section)
* **Discrepancy Description:** Mandatory gate in `roadmap.md` requires approving PII-scrubbed error reporting before staging rehearsal and host acceptance.
* **Rationale:** Prevents traveler phone numbers, booking details, or tokens from leaking in error reporting.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Add dedicated section for PII-scrubbed error reporting and logging hygiene. If unapproved, rehearsal remains non-production-like and host status is Blocked.
* **Proposed Edit:**
  * Location: New section "Error Reporting and Log Hygiene"
  * Change: Define scrubbing rules and gate conditions.

### 6. Missing Backups Gate in cPanel Capability Assessment [egm-design, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 122–136)
* **Discrepancy Description:** Gate 10 (Backups) from `05-cpanel-deployment-and-operations.md` was omitted.
* **Rationale:** Automated database/application backups and restore testing are mandatory for cPanel hosting.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Realign all 11 gates with `05-cpanel-deployment-and-operations.md`.
* **Proposed Edit:**
  * Location: Section "cPanel Capability Assessment"
  * Change: Reinstate Gate 10 (Backups) and combine environment/limits/symlinks into Gate 11.

### 7. Concurrency Locking Architecture [egm-design, egm-implementation-readiness, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 82, 102, 116)
* **Discrepancy Description:** `mkdir deploy.lock` leaves persistent stale locks on abrupt process termination.
* **Rationale:** MySQL `GET_LOCK` or Laravel `Cache::lock()` with kernel-managed `flock -n` prevents stale lock deadlocks.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Standardize on database advisory lock with `flock` file descriptor locking fallback and trap cleanup handlers. Mandate the same locking in `scripts/rollback-release`.
* **Proposed Edit:**
  * Location: Concurrency lock specifications
  * Change: Use database lock / `flock -n 200` with trap cleanup.

### 8. Path Parameterization for Multi-Environment and Test Execution [egm-implementation-readiness] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 53–83, 101–121, 175)
* **Discrepancy Description:** Hardcoded `/home/<account>/` prevents running automated tests on dev/CI runners.
* **Rationale:** Tests need to run against temporary directories.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Parameterize paths via `SPINELLANKA_BASE_DIR`, `CPANEL_PHP_BIN`, `CPANEL_SSH_PORT`.
* **Proposed Edit:**
  * Location: Technical Plan and Scripts
  * Change: Support environment variables with fallback defaults.

### 9. Automated Rollback on Health Check Failure & Maintenance Mode [egm-design, egm-implementation-readiness, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 104–115)
* **Discrepancy Description:** No automated rollback on health check failure; maintenance mode during migrations not detailed.
* **Rationale:** Health failure post-switch must automatically revert `current` to previous release and recache config. Maintenance mode must be entered during schema updates and exited cleanly.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Add auto-rollback to `scripts/deploy-release` and maintenance mode handling (`php artisan down` / `php artisan up`).
* **Proposed Edit:**
  * Location: Section "Release Activation Sequence"
  * Change: Add maintenance mode and automated rollback invocation.

### 10. Playwright and axe Public Shell Smoke Testing [egm-design, egm-implementation-readiness, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 86, 152–177)
* **Discrepancy Description:** Playwright/axe public shell smoke test from `roadmap.md` line 350 was omitted from dependencies and implementation list.
* **Rationale:** Baseline accessibility and route health verification in CI before packaging.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Add `@playwright/test` and `@axe-core/playwright` to `package.json` devDependencies and create `tests/e2e/smoke.spec.js`.
* **Proposed Edit:**
  * Location: Detailed Implementation Plan
  * Change: Add Playwright/axe infrastructure.

### 11. Workflow Dispatch Inputs and CI Verify Integration [egm-implementation-readiness, egm-codebase-details] - Severity: Major
* **Affected Scope:** `design_draft.md` (lines 94–100, 152–177)
* **Discrepancy Description:** `.github/workflows/deploy.yml` inputs and `.github/workflows/verify.yml` integration were not detailed.
* **Rationale:** `deploy.yml` needs `environment` and `release_sha`/`artifact_run_id` inputs; `verify.yml` needs to run packaging validation.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Explicitly specify workflow inputs and add packaging validation job to `verify.yml`.
* **Proposed Edit:**
  * Location: CI Workflow specifications
  * Change: Detail inputs and `verify.yml` packaging job.

### 12. Ponytail Plugin Baseline Specification [egm-implementation-readiness, egm-codebase-details] - Severity: Nit
* **Affected Scope:** `design_draft.md` (lines 138–140, 154)
* **Discrepancy Description:** Exact check for Ponytail baseline was missing file and error details.
* **Rationale:** Need clear alignment with `check-environment` patterns.
* **AI Suggestion:** PROPOSE FIX
* **Justification:** Specify checking `DietrichGebert/ponytail` `5.3.3` in `.claude/settings.json` and cache directory with explicit remediation message.
* **Proposed Edit:**
  * Location: Ponytail verification section
  * Change: Detail check in `scripts/check-environment`.

