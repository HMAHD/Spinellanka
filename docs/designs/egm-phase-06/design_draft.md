# Phase 06 Technical Design Draft: Pricing, Policy, and Immutable Estimates

* **Author:** Lead System Architect
* **Date:** 2026-11-24
* **Target Phase:** Phase 06 (`phase/06-pricing-policy-estimates`)
* **Role:** Lead System Architect (Full Elephant-Goldfish Model Workflow)

---

## 1. Domain Models & Value Objects

### 1.1 `App\Domain\Pricing\ValueObjects\Money`
Immutable Value Object encapsulating monetary values:
```php
namespace App\Domain\Pricing\ValueObjects;

final class Money implements \JsonSerializable, \Stringable
{
    private function __construct(
        public readonly int $amountMinorUnits,
        public readonly string $currency = 'USD'
    ) {}

    public static function of(int $minorUnits, string $currency = 'USD'): self;
    public static function ofDollars(float|int|string $dollars, string $currency = 'USD'): self;
    public static function zero(string $currency = 'USD'): self;

    public function add(self $other): self;
    public function subtract(self $other): self;
    public function multiply(float|int $multiplier, int $roundingMode = PHP_ROUND_HALF_UP): self;
    public function allocate(array $ratios): array;
    public function isZero(): bool;
    public function isPositive(): bool;
    public function isNegative(): bool;
    public function equals(self $other): bool;
    public function format(): string; // "$100.50" or "LKR 31,500"
    public function jsonSerialize(): array;
    public function __toString(): string;
}
```

### 1.2 Enums
- `PricingComponentType: string`
  - `Base = 'base'`
  - `Distance = 'distance'`
  - `Duration = 'duration'`
  - `Stop = 'stop'`
  - `Waiting = 'waiting'`
  - `AirportFee = 'airport_fee'`
  - `Toll = 'toll'`
  - `Delivery = 'delivery'`
  - `Insurance = 'insurance'`
  - `Deposit = 'deposit'`
  - `Tax = 'tax'`
  - `Discount = 'discount'`
  - `Extra = 'extra'`

- `PricingUnit: string`
  - `Flat = 'flat'`
  - `PerKm = 'per_km'`
  - `PerHour = 'per_hour'`
  - `PerDay = 'per_day'`
  - `PerStop = 'per_stop'`
  - `Percentage = 'percentage'`

- `EstimateStatus: string`
  - `Calculated = 'calculated'`
  - `CustomQuoteRequired = 'custom_quote_required'`
  - `Expired = 'expired'`
  - `Superseded = 'superseded'`

---

## 2. Database Schema & Migrations

### 2.1 Table: `pricing_rules`
- `id` (bigint unsigned, auto-increment, PK)
- `service_type` (string 30, indexed) -> `airport_pickup`, `airport_transfer`, `ride`, `rent`
- `vehicle_id` (bigint unsigned, nullable, FK -> `vehicles.id` ON DELETE CASCADE, indexed)
- `version` (int unsigned, default 1)
- `priority` (int unsigned, default 0, indexed)
- `is_active` (boolean, default true, indexed)
- `effective_from` (datetime, indexed)
- `effective_to` (datetime, nullable, indexed)
- `notes` (text, nullable)
- `metadata` (json, nullable)
- `created_by` (bigint unsigned, nullable, FK -> `users.id` ON DELETE SET NULL)
- `timestamps`
- Indexes:
  - `idx_rules_lookup: [service_type, is_active, effective_from, effective_to]`
  - `idx_rules_vehicle_lookup: [vehicle_id, service_type, is_active]`

### 2.2 Table: `pricing_rule_components`
- `id` (bigint unsigned, auto-increment, PK)
- `pricing_rule_id` (bigint unsigned, FK -> `pricing_rules.id` ON DELETE CASCADE, indexed)
- `component_type` (string 30) -> `base`, `distance`, etc.
- `unit` (string 30) -> `flat`, `per_km`, etc.
- `amount_minor_units` (bigint) -> Rate in minor units (e.g. cents)
- `currency` (string 3, default 'USD')
- `min_units` (int unsigned, nullable)
- `max_units` (int unsigned, nullable)
- `is_taxable` (boolean, default false)
- `is_deposit` (boolean, default false)
- `metadata` (json, nullable)
- `timestamps`
- Indexes:
  - `idx_components_rule: [pricing_rule_id, component_type]`

### 2.3 Table: `exchange_rate_bases`
- `id` (bigint unsigned, auto-increment, PK)
- `rate` (decimal 10,4) -> e.g. `315.5000` (LKR per 1 USD)
- `source_note` (string 255) -> e.g. "Central Bank of Sri Lanka indicative rate"
- `effective_at` (datetime, indexed)
- `created_by` (bigint unsigned, nullable, FK -> `users.id` ON DELETE SET NULL)
- `timestamps`

