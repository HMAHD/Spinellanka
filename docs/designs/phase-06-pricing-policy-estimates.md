# Phase 06 Architectural Specification: Pricing, Policy, and Immutable Estimates

* **Status:** Resolved & Approved via Full EGM Workflow
* **Target Branch:** `phase/06-pricing-policy-estimates`
* **Lead Architect:** Lead System Architect
* **Date:** 2026-11-24

---

## 1. Domain Overview & Invariants

Spinellanka's pricing subsystem provides deterministic, effective-dated transport pricing and immutable itemized estimates for advance reservations across its five-vehicle fleet.

### Core Invariants
1. **Integer Money Only**: All currency values are stored and calculated as integer minor units (`amount_minor_units: int`). Floating-point arithmetic is strictly prohibited in monetary domain paths.
2. **Fixed Display & Approximation**: Primary amounts are calculated and stored in `USD`. An approximate `LKR` amount is calculated from the authoritative staff-entered `ExchangeRateBasis`. LKR is clearly labeled as indicative approximation and never becomes schedule or settlement authority.
3. **Refundable Deposit Segregation**: Security deposits for self-drive Rent are flagged (`is_deposit = true`), tracked as distinct line items, and excluded from service fee totals.
4. **Deterministic Scope Precedence**: Pricing rule resolution follows an exact priority hierarchy:
   $$\text{Vehicle-Specific Rule} > \text{Service-Wide Default Rule} > \text{Priority Order}$$
5. **Input Sufficiency & Transparent Custom Quote**: If a rule component requires an input (e.g. distance > 0 km for `PerKm`, hours > 0 for `PerHour`) that is missing or unverified, the engine degrades gracefully to a transparent `CustomQuoteResult`. Missing or expired rules also yield custom quotes. The system never fabricates plausible numbers or zero-dollar quotes.
6. **Immutable Snapshotting**: An `Estimate` record freezes the exact rule ID, rule version, assumptions payload, line items, and exchange rate basis at calculation time.
7. **Audit Attribution**: All pricing rule creations/updates and exchange rate updates record immutable audit events with actor attribution via `AuditService`.

---

## 2. Value Objects & DTOs

### 2.1 `App\Domain\Pricing\ValueObjects\Money`
```php
namespace App\Domain\Pricing\ValueObjects;

use App\Domain\Pricing\Exceptions\CurrencyMismatchException;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public readonly int $amountMinorUnits,
        public readonly string $currency = 'USD'
    ) {}

    public static function of(int $minorUnits, string $currency = 'USD'): self;
    public static function ofDollars(string|int $dollars, string $currency = 'USD'): self;
    public static function zero(string $currency = 'USD'): self;

    public function add(self $other): self;
    public function subtract(self $other): self;
    public function multiply(int $multiplier): self;
    public function multiplyByBasisPoints(int $basisPoints, int $roundingMode = PHP_ROUND_HALF_UP): self;
    public function allocate(array $ratios): array;

    public function equals(self $other): bool;
    public function isGreaterThan(self $other): bool;
    public function isLessThan(self $other): bool;
    public function isGreaterThanOrEqualTo(self $other): bool;
    public function isLessThanOrEqualTo(self $other): bool;
    public function isZero(): bool;
    public function isPositive(): bool;
    public function isNegative(): bool;

    public function format(): string;
    public function jsonSerialize(): array;
    public function __toString(): string;
}
```

### 2.2 Enums
- `App\Domain\Pricing\Enums\PricingComponentType: string`: `Base = 'base'`, `Distance = 'distance'`, `Duration = 'duration'`, `Stop = 'stop'`, `Waiting = 'waiting'`, `AirportFee = 'airport_fee'`, `Toll = 'toll'`, `Delivery = 'delivery'`, `Insurance = 'insurance'`, `Deposit = 'deposit'`, `Tax = 'tax'`, `Discount = 'discount'`, `Extra = 'extra'`.
- `App\Domain\Pricing\Enums\PricingUnit: string`: `Flat = 'flat'`, `PerKm = 'per_km'`, `PerHour = 'per_hour'`, `PerDay = 'per_day'`, `PerStop = 'per_stop'`, `Percentage = 'percentage'`.
- `App\Domain\Pricing\Enums\EstimateStatus: string`: `Calculated = 'calculated'`, `CustomQuoteRequired = 'custom_quote_required'`, `Expired = 'expired'`, `Superseded = 'superseded'`.

### 2.3 Boundary DTOs
- `App\Domain\Pricing\DTOs\EstimateRequestData`:
  - `ServiceType $serviceType`
  - `?int $vehicleId`
  - `Carbon $serviceDate` (authoritative `Asia/Colombo`)
  - `?float $distanceKm = null`
  - `?float $durationHours = null`
  - `?int $rentalDays = null`
  - `?int $stopCount = null`
  - `?int $waitingHours = null`
  - `array $extraIds = []`
  - `array $assumptions = []`
  - `?string $sessionId = null`

- `App\Domain\Pricing\DTOs\EstimateResult`:
  - `EstimateStatus $status`
  - `bool $isCustomQuote`
  - `?string $customQuoteReason`
  - `?Money $subtotal`
  - `?Money $taxTotal`
  - `?Money $depositTotal`
  - `?Money $grandTotal`
  - `?Money $approximateLkr`
  - `?int $pricingRuleId`
  - `?int $ruleVersion`
  - `array $items` (collection of `EstimateItemData`)

---

## 3. Database Schema & Migrations

### 3.1 Migration Order
1. `2026_11_24_140000_create_exchange_rate_bases_table.php`
2. `2026_11_24_140001_create_pricing_rules_table.php`
3. `2026_11_24_140002_create_pricing_rule_components_table.php`
4. `2026_11_24_140003_create_estimates_table.php`
5. `2026_11_24_140004_create_estimate_items_table.php`

