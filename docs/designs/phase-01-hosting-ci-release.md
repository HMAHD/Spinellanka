# Design Document: Phase 01 — Hosting, CI, Release, and Rollback Foundation

* **Author:** HMAHD
* **Date:** 2026-11-23
* **Status:** Resolved
* **Target Release:** Phase 01 (`phase/01-hosting-ci-release`)
* **Review Protocol:** Full EGM Workflow (Brainstorm -> Draft -> Goldfish Review -> Resolved)

## The Problem and Overview

### The Problem
Deploying a modern Laravel 13 application with Vite-compiled assets and database queues to standard Linux cPanel shared hosting introduces critical operational, performance, and security challenges:
1. Shared cPanel accounts impose tight process memory ceilings (minimum 256MB) and execution timeouts, and lack Node.js LTS runtimes. Attempting to build assets or run development-tier Composer dependencies directly on cPanel can exhaust server memory, cause process termination by host OOM reapers, or leak development tools into production.
2. Naive cPanel deployments frequently collapse the Laravel directory structure into `public_html`, exposing `.env`, vendor packages, application logs, and database backups if web server rewrite configurations fail.
3. Non-atomic symlink replacement (such as raw `ln -sfn`) executes `unlink()` followed by `symlink()`, creating a race window where incoming requests receive HTTP 404/500 errors.
4. Extracting an untrusted or corrupted archive before validating its cryptographic SHA256 checksum risks path-traversal exploits, partial writes, or corrupt runtime states.
5. Without concurrency locking, simultaneous deployment triggers can collide, corrupting releases or database migrations.
6. Pull request CI pipelines must never have access to production deployment secrets, SSH keys, or hosting credentials.

### Overview
This resolved design implements a deterministic, immutable release artifact pipeline and atomic deployment/rollback lifecycle for Spinellanka:
- GitHub Actions packages pre-compiled Vite frontend assets and production-only PHP dependencies (`--no-dev --optimize-autoloader`) into an immutable gzip tarball (`release.tar.gz`) accompanied by a cryptographic SHA256 checksum file (`release.tar.gz.sha256`).
- Target cPanel hosting runs only pre-compiled PHP and assets. Application files reside outside `public_html` under `/home/<account>/spinellanka/releases/<release-id>`. Shared state (`.env`, `storage/app`, `storage/logs`) is symlinked from `/home/<account>/spinellanka/shared/`. Framework cache and view directories remain isolated per release to avoid cross-release Blade compilation collisions.
- The release activation sequence verifies the cryptographic SHA256 checksum in staging *before* any extraction occurs.
- Atomic activation replaces the `current` symlink using POSIX-guaranteed atomic `rename()` (`mv -Tf`).
- Health checks verify `/up`, the public home route (`/`), and the active service route (`/services/airport_pickup`). If health checks fail post-activation, the system automatically executes an automated rollback to the previous release. Staff login (`/admin/login`) is documented for inclusion in Phase 02 once Filament is registered.
- A standalone hosting assessment tool (`scripts/cpanel-assess`) verifies all 11 required cPanel capabilities before deployment authorization.
- Deployment is controlled via `.github/workflows/deploy.yml` with manual dispatch, environment protection, isolated secrets, and a concurrency lock.
- PII-scrubbed error reporting and logging hygiene rules are defined; if approved error monitoring is not active, rehearsals remain non-production-like and the hosting verdict remains `Blocked — hosting`.
- Developer environment checks (`scripts/check-environment`) are extended to verify and pin the official Ponytail review baseline `DietrichGebert/ponytail` `v5.3.3`.

## Goals and Non-Goals

