# Design Document: Phase 02 — Staff Identity, Access, and Audit

* **Author:** Lead Infrastructure & Security Engineer
* **Date:** 2026-11-23
* **Status:** Resolved — Pending Project Lead Approval
* **Target Release:** Phase 02 (`phase/02-identity-access-audit`)
* **Review Protocol:** Full Elephant-Goldfish Model (EGM) Workflow (Brainstorm -> Draft -> Goldfish Review -> Resolved)

---

## 1. Problem Statement and Architectural Overview

### 1.1 The Problem
Spinellanka is an advance-booking luxury transport platform operating a five-vehicle fleet in Sri Lanka. Operations involve sensitive traveler information (flight numbers, hotel destinations, contact details, payment transaction records, passport inspection results, driver assignments, and vehicle condition evidence).

The system runs on shared Linux cPanel hosting with a single transactional database (MariaDB 10.11+ / MySQL 8.0) and PHP 8.4/8.3, without an external identity provider or resident daemon workers. To satisfy OWASP least-privilege standards and strict regulatory compliance:
1. Operations must be housed in an authenticated staff shell (`/admin`) using Filament 5 and Laravel 13.
2. Individual accountability is mandatory: no shared accounts or default production passwords are permitted.
3. Multi-Factor Authentication (TOTP MFA) is non-negotiable before any staff member can view or mutate operational data.
4. Authorization must be capability-based using granular permissions (`spatie/laravel-permission`) enforced at the HTTP, route, policy, Filament resource, and query levels.
5. Navigation visibility is not authorization. Hiding UI elements must never substitute for server-side policy and query-scope enforcement.
6. Sensitive actions (role elevation, user deactivation, customer/financial exports, large refunds, policy adjustments, session revocation) require recent password confirmation (re-authentication).
7. An append-only audit trail must record every security, authentication, and administrative event.
8. First-owner provisioning must be reproducible, automated, and secure via CLI without committing default credentials.

### 1.2 Architectural Overview
This resolved design establishes a robust, hardened identity, access, and audit architecture:
- **Filament 5 Staff Shell (`AdminPanelProvider`)**: Bound to `/admin`, requiring active status (`is_active = true`), role membership (`Owner/Admin` or `Staff`), and mandatory TOTP MFA (`AppAuthentication`) with a tight 90-second drift window (`codeWindow(1)`) and single-use recovery codes.
- **Granular Capability Matrix**: 20 canonical permissions defined as PHP backed enums (`App\Domain\Identity\Permission`). Two roles: `Owner/Admin` (all 20 permissions) and `Staff` (8 operational permissions).
- **Bounded Super-Admin Gate**: In `AppServiceProvider`, `Gate::before()` grants abilities to `Owner/Admin` but **strictly defers** on `AuditEvent` and last-owner checks, preserving audit immutability and preventing accidental administrative lockout.
- **Session Lifecycle & Resurrection Prevention**: Database-backed sessions with 30-minute idle expiration (`SESSION_LIFETIME=30`). Sessions rotate on login and MFA verification. Remember-me cookies are explicitly disabled on the staff panel. A `token_version` column on `users` checked by `EnsureActiveStaff` middleware guarantees that deactivated users and revoked sessions are blocked immediately across in-flight and concurrent requests.
- **Sensitive-Action Re-Authentication Boundary**: Standardized 15-minute re-auth timeout (`config('auth.password_timeout', 900)`). A reusable `RequiresReauthentication` trait prompts for password confirmation in Filament actions and routes. Tier-1 ultra-sensitive actions immediately consume the confirmation timestamp.
- **Append-Only Forensic Audit Trail**: Dedicated `audit_events` schema with `restrictOnDelete()` on `actor_id` and denormalized `actor_name` and `actor_email` snapshots. Models enforce immutability (`public $timestamps = false;` and Eloquent update/delete hooks). Policy denies updates and deletions unconditionally.
- **First-Owner Provisioning (`php artisan staff:create-owner`)**: Zero-default CLI tool with interactive password prompting or cryptographically secure password generation (`--generate-password`).
- **Security Headers & Noindex**: All `/admin` routes enforce `X-Robots-Tag: noindex, nofollow, noarchive`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and strict CSP headers.

