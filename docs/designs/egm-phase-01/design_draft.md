# Design Document: Phase 01 — Hosting, CI, Release, and Rollback Foundation

* **Author:** HMAHD
* **Date:** 2026-11-23
* **Status:** Draft
* **Target Release:** Phase 01 (`phase/01-hosting-ci-release`)

## The Problem and Overview

### The Problem
Deploying a modern Laravel 13 application with Vite-built assets and database queues to standard shared Linux cPanel hosting presents significant operational and security risks:
1. Shared cPanel accounts impose constrained process memory (often 256MB), execution timeouts, and lack Node.js runtimes. Building frontend assets or running development-tier Composer operations directly on cPanel can exhaust server resources, crash processes, or leak development packages.
2. Naive cPanel deployments frequently collapse the Laravel directory structure into `public_html`, exposing `.env`, vendor files, application logs, and database backups if web server rewrite configurations fail.
3. Lack of atomic deployment mechanisms causes downtime or inconsistent runtime states where requests hit partially copied PHP files or uncompiled assets.
4. Without strict release artifact checksum verification, corrupt or incomplete network transfers can be activated silently.
5. Without concurrency locking, parallel workflow triggers can execute conflicting deployments simultaneously.
6. Pull request CI pipelines must never have access to production deployment secrets, SSH keys, or hosting credentials.

### Overview
This design implements a deterministic, immutable release artifact pipeline and atomic deployment/rollback lifecycle for Spinellanka:
- GitHub Actions packages pre-compiled Vite frontend assets and production-only PHP dependencies (`--no-dev --optimize-autoloader`) into an immutable gzip tarball (`release.tar.gz`) along with a cryptographic SHA256 checksum file (`release.tar.gz.sha256`).
- Deployment is modeled as an environment-protected workflow with manual dispatch approvals, isolated secrets, and a concurrency lock.
- Target cPanel hosting runs only compiled PHP and assets. Application files reside outside `public_html` under `/home/<account>/spinellanka/releases/<release-id>`. Shared state (`.env`, `storage/`) is symlinked from `/home/<account>/spinellanka/shared/`.
- Atomic activation swaps the `current` symlink to the new release. Health checks verify `/up`, home route, service route, staff login shell, and database connectivity.
- Rollback is instantaneous (repointing `current` to the previous release and clearing/recaching configuration).
- A standalone hosting assessment tool (`scripts/cpanel-assess`) verifies all 11 required cPanel capabilities before deployment authorization.
- The developer environment check (`scripts/check-environment`) is extended to verify and pin the official Ponytail review plugin baseline `DietrichGebert/ponytail` `v5.3.3`.

## Goals and Non-Goals

### Goals
1. Produce an immutable production release artifact containing only production PHP dependencies and Vite assets, strictly excluding `.env*`, tests, git metadata, local caches, and development dependencies.
2. Generate and verify SHA256 cryptographic checksums before any release can be unpacked or activated.
3. Provide an environment-scoped deployment workflow in GitHub Actions that isolates deployment secrets from pull requests and enforces a strict concurrency lock.
4. Implement atomic activation and one-command rollback scripts (`scripts/deploy-release`, `scripts/rollback-release`) using symlink swapping and Laravel maintenance/cache commands.
5. Create a standalone cPanel capability assessment script (`scripts/cpanel-assess`) that validates the 11 gates from `docs/planning/05-cpanel-deployment-and-operations.md`.
6. Add health and smoke verification checks covering `/up`, public routes, and staff login shell.
7. Extend `scripts/check-environment` to pin and verify `DietrichGebert/ponytail` `v5.3.3`.
8. Provide focused automated tests verifying artifact exclusions, checksum validation, secret isolation, concurrency locking, and rollback rehearsal.

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
├── public_html/                       (or domain document root -> points to current/public)
└── spinellanka/
    ├── releases/
    │   ├── 20261123120000-4f85639/    (immutable release directory)
    │   │   ├── app/
    │   │   ├── bootstrap/
    │   │   │   └── cache/             (writable)
    │   │   ├── config/
    │   │   ├── database/
    │   │   ├── public/                (contains index.php, build/ assets, robots.txt)
    │   │   ├── resources/
    │   │   ├── routes/
    │   │   ├── vendor/                (production-only Composer dependencies)
    │   │   ├── artisan
    │   │   ├── .env -> ../../shared/.env
    │   │   └── storage -> ../../shared/storage
    │   └── 20261123110000-f1ca692/    (retained previous release for instant rollback)
    ├── shared/
    │   ├── .env                       (protected production environment configuration, 0600)
    │   └── storage/                   (persistent logs, app, framework cache, sessions)
    │       ├── app/
    │       ├── framework/
    │       │   ├── cache/
    │       │   ├── sessions/
    │       │   └── views/
    │       └── logs/
    ├── current -> releases/20261123120000-4f85639 (atomic symlink)
    └── deploy.lock                    (advisory lock preventing concurrent deployment)
