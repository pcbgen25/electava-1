# Electava Developer Documentation Index

This documentation pack describes the Electava repository as it exists in this workspace. It is based only on the local site, API, workspace code, SQL files, scripts, and existing project notes.

## Start Here

| Document | Purpose |
|---|---|
| [DEEP_SYSTEM_DOCUMENTATION.md](DEEP_SYSTEM_DOCUMENTATION.md) | End-to-end architecture, runtime, business flows, data flow, database model, operations, and known implementation boundaries. |
| [CODE_REFERENCE.md](CODE_REFERENCE.md) | File-by-file developer reference for the public website, API bridge, PHP workspace, scripts, and data files. |
| [HANDOFF_AND_PIPELINE.md](HANDOFF_AND_PIPELINE.md) | Existing handoff and packaging notes. |
| [../README.md](../README.md) | Short repository overview and local script list. |
| [../SITE_AND_WORKSPACE_DOCUMENTATION.md](../SITE_AND_WORKSPACE_DOCUMENTATION.md) | Existing current-state product and workspace guide. |
| [../PROJECT_PROMPT_AND_FLOW.md](../PROJECT_PROMPT_AND_FLOW.md) | Existing prompt-style reference for developers and AI agents. |
| [../GENERAL_SITE_WORKSPACE_FLOW.md](../GENERAL_SITE_WORKSPACE_FLOW.md) | Existing site/workspace flow notes. |

## Local Services

| Service | Path | Default URL | Main Command |
|---|---|---|---|
| Public website | `user/` | `http://127.0.0.1:3000` | `npm.cmd run dev` |
| API bridge | `API/` | `http://127.0.0.1:5000` | `node server.js` |
| Workspace | `workspace/` | `http://127.0.0.1:8000` | `C:\xampp\php\php.exe -S 127.0.0.1:8000` |

The shared launcher is:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

## Most Important Source Files

| Area | Files |
|---|---|
| Website API helper | `user/src/lib/api.js` |
| Website homepage | `user/src/app/page.js` |
| Website product listing | `user/src/app/products/page.js` |
| Website product detail | `user/src/app/products/[id]/page.js` |
| Cart state | `user/src/context/CartContext.js` |
| Marketplace demo auth state | `user/src/context/MarketplaceAuthContext.js` |
| API entry point | `API/server.js` |
| API routes | `API/routes/api.js` |
| API database pool | `API/config/db.js` |
| Workspace database bootstrap | `workspace/includes/db.php` |
| Workspace auth and role helpers | `workspace/includes/auth.php` |
| Workspace login | `workspace/login.php` |
| Employee component workflow | `workspace/employee/components.php` |
| Vendor product workflow | `workspace/vendor/products_page.php` |
| Service team token workflow | `workspace/service_team/tokens.php` |
| Core admin user management | `workspace/core_admin/employees.php` |
| Main schema | `workspace/install.sql` |
| Tracking/token/careers schema | `workspace/tracking.sql` |

## Recommended Reading Order

1. Read [DEEP_SYSTEM_DOCUMENTATION.md](DEEP_SYSTEM_DOCUMENTATION.md) sections 1 through 5.
2. Read [CODE_REFERENCE.md](CODE_REFERENCE.md) for the area you will edit.
3. Check the related SQL table in `workspace/install.sql` or `workspace/tracking.sql`.
4. Run the local stack and test the affected URL.
5. If changing API response shapes, check all website fetches that use `getApiUrl`.

