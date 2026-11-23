# Shared development environment

This repository supports two maintainers. Lockfiles and committed agent settings are the source of truth. Run checks before changing global tools.

## Required runtimes

- Claude Code 2.1.327 or newer. EGM requires at least 2.1.271.
- PHP 8.4 preferred; PHP 8.3 supported and tested.
- Composer 2.x.
- Node 26.x from `.nvmrc`.
- npm 11.x. `package-lock.json` is authoritative.
- MariaDB 10.11 for production-like local work, or MySQL 8.0.
- Bun 1.3.5 is an optional script runner. npm remains the canonical installer and lockfile owner.

## First checkout

Run from a clean clone. Stop if `.env` already exists; inspect and preserve it rather than overwriting it or regenerating `APP_KEY`.

```sh
nvm install
nvm use
./scripts/check-environment

test ! -e .env || {
  printf '%s\n' 'STOP: .env already exists.' >&2
  exit 1
}

composer install --no-interaction --prefer-dist
cp .env.example .env
grep -Eq '^APP_KEY=[[:space:]]*$' .env || {
  printf '%s\n' 'STOP: APP_KEY is not empty.' >&2
  exit 1
}
php artisan key:generate
npm ci --ignore-scripts
php artisan config:clear
php artisan migrate
npm run build
```

Use `composer dev` for Laravel, queue, and Vite processes. Never copy another maintainer's `.env` or application key.

## Shared Claude Code tools

Project settings commit Graft hooks, the Graft MCP server, EGM's exact marketplace commit, the Matt Pocock plugin switch, and the locked Modern Web Guidance source.

Install machine-level dependencies under Node 26:

```sh
npm install --global @nanonets/graft@0.16.0
npx skills add juliusbrussee/caveman@caveman -g -y
npm install --global @caveman-ai/cli@2.1.0
```

After cloning, enter the verified repository root and install/align the project-scoped Impeccable baseline. The installer must leave the clean checkout unchanged:

```sh
git rev-parse --show-toplevel
npx -y impeccable@4.9.1 install --providers=claude --scope=project
git diff --exit-code
test -z "$(git status --porcelain)"
```

Inside Claude Code, install the official Ponytail reviewer and verify source `DietrichGebert/ponytail`, version v5.3.3:

```text
/plugin marketplace add DietrichGebert/ponytail
/plugin install ponytail@ponytail
```

Open the repository in Claude Code after installation. Project settings enable:

- EGM 0.1.1 from `google/elephant-goldfish-model` commit `70406c4d22cc7ea8299637c2c5b44625c48b3f08`.
- Matt Pocock skills from the official marketplace. Current verified plugin version is 1.3.1.
- Modern Web Guidance from commit `64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65`; the skill is vendored in `.agents/skills/` and locked by `skills-lock.json`.
- Graft 0.16.0 through `.mcp.json` and committed hooks.
- Ponytail from official `DietrichGebert/ponytail`, held at reviewed baseline v5.3.3 for phase reviews.

Run `./scripts/check-environment` again. Official marketplace plugin settings do not pin Matt Pocock's or Ponytail's installed version. The check reports Matt drift from 1.3.1; until Phase 01 adds automated Ponytail verification, inspect `/plugin` and stop if Ponytail is not official v5.3.3. The Skills CLI also lacks a committed Caveman content lock; keep its source unchanged during a shared work cycle and compare the installed `SKILL.md` when drift is reported.

Ask Matt is intentionally user-invoked: `/mattpocock-skills:ask-matt`. EGM writes design artifacts under `docs/designs/`. Use `/egm-lite` for explicit small-change protocols; use `/egm-brainstorm`, `/egm-draft`, `/egm-review`, and `/egm-resolve` for formal designs.

## Conditional Windows/XAMPP use

WSL2 remains the verified Windows baseline. Native XAMPP may be used only when its actual CLI PHP is 8.3+ with all required extensions and its database is MySQL 8.0 or MariaDB 10.11+ with InnoDB/transactions. Composer, Artisan, and any Apache PHP module must resolve to the same supported PHP major/minor. If any capability fails, use WSL2; never lower project constraints, switch to SQLite, or mix hidden PHP installations.

The preferred XAMPP-compatible run uses only its accepted database service while `composer dev` starts Laravel, the queue listener, and Vite at `http://127.0.0.1:8000`. Keep the repository outside `htdocs`. If Apache is separately approved, its virtual-host document root must be exactly the repository's `public/` directory; the queue and Vite still run separately. The complete copyable setup prompt is in `roadmap.md` under “Standalone AI prompt — guided maintainer setup with XAMPP.”

## Current audit note

As of 2026-11-18, npm reports `GHSA-fc5q-39xc-vjpf` through Vite's development-only glob dependencies. No patched `picomatch` release exists in the resolved ecosystem. Production dependencies report clean with `npm audit --omit=dev`. Recheck the full audit after each lockfile update; do not use `npm audit fix --force` because it proposes incompatible downgrades.

## Local services

The application defaults to database-backed sessions, cache, and queues. Start a queue worker during local booking-flow work. Schedule and worker behavior must match the cPanel operations plan before deployment.

Do not add production credentials to local docs, scripts, CI, or Git history.
