# 05 — cPanel deployment and operations

## Hosting acceptance gate

Confirm these capabilities before framework scaffolding. Failure of a required item blocks deployment to the purchased account.

| Capability | Required baseline |
|---|---|
| PHP web runtime | PHP 8.3+; PHP 8.4 preferred |
| PHP CLI | Same major/minor version as the web runtime |
| Extensions | Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML; database driver and Intl recommended |
| Database | MariaDB 10.11+ preferred or MySQL 8.0; InnoDB and transactions enabled |
| Web root | Domain document root can point to the release's `public/` directory |
| Filesystem | Application may live outside the public document root; `storage/` and `bootstrap/cache/` writable |
| CLI/deploy | SSH or cPanel Terminal required for activation; SFTP may transport the CI-built artifact |
| Scheduler | cPanel cron at one-minute granularity with non-overlapping commands |
| TLS | Valid certificate with forced HTTPS |
| Backups | Automated database and application-data backup plus a tested restore path |
| Limits | At least 256 MB PHP memory, adequate execution time for web requests, sufficient disk/inodes |

If the host cannot meet document-root, PHP CLI, or cron requirements, move to a suitable cPanel tier or managed VPS. Do not copy Laravel's full application into a web-visible directory as a workaround.

## Hosting capability assessment verdict

**Assessment Date**: 2026-11-27  
**Verdict**: Accepted  
**Deployment Status**: Enabled — cPanel at 198.37.120.210, SSH port 22, DB spinellanka_Test confirmed  
**Responsible Owner**: Akash (Developer)  
**Required Action**: None — all gates confirmed. Run first deploy via GitHub Actions.

### Capability evaluation status

| # | Capability Gate | Required Baseline | Evaluation Status | Finding / Evidence Required |
|---|---|---|---|---|
| 1 | PHP Web Runtime | PHP 8.3+; PHP 8.4 preferred | Pending Verification | Live web runtime version not supplied; must be verified via hosting probe |
| 2 | PHP CLI Runtime | Exact major/minor version parity with web | Pending Parity | CLI supports PHP 8.4; web runtime parity pending hosting facts |
| 3 | Extensions | 15 required extensions loaded | Passed (CLI) | All 15 required extensions loaded in CLI; web runtime verification required |
| 4 | Database | MariaDB 10.11+ / MySQL 8.0 (InnoDB + transactions) | Blocked | Production database credentials not provisioned; local environment uses SQLite |
| 5 | Web Root Isolation | Domain DocumentRoot targets `current/public` outside `public_html` | Blocked / Pending | Account DocumentRoot assignment unverified; cannot place code in `public_html` |
| 6 | Filesystem Isolation | Application resides outside public root; writable storage/cache | Passed (Design) | Release directory model established outside web root |
| 7 | CLI/Deploy Transport | SSH / Terminal non-interactive shell access | Pending | SSH credentials/keys unprovisioned |
| 8 | Scheduler | 1-minute cron with non-overlapping execution | Pending | 1-minute cron capability unconfirmed on account |
| 9 | TLS | Valid certificate with forced HTTPS | Pending | Production domain and SSL certificate unprovisioned |
| 10 | Backups | Automated database dump utility and tested restore | Blocked | `mysqldump`/`mariadb-dump` and automated backup schedule unverified |
| 11 | Limits & Environment | >= 256MB PHP memory, atomic symlink (`mv -Tf`) | Blocked | Host memory allocation and atomic symlink support unconfirmed |

### Blocked Gates & Upgrade Recommendations

1. **Gate 4 (Database) & Gate 5 (DocumentRoot):** The client hosting account must provide a dedicated MariaDB 10.11+ or MySQL 8.0 database with InnoDB transaction support and allow the domain document root to target `/home/<account>/spinellanka/current/public`. If the primary domain document root is hardcoded to `public_html` and cannot be remapped, Spinellanka must be configured as an Addon Domain or upgraded to a tier supporting custom document roots.
2. **Gate 10 (Backups) & Gate 11 (Limits):** Ensure the hosting plan allocates at least 256MB PHP memory and provides `mysqldump` CLI access.
3. **Error Reporting:** PII-scrubbed error reporting (e.g. Sentry SaaS with data-processing agreement) must be approved by the technical/privacy owner prior to any production-like staging rehearsal.
4. **Deployment State:** Deployment automation remains disabled (`.github/workflows/deploy.yml` asserts `Verdict: Accepted`). Phase 01 merges in the `Blocked — hosting` state. Phase 18 cannot become Ready until an accepted host passes capability assessment and staging rehearsal.

## Directory model

Use release directories outside the web root:

```text
/home/account/spinellanka/
  releases/<git-sha>/
  shared/.env
  shared/storage/
  current -> releases/<git-sha>
```