---

## 2. Concrete Security Matrix

### 2.1 Canonical Permission Keys

All permissions are string constants defined in `App\Domain\Identity\Permission`:

| # | Permission Key | Canonical Name | Description | Default: Owner/Admin | Default: Staff |
|:---:|:---|:---|:---|:---:|:---:|
| 1 | `users.view` | View Staff Accounts | List staff users and view user profiles | **YES** | NO |
| 2 | `users.manage` | Manage Staff Accounts | Create, update, activate/deactivate staff, revoke sessions | **YES** | NO |
| 3 | `roles.manage` | Manage Roles & Permissions | Create, edit, and assign roles and capability permissions | **YES** | NO |
| 4 | `audit.view` | View Audit Log | View append-only audit event history | **YES** | **YES** |
| 5 | `audit.export` | Export Audit Log | Export audit event history to CSV/JSON | **YES** | NO |
| 6 | `fleet.view` | View Fleet | View vehicle records, maintenance state, driver ties | **YES** | **YES** |
| 7 | `fleet.manage` | Manage Fleet | Create/edit vehicles, assign drivers, set turnaround/buffers | **YES** | NO |
| 8 | `booking.view` | View Bookings | View booking list, details, contact snapshots | **YES** | **YES** |
| 9 | `booking.review` | Review Requests | Review submissions, draft and send proposals | **YES** | **YES** |
| 10 | `booking.confirm` | Confirm Bookings | Transition booking to confirmed after payment/terms | **YES** | **YES** |
| 11 | `booking.override_conflict` | Authorize Conflict Override | Authorize schedule overlap, buffer reduction, manual override | **YES** | NO |
| 12 | `booking.cancel` | Cancel Bookings | Cancel active or confirmed bookings | **YES** | NO |
| 13 | `payment.view` | View Payments | View transaction ledger, balances, and receipts | **YES** | **YES** |
| 14 | `payment.record_offline` | Record Offline Payment | Log cash or bank transfer payment collection | **YES** | **YES** |
| 15 | `payment.refund` | Authorize Refund | Issue or approve refund transactions | **YES** | NO |
| 16 | `incident.view` | View Incidents | Inspect vehicle damage/condition reports and disputes | **YES** | **YES** |
| 17 | `incident.manage` | Manage Incidents | Log, update, and resolve incident records | **YES** | NO |
| 18 | `export.customer_data` | Export Traveler Data | Export traveler contact and booking histories | **YES** | NO |
| 19 | `export.financial_data` | Export Financial Ledger | Export transactions, revenue, and settlement data | **YES** | NO |
| 20 | `config.manage` | Manage Settings & Policies | Update exchange rates, policy texts, and retention settings | **YES** | NO |

### 2.2 Roles and Default Grants

1. **Role: `Owner/Admin`**:
   - Represents the business owner and designated technical administrators.
   - Granted all 20 canonical permissions explicitly via `RoleAndPermissionSeeder`.
   - Sole role authorized to manage users, roles, system configuration, data exports, refunds, and conflict overrides.
2. **Role: `Staff`**:
   - Represents operational team members handling inquiries, customer reviews, dispatch, and handovers.
   - Granted 8 least-privilege operational permissions:
     `audit.view`, `fleet.view`, `booking.view`, `booking.review`, `booking.confirm`, `payment.view`, `payment.record_offline`, `incident.view`.
   - Explicitly lacks administrative, configuration, refund, conflict override, user management, and data export capabilities.

### 2.3 Policy & Query Boundary Matrix

