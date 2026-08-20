# Electava Unified Ecosystem

This repository contains the full Electava local stack:

- `user/` - customer-facing Next.js website
- `API/` - Node.js/Express API bridge
- `workspace/` - PHP/MySQL internal workspace

For the full current project and workspace documentation, see:

- [SITE_AND_WORKSPACE_DOCUMENTATION.md](/D:/Electava/Codex/electava-1/SITE_AND_WORKSPACE_DOCUMENTATION.md)
- [PROJECT_PROMPT_AND_FLOW.md](/D:/Electava/Codex/electava-1/PROJECT_PROMPT_AND_FLOW.md)
- [GENERAL_SITE_WORKSPACE_FLOW.md](/D:/Electava/Codex/electava-1/GENERAL_SITE_WORKSPACE_FLOW.md)

## Local Run

1. Start MySQL on `127.0.0.1:3306`
2. Run [start-all.bat](/D:/Electava/Codex/electava-1/start-all.bat)

Local URLs:

- Website: [http://127.0.0.1:3000](http://127.0.0.1:3000)
- API: [http://127.0.0.1:5000](http://127.0.0.1:5000)
- Workspace: [http://127.0.0.1:8000](http://127.0.0.1:8000)

## Database

- Database name: `electava_workspace`
- Main schema file: [workspace/install.sql](/D:/Electava/Codex/electava-1/workspace/install.sql)
- Marketplace tracking/service tokens schema: [workspace/tracking.sql](/D:/Electava/Codex/electava-1/workspace/tracking.sql)

## Default Internal Logins

- Core Admin: `admin@electava.com` / `Electava@2025`
- Service Team: `service.team@electava.com` / `Electava@2025`
- Vendor: `vendor1@electava.com` / `Electava@2025`

## Notes

- `start-all.bat` now targets the current repo folders: `API`, `user`, and `workspace`
- If PHP is not on PATH, the launcher will use `C:\xampp\php\php.exe` when available
- This README is intentionally short; use the detailed documentation file for the current feature summary
- Use the prompt-and-flow file when you want AI-ready context, key file references, or flowcharts
- Use the general site/workspace flow file when you want only business/page flow without code references
