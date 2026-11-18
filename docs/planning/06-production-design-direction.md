# 06 — Production design direction

## Boundary

The temporary root `index.html` supplied after UX approval was used **only to extract a color direction** and was then deleted before Git initialization. Its layout, components, typography, imagery, content, interactions, and claims are not design authority and are not present in repository history.

The approved UX documents and wireframes control information, behavior, states, and responsive requirements. This document controls the initial production visual direction.

## Creative direction

**North star: “Calm Sri Lankan mobility desk.”**

The customer experience should feel prepared, trustworthy, and internationally legible rather than like a live ride-hailing clone. Light space keeps long forms calm; deep navy creates dependable navigation and summary anchors; amber marks the few decisions that move a booking forward.

## 60 / 30 / 10 palette

| Share | Token | Hex | Use |
|---:|---|---|---|
| 60% | Cloud Canvas | `#F8FAFC` | Page backgrounds, form fields, large quiet areas, table/list breathing room |
| 30% | Spinel Navy | `#0C1445` | Header, footer, booking summaries, admin navigation, strong text panels, selected states |
| 10% | Ceylon Amber | `#FBBF24` | Primary CTAs, current progress, focus accents, selected service marker, small highlights |

Composition is measured by perceived area, not by counting components. White may sit within the 60% light family. Amber never becomes a page background or large navigation field.

### Supporting neutrals

- Paper: `#FFFFFF`
- Primary text: `#0F172A`
- Secondary text: `#475569`
- Muted text: `#64748B`
- Rule/border: `#CBD5E1`
- Soft border: `#E2E8F0`
- Navy hover/raised: `#101A52`

### Semantic colors

Semantic colors do not compete for the 10% brand-accent role and appear only with text/icons:

- Success/available: `#047857` on `#ECFDF5`
- Information: `#1D4ED8` on `#EFF6FF`
- Warning: `#92400E` on `#FFFBEB`
- Error/blocked: `#B91C1C` on `#FEF2F2`

Amber text on white is not accessible for body copy. Use amber as a fill with navy text, or use a dark amber tone for text. Every status has a label; color is never the only cue.

## Typography

The palette is the only element retained from the temporary reference. The type choice below was made independently and is not an extracted reference decision.

- **Primary UI font:** self-hosted Inter Variable with `system-ui`, `-apple-system`, `Segoe UI`, and sans-serif fallbacks.
- **Operational identifiers:** system monospace only for booking references, dates/times, amounts in dense tables, and audit data.
- Body size starts at 16px with at least 1.5 line height.
- Form labels remain persistent and never rely on placeholder text.
- Headings are sentence case, concise, and weighted through scale/weight rather than decorative type.

Self-host fonts in the production bundle to avoid a render-blocking third-party font request and reduce privacy leakage.

## Layout and shape

- Mobile-first single-column booking flow; desktop adds a stable summary column when it improves review.
- Maximum reading/form measure around 72ch; full width is reserved for fleet schedules and operational tables.
- Use an 8px spacing foundation with 4px micro-adjustments.
- Corners are controlled and functional: 10–12px for fields/panels, smaller for tags; no pill-shaped container unless the content is truly compact status.
- Depth is quiet: one shadow level for raised summaries/dialogs; rules and surface changes do most grouping.
- Public pages use generous light space. Admin pages favor denser navy navigation, clear rules, and labeled state patterns.

## Component direction

### Navigation

Navy header with a white wordmark/logo area supplied by the client. Desktop navigation remains short. Mobile uses a conventional menu, not a horizontally crowded service switcher. Persistent support is visible but does not compete with the booking CTA.

### Service chooser

Four clearly defined service rows or panels. Each states driver included/self-drive and scheduling basis. Amber marks the selected service; the surface remains light.

### Forms

- One decision group at a time on mobile.
- Labels above controls, specific help/error text, Sri Lanka time attached to every date/time group.
- Navy summary rail on desktop; light expandable summary on mobile.
- Route stops have numbered order and keyboard/touch alternatives to drag.
- Required and optional status is explicit.

### Exact vehicle selection

Use real client photography, verified capacity/luggage facts, service inclusion, itemized estimate, and availability. Avoid generic vehicle-class cards. Selected vehicle receives navy structure and a small amber marker, not a large colored card.

### Status and private booking

Status header leads with plain language and one next action. Timeline, proposal comparison, payment state, documents, and support remain secondary sections. Pending/expired/error states use semantic labels and icons in addition to color.

### Operations

Filament provides the base shell, but theme tokens align it to Spinel Navy/Cloud/Amber. The exact-vehicle calendar uses text and line pattern for hold, pending, confirmed, Rent, maintenance, handover, and buffer. Mobile switches to the approved agenda representation.

## Imagery and identity

- Use only the five real vehicle image sets and client-approved driver/business imagery.
- No stock fleet photo may imply ownership.
- No invented ratings, review counts, licences, safety claims, or years in business.
- Logo/wordmark treatment waits for the approved brand asset; text fallback is “Spinellanka.”
- Photography should be documentary and useful: exterior, interior, luggage space, accessibility equipment, and handover context.

## Motion

- Motion explains state change, progress, drawer/dialog entrance, or route reordering.
- 120–220ms for local UI transitions; no autoplay hero motion or decorative parallax.
- Respect `prefers-reduced-motion` and preserve all information without animation.
- Countdown warnings are announced at meaningful thresholds without repeated screen-reader disruption.

## Accessibility acceptance

- WCAG 2.2 AA contrast for text, controls, focus, and status.
- Visible 3px focus ring with sufficient offset on both light and navy surfaces.
- Minimum 44×44px primary touch targets.
- Keyboard-complete service forms, stop reordering, dialogs, calendar detail, and admin actions.
- Programmatic labels, descriptions, error summaries, and live-status announcements.
- 200% zoom and 320px viewport without loss of action or information.
- No hover-only facts, color-only states, or image-embedded essential text.

## Initial design tokens

```text
color.canvas       #F8FAFC
color.surface      #FFFFFF
color.brand        #0C1445
color.brand.raised #101A52
color.accent       #FBBF24
color.text         #0F172A
color.text.muted   #475569
color.border       #CBD5E1
color.border.soft  #E2E8F0
font.ui            Inter Variable
space.unit         8px
radius.control     10px
radius.panel       12px
focus.width        3px
```

These tokens are the production starting point. High-fidelity screens may refine scale and component composition, but changing the 60/30/10 palette or approved behavioral hierarchy requires a recorded design decision.