| Resource / Scope | Policy Method | Required Permission / Condition | Query Scope Enforcement |
|:---|:---|:---|:---|
| **User** | `viewAny` | `users.view` | Filtered to staff records |
| **User** | `view` | `users.view` OR `$user->id === $target->id` (self profile) | Single record retrieval |
| **User** | `create` | `users.manage` | N/A |
| **User** | `update` | `users.manage` | Protected: cannot alter role without `roles.manage` |
| **User** | `deactivate` | `users.manage` AND `$user->id !== $target->id` AND NOT last active owner | Protected: prevents self-lockout |
| **User** | `delete` | **FORBIDDEN (Always returns `false`)** | Staff accounts are deactivated, never deleted |
| **Role** | `viewAny` / `view` | `roles.manage` | Read-only listing for authorized administrators |
| **Role** | `update` | `roles.manage` | Core roles (`Owner/Admin`, `Staff`) cannot be renamed |
| **AuditEvent** | `viewAny` / `view`| `audit.view` | Paginated append-only query |
| **AuditEvent** | `create` | Authorized internally via `AuditService` | System/Service boundary |
| **AuditEvent** | `update` / `delete` | **FORBIDDEN (Always returns `false` for ALL roles)** | Absolute immutability |

---

## 3. Session, Authentication, and MFA Architecture

### 3.1 Idle, Session, and Re-Authentication Invariants

| Security Parameter | Approved Value | Enforcement Mechanism |
|:---|:---:|:---|
| **Session Lifetime (Idle)** | 30 minutes | `SESSION_LIFETIME=30` in `config/session.php` |
| **Session Driver** | `database` | `sessions` table queried and indexed on `user_id` |
| **Session Encryption** | Enabled | `SESSION_ENCRYPT=true` |
| **Cookie Security** | `HttpOnly`, `SameSite=Lax`, `Secure` | Configured in `config/session.php` |
| **Remember-Me Cookie** | **Disabled** on Staff Panel | `$panel->login(Login::class)->rememberMe(false)` |
| **TOTP Drift Window** | 1 timestep (90s total window) | `AppAuthentication::make()->codeWindow(1)->recoverable()` |
| **Login Rate Limiting** | 5 attempts / minute per `email\|ip` | Filament `Timebox` and `WithRateLimiting` |
| **Re-Authentication Timeout** | 15 minutes (900 seconds) | `config('auth.password_timeout', 900)` |
| **Tier-1 Action Re-Auth** | **Single-Use** | Timestamp consumed immediately upon action execution |

### 3.2 Session Revocation & Concurrency Synchronization
1. **Schema Column**: `users.token_version` (integer, default 1).
2. **Login Hook**: Upon authentication and MFA verification, `session(['token_version' => $user->token_version])`.
3. **`EnsureActiveStaff` Middleware**:
   Executed on every authenticated panel request:
   - Validates `$user->is_active === true`.
   - Validates `session('token_version') === $user->token_version`.
   - If either check fails:
     ```php
     Auth::guard('web')->logout();
     $request->session()->invalidate();
     $request->session()->regenerateToken();
     abort(403, 'Your account has been deactivated or your session has been revoked.');
     ```
4. **Revocation Execution (`SessionRevocationService`)**:
   - Increments `$user->increment('token_version')`.
   - Deletes all records from `sessions` where `user_id = $user->id`.
   - This invalidates both database rows and any in-flight requests before their session handler writes.

### 3.3 Sensitive-Action Re-Authentication Boundary
The reusable `RequiresReauthentication` trait provides:
- Check via `ReauthService::isRecentlyConfirmed()`.
- If expired (> 15 min), renders a modal form requesting the staff user's password (`currentPassword()` rule).
- Upon confirmation, writes timestamp to `auth.password_confirmed_at` and audits `auth.reauthenticated`.
- For Tier-1 ultra-sensitive actions (deactivating users, role changes, bulk exports), the timestamp is forgotten immediately after execution: `session()->forget('auth.password_confirmed_at')`.

---

## 4. Append-Only Audit Logging Foundation