### Goals
1. Produce an immutable production release artifact containing only production PHP dependencies and Vite assets, strictly excluding `.env*`, tests, git metadata, local caches, and development dependencies.
2. Validate SHA256 cryptographic checksums before any release archive is unpacked or activated.
3. Provide an environment-scoped deployment workflow in GitHub Actions that isolates deployment secrets from pull requests and enforces strict concurrency locking.
4. Implement atomic activation and one-command rollback scripts (`scripts/deploy-release`, `scripts/rollback-release`) using POSIX atomic pointer replacement (`mv -Tf`), database advisory locking, maintenance mode handling, and automated health failure recovery.
5. Parameterize deployment and assessment scripts via environment variables (`SPINELLANKA_BASE_DIR`, `CPANEL_PHP_BIN`, `CPANEL_SSH_PORT`) to permit deterministic end-to-end testing in development and CI environments.
6. Create a standalone cPanel capability assessment script (`scripts/cpanel-assess`) that factually validates the 11 gates defined in `docs/planning/05-cpanel-deployment-and-operations.md`.
7. Define PII-scrubbed error reporting and log hygiene standards, enforcing that unapproved hosts remain `Blocked — hosting`.
8. Extend `scripts/check-environment`, `.claude/settings.json`, and `docs/development/environment.md` to pin and verify `DietrichGebert/ponytail` `v5.3.3`.
9. Add Playwright/axe public shell smoke test foundation verifying `/` and `/services/airport_pickup` meet WCAG accessibility standards.
10. Provide focused automated tests verifying artifact exclusions, checksum validation, secret isolation, concurrency locking, and rollback rehearsal.

### Non-Goals
1. Live production deployment or DNS switch in Phase 01.
2. Real payment gateway, WhatsApp Business, Google Maps, or transactional email provider activation.
3. Creation of any public web-accessible deployment endpoint or webhook.
4. Modification of Laravel's public root or weakening of directory permission boundaries.
5. Installation of Node.js or Composer build tooling on production servers.

## Technical Plan

### System Architecture and Directory Layout
The target filesystem layout on cPanel maintains complete separation between web-accessible documents and application source code:

```text
/home/<account>/
├── public_html/                       (or Addon Domain DocumentRoot -> points to spinellanka/current/public)
└── spinellanka/
    ├── .incoming/                     (temporary staging area for tarball & checksum verification)
    ├── releases/
    │   ├── 20261123120000-4f85639/    (immutable release directory)
    │   │   ├── app/
    │   │   ├── bootstrap/
    │   │   │   └── cache/             (writable, 0775)
    │   │   ├── config/
    │   │   ├── database/
    │   │   ├── public/                (contains index.php, build/ assets, robots.txt)
    │   │   ├── resources/
    │   │   ├── routes/
    │   │   ├── storage/
    │   │   │   ├── app -> ../../../shared/storage/app
    │   │   │   ├── framework/         (release-local: cache, sessions, views)
    │   │   │   └── logs -> ../../../shared/storage/logs
    │   │   ├── vendor/                (production-only Composer dependencies)
    │   │   ├── artisan
    │   │   └── .env -> ../../shared/.env
    │   └── 20261123110000-f1ca692/    (retained previous release for instant rollback)
    ├── shared/
    │   ├── .env                       (protected production environment configuration, 0600)
    │   └── storage/                   (persistent data and application logs)
    │       ├── app/
    │       └── logs/
    ├── current -> releases/20261123120000-4f85639 (atomic symlink via rename)
    └── deploy.lock                    (concurrency lock file descriptor / advisory lock)
```

### Packaging Pipeline (`scripts/package-release`)
Artifact generation runs strictly after continuous integration gates pass:
1. **Config Cleanup:** Execute `php artisan config:clear` to ensure no environment-specific configuration cache is packaged.
2. **Frontend Asset Compilation:** `npm ci --ignore-scripts` followed by `npm run build` generates optimized assets under `public/build/` with cache-busting manifests.
3. **Production Dependency Packaging:** In a clean staging directory, execute:
   `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress`
