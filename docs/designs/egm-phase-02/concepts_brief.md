# EGM Concept Brief: Phase 02 — Staff Identity, Access, and Audit

**Target Phase:** Phase 02 (`phase/02-identity-access-audit`)  
**Author:** Lead Infrastructure & Security Engineer  
**Date:** 2026-11-23  
**Status:** In Review (EGM Brainstorm -> Draft)  

---

## 1. Problem Statement and High-Level Vision

Spinellanka operates a five-vehicle luxury fleet for international travelers in Sri Lanka. Operations involve sensitive traveler flight details, hotel routes, passport inspection records, cash/credit card payments, vehicle handovers, and permanent driver schedules.

To protect this domain on shared cPanel hosting without an external identity provider:
1. Operations must be hosted behind an authenticated staff shell (`/admin`) powered by Filament 5 and Laravel 13.
2. All administrative accounts must be individual; shared credentials or default production passwords are strictly prohibited.
3. Multi-Factor Authentication (TOTP MFA) is mandatory before any operations data can be viewed or mutated.
4. Authorization must be capability-based using granular permissions (`spatie/laravel-permission`) enforced at the HTTP, route, policy, Filament resource, and query levels.
5. Navigation visibility is not authorization. Hiding a sidebar link must never substitute for server-side policy enforcement.
6. Highly sensitive operations (role elevation, user deactivation, customer export, large refunds, policy changes, session revocation) require recent re-authentication.
7. An immutable, append-only audit trail must record every security, authentication, and administrative change.
8. First-owner setup must be reproducible and secure via CLI without committing default passwords to code or migrations.

---

## 2. Five Goldfish Lenses

### Lens 1: Codebase Details (Pedantic Auditor)
- **Framework & Package Alignment**: Verify compatibility with Laravel 13.17+, Filament 5.0+, and Spatie Permission 7.0+.
- **Existing User Model & Migration**: `User` model currently uses `HasFactory, HasRoles, Notifiable` with `is_active` boolean and casts. Must implement `FilamentUser` (`canAccessPanel`), `HasAppAuthentication` (Filament TOTP MFA), and session management.
- **Database Session Store**: Configured in `config/session.php` with table `sessions` created in `0001_01_01_000000_create_users_table.php`. Enables immediate database-level session revocation (`user_id`).
- **No Extra Dependencies**: Use first-party Laravel 13 and Filament 5 features for MFA and password confirmation. Do not introduce third-party 2FA or audit packages that own opaque schemas.

### Lens 2: System Architecture & Economics (Hostile Critic)
- **Shared cPanel Constraints**: No resident Redis or Daemon. Sessions, permissions, and audit logs share the single transactional database (`MariaDB 10.11+`/`MySQL 8.0` in production, `SQLite` in local/testing).
- **Index Hygiene**: Ensure `audit_events` has composite indexes on `(created_at, action)` and `(auditable_type, auditable_id)`. Ensure `sessions` index on `user_id` is utilized for fast revocation.
- **Fail-Closed Design**: If a user is deactivated (`is_active = false`), their session is terminated and subsequent requests are rejected immediately across all guards.
- **Noindex & Header Hardening**: Staff routes must return `X-Robots-Tag: noindex, nofollow`, strict CSP, nosniff, frame denial, and tight referrer policy.

### Lens 3: Implementation Readiness (Pragmatic Tech Lead)
- **Concrete Permission Vocabulary**: Exactly define 20 canonical permissions covering users, roles, audit, fleet, bookings, payments, incidents, exports, and config.
- **Thin Filament Classes**: Filament Resources/Pages delegate security, audit logging, and lifecycle decisions to dedicated domain services (`UserService`, `AuditService`, `SessionRevocationService`).
- **Policy Enforcement**: Policies defined for `User`, `Role`, `AuditEvent`. Explicit `before()` super-admin gate in `AppServiceProvider`.
- **First-Owner CLI**: Interactive, zero-default artisan command `staff:create-owner` prompting for email and password securely with entropy validation.

### Lens 4: Naive Reader Comprehension
- Clearly document the difference between Traveler (no account, signed private links), Driver (high-entropy trip token), and Staff (authenticated, MFA-enforced individual account).
- Explicitly explain the session lifecycle: login -> MFA challenge -> active session -> idle timeout (30 min) -> re-auth challenge (15 min window for sensitive actions) -> revocation/logout.

### Lens 5: Technical Copy & Style
- Objective, crisp, precise specification. No fluff, sycophancy, or vague statements.
- Direct mapping of requirements to verification tests.

---

## 3. Goals and Non-Goals

### Goals
1. Configure Filament 5 Staff Panel with mandatory TOTP MFA, custom login page enforcing `is_active` validation, and neutral failure responses.
2. Define canonical capability permissions and two foundational roles: `Owner/Admin` (full capabilities) and `Staff` (operational least privilege).
3. Implement Laravel Policies and query scopes for User, Role, and Audit administration.
4. Build a reusable `ReauthenticatesAction` security boundary enforcing password confirmation within 15 minutes for sensitive actions.
5. Implement immediate session revocation across all active devices for a given user.
6. Create an append-only `audit_events` schema and service with immutable Eloquent hooks.
7. Provide `staff:create-owner` Artisan command for zero-credential first-owner onboarding.
8. Enforce security headers (`X-Robots-Tag: noindex, nofollow`, `X-Frame-Options: DENY`, etc.) across all staff routes.
9. Deliver comprehensive automated tests for MFA, throttling, inactivation, session rotation, policy denial, direct route probing, and audit capture.

### Non-Goals
1. Customer accounts or traveler registration (unaccounted private booking links only).
2. Driver accounts (Phase 14 token links only).
3. Core booking workflow, catalog CRUD, pricing, or payment gateway integration (Phases 03-13).
4. Shared admin credentials or committed production seeds.
5. Third-party SaaS identity providers (Auth0, Okta, etc.).

---

## 4. Key Architectural Alternatives Weighed

| Decision Area | Selected Option | Rejected Alternative | Reason for Rejection |
|:---|:---|:---|:---|
| **MFA Engine** | Filament 5 Native App Authentication (`AppAuthentication`) | Custom TOTP implementation (`pragmarx/google2fa`) or Fortify | Filament 5 provides battle-tested TOTP QR generation, window drift control, and recovery codes integrated directly into the panel lifecycle without extraneous dependencies. |
| **Permission Engine** | `spatie/laravel-permission` with Enum keys & Gate intercept | Custom role enum column or bespoke ACL table | `spatie/laravel-permission` is approved in stack plan (`01-technology-stack.md`), integrates natively with Laravel policies and Filament, and provides cached capability checks. |
| **Re-Authentication** | Laravel native `password.confirm` session timestamp (`auth.password_timeout`) | Custom TOTP-on-every-action prompt | Password confirmation is native, standard, and supported by Laravel sessions; prompting TOTP repeatedly creates severe operational fatigue without added security over an already-authenticated MFA session. |
| **Audit Storage** | Dedicated `audit_events` table with immutable model hooks | Third-party activity-log package (e.g. `spatie/laravel-activitylog`) | Keeps schema lightweight, custom-tailored to Spinellanka's security plan, avoids unneeded polymorphic bloat, and guarantees true append-only immutability. |
| **Session Revocation** | Direct deletion of database `sessions` table records | Cache-based session blacklist | The application already uses database sessions (`SESSION_DRIVER=database`). Deleting session records is atomic, transactional, and does not require Redis or in-memory caches. |

