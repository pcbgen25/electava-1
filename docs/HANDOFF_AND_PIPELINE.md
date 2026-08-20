# Electava Handoff And Pipeline

This document defines the cleaner handoff structure for local installs, testing-team rollout, vendor-use rollout, and future live deployment work.

## Product Separation

The project is now treated as three main modules:

- `user/` - public marketplace website
- `API/` - shared API service
- `workspace/` - internal workspace for all roles, including vendor access

Vendor users do not need a separate codebase. They use the `workspace/` module through the vendor role pages and vendor login.

## Clean Install Flow

For a new laptop or testing machine:

1. Install Node.js
2. Install PHP / XAMPP
3. Install or start MySQL / MariaDB
4. Run [scripts/setup-local.ps1](/D:/Electava/Codex/electava-1/scripts/setup-local.ps1)
5. Run [scripts/start-local.ps1](/D:/Electava/Codex/electava-1/scripts/start-local.ps1)
6. Run [scripts/validate-local.ps1](/D:/Electava/Codex/electava-1/scripts/validate-local.ps1)

## Runtime Configuration

Each module now has an environment template:

- [API/.env.example](/D:/Electava/Codex/electava-1/API/.env.example)
- [user/.env.example](/D:/Electava/Codex/electava-1/user/.env.example)
- [workspace/.env.example](/D:/Electava/Codex/electava-1/workspace/.env.example)

This keeps local, testing, and live values separated from code.

## Website Reliability Notes

The public website should use `src/lib/api.js` for API access instead of hardcoded localhost URLs. That keeps the same code usable on:

- your local machine
- testing team laptops
- staging
- live deployment

## Workspace Reliability Notes

The workspace now supports database values from `workspace/.env`.

Composer helper packages are separated into `workspace/packages/` so they do not conflict with the vendor-role workspace pages in `workspace/vendor/`.

## Validation Pipeline

Use [scripts/validate-local.ps1](/D:/Electava/Codex/electava-1/scripts/validate-local.ps1) as the repeatable local validation step.

It is intended to become the base release pipeline because it covers:

- PHP syntax validation
- API syntax validation
- optional frontend production build
- endpoint smoke checks if the stack is already running

## Testing Team Handoff

Use [scripts/package-handoff.ps1](/D:/Electava/Codex/electava-1/scripts/package-handoff.ps1) to create a clean delivery package.

The handoff package excludes runtime/build noise such as:

- `node_modules`
- `.next`
- local log folders
- codex temp logs
- `.env` files

This makes the code drop cleaner for:

- internal testing team
- laptop installs
- archive snapshots
- release candidates

## What Still Counts As Runtime Noise

These should not be treated as source-of-truth application code:

- `logs/`
- `runtime-logs/`
- `.codex-logs/`
- `.codex-local-logs/`
- local `.env` files
- generated build folders

They are excluded by [.gitignore](/D:/Electava/Codex/electava-1/.gitignore) and by the handoff packaging flow.

## Recommended Release Process

1. Make code changes in the repo
2. Run setup if a fresh machine is involved
3. Run validation
4. Start the local stack and smoke-test key pages
5. Create a handoff package
6. Give the package to testing team
7. After testing passes, use the same validated structure for live deployment work

## Recommended Next Hardening Step

The next strong reliability step would be a proper CI file for automated checks on every change. The local pipeline foundation is now in place for that.
