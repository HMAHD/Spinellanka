---
name: Sri Lanka Travel Mobility Review Artifact
description: A neutral ruled storyboard for reviewing transport-booking structure without implying final brand or production UI decisions.
colors:
  paper: "#ffffff"
  paper-soft: "#f3f3f3"
  paper-mid: "#e3e3e3"
  ink: "#111111"
  ink-soft: "#4b4b4b"
  rule: "#181818"
  rule-soft: "#9a9a9a"
  focus: "#111111"
typography:
  display:
    fontFamily: "Avenir Next, Avenir, Helvetica Neue, Helvetica, Arial, sans-serif"
    fontSize: "clamp(3.1rem, 7vw, 6.7rem)"
    fontWeight: 700
    lineHeight: 0.88
    letterSpacing: "-0.04em"
  headline:
    fontFamily: "Avenir Next, Avenir, Helvetica Neue, Helvetica, Arial, sans-serif"
    fontSize: "clamp(2rem, 5vw, 4.5rem)"
    fontWeight: 700
    lineHeight: 0.98
    letterSpacing: "-0.035em"
  title:
    fontFamily: "Avenir Next, Avenir, Helvetica Neue, Helvetica, Arial, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.15
  body:
    fontFamily: "Avenir Next, Avenir, Helvetica Neue, Helvetica, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "SFMono-Regular, Consolas, Liberation Mono, monospace"
    fontSize: "0.8125rem"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "0.04em"
rounded:
  square: "0"
  circular: "50%"
spacing:
  "1": "0.25rem"
  "2": "0.5rem"
  "3": "0.75rem"
  "4": "1rem"
  "5": "1.5rem"
  "6": "2rem"
  "7": "3rem"
  "8": "4.5rem"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    rounded: "{rounded.square}"
    padding: "0.65rem 1rem"
    height: "2.75rem"
  button-primary-hover:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
  button-secondary:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.square}"
    padding: "0.65rem 1rem"
    height: "2.75rem"
  input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-soft}"
    rounded: "{rounded.square}"
    padding: "0.55rem 0.65rem"
    height: "2.6rem"
  tag:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.label}"
    rounded: "{rounded.square}"
    padding: "0.12rem 0.35rem"
---

# Design System: Sri Lanka Travel Mobility Review Artifact

## Overview

**Creative North Star: "The Ruled Operations Storyboard"**

This system documents a deliberately low-fidelity, code-led client-review artifact. It feels like a working transport-operations document: paper-white planes, graphite text, hard rules, compact identifiers, and inspectable screen frames. Its authority comes from explicit structure and evidence, not polish. The visual language helps stakeholders approve workflows while continuously signaling that branding, commercial values, real fleet content, production components, and final implementation remain unresolved.

This is not the future customer-facing identity and must not be treated as a production UI design system. It may guide extensions to this review walkthrough and related planning artifacts only. The shipped surface rejects glossy travel imagery, gradients, soft cards, floating pills, decorative icons, and brand color because those devices could make placeholders appear approved.

**Key Characteristics:**
- Neutral grayscale surfaces with black, gray, and white carrying every distinction.
- Square ruled frames, dense annotation, and visible document numbering.
- Humanist sans-serif reading text paired with monospaced identifiers and operational data.
- Responsive studies that preserve meaning by reflowing, stacking, or switching representation.
- Placeholders that announce missing evidence instead of simulating final content.
- A unified five-vehicle schedule whose line styles encode operational state without color.

## Colors

The shipped palette is intentionally achromatic: white and two paper grays establish planes, while graphite and rule grays carry text, hierarchy, state, and interaction.

### Primary
- **Graphite Ink** (`paper` / `ink` tokens): The only high-emphasis pairing. Ink fills primary actions, active steps, the review rail, and confirmed decision markers; paper supplies their text and the principal canvas.

### Neutral
- **Soft Paper** (`paper-soft`): The page field, inset wireframe backing, summary panels, and skeletal asset fields.
- **Mid Paper** (`paper-mid`): Browser bars, warnings, annotations, calendar headers, and maintenance fields.
- **Soft Ink** (`ink-soft`): Explanatory copy, secondary facts, placeholder text, and de-emphasized labels.
- **Hard Rule** (`rule`): Structural borders, section dividers, timeline marks, and state-bearing line work.
- **Soft Rule** (`rule-soft`): Internal separators and low-emphasis table or list divisions.
- **Focus Graphite** (`focus`): The visible keyboard-focus outline; it deliberately matches the darkest ink.

### Named Rules

**The Grayscale Evidence Rule.** Use only the documented achromatic tokens; distinction comes from label, weight, fill, and line pattern, never hue alone.

