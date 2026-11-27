# Phase 10A: Public Experience, Deals, Promotions, and Post-Trip Reviews Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Phase 10A on branch `phase/10a-public-deals-promotions` providing preconfigured deals for all four services (Airport Pickup, Airport Transfer, Ride, Rent), a deterministic single non-stacking promotion engine, verified post-service customer reviews with Trustpilot integration, and administrative Filament management under an Offers cluster.

**Architecture:** Defined in [phase-10a-public-deals-promotions.md](file:///Users/akash/Documents/Spinellanka/docs/designs/phase-10a-public-deals-promotions.md). Deals provide non-authoritative presets pre-populating existing service booking flows without bypassing availability, suitability, 15-minute checkout holds, or staff confirmation. Promotions resolve deterministically (`Deal > Service > Global`) with single-discount caps in `PricingService`. Reviews are strictly gated on verified completed bookings (`BookingStatus::Completed`), invited via transactional outbox, moderated in Filament, and showcased on the homepage.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS 4, Alpine.js, Filament 5, Pest 3.

---

## User Review Required

> [!IMPORTANT]
> - **Zero Dark Patterns:** Deals and promotions strictly reject LESS-style fake scarcity ("Only 2 left!"), fake crossed-out original prices, generic car classes, unverified stock photos, and unmoderated reviews.
> - **Exact Fleet Vehicles:** Deals display exact fleet vehicles from Spinellanka's five verified vehicles, with real-time suitability filtering and 15-minute checkout holds.
> - **Non-Stacking Promotions:** Exactly one customer-facing discount may apply. If an underlying pricing rule has a built-in discount, promotions are blocked. Deposits are never discounted.
> - **Post-Service Reviews Only:** Public reviews require a completed booking (`BookingStatus::Completed`) and admin moderation before appearing publicly.

---

## Proposed Changes

```
app/
├── Domain/
│   ├── Booking/
│   │   ├── Enums/
│   │   │   ├── DealStatus.php
│   │   │   ├── PromotionScope.php
│   │   │   ├── PromotionDiscountType.php
│   │   │   ├── PromotionStatus.php
│   │   │   ├── ReviewStatus.php
│   │   │   └── ReviewSource.php
│   │   └── Events/
│   │       └── BookingCompleted.php
│   ├── Offers/
│   │   ├── DTOs/
│   │   │   ├── DealData.php
│   │   │   ├── PromotionData.php
│   │   │   └── ReviewSubmissionData.php
│   │   └── Services/
│   │       ├── DealCatalogService.php
│   │       └── ReviewService.php
│   └── Pricing/
│       ├── DTOs/
│       │   └── PromotionResolutionResult.php
│       └── Services/
│           ├── PromotionResolver.php
│           └── PricingService.php (Pass 2 discount update)
├── Filament/
│   └── Resources/
│       └── Offers/
│           ├── DealResource.php
│           ├── PromotionResource.php
│           └── ReviewResource.php
├── Http/
│   └── Controllers/
│       ├── DealController.php
│       └── ReviewController.php
└── Models/
    ├── Deal.php
    ├── Promotion.php
    └── Review.php

database/
└── migrations/
    ├── 2026_11_26_160001_create_deals_and_promotions_tables.php
    ├── 2026_11_26_160002_create_reviews_table.php
    └── 2026_11_26_160003_add_deal_and_promotion_to_estimates_and_proposals.php

resources/
└── views/
    ├── deals/
    │   ├── index.blade.php
    │   └── show.blade.php
    ├── reviews/
    │   └── submit.blade.php
    └── welcome.blade.php (featured deals & verified reviews sections)

tests/
└── Feature/
    ├── Offers/
    │   ├── DealCatalogTest.php
    │   ├── DealBookingHandoffTest.php
    │   ├── PromotionResolverTest.php
    │   ├── PricingPromotionTest.php
    │   └── ReviewLifecycleTest.php
    └── Filament/
        └── OffersResourceTest.php
```

---

## Tasks

### Task 1: Documentation & Sign-off Updates
- [ ] Update `docs/ux/08-acceptance-and-signoff.md` by recording approved Change Request CHG-003, advancing to Draft 3 dated 2026-11-25.
- [ ] Update `docs/ux/01-product-requirements.md` at line 238, replacing the promotion exclusion with explicit Deal, Promotion, and Review specifications.
- [ ] Update `roadmap.md`, inserting Phase 10A after Phase 10 and marking Phase 12 as dependent on Phase 10A.
- [ ] Update `PRODUCT.md` and `DESIGN.md` reflecting Deals, Promotions, and Post-Trip Reviews.
- [ ] **Verification:** Run `git diff docs/` to confirm exact documentation alignment.

### Task 2: Domain Enums, Database Migrations & Models
- [ ] Create domain enums in `app/Domain/Booking/Enums/`:
  - `DealStatus`: `Draft`, `Published`, `Archived`.
  - `PromotionScope`: `Global`, `Service`, `Deal`.
  - `PromotionDiscountType`: `FixedAmount`, `PercentageBasisPoints`.
  - `PromotionStatus`: `Draft`, `Active`, `Archived`.
  - `ReviewStatus`: `PendingModeration`, `Published`, `Rejected`.
  - `ReviewSource`: `Direct`, `Trustpilot`, `Google`.
- [ ] Create migration `2026_11_26_160001_create_deals_and_promotions_tables.php` defining `deals` and `promotions` with foreign keys and Asia/Colombo indexes.
- [ ] Create migration `2026_11_26_160002_create_reviews_table.php` defining `reviews` with unique `booking_id` and token indexes.
- [ ] Create migration `2026_11_26_160003_add_deal_and_promotion_to_estimates_and_proposals.php`.
- [ ] Create Eloquent models `Deal`, `Promotion`, `Review` with typed casts, relationship methods, and publication query scopes (`published()`, `active()`, `featured()`).
- [ ] Create Model Factories `DealFactory`, `PromotionFactory`, `ReviewFactory`.
- [ ] **Verification:** Run `php artisan migrate --pretend` and run Pest rollback tests.

### Task 3: Deterministic Promotion Resolver & Pricing Engine Integration
- [ ] Create `PromotionResolutionResult` DTO storing resolved promotion ID, name, discount type, value, and scope.
- [ ] Implement `PromotionResolver`:
  - Enforce scope hierarchy: `Deal > Service > Global`.
  - Filter active publication windows in `Asia/Colombo`.
  - Resolve highest priority within winning scope.
  - Enforce ambiguity fail-safe: equal-scope and equal-priority overlap returns `null` with audit warning.
- [ ] Integrate with `PricingService`:
  - In Pass 2 (Discounts), calculate promotion discount before tax.
  - Guard against stacking: skip promotion if the pricing rule already contains a `Discount` component.
  - Cap discount at service subtotal (`min(discount, subtotal)`).
  - Reduce taxable base; leave Pass 4 security deposits strictly untouched.
  - Append `EstimateItemData` with `isDiscount: true` and snapshot promotion metadata.
- [ ] Write unit & feature tests in `tests/Feature/Offers/PromotionResolverTest.php` and `PricingPromotionTest.php`.
- [ ] **Verification:** Run `./vendor/bin/pest tests/Feature/Offers/PromotionResolverTest.php tests/Feature/Offers/PricingPromotionTest.php`.

### Task 4: Public Deals Catalog & Booking Handoff
- [ ] Implement `DealController`:
  - `index()`: Fetch published deals filtered by `service` and `destination`, ordered by `priority desc`.
  - `show($slug)`: Render published deal details with verified media or text-only hero fallback.
  - `book($slug)`: Validate submitted date/time/service criteria, pre-fill deal preset, store in session, and redirect to service booking route.
- [ ] Create Blade templates:
  - `resources/views/deals/index.blade.php`: Service filtering pills, responsive deal card grid.
  - `resources/views/deals/show.blade.php`: Media gallery, itinerary summary, inclusions, and exact vehicle selector.
- [ ] Update service booking forms (`AirportPickup`, `AirportTransfer`, `Ride`, `Rent`) to recognize session deal presets.
- [ ] Write feature tests in `tests/Feature/Offers/DealCatalogTest.php` and `DealBookingHandoffTest.php`.
- [ ] **Verification:** Verify rendered layouts at 320px, 390px, 768px, and 1440px viewports.

### Task 5: Post-Trip Verified Reviews & Homepage Integration
- [ ] Hook into `BookingLifecycleService::complete()`:
  - Generate cryptographically secure `invitation_token`.
  - Create `Review` record with `status: PendingModeration`.
  - Dispatch outbox event `booking.review_invited` with review submission link and Trustpilot redirect.
- [ ] Implement `ReviewController`:
  - `create($token)`: Display review submission form for verified completed trip.
  - `store($token)`: Validate rating (1–5) and feedback text; queue for staff moderation.
- [ ] Create Blade view `resources/views/reviews/submit.blade.php`.
- [ ] Update homepage `resources/views/welcome.blade.php`:
  - Add Featured Deals section (rendered strictly when `$featuredDeals->isNotEmpty()`).
  - Add Verified Reviews section (rendered strictly when `$featuredReviews->isNotEmpty()`) displaying star ratings, customer quotes, completed trip badge, and Trustpilot profile link.
- [ ] Write feature tests in `tests/Feature/Offers/ReviewLifecycleTest.php`.
- [ ] **Verification:** Test outbox event generation on booking completion and customer review submission.

### Task 6: Filament Admin Offers Management
- [ ] Add `Permission::OffersManage = 'offers.manage'` to `Permission` enum and assign to Owner and Admin roles.
- [ ] Implement Filament resources in `app/Filament/Resources/Offers/`:
  - `DealResource`: Service preset builder, media approver tracking, publication dates, featured toggle.
  - `PromotionResource`: Scope selector, discount value/basis points, priority, live preview.
  - `ReviewResource`: Moderation queue with `Approve`, `Feature on Homepage`, and `Reject` actions.
- [ ] Write permission and CRUD tests in `tests/Feature/Filament/OffersResourceTest.php`.
- [ ] **Verification:** Test unauthorized access rejection for regular operational staff.

### Task 7: Full Quality Gates, Regression Testing & Rollback Verification
- [ ] Execute Pint formatting: `./vendor/bin/pint --test`.
- [ ] Execute PHPStan static analysis: `./vendor/bin/phpstan analyse`.
- [ ] Execute full Pest test suite: `./vendor/bin/pest`.
- [ ] Run migration rollback test: `./artisan migrate:rollback --step=3 && ./artisan migrate`.
- [ ] Prepare Phase 10A handoff report with screenshots at 320px, 390px, 768px, and 1440px viewports.

