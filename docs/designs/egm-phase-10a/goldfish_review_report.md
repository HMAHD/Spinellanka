# EGM Phase 10A Swarm Review Synthesis Report

**Date:** 2026-11-26  
**Topic:** Spinellanka Phase 10A: Public Experience, Deals, Promotions & Post-Trip Reviews  
**Reviewers:**
1. Hostile Design Critic (`egm-design`)
2. Technical Quality Auditor (`egm-codebase-details`)
3. Pragmatic Tech Lead (`egm-implementation-readiness`)

---

## 1. Executive Summary & Review Consensus

The review swarm fully endorsed the functional architecture of Phase 10A:
- Elevates the public booking experience with curated, verified deals without succumbing to LESS-style dark patterns (no fake scarcity, no fabricated crossed-out prices, no generic vehicle classes).
- Implements a single, non-stacking, deterministic promotion engine seamlessly integrated into `PricingService`.
- Integrates a complete, authentic post-trip review lifecycle gated on verified completed bookings (`BookingStatus::Completed`), automated via the transactional outbox, and moderated before public display.

All three auditors identified critical failure vectors and edge cases in the draft architecture, which have been analyzed and resolved below.

---

## 2. Review Findings & Resolutions Matrix

| ID | Issue & Threat Scenario | Auditor | Severity | Resolution in Final Architecture |
|---|---|---|---|---|
| **D1** | **Promotion Stacking Loophole**: Staff assigns a deal-level promotion while a service-level promotion is active; traveler receives combined double discount. | Hostile Design Critic | Blocker | Enforce strict single-promotion precedence: `Deal > Service > Global`. Exactly one customer discount applies. If rule has existing discount, promotion is disabled. |
| **D2** | **Ambiguous Overlap Exploitation**: Two promotions exist with equal scope and equal priority; system might select randomly or flip on cache rebuild. | Hostile Design Critic | Major | Deterministic fail-safe: equal-scope/equal-priority overlap is flagged as ambiguous, logs an audit warning, and falls back to standard quote with zero discount. |
| **D3** | **Preset Query Tampering & Security Bypass**: Traveler modifies form inputs on deal detail (e.g. injects unserviced airport or negative rental days) to bypass service validation. | Technical Quality Auditor | Blocker | Presets are non-authoritative defaults. Handoff redirects into normal service validators (`AirportPickupRequest`, `RideBookingRequest`, etc.). All suitability, route, and availability checks execute identically. |
| **D4** | **Unapproved Media Leakage on Public Cards**: Deals marked "published" display unverified stock images or broken placeholders. | Hostile Design Critic | Major | Enforce publication invariant: deal image is rendered ONLY if `image_approver` and `image_approved_at` are non-null. Otherwise, layout renders the approved text-only hero fallback. |
| **D5** | **Fake Review Injection & Review Bombing**: Unauthenticated users submit bogus reviews or competitors inflate ratings without booking. | Pragmatic Tech Lead | Blocker | Reviews require a completed booking (`booking_id`). Each completed booking can generate at most ONE review invitation via high-entropy token (`invitation_token`). Moderation is required before publication. |
| **D6** | **Pricing Subtotal Inversion**: Fixed-amount discount exceeds service subtotal, causing negative invoice amounts. | Technical Quality Auditor | Blocker | Discounts are capped at `$subtotal` (cannot exceed subtotal), reduce taxable base, and deposits are strictly excluded from discounts. |
| **D7** | **Deposit Discounting & Liability Risk**: Percentage discount applies to refundable security deposits. | Technical Quality Auditor | Blocker | In `PricingService`, discounts run in Pass 2 on the service fee. Security deposits run in Pass 4 on the base amount, completely shielded from discounts. |
| **D8** | **Timezone Drift on Publication Windows**: Deal published until midnight UTC expires 5.5 hours late in Sri Lanka. | Technical Quality Auditor | Major | All dates (`published_at`, `expires_at`, `starts_at`, `ends_at`) are explicitly parsed, compared, and stored in `Asia/Colombo` timezone. |
| **D9** | **Empty Promotional Slop on Homepage**: No active deals or reviews exist, leaving broken carousel frames or empty headings. | Hostile Design Critic | Major | Public Blade templates use strict `@if($featuredDeals->isNotEmpty())` and `@if($featuredReviews->isNotEmpty())` guards; empty states are completely omitted from view. |
| **D10** | **Filament Permission Drift**: Operations staff without pricing authority modify active promotions. | Pragmatic Tech Lead | Major | Created distinct permission `offers.manage`, granted exclusively to Owner and Admin. Operational staff cannot alter deals or promotions. |

---

## 3. Final Architecture Approval

The swarm certifies that Phase 10A:
1. Meets all financial correctness and invariant preservation requirements.
2. Avoids competitor anti-patterns while improving customer orientation.
3. Incorporates post-service verified reviews natively with outbox messaging and moderation.
4. Is ready for implementation planning and execution.

