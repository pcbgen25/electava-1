# Electava Workspace

This module is the internal PHP workspace used by core admin, admin, employee, vendor, and service-team users.

## Stack

- PHP 8+
- MySQL / MariaDB
- Composer helper packages stored in `packages/`

## Important Folders

- `includes/` - auth, database, shared layout
- `core_admin/` - master control area
- `admin/` - approvals and team ops
- `employee/` - component creation, tasks, submissions
- `vendor/` - vendor-facing workspace pages
- `service_team/` - service handling
- `packages/` - Composer dependency output

## Local Run

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000
```

Default URL:

- `http://127.0.0.1:8000`

## Environment

Create `.env` from `.env.example` when needed.

Main keys:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

## Dependencies

Composer output is separated into `packages/` so it does not conflict with the vendor-role workspace pages in `vendor/`.

Install if needed:

```powershell
C:\xampp\php\php.exe composer.phar install --no-dev
```
