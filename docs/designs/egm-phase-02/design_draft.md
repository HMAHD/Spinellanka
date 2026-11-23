# EGM Draft Design: Phase 02 — Staff Identity, Access, and Audit

**Target Phase:** Phase 02 (`phase/02-identity-access-audit`)  
**Author:** Lead Infrastructure & Security Engineer  
**Date:** 2026-11-23  
**Status:** In Review (EGM Draft -> Goldfish Review)  

---

## 1. Concrete Security Matrix

### 1.1 Canonical Permission Keys

All permissions are string constants defined in a centralized enum `App\Domain\Identity\Permission`:

| Permission Key | Canonical Name | Description | Default: Owner/Admin | Default: Staff |
|:---|:---|:---|:---:|:---:|
| `users.view` | View Staff Accounts | List staff users and view profiles | YES | NO |
| `users.manage` | Manage Staff Accounts | Create, update, activate/deactivate staff, revoke sessions | YES | NO |
| `roles.manage` | Manage Roles & Permissions | Create, edit, and assign roles and capability permissions | YES | NO |
| `audit.view` | View Audit Log | View append-only audit event history | YES | YES |
| `audit.export` | Export Audit Log | Export audit event history to CSV/JSON | YES | NO |
| `fleet.view` | View Fleet | View vehicle records, maintenance state, driver ties | YES | YES |
| `fleet.manage` | Manage Fleet | Create/edit vehicles, assign drivers, set turnaround/buffers | YES | NO |
| `booking.view` | View Bookings | View booking list, details, contact snapshots | YES | YES |
| `booking.review` | Review Requests | Review submissions, draft and send proposals | YES | YES |
| `booking.confirm` | Confirm Bookings | Transition booking to confirmed after payment/terms | YES | YES |
| `booking.override_conflict` | Authorize Conflict Override | Authorize schedule overlap, buffer reduction, manual override | YES | NO |
| `booking.cancel` | Cancel Bookings | Cancel active or confirmed bookings | YES | NO |
| `payment.view` | View Payments | View transaction ledger, balances, and receipts | YES | YES |
| `payment.record_offline` | Record Offline Payment | Log cash or bank transfer payment collection | YES | YES |
| `payment.refund` | Authorize Refund | Issue or approve refund transactions | YES | NO |
| `incident.view` | View Incidents | Inspect vehicle damage/condition reports and disputes | YES | YES |
| `incident.manage` | Manage Incidents | Log, update, and resolve incident records | YES | NO |
| `export.customer_data` | Export Traveler Data | Export traveler contact and booking histories | YES | NO |
| `export.financial_data` | Export Financial Ledger | Export transactions, revenue, and settlement data | YES | NO |
| `config.manage` | Manage Settings & Policies | Update exchange rates, policy texts, and retention settings | YES | NO |

### 1.2 Roles and Default Grants

1. **Role: `Owner/Admin`**:
   - Represents business owners and system administrators.
   - Granted all 20 canonical permissions.
   - Handled cleanly via `Gate::before()` callback in `AppServiceProvider`: users with role `Owner/Admin` are granted all abilities implicitly, while retaining explicit permission records in `role_has_permissions` for auditing.
   - Sole role authorized to manage users, roles, system configuration, data exports, refunds, and conflict overrides.

2. **Role: `Staff`**:
   - Represents operational team members handling inquiries, bookings, and customer communications.
   - Granted 8 operational permissions:
     - `audit.view`
     - `fleet.view`
     - `booking.view`
     - `booking.review`
     - `booking.confirm`
     - `payment.view`
     - `payment.record_offline`
     - `incident.view`
   - Explicitly lacks administrative, configuration, refund, conflict override, user management, and data export privileges.

---

## 2. Authentication, Session, and MFA Architecture

### 2.1 Staff Panel Configuration (`AdminPanelProvider`)
- **Route Path**: `/admin`
- **Panel ID**: `admin`
- **Authentication Guard**: `web`
- **Login Action**: Custom Login page extending Filament's `Login` to enforce:
  1. Active user validation (`is_active === true`). If inactive, fail with generic invalid credential message: *"These credentials do not match our records."*
  2. Rate limiting: 5 failed attempts per minute per `email|ip` via Laravel RateLimiter.
  3. Session regeneration: `$request->session()->regenerate()` upon successful password verification.
- **MFA Configuration**:
  ```php
  $panel->multiFactorAuthentication([
      AppAuthentication::make()
          ->codeWindow(4), // Valid for 2 minutes (standard clock drift window)
  ], isRequired: true);
  ```
- **Panel Access Policy**: `User::canAccessPanel(Panel $panel): bool` returns `true` only if:
  `$this->is_active && ($this->hasRole('Owner/Admin') || $this->hasRole('Staff'))`

### 2.2 Session Lifetime, Idle Policy, and Rotation
- **Idle Timeout**: Set `SESSION_LIFETIME=30` (30 minutes of inactivity) in staff environments.
- **Session Security**: `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE=true` in production.
- **Session Rotation**: Session ID is rotated upon:
  1. Initial password authentication.
  2. MFA code verification.
  3. Password change / credential update.
- **Session Revocation**:
  - `SessionRevocationService::revokeUserSessions(User $user, ?string $exceptSessionId = null)`:
    Deletes all matching rows from the `sessions` table (`user_id = $user->id`).
  - When an admin deactivates an account or clicks "Revoke Sessions", all active sessions are instantly wiped, forcing immediate logout across all devices.