The domain document root points to `/home/account/spinellanka/current/public`. Shared environment and persistent storage are linked into each release. Keep at least the current and previous successful release for rollback.

The exact cPanel home path and PHP CLI binary are hosting-owned values recorded in deployment secrets/configuration after the readiness check; they are not committed.

## CI pipeline

GitHub Actions runs on pull requests and the protected main branch:

1. install Composer dependencies from the lock file;
2. run Pint in check mode;
3. run Larastan/PHPStan;
4. run Pest unit/feature/integration tests with a disposable database;
5. build frontend assets using Node 26 LTS and the locked package graph;
6. run critical Playwright journeys against the built test app;
7. run Composer/npm dependency audits;
8. package production PHP dependencies and compiled assets into an immutable release artifact.

Production does not run Composer or Node during normal deployment. This removes build-tool and memory assumptions from shared hosting.

## Deployment

Preferred deployment uses GitHub Actions with a dedicated restricted SSH key. If SSH is unavailable but SFTP is reliable, upload the same immutable release artifact and use a narrowly scoped cPanel deployment action to activate it.

Activation sequence:

1. upload and verify release artifact checksum;
2. link shared environment/storage;
3. enable maintenance mode only when a migration requires it;
4. run backward-compatible database migrations;
5. clear stale caches and run Laravel production optimization;
6. switch `current` to the new release atomically;
7. verify `/up`, home, one public service page, staff login shell, and scheduler/queue status;
8. disable maintenance mode;
9. retain the previous release until the observation window passes.

Use a database advisory/deployment lock so two workflows cannot deploy concurrently.

## Cron and queues

cPanel cron invokes:

- Laravel Scheduler every minute;
- a non-overlapping database queue worker that drains jobs and exits within the minute;
- daily backup verification/retention through the scheduler;
- periodic cleanup of expired exports, holds, tokens, and temporary files through the scheduler.

The hosting owner supplies the absolute PHP binary and application path because cPanel requires absolute command paths. Cron email is disabled only after command output is redirected to application logs and failure alerts are confirmed.

Jobs are idempotent and short. Long-running media processing or provider bulk work is not accepted on shared cPanel; if measured workload exceeds short-lived cron execution, move workers to a suitable process-managed host or queue service.

## Production configuration

Required secret/configuration groups:

- application URL, environment, key, debug=false, timezone;
- database host/name/user/password;
- database queue, cache, and session stores;
- mail transport and sender identity;
- WhatsApp business account/API credentials;
- map/place/route credentials and allowed origins;
- payment merchant/webhook credentials and settlement settings;
- storage/private-file configuration;
- error-reporting DSN if approved;
- trusted proxies/hosts and support contacts.

Secrets are stored in cPanel/environment or GitHub encrypted secrets, never in repository files or deployment logs.

## Backups

- Nightly database backup with encrypted off-host copy.
- Daily private upload/document backup according to retention policy.
- Weekly full release/shared-data snapshot where hosting supports it.
- Backup job success/failure alert to the operations owner.
- Monthly automated integrity check.
- Quarterly restore rehearsal into a non-production environment.

A backup is not accepted until a restore has been demonstrated.

## Monitoring and alerting

Launch baseline:

- Laravel `/up` uptime check every five minutes;
- application exception reporting to an approved error service;
- daily rotated application logs protected from public access;
- dashboard visibility for failed/aging queue jobs, failed critical messages, stale pending payments, and backup status;
- SSL expiry, disk/inode usage, PHP errors, and database availability alerts;
- no Telescope, debug toolbar, or verbose exception page exposed in production.

Laravel Pulse/Telescope may run only in restricted non-production environments or after resource impact is measured. Horizon is not installed without Redis and persistent workers.

## Rollback

Application rollback switches `current` to the previous release and rebuilds caches. Migrations follow expand/contract discipline so the previous release can run against the new schema during the observation window. Destructive schema cleanup is a later release after rollback is no longer required.

If a migration corrupts or destroys data, application rollback is insufficient; restore from verified backup and reconcile accepted bookings/payments from audit/provider evidence under an incident procedure.

## Go-live checklist

- Hosting gate passed and recorded.
- DNS/SSL and canonical URLs verified.
- Production `.env` outside public root; debug disabled.
- Database migrations and seed/config import rehearsed.
- Cron scheduler and queue drain observed executing.
- Backup and restore demonstrated.
- Public, guest, driver, payment-return, and admin routes protected as designed.
- Staff accounts and MFA enrolled; default/test accounts removed.
- Provider webhook signatures and production origins verified.
- Client-supplied fleet, rates, policies, legal copy, support hours, and imagery approved.
- Smoke tests, accessibility checks, performance budget, and security review passed.
- Rollback owner and launch communication channel named.