### 4.1 Schema (`audit_events` table)
```php
Schema::create('audit_events', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
    $table->string('actor_name', 128)->nullable();     // Immutable forensic snapshot
    $table->string('actor_email', 191)->nullable();    // Immutable forensic snapshot
    $table->string('actor_type', 32)->default('staff'); // staff, system, traveler, driver
    $table->string('action', 64);                       // e.g. auth.login, user.deactivated
    $table->nullableMorphs('auditable');                // auditable_type, auditable_id
    $table->string('summary', 255);                     // Human-readable summary
    $table->json('metadata')->nullable();               // Structured context (diffs, IP, reason)
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent();
    
    $table->index(['created_at', 'action']);
});
```

### 4.2 Multi-Layer Immutability Guarantees
1. **Model Layer**: `App\Models\AuditEvent` declares `public $timestamps = false;` and binds `static::updating()` and `static::deleting()` hooks that throw `ImmutableAuditLogException`.
2. **Policy Layer**: `AuditEventPolicy::update()` and `AuditEventPolicy::delete()` return `false` unconditionally.
3. **Gate Layer**: `Gate::before()` explicitly falls through (returns `null`) for `AuditEvent` so administrators cannot bypass policy.
4. **Database Trigger Layer**: Migration executes MariaDB/MySQL triggers raising error `45000` on any raw SQL `UPDATE` or `DELETE`.

### 4.3 Sanitization & Forensic PII Scrubbing
In `AuditService::record()`:
- Passwords, credit cards, recovery codes, and tokens are recursively stripped.
- For `auth.failed` events, the input is only stored as `attempted_email` if it passes `filter_var($input, FILTER_VALIDATE_EMAIL)` to prevent accidentally logging passwords typed into the username field.

---

## 5. First-Owner Provisioning Runbook

### 5.1 CLI Command: `php artisan staff:create-owner`
- Command options: `--name=`, `--email=`, `--generate-password`, `--force`.
- Plaintext password argument is **prohibited** (prevents leakage into `ps aux` and shell history).
- If `--generate-password` is passed, generates a 24-character high-entropy password using `Str::password(24)` and outputs it once to stdout.
- Otherwise, prompts securely via `$this->secret('Enter owner password')`.
- Password validation: `Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()`.
- Assigns role `Owner/Admin`.
- Logs `actor_type = 'system'`, `action = 'user.created'` in `audit_events`.
- Guarantees zero committed production passwords in git, `.env`, or migrations.

---

## 6. Testing Strategy & Required Proof

| Test Suite | File Path | Focus & Assertions |
|:---|:---|:---|
| **MFA Authentication** | `tests/Feature/Identity/MfaAuthenticationTest.php` | Active staff login prompts for TOTP; valid code authenticates; invalid/expired code rejected; inactive account denied. |
| **Session Lifecycle** | `tests/Feature/Identity/SessionLifecycleTest.php` | Session rotates on login; idle timeout triggers after 30 min; `revokeUserSessions()` clears DB rows; deactivated user rejected mid-session. |
| **Authorization & Roles**| `tests/Feature/Identity/AuthorizationPolicyTest.php` | `Owner/Admin` has 20 permissions; `Staff` has 8 permissions; direct route/action access to `/admin/users` denied for Staff; navigation hiding verified not sole defense. |
| **Re-Authentication** | `tests/Feature/Identity/ReauthenticationTest.php` | Sensitive action prompts password; valid password confirms session; 15-minute expiry verified; single-use token consumed on Tier-1 action. |
| **Audit Immutability** | `tests/Feature/Audit/AuditImmutabilityTest.php` | `AuditEvent` model update/delete throws exception; `Gate::authorize` rejects owner delete; DB trigger prevents raw SQL update/delete; PII scrubbed. |
| **Security Headers** | `tests/Feature/Identity/StaffSecurityHeadersTest.php` | All `/admin` routes return `X-Robots-Tag: noindex, nofollow`, `X-Frame-Options: DENY`, `nosniff`, and restrictive referrer policy. |
| **First-Owner CLI** | `tests/Feature/Identity/CreateOwnerCommandTest.php` | CLI creates owner with secure password; duplicate email rejected; audit event logged; no password leaked in logs. |

