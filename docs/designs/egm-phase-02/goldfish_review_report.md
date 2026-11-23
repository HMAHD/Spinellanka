# EGM Goldfish Review Synthesis: Phase 02 — Staff Identity, Access, and Audit

**Target Phase:** Phase 02 (`phase/02-identity-access-audit`)  
**Date:** 2026-11-23  
**Reviewers:**
- `egm-design` (Hostile Design Critic)
- `egm-implementation-readiness` (Pragmatic Tech Lead)
- `egm-codebase-details` (Pedantic Technical Quality Auditor)
**Status:** Synthesized & Resolved  

---

## 1. Executive Summary & Consensus

All three specialist reviewers converged on key architectural vulnerabilities, integration gaps, and operational risks in the preliminary draft design. The design concept (mandatory TOTP MFA, granular capability permissions, zero default credentials, and append-only audit logging) is strongly validated, but the technical execution required 12 specific structural corrections to be production-hardened and implementation-ready.

---

## 2. Synthesized Priority Findings & Resolutions

### Finding 1 (Blocker): `Gate::before` Super-Admin Bypass Neutralizes Audit Immutability & Last-Owner Guards
- **Reported by:** `egm-design` (CRIT-01), `egm-implementation-readiness` (P0-2), `egm-codebase-details` (Finding 7)
- **Problem:** Returning an unconditional `true` in `Gate::before()` for `Owner/Admin` bypasses all policy checks. Filament would render "Edit" and "Delete" buttons for audit events, and an administrator could bypass last-owner and immutability checks.
- **Resolution:**
  1. Synchronize all 20 canonical permissions explicitly to the `Owner/Admin` role in the seeder.
  2. In `AppServiceProvider::boot()`, implement a bounded `Gate::before()` callback that explicitly defers to policies for `AuditEvent`:
     ```php
     Gate::before(function (User $user, string $ability, array $arguments = []) {
         $target = $arguments[0] ?? null;
         if ($target === AuditEvent::class || $target instanceof AuditEvent) {
             return null; // Defer explicitly to AuditEventPolicy
         }
         return $user->hasRole('Owner/Admin') ? true : null;
     });
     ```
  3. `AuditEventPolicy::update()` and `AuditEventPolicy::delete()` return `false` unconditionally.

### Finding 2 (Blocker): Audit Schema `nullOnDelete()` Wipes Forensic Attribution & Violates Immutability
- **Reported by:** `egm-design` (CRIT-02)
- **Problem:** `foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()` automatically updates `audit_events` rows when a user is deleted, mutating an append-only table and stripping historical accountability.
- **Resolution:**
  1. Forbid hard deletion of staff users: accounts can only be deactivated (`is_active = false`). `UserPolicy::delete()` returns `false` for all staff accounts.
  2. Set `foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete()`.
  3. Denormalize immutable forensic attributes `actor_email` and `actor_name` directly into the `audit_events` row so historical attribution remains intact even if user records change.

### Finding 3 (Blocker): Missing MFA Columns on `users` Table
- **Reported by:** `egm-codebase-details` (Finding 1), `egm-implementation-readiness` (P0-1)
- **Problem:** Filament 5's `InteractsWithAppAuthentication` and `InteractsWithAppAuthenticationRecovery` expect `app_authentication_secret` and `app_authentication_recovery_codes` on the `users` table. Without them, TOTP enrollment crashes with SQL column errors.
- **Resolution:**
  Add migration `add_app_authentication_columns_to_users_table.php` adding nullable `app_authentication_secret` (text) and `app_authentication_recovery_codes` (text) after `remember_token`. Exclude them from `$fillable`.

### Finding 4 (Blocker): Spatie Permission Schema & Configuration Not Published
- **Reported by:** `egm-codebase-details` (Finding 3), `egm-implementation-readiness` (P0-4)
- **Problem:** `spatie/laravel-permission: ^7.0` is required in `composer.json`, but `config/permission.php` and permission table migrations are absent from the repository.
- **Resolution:**
  Publish Spatie configuration and migration, create deterministic `RoleAndPermissionSeeder` that seeds all 20 canonical permissions, syncs all 20 to `Owner/Admin`, and syncs 8 operational permissions to `Staff`.

### Finding 5 (Blocker): Eloquent `AuditEvent` Timestamp Collision
- **Reported by:** `egm-implementation-readiness` (P0-3), `egm-codebase-details` (Finding 6)
- **Problem:** The `audit_events` table omits `updated_at` to enforce immutability. Default Eloquent models attempt to insert `updated_at`, triggering SQL exceptions.
- **Resolution:**
  In `App\Models\AuditEvent`, explicitly set `public $timestamps = false;` or `const UPDATED_AT = null;`.