**The Approval Boundary Rule.** Do not introduce brand color, photography, decorative imagery, or polished marketing effects into this review system; they would imply decisions the client has not supplied.

## Typography

**Display Font:** Avenir Next with Avenir, Helvetica Neue, Helvetica, Arial, and sans-serif fallbacks  
**Body Font:** Avenir Next with the same system-safe fallback chain  
**Label/Mono Font:** SFMono-Regular with Consolas, Liberation Mono, and monospace fallbacks

**Character:** The humanist sans-serif keeps long explanatory material direct and readable. Monospaced text marks frame IDs, references, times, status codes, indices, and other operational data so the document reads as a working specification rather than branded editorial.

### Hierarchy
- **Display** (bold, fluid display token, compact line height): Reserved for the cover thesis; it is tightly set and constrained to a short measure.
- **Headline** (bold, fluid headline token, compact line height): Numbered section headings; scale rather than color separates major chapters.
- **Title** (bold, title token): Screen-study headings, form titles, and prominent in-frame decisions.
- **Body** (regular, body token): Explanations and review copy; long introductions remain constrained by the shipped `74ch` measure.
- **Label** (bold mono, label token, uppercase where the component establishes it): Frame labels, state codes, references, times, tags, and annotations. Tabular numerals are used for indices and countdown-like values.

### Named Rules

**The Data Voice Rule.** Use the mono stack for identifiers, state codes, references, times, numeric indices, and annotations—not for ordinary paragraphs.

**The Scale-Not-Color Rule.** Establish reading hierarchy with type size, weight, spacing, and rules before considering fill reversal.

## Layout

The outer document uses a sticky `16rem` review rail and a flexible main plane. Main content is capped at `94rem`, with responsive horizontal padding from `1.25rem` to `5rem`. Sections are separated by a four-pixel top rule and use a numbered two-column heading lockup. Screen studies sit on a 12-column grid: full-width frames span all columns, wide frames span eight, narrow frames four, and halves six.

The spacing rhythm is the shipped eight-step quarter-rem scale. Tight labels and separators use steps 1–3; component interiors use steps 3–5; section and chapter rhythm uses steps 6–8. Reading copy uses a maximum measure of `74ch`. The desktop booking spine intentionally scrolls horizontally rather than compressing nine steps below recognition.

At `76rem`, the rail narrows to `13rem`, the four-service contract becomes two columns, all study frames become full width, and three-column workspaces stack. At `58rem`, the sticky rail becomes a horizontal scrollable section navigator; hero, form, private-booking, and admin layouts collapse to one column; sticky summaries become static; dense vehicle, KPI, attention, and client-input structures reflow. At `38rem`, service contracts and forms become single-column, compact status strips stack vertically, and vehicle, route, proposal, and payment layouts simplify to one column. The full desktop fleet timeline is not squeezed onto mobile: the shipped mobile counterpart is a chronological five-vehicle agenda with the same state and conflict information. Print removes navigation and review actions, resets the gray field to white, creates section page breaks, and avoids splitting frames and annotations.

**The Representation-Change Rule.** When a desktop mechanism cannot remain legible, preserve its facts and decisions in a mobile-native representation rather than shrinking it.

## Elevation & Depth

The system is entirely flat and contains no shadows, gradients, blur, translucency, or simulated elevation. Hierarchy comes from white, soft-paper, and mid-paper planes plus one-, two-, three-, and four-pixel rules. Filled black states indicate emphasis or current position, while dashed, dotted, and double borders distinguish evidence and operational status.

**The Ruled-Plane Rule.** Separate levels with background tone and explicit rules; never add drop shadows to these review artifacts.

## Shapes

Corners are square throughout: buttons, fields, frames, panels, cards, tables, tags, and navigation all use zero radius. Circles appear only when their meaning depends on the primitive: browser dots, radio controls, and route-stop nodes. Borders are not decoration; width and pattern carry hierarchy or state. One-pixel rules divide content, two-pixel rules define actions and primary frames, three- or four-pixel rules mark priority or exception, dashed rules identify client-supplied placeholders and pending items, dotted rules identify temporary holds, and double rules identify maintenance, deposits, errors, or conflict-like states.

**The Square Frame Rule.** Keep structural containers and controls square; do not soften the planning artifact into rounded product cards or pills.

**The Line Carries Meaning Rule.** Preserve established solid, dashed, dotted, and double patterns whenever the corresponding state is shown, and pair every pattern with text.

## Components

