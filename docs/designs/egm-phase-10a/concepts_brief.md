# EGM Phase 10A Concepts Brief: Public Experience, Deals, Promotions & Post-Trip Reviews

**Branch:** `phase/10a-public-deals-promotions`  
**Base:** `main` (Post-Phase 10, compatible with merged Phases 11–12)  
**Date:** 2026-11-26  
**Status:** Approved by Project Lead  

---

## 1. Problem Statement & Functional Context

Spinellanka provides high-touch, advance-booked chauffeured travel (Airport Pickup, Airport Transfer, Ride) and self-drive rentals (Rent) across an exact five-vehicle fleet. Previously, travelers had to build their journeys from scratch on individual service landing pages.

Competitor analysis (notably LESS) demonstrates the high customer value of preconfigured, curated "Deals" for popular routes, holiday packages, and seasonal offers. However, LESS pairs this useful browse-to-book flow with severe anti-patterns:
- Generic vehicle classes ("Standard Sedan", "VIP Van") instead of exact selectable vehicles.
- Fake urgency counters ("Only 2 cars left at this price!").
- Fabricated strikethrough "market" prices with unprovable discounts.
- Stock, unverified vehicle and destination photographs.
- Unmoderated or fabricated traveler reviews.
- Inflexible fixed pricing that bypasses route feasibility, passenger luggage suitability, and real-time availability.

Spinellanka's Phase 10A adopts LESS's useful browse-and-book workflow while strictly adhering to Spinellanka's core design system and integrity principles:
1. **Browse Preconfigured Deals:** Dedicated `/deals` index with service and destination filters, and `/deals/{slug}` detail pages.
2. **Preset-to-Booking Handoff:** Deal presets are strictly non-authoritative defaults; they pre-fill service criteria, while the existing `AvailabilityService`, `SuitabilityValidator`, 15-minute checkout hold, and `PricingService` recalculate real estimates for exact vehicles.
3. **Single Non-Stacking Promotions:** A deterministic promotion resolution engine (Deal > Service > Global) applying at most one customer-facing discount, capped at subtotal, before tax, with security deposits strictly excluded from discounts.
4. **Verified Post-Service Reviews:** An end-to-end review lifecycle: automated outbox review invitations triggered upon booking completion (`BookingStatus::Completed`), staff-moderated review ingestion (supporting direct verified reviews, Trustpilot, and Google), and verified homepage review displays with "Verified Completed Trip" badges.
5. **No Slop / No Slime:** Zero fabricated scarcity, zero unapproved stock media, zero fake ratings, and zero unconfirmed pricing bypasses.

---

## 2. Core Architectural Pillars

### Pillar A: Non-Authoritative Deal Presets & Exact Fleet Integrity
- A `Deal` entity contains an approved public presentation (title, slug, summary, markdown description, approved media) and a service-specific `preset_payload` (JSON).
- When a traveler clicks "Book This Deal", the preset pre-populates the service booking form (e.g. pickup airport, destination town, stops, or rental duration).
- The preset NEVER bypasses the standard booking pipeline:
  - Required missing traveler criteria (date, time, passenger/luggage counts, flight number) are collected.
  - Real-time fleet availability is checked via `AvailabilityService`.
  - The exact vehicle must be chosen from the available five vehicles.
  - The standard 15-minute checkout hold is applied.
  - The booking is submitted into the standard `Submitted` / `UnderReview` state for staff verification.

### Pillar B: Deterministic, Single Non-Stacking Promotion Hierarchy
- Promotions apply at three explicit scopes: `Deal` (linked directly to a deal), `Service` (targeted to a `ServiceType`), or `Global` (all services).
- **Precedence Order:**
  1. Deal-specific promotion.
  2. Service-specific promotion.
  3. Global promotion.
  4. Highest configured priority within matching scope.
- **Ambiguity Rule:** If two promotions share the identical scope and identical priority, the resolver flags ambiguity, applies NO arbitrary discount, logs an operational audit warning, and yields a transparent unpromoted estimate.
- **Single Discount Guarantee:** Only ONE customer-facing promotion can apply to an estimate. If the selected pricing rule already has a rule-level discount, promotion activation is blocked.
- **Financial Bounds:** Discounts are calculated in Pass 2 of `PricingService`, capped at the service subtotal, reduce the taxable base, and strictly NEVER discount refundable security deposits.

### Pillar C: Verified Post-Service Review Lifecycle
- **Completed-Trip Constraint:** Reviews are strictly restricted to customers who completed a genuine booking. No anonymous or pre-service reviews are permitted.
- **Automated Outbox Trigger:** When a booking transitions to `BookingStatus::Completed` in `BookingLifecycleService`, an outbox event (`booking.review_invited`) is dispatched with a single-use review link or external Trustpilot/Google review destination.
- **Moderation Boundary:** Reviews are stored in `reviews` with status `pending_moderation`. Authorized staff (`offers.manage`) review, approve, feature, or reject submissions in Filament.
- **Homepage Social Proof:** The homepage displays only approved, published reviews with star ratings (1–5), verified traveler name, trip date, service badge, and platform link (Trustpilot/Google). If no published reviews exist, the section remains visibly absent.

### Pillar D: Administrative Governance & Auditability
- Offers management is unified under an **Offers** Filament cluster with permission `offers.manage`, restricted to Owner and Admin. Operational staff cannot alter public prices or publish unverified deals.
- All actions (create, update, publish, archive, approve media, moderate review) record comprehensive `AuditEvent` records.
- Deal media requires explicit recording of `image_source`, `image_approver`, and `image_approved_at`.

