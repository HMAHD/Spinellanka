# Spinellanka

## Authority

Read `PRODUCT.md` for product truth and `docs/planning/` for approved architecture and delivery constraints. Client-approved changes in `docs/ux/08-acceptance-and-signoff.md` win conflicts.

Use only verified fleet records, rates, policies, legal text, reviews, support details, provider credentials, and imagery. Keep unavailable facts visibly absent. Preserve advance-only scheduling, exact-vehicle selection, `Asia/Colombo` schedule authority, 15-minute holds, and pending-until-staff-confirmation behavior.

## Repository workflow

- Query Graft before reading or searching application source. Use direct file tools for documentation or files Graft does not index.
- Run Modern Web Guidance before editing HTML, CSS, browser JavaScript, forms, accessibility, performance, or web-platform behavior. Apply retrieved fallbacks and cite applied guides in the delivery response.
- Run Impeccable for public UI and design-system work. Keep route strategy in `.impeccable/surfaces/`; finish with rendered desktop/mobile review and document the shipped system in `DESIGN.md` plus `.impeccable/design.json`.
- Ask Matt is user-invoked only through `/mattpocock-skills:ask-matt`; never invoke it automatically.
- Caveman mode persists only after the user invokes `/caveman`; keep repository prose, code comments, commits, and third-party text in normal English.
- EGM artifact directory: docs/designs
- Use full EGM for multi-component architecture: `/egm-brainstorm`, `/egm-draft`, `/egm-review`, then `/egm-resolve`. Use `/egm-lite` only for explicit small-change workflows and follow its gated state machine.

## Engineering boundaries

Laravel remains one modular monolith and one transactional database. Keep controllers, Livewire components, Filament pages, jobs, and webhooks thin. Put lifecycle, pricing, availability, payment, and audit rules behind domain/application services. Keep integrations provider-neutral and secrets outside Git.

Use PHP 8.3-compatible syntax. Prefer PHP 8.4 locally. Use Node 26 for frontend tooling. Production runs compiled assets and PHP only.

## Verification

Run before handoff:

```sh
./scripts/check-environment
composer validate --strict
composer verify
npm run format:check
npm run build
composer audit --locked
npm audit --omit=dev
```

For UI changes, also run the Impeccable detector, launch the app, inspect 1440px and 390px renders, and exercise keyboard, popover, select, and reduced-motion states.

Commit messages and pull request descriptions contain only project content. Do not add agent attribution, generated-by text, watermarks, or signatures.