### 2.4 Table: `estimates`
- `id` (bigint unsigned, auto-increment, PK)
- `reference` (string 32, unique) -> e.g. `EST-7F8A2B9C`
- `service_type` (string 30, indexed)
- `vehicle_id` (bigint unsigned, nullable, FK -> `vehicles.id` ON DELETE SET NULL)
- `status` (string 30, default 'calculated', indexed)
- `subtotal_minor_units` (bigint default 0)
- `tax_minor_units` (bigint default 0)
- `deposit_minor_units` (bigint default 0)
- `total_amount_minor_units` (bigint default 0)
- `currency` (string 3, default 'USD')
- `approximate_lkr_amount_minor_units` (bigint default 0)
- `exchange_rate_basis_id` (bigint unsigned, nullable, FK -> `exchange_rate_bases.id` ON DELETE SET NULL)
- `rule_version` (int unsigned, default 1)
- `is_custom_quote` (boolean, default false, indexed)
- `custom_quote_reason` (string 255, nullable)
- `assumptions` (json, nullable)
- `expires_at` (datetime, indexed)
- `timestamps`

### 2.5 Table: `estimate_items`
- `id` (bigint unsigned, auto-increment, PK)
- `estimate_id` (bigint unsigned, FK -> `estimates.id` ON DELETE CASCADE, indexed)
- `rule_component_id` (bigint unsigned, nullable, FK -> `pricing_rule_components.id` ON DELETE SET NULL)
- `component_type` (string 30)
- `description` (string 255)
- `unit` (string 30)
- `quantity` (decimal 10,2, default 1.00)
- `unit_price_minor_units` (bigint)
- `total_amount_minor_units` (bigint)
- `currency` (string 3, default 'USD')
- `is_deposit` (boolean, default false)
- `is_tax` (boolean, default false)
- `timestamps`

---

## 3. Pricing & Estimate Domain Services

### 3.1 `PricingService`
```php
namespace App\Domain\Pricing\Services;

class PricingService
{
    public function resolveRule(
        ServiceType $serviceType,
        ?Vehicle $vehicle = null,
        ?Carbon $serviceDate = null
    ): ?PricingRule;

    public function calculateEstimate(
        EstimateRequestData $request
    ): EstimateResult;
}
```

Resolution logic:
1. Normalize `$serviceDate` to `Asia/Colombo` (defaults to current time).
2. Query active rules matching `$serviceType` within date boundaries:
   `effective_from <= $serviceDate AND (effective_to IS NULL OR effective_to > $serviceDate)`.
3. Prioritize vehicle-specific rule:
   - If `$vehicle` provided, search for rule with `vehicle_id = $vehicle->id`.
   - If found, sort by `priority DESC, id DESC` and take first.
4. Fall back to service-wide rule:
   - Search for rule with `vehicle_id IS NULL`.
   - Sort by `priority DESC, id DESC` and take first.
5. If no rule matches, return `null`.

Calculation logic:
1. If no rule matches or vehicle is ineligible for the service, return `EstimateResult::customQuote("No active pricing rule configured for {$serviceType->value} on this vehicle.")`.
2. Iterate through rule components:
   - Calculate line totals in integer minor units.
   - Aggregate `subtotal_minor_units` (excluding deposit and tax).
   - Aggregate `tax_minor_units` (based on tax components or percentage).
   - Aggregate `deposit_minor_units` (segregated refundable deposit).
   - Total = `subtotal + tax + deposit`.
3. Convert total to approximate LKR using latest `ExchangeRateBasis`.
4. Persist immutable `Estimate` and `EstimateItem` rows.
5. Record audit event `pricing.estimate.created`.

---

## 4. Filament Operations UI

### 4.1 `PricingRuleResource`
- Path: `app/Filament/Resources/Operations/Pricing/PricingRuleResource.php`
- Permission: `Permission::ConfigManage`
- Navigation Group: `Operations`
- Form:
  - Header: `service_type` (Select), `vehicle_id` (Select, optional, excludes retired), `priority` (Number), `is_active` (Toggle), `effective_from` (DateTimePicker), `effective_to` (DateTimePicker, optional), `notes` (Textarea).
  - Repeater: `components` (`component_type`, `unit`, `amount_dollars` dehydrated to `amount_minor_units`, `min_units`, `max_units`, `is_taxable`, `is_deposit`).
- Table:
  - Columns: `service_type`, `vehicle.public_name`, `version`, `priority`, `is_active`, `effective_from`, `effective_to`.
  - Filters: Service type, Active toggle.

### 4.2 `ExchangeRateBasisResource`
- Path: `app/Filament/Resources/Operations/Pricing/ExchangeRateBasisResource.php`
- Permission: `Permission::ConfigManage`
- Navigation Group: `Operations`
- Form:
  - `rate` (TextInput, numeric, LKR per 1 USD)
  - `source_note` (TextInput, required, e.g. "Central Bank of Sri Lanka 2026-11-24")
  - `effective_at` (DateTimePicker, required, defaults to now in Asia/Colombo)
- Table:
  - Columns: `rate`, `source_note`, `effective_at`, `creator.name`, `created_at`.

