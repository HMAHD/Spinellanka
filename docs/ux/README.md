# UX Documentation Index

**Audience:** client stakeholders, product/UX designers, operations staff, and the future development team.

## Package goal

This package turns the approved discovery decisions into an auditable specification and a low-fidelity review artifact. It answers:

- What are we building?
- Who uses it and in what situation?
- How do the four services differ?
- What information and states must each screen support?
- How does the five-vehicle operation prevent conflicts?
- What must the client still supply?
- What exactly is the client approving now?

## Documents

| File | Purpose | Primary reader |
|---|---|---|
| [01 — Product requirements](01-product-requirements.md) | Scope, terminology, roles, functional and quality requirements, exclusions | Product, client, development |
| [02 — Information architecture](02-information-architecture.md) | Sitemap, navigation, content hierarchy, public/private/admin boundaries | UX, content, development |
| [03 — User flows](03-user-flows.md) | Customer, staff, driver, payment, change, cancellation, and handover flows | UX, operations, QA |
| [04 — Screen and state inventory](04-screen-and-state-inventory.md) | Complete screen coverage and required loading/empty/error/expired states | UX, UI, development, QA |
| [05 — Wireframe annotations](05-wireframe-annotations.md) | Layout behavior, responsive rules, field sequencing, and frame-by-frame notes | UX, UI, client |
| [06 — Admin operations](06-admin-operations.md) | Roles, booking lifecycle, calendar, pricing, payments, messaging, incidents, and audit | Operations, product, development |
| [07 — Client input checklist](07-client-input-checklist.md) | Missing facts, assets, policy values, rate data, and proof the client must provide | Client, project manager |
| [08 — Acceptance and sign-off](08-acceptance-and-signoff.md) | Approval checklist, testable acceptance conditions, decision register, and sign-off record | Client, product, project manager |
| [09 — Wireframe review design system](09-wireframe-review-design-system.md) | Grayscale review-artifact tokens and rules; not production visual direction | UX, UI, development |
| [Visual walkthrough](../../wireframes/index.html) | Grayscale client-review storyboard of key responsive screens and states | Client, UX, operations |
| [Product context](../../PRODUCT.md) | Durable product truth for future design work | Product and future agents |

## Review sequence

### Client workshop (recommended 60–90 minutes)

1. Begin with the visual walkthrough’s scope marker and service comparison.
2. Review the shared booking spine.
3. Inspect each service-specific search form.
4. Confirm vehicle-selection, estimate, and pending-confirmation behavior.
5. Review the guest booking page and payment choices.
6. Review the operations dashboard, booking review, and fleet calendar.
7. Inspect Rent handover and driver trip-link flows.
8. Discuss failure states and replacement/extension rules.
9. Complete the client-input checklist.
10. Mark the sign-off checklist as approved, approved with changes, or not approved.

### Design handoff

The designer should use documents 02, 04, and 05 as the direct design brief. Final UI may change composition and visual language, but it must preserve the requirements, states, accessibility, and service distinctions unless a change is recorded.

### Development planning handoff

After UX approval, development planning should convert the requirements into:

- system architecture and data model;
- implementation stack and hosting decision;
- provider selection for maps/routes, payment, WhatsApp, email, and exchange rates;
- security and privacy implementation plan;
- migration/import approach for existing bookings, if any;
- delivery phases, estimates, and test strategy.

These are intentionally not invented in the current package.

## Status labels

- **Confirmed:** settled during product discovery.
- **Client input required:** structure is approved, but a real value or asset is missing.
- **Deferred:** explicitly outside this phase.
- **Assumption:** temporary interpretation that must be confirmed; this package aims to contain none that affect behavior.

## Change control

When the client requests a change:

1. Add it to the change table in [08-acceptance-and-signoff.md](08-acceptance-and-signoff.md).
2. Identify affected requirement and screen IDs.
3. Update the functional source of truth first.
4. Update flows, screen inventory, annotations, and walkthrough as needed.
5. Increment the package revision and request renewed approval for affected areas.

A comment made during a meeting is not approved scope until it is written into the change table.