### 2.3 Sensitive-Action Re-Authentication Boundary
- Standard Laravel password confirmation is enforced for sensitive operations:
  1. Role & permission assignments / revocations.
  2. Account deactivation or deletion.
  3. Customer PII and financial exports.
  4. Issuing refunds.
  5. Updating system configuration or exchange rates.
- **Window**: 15 minutes (`config('auth.password_timeout', 900)`).
- **Implementation**:
  - Filament Action modal: Uses `$action->requiresConfirmation()` or a dedicated re-auth modal that verifies `$request->user()->password` and triggers `$request->session()->passwordConfirmed()`.
  - HTTP Routes / Middleware: Standard `password.confirm` middleware.
  - Reusable trait / helper: `ReauthenticatesAction` ensuring consistent UX and audit logging across panels.

---

## 3. Append-Only Audit Logging Foundation

### 3.1 Schema (`audit_events` table)
```php
Schema::create('audit_events', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('actor_type', 32)->default('staff'); // staff, system, traveler, driver
    $table->string('action', 64)->index();               // e.g. auth.login, user.deactivated, role.assigned
    $table->nullableMorphs('auditable');                 // auditable_type, auditable_id
    $table->string('summary', 255);                      // Human-readable summary
    $table->json('metadata')->nullable();                // Structured context (diffs, IP, UA, reason)
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent()->index();
    
    $table->index(['created_at', 'action']);
});
```

### 3.2 Immutability Guarantees
- No `updated_at` column.
- Eloquent Model `App\Models\AuditEvent`:
  ```php
  protected static function booted(): void
  {
      static::updating(function () {
          throw new ImmutableAuditLogException('Audit events cannot be modified.');
      });
      static::deleting(function () {
          throw new ImmutableAuditLogException('Audit events cannot be deleted.');
      });
  }
  ```
- `AuditEventPolicy`:
  - `viewAny()`: authorized if `$user->can('audit.view')`.
  - `view()`: authorized if `$user->can('audit.view')`.
  - `create()`: authorized (used internally by `AuditService`).
  - `update()`: returns `false` unconditionally for ALL roles.
  - `delete()`: returns `false` unconditionally for ALL roles.

### 3.3 Core Audited Events in Phase 02
- `auth.login`: Staff login success (after password + MFA).
- `auth.failed`: Failed login attempt (record IP and email).
- `auth.mfa_enrolled`: User completed initial TOTP setup.
- `auth.logout`: User initiated logout.
- `auth.reauthenticated`: User passed sensitive-action password confirmation.
- `user.created`: New staff account provisioned.
- `user.updated`: Staff details or active status changed.
- `user.deactivated`: Staff account deactivated.
- `session.revoked`: Admin revoked user sessions.
- `role.assigned`: Role assigned to user.
- `role.revoked`: Role revoked from user.

---

## 4. First-Owner Provisioning

### 4.1 CLI Command: `php artisan staff:create-owner`
- Interactive or flag-driven Artisan command (`--name`, `--email`).
- If `--password` is omitted, prompts securely via `$this->secret('Enter owner password')` or generates a cryptographically secure 24-character random password.
- Password complexity validation: minimum 12 characters, mixed case, numbers, symbols.
- Creates `User` with `is_active = true`.
- Assigns role `Owner/Admin`.
- Logs `user.created` and `role.assigned` in `audit_events`.
- Output: Displays credentials once. Informs the owner that TOTP MFA setup will be mandatory upon first login at `/admin/login`.
- **Zero Default Secrets**: No seeders create pre-defined production passwords.

---

## 5. Security Headers and Route Protection

### 5.1 Middleware: `StaffSecurityHeaders`
Applied to all `/admin` routes:
- `X-Robots-Tag`: `noindex, nofollow, noarchive`
- `X-Frame-Options`: `DENY`
- `X-Content-Type-Options`: `nosniff`
- `Referrer-Policy`: `strict-origin-when-cross-origin`
- `Permissions-Policy`: `camera=(), microphone=(), geolocation=(), payment=()`

---

## 6. Implementation Architecture & Layering

```text
app/
├── Domain/
│   └── Identity/
│       ├── Permission.php             (Backed enum of canonical keys)
│       ├── Role.php                   (Constants: Owner/Admin, Staff)
│       └── Services/
│           ├── UserService.php        (Account lifecycle, activation, password updates)
│           ├── SessionService.php     (Revocation, listing active sessions)
│           └── ReauthService.php      (Password confirmation validation)
│   └── Audit/
│       ├── Services/
│       │   └── AuditService.php       (Event creation, PII scrubbing, context capture)
│       └── Exceptions/
│           └── ImmutableAuditLogException.php
├── Http/
│   └── Middleware/
│       ├── StaffSecurityHeaders.php   (Noindex, frame deny, security headers)
│       └── EnsureActiveStaff.php      (Re-checks is_active on every request)
├── Models/
│   ├── User.php                       (Implements FilamentUser, HasAppAuthentication, HasRoles)
│   └── AuditEvent.php                 (Append-only model)
├── Policies/
│   ├── UserPolicy.php
│   ├── RolePolicy.php
│   └── AuditEventPolicy.php
├── Filament/
│   ├── Pages/
│   │   ├── Auth/
│   │   │   └── Login.php              (Custom rate-limited, is_active checking login)
│   │   └── ReauthModal.php            (Reusable confirmation component)
│   └── Resources/
│       ├── UserResource.php           (Staff user administration)
│       └── AuditEventResource.php     (Read-only audit log viewer)
└── Console/
    └── Commands/
        └── CreateOwnerCommand.php     (staff:create-owner CLI)
```

