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

```sh
nvm install
nvm use
./scripts/check-environment
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci --ignore-scripts
npm run build
```

Use `composer dev` for Laravel, queue, and Vite processes. Never copy another maintainer's `.env` or application key.

## Shared Claude Code tools

Project settings commit Graft hooks, the Graft MCP server, EGM's exact marketplace commit, the Matt Pocock plugin switch, and the locked Modern Web Guidance source.

Install machine-level dependencies:

```sh
npm install --global @nanonets/graft@0.16.0
npx -y impeccable@4.9.1 install --providers=claude --scope=project
npx skills add juliusbrussee/caveman@caveman -g -y
npm install --global @caveman-ai/cli@2.1.0
```

Open the repository in Claude Code after installation. Project settings enable:

- EGM 0.1.1 from `google/elephant-goldfish-model` commit `70406c4d22cc7ea8299637c2c5b44625c48b3f08`.
- Matt Pocock skills from the official marketplace. Current verified plugin version is 1.3.1.
- Ponytail code review plugin pinned to `DietrichGebert/ponytail` `v5.3.3`.
- Modern Web Guidance from commit `64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65`; the skill is vendored in `.agents/skills/` and locked by `skills-lock.json`.
- Graft 0.16.0 through `.mcp.json` and committed hooks.

Run `./scripts/check-environment` again. Official marketplace plugin settings do not pin Matt Pocock's version. The check reports drift from 1.3.1 so both maintainers can align before work. Ponytail plugin baseline `v5.3.3` is verified against `.claude/settings.json` and the local plugin cache. The Skills CLI also lacks a committed Caveman content lock; keep its source unchanged during a shared work cycle and compare the installed `SKILL.md` when drift is reported.

Ask Matt is intentionally user-invoked: `/mattpocock-skills:ask-matt`. EGM writes design artifacts under `docs/designs/`. Use `/egm-lite` for explicit small-change protocols; use `/egm-brainstorm`, `/egm-draft`, `/egm-review`, and `/egm-resolve` for formal designs.

## Current audit note

As of 2026-11-18, npm reports `GHSA-fc5q-39xc-vjpf` through Vite's development-only glob dependencies. No patched `picomatch` release exists in the resolved ecosystem. Production dependencies report clean with `npm audit --omit=dev`. Recheck the full audit after each lockfile update; do not use `npm audit fix --force` because it proposes incompatible downgrades.

## Local services

The application defaults to database-backed sessions, cache, and queues. Start a queue worker during local booking-flow work. Schedule and worker behavior must match the cPanel operations plan before deployment.

Do not add production credentials to local docs, scripts, CI, or Git history.
