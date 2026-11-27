# EGM Phase 10A Design Draft: Public Experience, Deals, Promotions & Post-Trip Reviews

**Branch:** `phase/10a-public-deals-promotions`  
**Date:** 2026-11-26  

---

## 1. Database Schema & Relational Specifications

### 1.1 `deals` Table
```sql
CREATE TABLE deals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL UNIQUE,
    service_type VARCHAR(32) NOT NULL, -- airport_pickup, airport_transfer, ride, rent
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    description_markdown TEXT NOT NULL,
    preset_payload JSON NOT NULL, -- Service-specific initial booking form fields
    image_path VARCHAR(255) NULL,
    image_alt VARCHAR(255) NULL,
    image_source VARCHAR(255) NULL, -- Client-verified source attribution
    image_approver VARCHAR(255) NULL, -- Name/role of authorizer
    image_approved_at TIMESTAMP NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft', -- draft, published, archived
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    priority INT NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL, -- Asia/Colombo start of visibility
    expires_at TIMESTAMP NULL,   -- Asia/Colombo end of visibility
    promotion_id BIGINT UNSIGNED NULL, -- Optional linked deal-specific promotion
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deals_status_window (status, published_at, expires_at),
    INDEX idx_deals_service (service_type),
    INDEX idx_deals_featured_priority (is_featured, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 `promotions` Table
```sql
CREATE TABLE promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(64) NULL UNIQUE, -- Internal administrative code (optional)
    explanation VARCHAR(500) NOT NULL, -- Public customer-facing explanation
    scope VARCHAR(32) NOT NULL DEFAULT 'global', -- global, service, deal
    service_type VARCHAR(32) NULL, -- Populated when scope = 'service'
    deal_id BIGINT UNSIGNED NULL,  -- Populated when scope = 'deal'
    discount_type VARCHAR(32) NOT NULL, -- fixed_amount, percentage_basis_points
    discount_value INT UNSIGNED NOT NULL, -- Minor units for fixed, basis points (1000 = 10%) for percentage
    currency VARCHAR(3) NULL DEFAULT 'USD', -- Required for fixed_amount
    status VARCHAR(32) NOT NULL DEFAULT 'draft', -- draft, active, archived
    priority INT NOT NULL DEFAULT 0,
    starts_at TIMESTAMP NULL, -- Asia/Colombo
    ends_at TIMESTAMP NULL,   -- Asia/Colombo
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_promotions_scope (scope, service_type, deal_id),
    INDEX idx_promotions_status_dates (status, starts_at, ends_at),
    INDEX idx_promotions_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 `reviews` Table
```sql
CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL UNIQUE, -- One verified review per completed booking
    traveler_name VARCHAR(255) NOT NULL,
    traveler_country VARCHAR(2) NULL, -- ISO 3166-1 alpha-2
    service_type VARCHAR(32) NOT NULL,
    rating INT UNSIGNED NOT NULL, -- 1 to 5 stars
    title VARCHAR(255) NULL,
    review_text TEXT NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'direct', -- direct, trustpilot, google
    external_url VARCHAR(500) NULL, -- Link to verified public review on Trustpilot/Google
    status VARCHAR(32) NOT NULL DEFAULT 'pending_moderation', -- pending_moderation, published, rejected
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    verified_trip BOOLEAN NOT NULL DEFAULT TRUE,
    invitation_token VARCHAR(64) NULL UNIQUE,
    invited_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    moderated_by_user_id BIGINT UNSIGNED NULL,
    moderated_at TIMESTAMP NULL,
    moderation_notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (moderated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reviews_status_featured (status, is_featured),
    INDEX idx_reviews_service (service_type),
    INDEX idx_reviews_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.4 `estimates` and `proposals` Delta Migrations
Add columns to snapshot applied deal and promotion immutably:
- `applied_deal_id` (nullable bigint unsigned)
- `applied_promotion_id` (nullable bigint unsigned)
- `promotion_snapshot` (nullable JSON: `{id, name, scope, discount_type, discount_value, explanation}`)

---

## 2. Domain Services & Resolution Logic

### 2.1 `PromotionResolver`
```php
namespace App\Domain\Pricing\Services;

class PromotionResolver
{
    /**
     * Resolve the single authoritative applicable promotion.
     * Returns null if no promotion applies or if ambiguity is detected.
     */
    public function resolve(
        ServiceType $serviceType,
        ?Deal $deal = null,
        ?Carbon $travelDate = null,
        string $currency = 'USD'
    ): ?PromotionResolutionResult;
}
```
**Resolution Invariants:**
1. Collect candidate active promotions where `status === 'active'` and `starts_at <= now <= ends_at`.
2. Filter candidates by compatibility (currency matches if fixed amount).
3. If `$deal` is provided and has an active linked promotion: Deal promotion wins.
4. Otherwise, query Service promotions matching `$serviceType`. If found, select highest priority.
5. If no Service promotion, query Global promotions. If found, select highest priority.
6. **Ambiguity Gate:** If two or more promotions within the highest matching scope have the exact same `priority`, resolution fails safe: returns `null`, sets `isAmbiguous = true`, and logs a warning so staff are alerted.

### 2.2 Integration with `PricingService`
- In `PricingService::calculate()`, accept optional `?PromotionResolutionResult $promotion`.
- After Pass 1 (Base, distance, extras), execute Pass 2 (Discounts):
  - If pricing rule already has a `PricingComponentType::Discount`, promotion discount is skipped (non-stacking rule).
  - Otherwise, calculate discount amount (fixed minor units or basis points of subtotal).
  - Cap discount at `$subtotal`.
  - Reduce `$subtotal` and `$taxableBase`.
  - Append `EstimateItemData` with `componentType: PricingComponentType::Discount`, `description: $promotion->name`, `isDiscount: true`.
  - Pass 3 (Taxes) calculates tax on reduced `$taxableBase`.
  - Pass 4 (Deposits) calculates security deposit on base and remains untouched by promotions.

### 2.3 Post-Trip Review Invitation in `BookingLifecycleService`
- In `BookingLifecycleService::complete(Booking $booking)`:
  - Generate a secure, unique `invitation_token`.
  - Insert initial `Review` record with `status: ReviewStatus::PendingModeration`, `verified_trip: true`.
  - Dispatch outbox message `booking.review_invited` to traveler via WhatsApp/Email containing review submission link and Trustpilot/Google review redirection options.

---

## 3. Web Controllers & Routes

- `GET /deals`: Public catalog of published deals. Filters: `service`, `destination`.
- `GET /deals/{slug}`: Deal detail page with media, description, included amenities, and booking date/time selector.
- `POST /deals/{slug}/book`: Validates traveler input, sets session draft with deal preset, and redirects to service-specific booking flow (`booking.airport-pickup.create`, `booking.airport-transfer.create`, `booking.ride.create`, `booking.rent.create`).
- `GET /reviews`: Public verified reviews archive (filterable by service and rating).
- `GET /reviews/submit/{token}`: Customer-facing review submission form for completed bookings.
- `POST /reviews/submit/{token}`: Ingest traveler rating and review text into moderation queue.

---

## 4. Admin Filament Resources (`Offers` Cluster)

- `DealResource`: Full CRUD, preset builder per service, image approval fields, publish date pickers.
- `PromotionResource`: Scope selector, discount value / basis points, priority, live eligibility preview.
- `ReviewResource`: List with filters (`status`, `rating`, `service_type`), actions: `Approve`, `Reject`, `Feature on Homepage`.

