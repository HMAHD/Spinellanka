---
name: Spinellanka Public Shell
description: A calm Sri Lankan mobility desk for clear, advance-booked travel decisions.
colors:
  cloud-canvas: "#f8fafc"
  cloud-step: "#eef2f7"
  spinel-navy: "#0c1445"
  spinel-navy-soft: "#18235d"
  ceylon-amber: "#fbbf24"
  ceylon-amber-strong: "#e6a800"
  primary-ink: "#0f172a"
  muted-ink: "#475569"
  quiet-line: "#cbd5e1"
  paper: "#ffffff"
  navy-reading: "#dbe4ff"
  navy-muted: "#c7d2fe"
  navy-line: "#7180bf"
typography:
  display:
    fontFamily: "Allo, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(3.5rem, 5.4vw, 5.75rem)"
    fontWeight: 680
    lineHeight: 0.98
    letterSpacing: "-0.035em"
  headline:
    fontFamily: "Allo, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(2.75rem, 4.6vw, 4.75rem)"
    fontWeight: 680
    lineHeight: 0.98
    letterSpacing: "-0.035em"
  title:
    fontFamily: "Allo, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(1.75rem, 2.5vw, 2.5rem)"
    fontWeight: 680
    lineHeight: 0.98
    letterSpacing: "-0.035em"
  body:
    fontFamily: "Allo, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Allo, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 650
    lineHeight: 1.6
rounded:
  functional: "0.75rem"
spacing:
  xs: "0.5rem"
  sm: "0.75rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
  2xl: "2.5rem"
  page-margin: "clamp(1rem, 2.2vw, 2rem)"
  content-pad: "clamp(1.5rem, 5vw, 5.5rem)"
  section-mobile: "7rem"
components:
  button-primary:
    backgroundColor: "{colors.ceylon-amber}"
    textColor: "{colors.spinel-navy}"
    typography: "{typography.label}"
    rounded: "{rounded.functional}"
    padding: "0 1.25rem"
    height: "3.25rem"
  button-primary-hover:
    backgroundColor: "{colors.ceylon-amber-strong}"
    textColor: "{colors.spinel-navy}"
  button-navigation:
    backgroundColor: "{colors.spinel-navy}"
    textColor: "{colors.paper}"
    typography: "{typography.label}"
    rounded: "{rounded.functional}"
    padding: "0 1rem"
    height: "2.75rem"
  field-booking:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.primary-ink}"
    borderColor: "{colors.quiet-line}"
    rounded: "0.5rem"
    padding: "0 1rem"
    height: "3rem"
  fact-tile:
    backgroundColor: "{colors.cloud-step}"
    textColor: "{colors.primary-ink}"
    rounded: "{rounded.functional}"
    padding: "1.5rem"
  process-field:
    backgroundColor: "{colors.spinel-navy}"
    textColor: "{colors.paper}"
    rounded: "{rounded.functional}"
    padding: "clamp(2rem, 5vw, 5.5rem)"
---

# Design System: Spinellanka Public Shell

## Overview

**Creative North Star: "The Calm Sri Lankan Mobility Desk"**

Spinellanka feels prepared, trustworthy, and internationally legible. Cloud-light reading space and deep navy working surfaces divide information from action; amber appears only where a traveler must choose, focus, or move forward. The result is a measured transport service rather than a live-dispatch interface or a glossy travel campaign.

The current public shell expresses this world as a booking-first editorial sequence. A light first field pairs the transport promise with exact-vehicle and staff-confirmation facts; broad ruled service rows expose all four choices directly; navy fields then carry the exact-vehicle principle and request sequence. The system relies on strong scale, open space, tonal fields, and plainly worded facts. It does not use decoration to stand in for unavailable evidence.

**Key Characteristics:**
- Deep navy work surfaces set against a quiet cloud canvas.
- Ceylon Amber is a scarce decision color, not general decoration.
- One self-hosted variable sans family carries display, reading, and interface roles.
- Generous vertical rhythm and restrained functional corners keep the shell calm.
- Flat tonal grouping replaces ornamental depth.
- Fleet and business imagery appears only after the client supplies and approves it.

## Colors

The palette separates calm reading space from dependable decision surfaces, with one warm accent reserved for consequential interaction.