### Buttons
- **Shape:** Square, high-contrast, and compact, with a two-pixel border and a minimum height of `2.75rem`.
- **Primary:** Graphite fill, paper text, bold label, and `0.65rem 1rem` padding.
- **Hover / Focus:** Hover reverses primary fill to paper; keyboard focus uses a three-pixel graphite outline with a three-pixel offset. There is no animated lift.
- **Secondary:** Paper fill with graphite text and border; hover changes only to mid paper.
- **Disabled:** Mid-paper fill, muted text, soft-rule border, and a not-allowed cursor. Disabled appearance must be paired with meaningful text or workflow state.

### Tags
- **Style:** Compact mono labels with square outlines. Client-required tags are dashed; decisions reverse to ink fill with paper text; general state tags use a three-pixel double border.
- **State:** Tags reinforce adjacent wording. They never carry status through border style or fill alone.

### Cards / Containers
- **Corner Style:** Square.
- **Background:** Paper for primary content, soft paper for inset summaries or placeholders, and mid paper for annotations and caution states.
- **Shadow Strategy:** None; see the ruled-plane rule.
- **Border:** One-pixel internal containers, two-pixel wireframe and approval frames, heavier rules only for an established priority or state.
- **Internal Padding:** Primarily spacing steps 3–5.

### Inputs / Fields
- **Style:** Paper fill, one-pixel hard rule, square corners, soft-ink placeholder content, and a minimum height of `2.6rem`.
- **Focus:** The global three-pixel offset focus outline applies to real interactive controls.
- **Error / Disabled:** Error treatment uses explicit text plus a four-pixel double rule; disabled actions use the documented disabled button state. Static `.fake-input` blocks remain visibly schematic and are not evidence of production form behavior.

### Navigation
- **Style:** Desktop review navigation is a sticky black rail with mono indices, compact sans-serif labels, and one-pixel row separators. Hover reverses the row to paper and ink. Below `58rem`, it becomes a horizontally scrollable strip; ancillary document links and rail status are hidden. A skip link moves on-screen when focused.

### Ruled Review Frame
- **Style:** Each study is introduced by a two-part mono frame label, enclosed by a two-pixel ruled wireframe with soft-paper inset, and followed by a mid-paper annotation with a two-pixel top rule.
- **Purpose:** Keep screen purpose, review decision, and unresolved evidence physically attached. The frame is documentation, not a reusable production card.

### Placeholder Field
- **Style:** Soft-paper field, dashed hard-rule border, centered mono uppercase copy, and an explicit owner such as `CLIENT` or a bracketed value.
- **Purpose:** Reserve space without fabricating identity, imagery, prices, policy, vehicle facts, or proof.

### Five-Vehicle Schedule
- **Desktop mechanism:** A horizontally scrollable ruled grid aligns five exact vehicle/driver rows against time columns. Solid blocks are confirmed work, dashed blocks are pending, dotted blocks are 15-minute checkout holds, double mid-paper blocks are maintenance, and thin muted blocks are buffers. Rent rows explicitly state that no driver is assigned.
- **Mobile mechanism:** A chronological agenda retains all five exact vehicles, tied-driver context, times, holds, pending work, maintenance, buffers, manual bookings, deadlines, and conflicts. Active conflicts remain visible by default and include a plain-language resolution path.
- **Accessibility:** Every state has text; line pattern and tone are redundant cues. The desktop calendar wrapper is keyboard focusable and horizontally scrollable.

## Do's and Don'ts

### Do:
- **Do** keep extensions neutral, square, ruled, and visibly low fidelity.
- **Do** label every status in text and use fill or line pattern only as a redundant cue.
- **Do** preserve real responsive behavior: stack, reflow, scroll, or change representation according to the shipped breakpoints.
- **Do** mark unresolved assets and values with dashed skeletal fields, explicit ownership, and bracketed placeholders.
- **Do** keep the five exact vehicles, their driver relationship, blocking windows, and conflicts visible in schedule studies.
- **Do** preserve keyboard focus, skip navigation, reduced-motion behavior, adequate control height, and print-safe section framing.

### Don't:
- **Don't** treat this artifact as final brand guidance or reuse its wireframe components as production implementation decisions.
- **Don't** invent a product name, logo, commercial value, rate, policy, claim, review, vehicle fact, provider, or image.
- **Don't** add brand color, gradients, shadows, decorative imagery, floating pills, or rounded cards to make the artifact feel finished.
- **Don't** encode status through color, gray tone, or border pattern alone.
- **Don't** squeeze the desktop fleet timeline into a narrow viewport; use the shipped agenda representation.
- **Don't** replace explicit placeholders with plausible-looking content before verified client evidence exists.