```

### Packaging Pipeline (`.github/workflows/package-release.yml` / Release Step)
Artifact generation runs strictly after continuous integration gates pass (linting, static analysis, Pest tests, frontend build, dependency audits):
1. **Frontend Asset Compilation:** `npm ci --ignore-scripts` followed by `npm run build` generates optimized assets under `public/build/` with cache-busting asset manifests.
2. **Production Dependency Packaging:** In a clean workspace, `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` installs locked production PHP packages.
3. **Artifact Assembly:** The release package is assembled into an archive `release.tar.gz` with an explicit exclusion list:
   - Excluded: `.git/`, `.github/`, `.env*`, `tests/`, `node_modules/`, `storage/logs/*`, `storage/framework/cache/*`, `storage/framework/sessions/*`, `storage/framework/views/*`, `phpunit.xml`, `phpstan.neon`, `vite.config.js`, `package.json`, `package-lock.json`, `CLAUDE.md`, `README.md`.
   - Included: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/views/`, `routes/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`.
4. **Cryptographic Checksum:** `sha256sum release.tar.gz > release.tar.gz.sha256`. Both files are published as workflow artifacts with restricted retention.

### Deployment Workflow Boundary & Concurrency Lock
- Workflow: `.github/workflows/deploy.yml`
- Trigger: `workflow_dispatch` (manual authorization only) with environment selection (`staging` or `production`).
- Environment: Bound to GitHub Environments (`staging`, `production`) requiring explicit reviewer approval.
- Concurrency: `concurrency: deployment-${{ inputs.environment }}` with `cancel-in-progress: false` to ensure deployments never collide.
- Secrets Isolation: Secrets (`CPANEL_SSH_HOST`, `CPANEL_SSH_USER`, `CPANEL_SSH_KEY`, `CPANEL_DEPLOY_PATH`) are scoped strictly to the deployment environment and are inaccessible to PR workflows.

### Release Activation Sequence (`scripts/deploy-release`)
1. **Acquire Deployment Lock:** Create `/home/<account>/spinellanka/deploy.lock` using `flock` or atomic `mkdir`. If locked, abort immediately.
2. **Transfer & Verify Checksum:** Unpack `release.tar.gz` into `/home/<account>/spinellanka/releases/<release-id>`. Run `sha256sum -c release.tar.gz.sha256`. If checksum verification fails, delete the unpacked directory and exit with error.
3. **Link Shared Directories:** Symlink `shared/.env` to `releases/<release-id>/.env`. Symlink `shared/storage` to `releases/<release-id>/storage`. Ensure `bootstrap/cache` and `storage` permissions are writable (0775 / 0755).
4. **Database Migrations:** Run `php artisan migrate --force` inside `releases/<release-id>`. Migrations must be backward-compatible with the active release.
5. **Optimize Caches:** Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` in the new release directory.
6. **Atomic Symlink Switch:** Execute `ln -sfn releases/<release-id> current` atomically.
7. **Health Verification:** Perform HTTP checks against:
   - `/up` (Laravel built-in health check -> returns HTTP 200)
   - `/` (Public home page -> HTTP 200)
   - `/services/airport-pickup` (Public service route -> HTTP 200)
   - `/admin/login` (Staff login shell -> HTTP 200)
8. **Prune Old Releases:** Retain the active release and the immediately preceding release for rollback; remove older release folders.
9. **Release Lock:** Remove `deploy.lock`.

### Rollback Sequence (`scripts/rollback-release`)
1. **Identify Previous Release:** Read the target previous release directory from `/home/<account>/spinellanka/releases/`.
2. **Atomic Symlink Switch:** Execute `ln -sfn releases/<previous-release-id> current`.
3. **Recache Configuration:** Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` within the restored release.
4. **Health Verification:** Verify `/up` and `/` return HTTP 200.

### cPanel Capability Assessment (`scripts/cpanel-assess`)
A diagnostic script checks the 11 required hosting capabilities:
1. Web PHP version >= 8.3
2. CLI PHP version equals Web PHP version (exact major.minor match)
3. Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`, `pdo_mysql`, `intl`
4. Database engine: MariaDB 10.11+ or MySQL 8.0 with InnoDB and transaction support
5. Web root isolation: Document root can target `current/public`
6. Filesystem isolation: Application directory resides outside public document root
7. Deployment transport: SSH or cPanel Terminal available
8. Cron capability: 1-minute cron granularity
9. TLS: Valid HTTPS certificate
10. Memory limits: PHP `memory_limit` >= 256M
11. Atomic symlink support: Operating system supports `ln -sfn` atomic symlinks

If any gate fails or factual hosting evidence is unavailable, the verdict is recorded as `Blocked — hosting`, deployment remains disabled, and a hosting upgrade is recommended.

### Ponytail Baseline Verification
Extend `scripts/check-environment` to check that the Ponytail plugin is installed and pinned to `DietrichGebert/ponytail` `v5.3.3`, preventing unverified review divergence between maintainers.

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
   - *Rationale:* Extend the shared environment script to verify the Ponytail review baseline plugin `DietrichGebert/ponytail` `v5.3.3` in the local plugin cache or global plugin registry.
2. **`scripts/cpanel-assess`**
   - *Rationale:* Standalone diagnostic script that factually validates the 11 cPanel capability gates (PHP web/CLI versions, extensions, database engine, cron, document root, atomic symlinks, memory).
3. **`scripts/package-release`**
   - *Rationale:* Packaging script to build Vite assets, install production PHP dependencies, bundle the release tarball with strict exclusions, and generate SHA256 checksums.
4. **`scripts/deploy-release`**
   - *Rationale:* Release deployment and activation script handling lock acquisition, checksum validation, unpacking, shared symlinks, database migrations, cache optimization, atomic symlink switch, health checks, and old release pruning.
5. **`scripts/rollback-release`**
   - *Rationale:* Rollback script to atomically switch the `current` symlink back to the previous release, recache configurations, and execute health checks.
6. **`.github/workflows/package-release.yml`**
   - *Rationale:* Reusable or triggered GitHub Actions workflow to build and verify the release artifact, test artifact exclusions, and upload release archives.
7. **`.github/workflows/deploy.yml`**
   - *Rationale:* Manual dispatch deployment workflow targeting GitHub environments (`staging`, `production`), with concurrency locking and secret isolation.
8. **`docs/planning/05-cpanel-deployment-and-operations.md`**
   - *Rationale:* Record the factual capability assessment verdict, status, and gate proof without secrets.
9. **`roadmap.md`**
   - *Rationale:* Update Phase 01 status according to hosting verdict and record progress.
10. **`tests/Feature/Infrastructure/ReleaseArtifactTest.php`**
    - *Rationale:* Automated test proving artifact packaging exclusions (no `.env`, no tests, no git, no dev configs) and SHA256 checksum verification success/failure.
11. **`tests/Feature/Infrastructure/DeploymentLifecycleTest.php`**
    - *Rationale:* Automated test proving deployment concurrency lock, atomic symlink switching, health check responses, and rollback restoration.
12. **`tests/Feature/Infrastructure/SecretIsolationTest.php`**
    - *Rationale:* Automated test proving deployment credentials and production secrets are isolated from pull request workflows and repository files.