4. **Artifact Assembly:** The archive `release.tar.gz` is assembled with an explicit exclusion list:
   - Excluded: `.git/`, `.github/`, `.env*`, `tests/`, `node_modules/`, `storage/`, `*.sqlite*`, `public/hot`, `public/storage`, `bootstrap/cache/*.php`, `phpunit.xml`, `phpstan.neon`, `vite.config.js`, `package.json`, `package-lock.json`, `CLAUDE.md`, `README.md`.
   - Included: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/views/`, `routes/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`.
5. **Cryptographic Checksum:** Compute `sha256sum release.tar.gz > release.tar.gz.sha256`.

### Deployment Workflow Boundary & Concurrency Lock (`.github/workflows/deploy.yml`)
- Trigger: `workflow_dispatch` requiring explicit manual authorization:
  ```yaml
  inputs:
    environment:
      description: 'Target deployment environment'
      required: true
      default: 'staging'
      type: choice
      options: ['staging', 'production']
    release_sha:
      description: 'Git commit SHA from main to deploy'
      required: true
      type: string
  ```
- Environment: Bound to GitHub Environments (`staging`, `production`) requiring explicit project lead review.
- Concurrency: `concurrency: deployment-${{ inputs.environment }}` with `cancel-in-progress: false` to ensure deployments never overlap.
- Secrets Isolation: Secrets (`CPANEL_SSH_HOST`, `CPANEL_SSH_PORT`, `CPANEL_SSH_USER`, `CPANEL_SSH_KEY`, `CPANEL_DEPLOY_PATH`, `CPANEL_PHP_BIN`) are scoped strictly to deployment environments and are completely unavailable to pull request jobs.
- Hosting Gate Assertion: The workflow asserts that `docs/planning/05-cpanel-deployment-and-operations.md` contains `Verdict: Accepted` before allowing staging or production activation. If `Verdict: Blocked`, deployment immediately exits with error.

### Release Activation Sequence (`scripts/deploy-release`)
1. **Acquire Concurrency Lock:** Acquire a non-blocking lock using file descriptor locking (`flock -n 200`) on `$SPINELLANKA_BASE_DIR/deploy.lock` and an optional database advisory lock (`SELECT GET_LOCK('spinellanka_deploy', 0)`). Set a bash trap `trap cleanup EXIT INT TERM` to guarantee lock release.
2. **Staged Transfer & Cryptographic Checksum Validation:**
   - Transfer `release.tar.gz` and `release.tar.gz.sha256` into `$SPINELLANKA_BASE_DIR/.incoming/`.
   - Validate checksum: `sha256sum -c release.tar.gz.sha256`.
   - If checksum validation fails, immediately delete the staged files, release lock, and abort with exit code `1`.
3. **Unpack Release:**
   - Only after successful checksum validation, create `$SPINELLANKA_BASE_DIR/releases/<release-id>`.
   - Unpack archive: `tar -xzf $SPINELLANKA_BASE_DIR/.incoming/release.tar.gz -C $SPINELLANKA_BASE_DIR/releases/<release-id>`.
   - Delete staged archive files in `.incoming/`.
4. **Link Shared Directories & Prepare Local Storage:**
   - Symlink `shared/.env` -> `releases/<release-id>/.env`.
   - Create release-local `releases/<release-id>/storage/framework/{cache,sessions,views}` with `0775` permissions.
   - Symlink `shared/storage/app` -> `releases/<release-id>/storage/app`.
   - Symlink `shared/storage/logs` -> `releases/<release-id>/storage/logs`.
   - Ensure `releases/<release-id>/bootstrap/cache` exists with `0775` permissions.
5. **Maintenance Mode & Database Migrations:**
   - If migrations are required, activate maintenance mode:
     `$PHP_BIN artisan down --secret="$MAINTENANCE_BYPASS_TOKEN" --render="errors::503"`
   - Run migrations: `$PHP_BIN artisan migrate --force`.
6. **Cache Optimization:**
   - Run `$PHP_BIN artisan config:cache`, `$PHP_BIN artisan route:cache`, and `$PHP_BIN artisan view:cache` in the new release directory.
7. **Atomic Symlink Switch:**
   - Create temporary symlink: `ln -sfn "releases/<release-id>" "$SPINELLANKA_BASE_DIR/current.tmp.$$"`.
   - Atomically replace pointer: `mv -Tf "$SPINELLANKA_BASE_DIR/current.tmp.$$" "$SPINELLANKA_BASE_DIR/current"`.
8. **Restart Workers & Clear OPcache:**
   - Run `$PHP_BIN artisan queue:restart`.
   - Invalidate PHP CLI stat cache: `$PHP_BIN -r 'clearstatcache(true);'`.
9. **Health Verification:**
   - Deactivate maintenance mode: `$PHP_BIN artisan up`.
   - Perform HTTP health checks against:
     - `/up` (Laravel health check -> HTTP 200)
     - `/` (Public home page -> HTTP 200)
     - `/services/airport_pickup` (Public service route -> HTTP 200)
10. **Auto-Rollback on Failure:**
    - If any health check fails or times out:
      - Immediately invoke `scripts/rollback-release`.
      - Rename failed release directory to `<release-id>.failed`.
      - Exit with code `2`.
11. **Prune Old Releases:**
    - Retain active release and the immediately preceding release; prune older release directories.
12. **Release Concurrency Lock:** Lock released via trap handler upon exit.

### Rollback Sequence (`scripts/rollback-release`)
1. **Acquire Concurrency Lock:** Acquire `$SPINELLANKA_BASE_DIR/deploy.lock`.
2. **Identify Target Release:** Locate the previous valid release in `$SPINELLANKA_BASE_DIR/releases/`.
3. **Atomic Symlink Switch:**
   `ln -sfn "releases/<previous-release-id>" "$SPINELLANKA_BASE_DIR/current.tmp.$$" && mv -Tf "$SPINELLANKA_BASE_DIR/current.tmp.$$" "$SPINELLANKA_BASE_DIR/current"`.
4. **Recache Configuration:**
   - Run `$PHP_BIN artisan config:cache`, `$PHP_BIN artisan route:cache`, and `$PHP_BIN artisan view:cache` inside the restored release.
   - Run `$PHP_BIN artisan queue:restart`.
   - Invalidate CLI stat cache.
5. **Health Verification:**
   - Assert `/up` and `/` return HTTP 200.
6. **Release Lock.**

### cPanel Capability Assessment (`scripts/cpanel-assess`)
Evaluates the 11 gates from `docs/planning/05-cpanel-deployment-and-operations.md`:
1. **PHP Web Runtime:** Version >= 8.3.0.
2. **PHP CLI Runtime:** Exact major.minor version match with Web runtime.
3. **Extensions:** `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`, `pdo_mysql`, `intl`.
4. **Database:** MariaDB 10.11+ or MySQL 8.0 with InnoDB and transaction support verified.
5. **Web Root Isolation:** Document root configured to point to `current/public` outside `public_html`.
6. **Filesystem Isolation:** Application lives outside public root; `bootstrap/cache` and `storage` writable.
7. **CLI / Deploy Transport:** SSH or cPanel Terminal available; non-interactive execution functional.
8. **Scheduler:** cPanel cron granularity at one minute with non-overlapping execution.
9. **TLS:** Valid SSL certificate with HTTPS redirection active.
10. **Backups:** Automated database dump utility (`mysqldump` / `mariadb-dump`) and application backup storage verified.
11. **Limits & Environment:** PHP `memory_limit` >= 256MB, execution timeout adequate, atomic symlink (`mv -Tf`) supported.

If any gate fails or hosting evidence is missing/unverified, record the verdict as `Blocked — hosting` in `docs/planning/05-cpanel-deployment-and-operations.md`, keep deployment disabled, and document hosting upgrade requirements.

### Error Reporting and Log Hygiene
- Staging and production log configurations apply strict PII redaction:
  - Redacted from request parameters, context, and stack traces: phone numbers, WhatsApp headers, international mobile contacts, guest/driver link token hashes, passwords, and session cookies.
- An approved error reporting provider (e.g. Sentry or client-approved alternative) must be approved before a production-like staging rehearsal. Without approval, staging rehearsals must be explicitly labeled synthetic/non-production-like, and Phase 01 status remains `Blocked — hosting`.

### Ponytail Baseline Verification
- In `scripts/check-environment`, assert that `DietrichGebert/ponytail` `v5.3.3` is installed and registered in `.claude/settings.json`.
- Update `docs/development/environment.md` with installation commands for maintainer alignment:
  `/plugin install DietrichGebert/ponytail@v5.3.3`.

## Alternatives Considered

1. **Building on cPanel via Git Deployment:**
   - *Rationale for Rejection:* Rejected by author: Shared cPanel accounts lack Node 26 LTS and have low memory limits, which frequently causes `npm run build` or `composer install` to be killed by the host OOM reaper. Pre-packaging in CI produces deterministic, zero-dependency release artifacts.
2. **Deploying via Public Webhook Endpoint:**
   - *Rationale for Rejection:* Rejected by author: Exposes an unauthenticated or token-based public HTTP attack surface that could trigger arbitrary execution or denial-of-service on shared hosting.
3. **Packaging Entire Repository with Dev Dependencies:**
   - *Rationale for Rejection:* Rejected by author: Unnecessarily bloats deployment artifacts, introduces development-only security vulnerabilities into production, and risks leaking local test configs or secrets.
4. **Moving Laravel Application Directly into `public_html` with `.htaccess` Protection:**
   - *Rationale for Rejection:* Rejected by author: Violates Laravel security boundaries and creates high risk of source code or environment credential leakage if Apache configuration lapses.

## Detailed Implementation Plan

1. **`scripts/check-environment`**
   - *Rationale:* Extend script to verify `DietrichGebert/ponytail` `v5.3.3` in `.claude/settings.json` and plugin directory.
2. **`.claude/settings.json`**
   - *Rationale:* Pin Ponytail plugin baseline `v5.3.3` in enabled plugins and extra known marketplaces.
3. **`docs/development/environment.md`**
   - *Rationale:* Document Ponytail `v5.3.3` verification and plugin installation command for maintainers.
4. **`scripts/cpanel-assess`**
   - *Rationale:* Standalone diagnostic script that factually validates the 11 cPanel capability gates.
5. **`scripts/package-release`**
   - *Rationale:* Packaging script to build Vite assets, install production PHP dependencies, bundle the release tarball with strict exclusions, and generate SHA256 checksums.
6. **`scripts/deploy-release`**
   - *Rationale:* Release deployment and activation script handling lock acquisition, checksum validation before extraction, shared symlinks, database migrations, maintenance mode, cache optimization, atomic symlink switch, health checks, auto-rollback, and old release pruning.
7. **`scripts/rollback-release`**
   - *Rationale:* Rollback script to atomically switch the `current` symlink back to the previous release, recache configurations, restart queue workers, and execute health checks.
8. **`.github/workflows/verify.yml`**
   - *Rationale:* Extend CI workflow to include artifact packaging verification job asserting strict artifact exclusions and SHA256 integrity.
9. **`.github/workflows/deploy.yml`**
   - *Rationale:* Manual dispatch deployment workflow targeting GitHub environments (`staging`, `production`), with concurrency locking, secret isolation, and hosting gate assertion.
10. **`package.json`**
    - *Rationale:* Add `@playwright/test` and `@axe-core/playwright` to devDependencies for public shell smoke testing.
11. **`playwright.config.js` and `tests/e2e/smoke.spec.js`**
    - *Rationale:* Smoke test verifying home and service routes render successfully with zero critical accessibility violations.
12. **`docs/planning/05-cpanel-deployment-and-operations.md`**
    - *Rationale:* Record the factual capability assessment verdict, status, and gate proof without secrets.
13. **`roadmap.md`**
    - *Rationale:* Update Phase 01 status to reflect hosting verdict (`Blocked — hosting` with deployment disabled and upgrade recommendations).
14. **`tests/Feature/Infrastructure/ReleaseArtifactTest.php`**
    - *Rationale:* Automated test proving artifact packaging exclusions (no `.env`, no tests, no git, no dev configs) and SHA256 checksum verification success/failure.
15. **`tests/Feature/Infrastructure/DeploymentLifecycleTest.php`**
    - *Rationale:* Automated test proving deployment concurrency lock, atomic symlink switching, health check responses, maintenance mode, and rollback restoration.
16. **`tests/Feature/Infrastructure/SecretIsolationTest.php`**
    - *Rationale:* Automated test proving deployment credentials and production secrets are isolated from pull request workflows and repository files.

