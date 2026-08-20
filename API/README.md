# Electava API

This module is the shared API bridge between the public website and the workspace database.

## Stack

- Node.js
- Express 5
- MySQL2

## Important Files

- `server.js` - API entrypoint
- `routes/api.js` - marketplace, careers, tracking, and service-token routes
- `config/db.js` - MySQL connection pool
- `.env.example` - API runtime config template

## Local Run

```powershell
npm.cmd install
npm.cmd run dev
```

Default URL:

- `http://127.0.0.1:5000`
- health endpoint: `http://127.0.0.1:5000/health`

## Environment

Create `.env` from `.env.example` when needed.

Main keys:

- `PORT`
- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `CORS_ORIGIN`

## Validation

```powershell
npm.cmd run check
```
