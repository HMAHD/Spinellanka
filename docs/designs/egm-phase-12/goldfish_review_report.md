# EGM Phase 12 Swarm Review Synthesis Report

**Date:** 2026-11-25  
**Topic:** Spinellanka Phase 12: Versioned Proposals & Private Guest Control  
**Reviewers:**
1. Hostile Design Critic (`egm-design`)
2. Technical Quality Auditor (`egm-codebase-details`)
3. Pragmatic Tech Lead (`egm-implementation-readiness`)

---

## 1. Executive Summary & Consensus

The review swarm unreservedly validated the core architectural premise of Phase 12:
- Dual-track lifecycle for revisions where confirmed bookings remain active and authoritative during pending revisions (Pillar B).
- Immutable, versioned proposals with strict row-locked transitions and full snapshotting.
- Zero-leakage tokenized private guest links with constant-time cryptographic verification and neutral error handling.

However, all three reviewers identified critical failure vectors in the initial draft that have now been resolved:
1. **Self-Conflict Deadlock on Revision**: Creating a proposal hold on the same vehicle for an existing confirmed booking would fail due to schedule conflict. Resolved by introducing `ignoreBlockId` in `AvailabilityService`.
2. **Confirmed Schedule Block Loss on Supersession**: Proposals must never claim or delete the confirmed booking's active block. `proposals.schedule_block_id` stores only temporary `ProposalHold` blocks; `bookings.schedule_block_id` anchors the active confirmed booking.
3. **State Machine Destruction on Revision Decline**: Declining or expiring a revision must only retire the proposal and reset `pending_revision = false`, keeping `booking->status = Confirmed`.
4. **Enum Gaps**: `BookingStatus` lacks `ProposalSent` and `AwaitingPayment`. `ConfirmationChannel` lacks `GuestPrivateLink`. Both are now added.
5. **Database Migration Cycle**: `bookings.current_proposal_id` and `proposals.booking_id` require two sequential migrations to avoid circular foreign key issues.
6. **Payment Tampering Guard**: Acceptance endpoint must strictly validate that the chosen payment method matches the proposal's allowed choices (`ProposalPaymentChoice`).
7. **Pricing Schema Alignment**: `proposal_items.quantity` must be `DECIMAL(10,2)` (not `INT UNSIGNED`), matching `estimate_items` and supporting fractional durations/hours. Field names and enums must strictly align with `PricingComponentType` and `PricingUnit`.
8. **Internal Note Redaction**: `proposals.internal_notes` must be hidden and filtered via `GuestProposalViewDTO` to prevent margin and negotiation leakage.
9. **Acceptance Idempotency**: Double-clicking or retrying an accepted proposal must safely return the transition result without 500 exceptions.

---

## 2. Action Items Matrix & Resolution

| ID | Issue Description | Source | Severity | Resolution in Final Design |
|---|---|---|---|---|
| A1 | `BookingStatus` missing `ProposalSent` & `AwaitingPayment` | All | Blocker | Add cases to `BookingStatus`, update `isPending()` and `canBeConfirmed()`. |
| A2 | `AvailabilityService` revision self-conflict deadlock | Design, Readiness | Blocker | Add `ignoreBlockId` to `AvailabilityService::getConflictingBlocks` and `createProposalHold`. |
| A3 | Accidental release of confirmed block on proposal expiry/supersession | Design | Blocker | Only release blocks where `kind === ScheduleBlockKind::ProposalHold`. Preserve `booking->schedule_block_id`. |
| A4 | Revision decline cancels confirmed booking | Design, Readiness | Blocker | Branch lifecycle: if `booking->status === Confirmed`, leave confirmed and clear `pending_revision`. |
| A5 | `proposal_items.quantity` integer truncation | Codebase Details | Blocker | Use `DECIMAL(10,2) NOT NULL DEFAULT 1.00`. Align with `PricingComponentType` and `PricingUnit`. |
| A6 | Circular foreign key in migration | Readiness | Major | Split into two migrations: create `proposals` & `proposal_items`, then add `current_proposal_id` & `pending_revision` to `bookings`. |
| A7 | Payment choice tampering / self-confirmation bypass | Design | Major | Validate submitted payment choice strictly against proposal's configured `ProposalPaymentChoice`. |
| A8 | Missing `ConfirmationChannel::GuestPrivateLink` | Readiness | Major | Add `GuestPrivateLink` case to `ConfirmationChannel`. |
| A9 | Internal staff notes margin leakage | Design | Major | Mark `internal_notes` `$hidden`, map view data through `GuestProposalViewDTO`. |
| A10 | Route signature mismatch (`/booking/{reference}/{token}`) | Codebase Details | Major | Use existing `/booking/{reference}/{token}` and keep all current dispatchers aligned. |
| A11 | Acceptance network retry crash | Readiness | Major | Implement idempotent acceptance in `GuestControlService`. |
| A12 | Redundant `rent_details.extension_state` migration | Codebase Details | Major | Drop redundant migration; column already exists. |
| A13 | Stale proposal hold background sweep | Codebase Details, Readiness | Minor | Add `ExpireStaleProposalsCommand` and `AvailabilityService` sweep for expired `ProposalHold` blocks. |
| A14 | Filament Staff UI specification & re-authentication | Readiness | Major | Document Filament action modals, permissions (`Permission::BookingReview`), and re-auth rule for revising confirmed bookings. |

