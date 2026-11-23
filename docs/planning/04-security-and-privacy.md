# 04 — Security and privacy plan

## Security posture

Spinellanka handles travel plans, contact details, payment status, driver assignments, and Rent condition evidence. The launch security baseline is OWASP-oriented, least-privilege, auditable, and designed for a shared cPanel environment without pretending that obscurity or a private URL is authorization.

## Trust boundaries

1. **Public traveler surface:** untrusted input, no account, rate-limited booking and recovery actions.
2. **Private guest surface:** possession of a high-entropy booking token grants narrowly scoped access.
3. **Driver surface:** separate high-entropy, trip-scoped token with less data than the guest page.
4. **Staff surface:** authenticated individual account, MFA, explicit permission checks.
5. **Provider webhooks:** unauthenticated internet traffic until signature, timestamp, replay, and event-ID checks pass.
6. **cPanel/hosting:** privileged infrastructure; access is limited to named maintainers and protected with MFA where offered.

## Staff authentication

- Individual accounts only; no shared administrator credential.
- TOTP MFA required before access to operations data.
- Strong password hashing through Laravel's supported Argon2id/bcrypt configuration.
- Login throttling and neutral recovery responses.
- Session rotation after authentication and privilege change.
- Secure, HTTP-only, SameSite cookies; HTTPS everywhere.
- Configurable idle timeout and immediate account/session revocation.
- Re-authentication before role changes, exports, large refunds, retention changes, or private-link regeneration.

## Authorization

Navigation visibility is not security. Every route, Livewire action, Filament action, queued command, export, and record query applies Laravel policies/permissions.

Owner/Admin and Staff receive the smallest role baseline, with explicit capabilities for:

- conflict/policy override;
- confirm, decline, or cancel;
- payment/refund recording;
- incident access;
- fleet/configuration changes;
- customer/payment export;
- user/role management;
- full audit access.

The 20 canonical capability permissions (`App\Domain\Identity\Permission`):
- Staff Administration: `users.view`, `users.manage`, `roles.manage`
- Audit Foundation: `audit.view`, `audit.export`
- Fleet & Maintenance: `fleet.view`, `fleet.manage`
- Booking Operations: `booking.view`, `booking.review`, `booking.confirm`, `booking.override_conflict`, `booking.cancel`
- Financial Operations: `payment.view`, `payment.record_offline`, `payment.refund`
- Incident Operations: `incident.view`, `incident.manage`
- Data Exports: `export.customer_data`, `export.financial_data`
- System & Policies: `config.manage`

`Owner/Admin` receives all 20 permissions. `Staff` receives 8 operational permissions (`audit.view`, `fleet.view`, `booking.view`, `booking.review`, `booking.confirm`, `payment.view`, `payment.record_offline`, `incident.view`).

Driver and guest links never enter the staff authorization system.

## Private links

- Generate at least 256 bits of cryptographically secure randomness.
- Store only a keyed or password-style hash of the token.
- Bind token to one booking/trip and one purpose.
- Record issue, expiry, revocation, and last-use metadata.
- Rotate after suspected disclosure or sensitive assignment change.
- Do not place PII in the path or token payload.
- Set `Referrer-Policy: no-referrer` on private/driver pages.
- Mark private/admin/driver/payment-return pages `noindex, nofollow`.
- Invalid, expired, and revoked links return the same privacy-safe shell without confirming record existence.

## Input and browser protections

