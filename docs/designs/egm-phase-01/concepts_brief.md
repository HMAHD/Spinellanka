# Phase 01 — Hosting, CI, Release, and Rollback Foundation

* **Author:** HMAHD
* **Date:** 2026-11-23

## Overview

Phase 01 establishes the deployment, continuous integration, artifact packaging, and rollback foundation for Spinellanka on Linux cPanel shared hosting without compromising Laravel security boundaries or requiring Node/Composer runtimes on production servers. It introduces an immutable release-artifact packaging workflow with cryptographic checksums, an environment-scoped deployment gate with concurrency locking, release lifecycle scripts for atomic symlink switching and rollback, a factual cPanel capability assessment tool, and developer environment verification pinning the required Ponytail review baseline.

## Analysis

### Contrarian / YAGNI Lens
Shared cPanel hosting cannot reliably execute resource-intensive asset builds or long-running dependency installations due to process timeouts, memory limits (minimum 256 MB), and lack of root/Node runtimes. Packaging pre-compiled assets (Vite) and production-only PHP dependencies (`composer install --no-dev --prefer-dist --optimize-autoloader`) in CI eliminates the need for Node or Composer on the target host. Rather than introducing complex third-party deployment agents or SaaS tools, the system uses standard POSIX shell scripts, SSH/SFTP transfer, and atomic directory symlinking (`current -> releases/<release-id>`), reusing Laravel's built-in commands (`artisan down`, `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `artisan up`).

### Cost, Quota & Token Economics Lens
Building artifacts in GitHub Actions incurs zero infrastructure hosting cost on cPanel. The release archive is compressed using gzip (`.tar.gz`), excluding test suites, dev dependencies, documentation, git history, and local caches to minimize transfer payload, disk inode usage, and network bandwidth. Concurrency locking in GitHub Actions (`concurrency: production-deployment`) and local advisory locks prevent overlapping deployment executions that would exhaust shared CPU/memory quotas.

### Observability & SRE Debuggability Lens
Release health verification is conducted immediately post-activation via HTTP checks against `/up`, the public home route, a service route, and the staff authentication shell, combined with verification of cron/queue execution readiness. Rollback is deterministic and near-instantaneous (O(1)) by repointing the `current` symlink to the previous release directory and recaching configuration without needing to rebuild or re-download assets. Error reporting must have PII scrubbing approved before production-like staging; without it, errors must not expose traveler phone numbers or booking tokens.

### Migration & Rollback Compatibility Lens
All database migrations follow backward-compatible expand/contract principles so that the prior release remains functional against the schema during the observation window. If an activation check fails, the automated or manual rollback procedure restores the `current` symlink to the preceding release directory and runs cache optimization. Destructive schema alterations are strictly deferred to subsequent cleanup releases.

### Security & Secret Isolation Lens
Production secrets (`.env`) are never committed to git, never packaged into the build artifact, and never exposed to pull request workflows. Deployment workflows use GitHub Environment protection rules with manual approvals and restricted secrets (`CPANEL_SSH_KEY`, `CPANEL_HOST`, etc.). Production document root points exclusively to `/home/<account>/spinellanka/current/public`, keeping application source, storage, and configuration completely outside the web-accessible root. No public web-accessible deploy webhook is permitted.

## Goals vs. Non-Goals

### In-Scope Goals
* Build an immutable release artifact packaging workflow in GitHub Actions that combines production PHP dependencies and Vite-compiled assets with SHA256 checksum generation and verification.
* Enforce artifact exclusion rules: strip `.env*`, tests, git metadata, developer configs, and node_modules.
* Implement environment-scoped deployment workflow with manual dispatch approval, concurrency lock, and isolated deployment secrets.
* Provide cPanel capability assessment tooling (`scripts/cpanel-assess` and checklist) to factually evaluate the 11 hosting gates defined in `docs/planning/05-cpanel-deployment-and-operations.md`.
* Create standardized release directory structure, atomic symlink activation, maintenance mode handling, health checks, and previous-release rollback scripts.
* Extend `scripts/check-environment` to pin and verify the official Ponytail review baseline `DietrichGebert/ponytail` `v5.3.3`.
* Add automated tests for artifact exclusions, checksum validation, concurrency locks, secret isolation, and rollback rehearsal.
* Add Playwright/axe smoke check foundation for the public shell.

### Out-of-Scope Non-Goals
* No production deployment or live DNS cutover in Phase 01.
* No real customer, provider, messaging, or payment activation.
* No public or web-accessible deployment endpoint.
* No flattening or copying of application source into `public_html`.
* No installation of Node, npm, or Composer on the cPanel production server.

## Alternatives Weighed

* **Build on cPanel via Git Deployment or Terminal:** Rejected by author: shared hosting environments frequently hit memory/execution time limits during npm/Composer builds, lack Node LTS 26, and risk leaving intermediate uncompiled states visible.
* **Public Webhook Deployment Endpoint:** Rejected by author: creates an unauthenticated or token-based public HTTP attack surface that could trigger arbitrary execution or denial-of-service on shared hosting.
* **Deploying Full Repository including Dev Dependencies:** Rejected by author: bloats deployment artifacts, introduces development-only security vulnerabilities (e.g. dev-dependencies) into production, and risks leaking local test secrets or configs.
* **Moving Laravel Application Directly into `public_html` with `.htaccess` Protection:** Rejected by author: violates Laravel security boundaries and creates high risk of source code or environment credential leakage if Apache configuration lapses.

## Key Decisions & Staging for Next Steps

1. **Release Packaging:** GitHub Actions job runs after verify jobs pass; creates `release.tar.gz` and `release.tar.gz.sha256`.
2. **Directory Layout:** Standardized as `/home/<account>/spinellanka/{releases/<release-id>,shared/.env,shared/storage,current}`.
3. **Activation & Rollback:** Managed via `scripts/deploy-release` and `scripts/rollback-release` scripts utilizing atomic symlink swap `ln -sfn`.
4. **Hosting Gate Assessment:** `scripts/cpanel-assess` outputs a factual pass/fail checklist. If any critical gate fails or hosting evidence is missing/rejected, Phase 01 records the verdict as `Blocked — hosting` with deployment disabled and upgrade recommendations.
5. **Ponytail Baseline:** Verified in `scripts/check-environment` against version `5.3.3` of `DietrichGebert/ponytail`.