### Primary
- **Spinel Navy:** The main work-surface color for vehicle-principle and process fields, service heroes, strong actions, and footer anchors.
- **Soft Spinel Navy:** The raised navy state used inside dark controls and as the hover state for navy actions.

### Secondary
- **Ceylon Amber:** The scarce action and focus color used for primary controls, selection, wordmark emphasis, markers, and text selection.
- **Strong Ceylon Amber:** The darker interactive state used for amber hover fills, list markers, and underlines on light surfaces.

### Neutral
- **Cloud Canvas:** The default page field and broad reading surface.
- **Cloud Step:** A quiet grouping surface for facts, service rows, and supporting records.
- **Paper:** A higher-contrast light surface used sparingly to alternate rows or clarify content within a field.
- **Primary Ink:** The default text color on light surfaces.
- **Muted Ink:** Supporting copy and secondary labels on light surfaces.
- **Quiet Line:** The boundary color for the mobile menu and other light-surface control edges.
- **Navy Reading:** The higher-contrast supporting text color on navy.
- **Navy Muted:** The quieter supporting text color on navy.
- **Navy Line:** The blue-violet boundary color (`#7180bf`) for dark-surface form controls.

### Named Rules

**The Amber Decision Rule.** Amber marks selection, focus, or the next meaningful action; it never becomes a large background field.

**The Evidence Before Imagery Rule.** Use only client-supplied, approved fleet and business photography; the required launch imagery is a delivery gate, not an invitation to use stock or invented assets.

## Typography

**Display Font:** Allo (with UI sans-serif, system UI, -apple-system, Segoe UI, and generic sans-serif fallbacks)  
**Body Font:** Allo (with the same fallback stack)  
**Label Font:** Allo (with the same fallback stack)

**Character:** A single variable family (Allo 1.0.3 variable upright WOFF2 under SIL OFL 1.1) makes the public shell direct and internationally familiar. Tight, heavy headings provide confidence while regular reading copy remains open and calm.

### Hierarchy
- **Display** (680, fluid 3.5–5.75rem, 0.98): First-view titles and major service-page names; mobile uses a separately observed fluid range to preserve the same authority.
- **Headline** (680, fluid 2.75–4.75rem, 0.98): Major section headings, generally held to about 12 characters per line.
- **Title** (680, fluid 1.75–2.5rem, 0.98): Service-row and compact content titles.
- **Body** (400, 1.0625rem, 1.6): Default reading copy; important leads rise fluidly to 1.25rem and remain near 42 characters wide.
- **Label** (650, 0.9375rem, 1.6): Navigation, form labels, and compact interface text; primary actions increase to 750 rather than changing family or case.

### Named Rules

**The One-Family Rule.** Build hierarchy with the Allo variable scale, weight, spacing, and tonal contrast; do not introduce a display face to manufacture personality.

**The Short-Line Rule.** Keep explanatory copy near the shipped 38–42-character measures and let headings balance naturally rather than stretching either across the field.

## Layout

The public home uses a centered editorial sequence capped at 90rem. Its first field is an asymmetric two-column grid: the title and two actions lead, while exact-vehicle and staff-confirmation facts align as ruled rows. The service decision follows immediately as four full-width ruled links rather than a select, card grid, or JavaScript-controlled chooser. A contained navy exact-vehicle statement, a navy request-process field, and one closing action complete the path.

Spacing grows from an 8px rhythm with 4px adjustments. Page edges use a fluid margin, headings and supporting copy retain compact measures, and major sections use generous but purposeful separation. At 64rem, editorial grids collapse to one column while service actions retain a compact second track. Below 52rem, the header becomes a 4.5rem mobile bar with a vertical popover menu; service rows, actions, facts, and process content stack in DOM order. Below 29rem, primary actions become full width.

**The Reorder Without Loss Rule.** Responsive layouts may reorder and stack the shell, but the title, four-service choice, exact-vehicle fact, and pending-confirmation fact remain visible and legible.

## Elevation & Depth

The shipped public shell has no box-shadow vocabulary. Depth comes from contained navy decision fields, ruled light rows, stepped tonal surfaces, and the controlled top-layer overlap of the mobile navigation popover.