- Server-side validation is authoritative; client validation is usability only.
- Blade escaping remains enabled; any rich text is sanitized against a strict allowlist.
- CSRF protection covers state-changing browser requests.
- File uploads use allowlisted MIME/type/size, random names, private storage, and authorization on download.
- Security headers include HSTS after HTTPS validation, CSP, frame restrictions, nosniff, restrictive referrer policy, and permissions policy.
- Default-deny browser geolocation outside the active Ride surface. On that surface, allow only same-origin top-level use, explain the purpose before the browser prompt, and start the location watch only after an explicit traveler action.
- Ride current-device coordinates remain ephemeral browser memory. Do not send them through Livewire/fetch, map-provider requests, analytics, error reporting, logs, queues, URLs, forms, local/session storage, service workers, or the database. Clear the watch when disabled, hidden/unloaded, or no longer needed.
- Permission denial, unsupported APIs, unavailable/inaccurate fixes, and interruptions keep the selected-destination route and itinerary usable. Never present browser location as vehicle tracking, route-completion proof, emergency monitoring, or an authoritative operational signal.
- CORS is closed because no public cross-origin API exists at launch.
- Rate limits apply to availability search, hold creation, submission, private-link recovery, login, password recovery, and webhooks.
- Bot protection is introduced only if measured abuse appears; accessibility-safe rate limiting and honeypot techniques precede CAPTCHA.

## Database and secrets

- Production `.env` is never committed or placed under the public document root.
- Use a dedicated database user limited to the Spinellanka database.
- Encrypt provider secrets and sensitive application values at rest where Laravel supports it.
- Rotate keys through a documented maintenance process; never casually replace `APP_KEY` because it invalidates encrypted data.
- Disable `APP_DEBUG` in production.
- Prevent web access to application source, storage, backups, logs, Git metadata, Composer files, and environment files.
- Database backups and exported CSVs are encrypted or stored in access-controlled destinations with expiry.

## Payment security

- Use provider-hosted checkout; Spinellanka never accepts or stores raw card number, CVC, or card authentication data.
- Verify webhook signatures using the provider's official method before parsing business meaning.
- Enforce unique provider event and transaction IDs.
- Match amount, currency, booking, proposal version, and merchant account before settlement changes booking state.
- Treat browser return parameters as display context only.
- Log payment transitions and refund approval without logging secrets or prohibited card data.

## Integration and queue security

- Outbound requests use TLS, bounded timeouts, and explicit retry rules.
- Webhook endpoints have body-size limits and replay protection.
- Queue payloads contain identifiers and minimum necessary data, not full customer records.
- Failed jobs are access-controlled and scrubbed of secrets.
- Template variables are allowlisted; internal notes never enter customer messages.

## Privacy and retention

- Collect only information required for fulfillment, safety, payment, legal obligations, and chosen communication.
- Required booking/privacy acceptance is separate from optional marketing consent.
- Do not upload/copy passport or driving-licence documents in phase one; record only the approved inspection result.
- Show staff only the data needed for their role and drivers only the data needed for the current trip.
- Define retention periods with the client's legal owner before production data is accepted.
- Data access, correction, export, anonymization/deletion, and legal hold actions are permissioned and audited.
- Analytics are consent-aware and avoid private booking, admin, driver, payment, handover, and current-device geolocation content.

## Audit events

At minimum, audit:

- authentication/MFA/session security events;
- role and user changes;
- booking lifecycle, vehicle, driver, route, time, and assignment changes;
- proposals, pricing components, discounts, and overrides;
- payment/refund changes;
- policy acceptance and exceptions;
- message templates and sends/resends;
- handover, condition, incident, fleet, and configuration changes;
- export, data request, retention, anonymization, and deletion actions.

Audit records are append-only to ordinary staff. Sensitive payloads are summarized rather than duplicated.

## Verification before launch

- Automated authorization tests for every role/action boundary.
- Private-link expiry, revocation, token leakage, and enumeration tests.
- CSRF, XSS, open-redirect, file-upload, and rate-limit tests.
- Webhook signature, replay, duplicate, wrong-amount, and wrong-currency tests.
- Dependency audit and static analysis pass.
- Production headers, HTTPS, cookies, debug mode, directory exposure, and backup restore checked manually.
- Permission-controlled penetration test of public, guest, driver, and staff surfaces before accepting real payments.