### 3.2 Schema Specifications

```sql
-- 1. exchange_rate_bases
CREATE TABLE exchange_rate_bases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate DECIMAL(10, 4) NOT NULL,
    source_note VARCHAR(255) NOT NULL,
    effective_at DATETIME NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_erb_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_erb_effective_at (effective_at)
);

-- 2. pricing_rules
CREATE TABLE pricing_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_type VARCHAR(30) NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    effective_from DATETIME NOT NULL,
    effective_to DATETIME NULL,
    notes TEXT NULL,
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_pr_vehicle_id FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pr_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_rules_resolution (service_type, vehicle_id, is_active, effective_from)
);

-- 3. pricing_rule_components
CREATE TABLE pricing_rule_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pricing_rule_id BIGINT UNSIGNED NOT NULL,
    component_type VARCHAR(30) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    amount_minor_units BIGINT NOT NULL, -- minor units (cents) or basis points for percentage
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    min_units DECIMAL(10, 2) UNSIGNED NULL,
    max_units DECIMAL(10, 2) UNSIGNED NULL,
    is_taxable BOOLEAN NOT NULL DEFAULT FALSE,
    is_deposit BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_prc_pricing_rule_id FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rules(id) ON DELETE CASCADE,
    INDEX idx_components_rule (pricing_rule_id, component_type),
    CONSTRAINT chk_prc_deposit_not_taxable CHECK (NOT (is_deposit = TRUE AND is_taxable = TRUE))
);

-- 4. estimates
CREATE TABLE estimates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(32) NOT NULL UNIQUE,
    service_type VARCHAR(30) NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    pricing_rule_id BIGINT UNSIGNED NULL,
    exchange_rate_basis_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'calculated',
    is_custom_quote BOOLEAN NOT NULL DEFAULT FALSE,
    custom_quote_reason VARCHAR(255) NULL,
    subtotal_minor_units BIGINT NULL,
    tax_minor_units BIGINT NULL,
    deposit_minor_units BIGINT NULL,
    total_amount_minor_units BIGINT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    approximate_lkr_amount_minor_units BIGINT NULL,
    rule_version INT UNSIGNED NOT NULL DEFAULT 1,
    assumptions JSON NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_est_vehicle_id FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    CONSTRAINT fk_est_pricing_rule_id FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rules(id) ON DELETE SET NULL,
    CONSTRAINT fk_est_exchange_rate_basis_id FOREIGN KEY (exchange_rate_basis_id) REFERENCES exchange_rate_bases(id) ON DELETE SET NULL,
    INDEX idx_estimates_lookup (reference, status),
    INDEX idx_estimates_expires_at (expires_at),
    CONSTRAINT chk_est_custom_quote_null_amounts CHECK (
        (is_custom_quote = TRUE AND total_amount_minor_units IS NULL) OR
        (is_custom_quote = FALSE AND total_amount_minor_units IS NOT NULL)
    )
);

-- 5. estimate_items
CREATE TABLE estimate_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    estimate_id BIGINT UNSIGNED NOT NULL,
    rule_component_id BIGINT UNSIGNED NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    component_type VARCHAR(30) NOT NULL,
    description VARCHAR(255) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price_minor_units BIGINT NOT NULL,
    total_amount_minor_units BIGINT NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    is_deposit BOOLEAN NOT NULL DEFAULT FALSE,
    is_tax BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_ei_estimate_id FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE,
    CONSTRAINT fk_ei_rule_component_id FOREIGN KEY (rule_component_id) REFERENCES pricing_rule_components(id) ON DELETE SET NULL,
    INDEX idx_estimate_items_order (estimate_id, sort_order)
);
```

---

## 4. Application & Domain Services

### 4.1 `PricingRuleService`
Encapsulates rule creation, editing, cloning, deactivation, and component configuration inside database transactions, with `AuditService::record()` logging:
- `createRule(array $data, ?User $actor): PricingRule`
- `updateRule(PricingRule $rule, array $data, ?User $actor): PricingRule`
- `deactivateRule(PricingRule $rule, ?User $actor): PricingRule`

### 4.2 `ExchangeRateService`
Encapsulates manual USD/LKR approximation rate entries:
- `recordRate(float $rate, string $sourceNote, Carbon $effectiveAt, ?User $actor): ExchangeRateBasis`
- `getLatestRate(?Carbon $asOf = null): ?ExchangeRateBasis`
- `approximateLkr(Money $usdAmount, ?Carbon $asOf = null): ?Money`

### 4.3 `PricingService`
Core calculation engine:
- `resolveRule(ServiceType $serviceType, ?Vehicle $vehicle, Carbon $serviceDate): ?PricingRule`
- `calculateEstimate(EstimateRequestData $request): EstimateResult`

### 4.4 `EstimateService`
Snapshot persistence:
- `snapshotEstimate(EstimateResult $result, EstimateRequestData $request, int $ttlHours = 24): Estimate`
- `findByReference(string $reference): ?Estimate`

---

## 5. Filament 5 Staff Resources

- Directory: `app/Filament/Resources/Operations/PricingRules/` and `app/Filament/Resources/Operations/ExchangeRateBases/`
- Navigation Group: `Pricing & Policies`
- Permissions: `Permission::ConfigManage` (`config.manage`)
- Model Policies: `PricingRulePolicy`, `ExchangeRateBasisPolicy`
- Repeater state handling: Accessor `amount_display` on `PricingRuleComponent` seamlessly hydrates/dehydrates minor units and basis points with dynamic `$` or `%` labels.

