# Phase 06 Goldfish Review Report: Pricing, Policy, and Immutable Estimates

* **Compiled By:** Lead System Architect / EGM Synthesizer
* **Date:** 2026-11-24
* **Target Phase:** Phase 06 (`phase/06-pricing-policy-estimates`)
* **Reviewers:**
  1. `egm-design` (Hostile Design Critic)
  2. `egm-implementation-readiness` (Pragmatic Tech Lead)
  3. `egm-codebase-details` (Technical Quality Auditor)

---

## 1. Consolidated Review Matrix

| ID | Severity | Category | Description | Resolution / Architectural Action |
|:---|:---|:---|:---|:---|
| **F-01** | 🔴 Blocker | Financial Precision | `float` in `Money::ofDollars()` and `Money::multiply()` risks IEEE-754 penny truncation. | Remove `float`; enforce `int\|string` parsing for dollars and `multiplyByBasisPoints(int $basisPoints)`. |
| **F-02** | 🔴 Blocker | Financial Integrity | Custom quote storing `$0.00` total amount risks downstream free checkout. | Make monetary columns in `estimates` nullable when `is_custom_quote = true`. Add DB check constraint. |
| **F-03** | 🔴 Blocker | Pricing Safety | Silent underpricing: missing route/duration parameters compute base fee only instead of degrading. | Input sufficiency validation: if component requires unverified unit (e.g. distance > 0), degrade to Custom Quote. |
| **F-04** | 🔴 Blocker | Data Integrity | `pricing_rules.vehicle_id` `ON DELETE CASCADE` erases historical financial rules. | Change foreign key constraint to `ON DELETE RESTRICT`. |
| **F-05** | 🔴 Blocker | Architecture Seam | Filament resources mutating Eloquent directly bypasses domain layer and `AuditService`. | Introduce `PricingRuleService` and `ExchangeRateService`; Filament delegates mutations via `->using(...)`. |
| **F-06** | 🟡 Major | Rate Semantics | Percentage units stored in `amount_minor_units` without scale definition. | Codify that for `PricingUnit::Percentage`, rate is stored as integer basis points (1% = 100 bps, 15% = 1500 bps). |
| **F-07** | 🟡 Major | Timezone Boundary | Unstated timezone convention between `Asia/Colombo` business dates and DB timestamps. | Business effective dates stored with explicit `Asia/Colombo` invariant; service dates normalized to Colombo before evaluation. |
| **F-08** | 🟡 Major | Schema Link | `estimates` missing `pricing_rule_id` foreign key; only holds opaque `rule_version`. | Add `pricing_rule_id` nullable foreign key to `estimates` table. |
| **F-09** | 🟡 Major | DDL Precision | `min_units`/`max_units` as `int unsigned` prevents fractional distance (e.g. 2.5 km). | Change `min_units` and `max_units` to `decimal(10,2) unsigned nullable`. |
| **F-10** | 🟡 Major | Display Ordering | `estimate_items` lacking `sort_order` creates jitter across database engines. | Add `sort_order (unsignedSmallInteger)` to `estimate_items`. |
| **F-11** | 🟡 Major | Filament UI | Navigation group `'Operations'` conflates staff daily workflow with config permissions. | Move pricing resources to navigation group `'Pricing & Policies'` gated by `Permission::ConfigManage`. |
| **F-12** | 🟡 Major | Scaling & IOPS | Persisting `estimates` and audit rows on every public calculation will exhaust DB write IOPS. | Decouple in-memory calculation DTO (`EstimateResult`) from database snapshot persistence. |
| **F-13** | ⚪ Nit | Tax Invariant | `is_deposit` and `is_taxable` both checked violates trust liability status of deposits. | Add check constraint: `CHECK (NOT (is_deposit = true AND is_taxable = true))`. |

---

## 2. Unanimously Approved Architectural Decisions

1. **Strict Integer-Only Money Model**:
   - `Money` value object stores `amountMinorUnits: int` and ISO-4217 `currency: string`.
   - Dollar string parsing: splits integer and fractional parts via string manipulation; zero float casts.
   - Percentage multiplication: integer basis points math: `intval(round(($taxableBase * $basisPoints) / 10000, 0, PHP_ROUND_HALF_UP))`.
   - Comprehensive comparison predicates (`isGreaterThan`, `isLessThan`, `equals`, etc.) and `CurrencyMismatchException`.

2. **Deterministic Pricing Rule Resolution Pipeline**:
   - Normalized `Asia/Colombo` service date lookup against `effective_from <= $date AND (effective_to IS NULL OR effective_to > $date)`.
   - Strict Scope Precedence: Exact Vehicle Rule > Service-Wide Default Rule > Highest Priority.
   - Input Sufficiency: Missing required trip parameters (distance, hours, stops) immediately triggers `CustomQuoteResult`.

3. **Immutable Snapshot & Deposit Segregation**:
   - `estimates` snapshots rule ID, rule version, assumptions payload, and USD/LKR approximation basis.
   - Monetary amounts nullable on custom quote to prevent `$0.00` checkout vulnerabilities.
   - `estimate_items` sorted deterministically via `sort_order`.
   - Refundable deposits segregated (`is_deposit = true`) and excluded from service fee totals.

4. **Domain Service Encapsulation & Audit Attribution**:
   - `PricingRuleService` and `ExchangeRateService` own all mutation and transactional persistence.
   - `AuditService::record()` integration for `pricing.rule.created`, `pricing.rule.updated`, `pricing.exchange_rate.created`, and `pricing.estimate.created`.
   - Filament resources in `app/Filament/Resources/Operations/PricingRules/` and `ExchangeRateBases/` under navigation group `'Pricing & Policies'`.

5. **Stop-Gate Adherence**:
   - Production database seeds zero pricing rules.
   - Client pricing inputs (rates, taxes, fees) remain unapproved; system degrades transparently to Custom Quote.
   - Test suites execute against isolated, unmistakably synthetic test fixtures.