**The Flat Field Rule.** Separate levels with tonal fields, spacing, and position; do not add shadows merely to make a panel look more finished.

## Shapes

Functional controls, compact panels, dark mobile stages, and process fields share one gently controlled 0.75rem corner. Larger service rows and broad section fields remain square, which keeps them reading as paper slips and structural planes rather than a grid of floating cards. Borders are uncommon; when a control needs a boundary, it uses a clear one- or two-pixel line rather than decorative framing.

**The Functional Corner Rule.** Use the shared corner only on controls or contained working surfaces; leave broad rows and section planes square.

## Components

### Buttons
- **Shape:** Functional corners with a minimum 3.25rem primary-action height; compact navigation actions use a 2.75rem target.
- **Primary:** Amber fill, navy text, strong label weight, and 1.25rem horizontal padding.
- **Hover / Focus:** Hover darkens the amber fill; press scales to 97%; keyboard focus uses a 3px amber outline with 4px offset. State transitions run from 140–180ms and are removed for reduced motion.
- **Navigation:** Navy fill and paper text on light surfaces; hover shifts to Soft Spinel Navy.
- **Text actions:** Navy text with a Strong Ceylon Amber underline; hover moves the text to Muted Ink and lets the underline inherit.

### Cards / Containers
- **Fact tiles:** Cloud Step, functional corners, 1.5rem padding, and no shadow on supporting content pages.
- **Service rows:** Full-width Cloud Canvas links separated by Quiet Line rules; three tracks align service name, factual summary, and action on desktop, then collapse without reordering. Hover may shift the row to Paper.
- **Vehicle principle:** A contained Spinel Navy statement plane uses one functional corner and a single amber phrase; its neighboring explanation remains on Cloud Canvas.
- **Request process:** A contained Spinel Navy field pairs a concise heading with three numbered ruled rows and no nested cards.

### Inputs / Fields
- **Booking fields:** Paper fill, Quiet Line boundary, 0.5rem functional corners, inherited Allo type at 1rem minimum, and at least 48px height.
- **Focus:** The global 3px amber ring sits 4px outside navigation and public-shell controls; booking fields retain their explicit high-contrast field focus treatment.
- **Labels:** Persistent and positioned above the control; placeholder-only labeling is not part of the system.

### Navigation
- The desktop header is one compact Cloud Canvas row: a Spinel Navy wordmark with an amber suffix, short navy links, a current-page underline, and one contained navy action.
- Below 52rem the brand and Menu trigger lead on Cloud Canvas. JavaScript enhances the same navigation tree into a bordered vertical popover with light-dismiss and Escape behavior; every link stays inside the viewport with at least a 44px touch target.

### Public Service Index

The signature decision component is a four-row ruled list on Cloud Canvas. Every row is one ordinary link containing the service name, approved factual summary, and a consistent “View service” action. It uses no select, cards, JavaScript state, or hidden fallback copy. Desktop aligns the three parts across a shared grid; tablet and mobile collapse the tracks while preserving DOM and keyboard order.

## Do's and Don'ts

### Do:
- **Do** reserve amber for decisions, focus, selection, and small wayfinding details.
- **Do** keep dark-surface reading text in the dedicated Navy Reading or Navy Muted tones rather than reducing opacity arbitrarily.
- **Do** use generous section space, compact line measures, and the 8px rhythm to create calm without adding decoration.
- **Do** preserve the title-first mobile order, accessible menu fallback, visible focus ring, and reduced-motion behavior.
- **Do** use the text “Spinellanka” until the client supplies and approves a final identity asset.
- **Do** treat approved, client-supplied fleet and business photography as a launch gate for image-bearing surfaces.

### Don't:
- **Don't** use stock vehicles, invented fleet details, unverified claims, or decorative travel imagery to fill missing evidence.
- **Don't** turn amber into a page field, body-text color, or general-purpose decoration.
- **Don't** add gradients, ornamental shadows, pill containers, or a second display typeface to make the restrained shell feel busier.
- **Don't** convert the service index into a generic equal-card grid; preserve its broad paper-row character.
- **Don't** hide the service choice or its four direct links behind JavaScript-only behavior.
- **Don't** animate essential information; all reveals and transitions must yield to reduced-motion preferences.