### Finding 6 (Major): In-Flight Session Resurrection Race Condition During User Deactivation
- **Reported by:** `egm-design` (CRIT-03)
- **Problem:** If an active user's request is in flight when an admin deactivates them, the in-flight request's `DatabaseSessionHandler::write()` will re-insert the session record into MariaDB upon termination, resurrecting the session.
- **Resolution:**
  1. Add `token_version` (integer, default 1) to `users`. Store `session('token_version')` upon login.
  2. Implement `EnsureActiveStaff` middleware in `$panel->authMiddleware()` that executes on every request:
     ```php
     if (! $user->is_active || session('token_version') !== $user->token_version) {
         Auth::guard('web')->logout();
         $request->session()->invalidate();
         $request->session()->regenerateToken();
         abort(403, 'Your account has been deactivated or your session has been revoked.');
     }
     ```
  3. Revoking sessions increments `users.token_version` and purges the `sessions` table.

### Finding 7 (Major): Re-Authentication Mechanism Specification & Window Drift
- **Reported by:** `egm-design` (CRIT-05), `egm-implementation-readiness` (P1-2), `egm-codebase-details` (Finding 4)
- **Problem:**
  - `config/auth.php` defaults to 10800s (3 hours), contradicting the 15-minute plan.
  - Filament's `$action->requiresConfirmation()` is a generic dialog, not a password prompt.
  - A global 15-minute window allows unrestricted escalation for session hijackers.
- **Resolution:**
  1. Set `AUTH_PASSWORD_TIMEOUT=900` in `config/auth.php` and `.env.example`.
  2. Implement reusable `RequiresReauthentication` trait and `ReauthService` mounting an explicit password input modal (`TextInput::make('reauth_password')->password()->required()->currentPassword()`).
  3. Ultra-sensitive actions (deactivating users, role changes, bulk exports) immediately consume the re-auth timestamp (`session()->forget('auth.password_confirmed_at')`).

### Finding 8 (Major): TOTP MFA Drift Window & Replay Suppression
- **Reported by:** `egm-design` (CRIT-06)
- **Problem:** `codeWindow(4)` allows a 4.5-minute validity window, creating a wide window for token interception and replay.
- **Resolution:**
  Reduce drift window to `codeWindow(1)` (1 past timestep, 1 future timestep = 90 seconds total). Chain `->recoverable()` to support secure single-use recovery codes.

### Finding 9 (Major): Remember-Me Bypass Risk on Admin Panel
- **Reported by:** `egm-design` (CRIT-07)
- **Problem:** Persistent remember-me cookies allow users to bypass the TOTP MFA challenge after idle session expiration.
- **Resolution:**
  Explicitly disable remember-me on the admin panel: `$panel->login(Login::class)->rememberMe(false)`.

### Finding 10 (Major): First-Owner Provisioning Security Vulnerability
- **Reported by:** `egm-implementation-readiness` (P1-4)
- **Problem:** Accepting `--password` in CLI arguments leaks plaintext passwords to process tables (`ps aux`) and shell history.
- **Resolution:**
  Disallow `--password` CLI flag. Prompt securely via `$this->secret('Enter owner password')` or use `--generate-password` to output a 24-character cryptographically secure password to stdout once.

### Finding 11 (Major): cPanel Buffer Pool & Scaling Starvation
- **Reported by:** `egm-design` (CRIT-08), `egm-codebase-details` (Finding 9)
- **Problem:** Default Filament `COUNT(*)` pagination on `audit_events` causes heavy table scans on shared MariaDB instances.
- **Resolution:**
  Configure `simplePagination()` on `AuditEventResource`. Ensure exports use chunked streaming with `LazyCollection`. Optimize database index to single composite `(created_at, action)`.

### Finding 12 (Major): Self-Deactivation & Last Active Owner Lockout
- **Reported by:** `egm-design` (CRIT-09), `egm-implementation-readiness` (P1-3)
- **Problem:** Lack of guardrails allows the sole active owner to accidentally deactivate their own account or revoke their `Owner/Admin` role, causing total system lockout.
- **Resolution:**
  In `UserPolicy` and `UserService`, enforce that an owner cannot deactivate themselves or remove their `Owner/Admin` role if they are the last active owner.

---

## 3. Review Sign-Off Matrix

| Reviewer Persona | Initial Status | Resolved Status | Sign-off Notes |
|:---|:---:|:---:|:---|
| **Hostile Design Critic** (`egm-design`) | Blocked | **Approved** | All 10 design critique items resolved (bounded `Gate::before`, `restrictOnDelete`, database triggers, token versioning, single-use re-auth, `codeWindow(1)`). |
| **Pragmatic Tech Lead** (`egm-implementation-readiness`) | Conditional Pass | **Approved** | Implementation gaps closed (MFA migration, Spatie seeder, `RequiresReauthentication` trait, `public $timestamps = false`). |
| **Pedantic Quality Auditor** (`egm-codebase-details`) | Blocked | **Approved** | Codebase contracts aligned (MFA columns, `User` interfaces/traits, `auth.php` 900s timeout, provider registration). |

---

All findings are incorporated into the formal architecture document: [docs/designs/phase-02-identity-access-audit.md](file:///Users/akash/Documents/Spinellanka/docs/designs/phase-02-identity-access-audit.md).

