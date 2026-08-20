# Electava Site And Workspace Documentation

## Purpose

This document is a current-state working note for the full Electava project. It covers the public website, API bridge, internal workspace, main workflows, local setup, and the latest implemented internal tools. It is intended to be easy to edit later if features change.

## Project Structure

- `user/`
  Customer-facing website built with Next.js.
- `API/`
  Express API that reads marketplace and tracking data from MySQL.
- `workspace/`
  Internal multi-role PHP workspace for operations.
- `start-all.bat`
  Local launcher for website, API, and workspace.

## Technology Stack

### Public Website

- Framework: Next.js 16
- React: React 19
- Styling: app-level CSS plus component CSS
- Theme support: `next-themes`

### API Layer

- Runtime: Node.js
- Framework: Express 5
- Database client: `mysql2`
- CORS enabled

### Internal Workspace

- PHP application
- MySQL database
- Shared header/footer layout
- Role-based access control inside the workspace

## Local Development Setup

### Required Services

- MySQL on `127.0.0.1:3306`
- Website on `127.0.0.1:3000`
- API on `127.0.0.1:5000`
- Workspace on `127.0.0.1:8000`

### Main Run File

- [start-all.bat](/D:/Electava/Codex/electava-1/start-all.bat)

This launcher:

- starts the Node API from `API/`
- starts the Next.js site from `user/`
- starts the PHP workspace from `workspace/`
- uses `C:\xampp\php\php.exe` automatically when available

### Database Files

- Main workspace schema: [install.sql](/D:/Electava/Codex/electava-1/workspace/install.sql)
- Marketplace tracking and service token schema: [tracking.sql](/D:/Electava/Codex/electava-1/workspace/tracking.sql)
- Role migration history: [db_refactor.sql](/D:/Electava/Codex/electava-1/workspace/db_refactor.sql)

### Database Name

- `electava_workspace`

## Public Website

### Main Public Sections

Current route folders under `user/src/app/` include:

- `about`
- `account`
- `blog`
- `careers`
- `cart`
- `checkout`
- `contact`
- `forgot-password`
- `language`
- `login`
- `manufacturers`
- `products`
- `quotation`
- `quotations`
- `register`
- `resources`
- `search`
- `sell`
- `seller`

### Website Purpose

The public website serves as the customer-facing Electava marketplace and inquiry platform. It presents products, manufacturers, quotation/service inquiry flows, informational content, careers, and customer account/cart/checkout pages.

### Website Data Sources

The website uses:

- local mock/data files in `user/src/data/`
- API-fed dynamic product/category/manufacturer content from the Node API
- tracking and service token creation through API endpoints

## API Layer

### Main API File

- [api.js](/D:/Electava/Codex/electava-1/API/routes/api.js)

### Current API Endpoints

- `GET /api/components`
- `GET /api/components/:id`
- `GET /api/categories`
- `GET /api/manufacturers`
- `POST /api/tracking`
- `POST /api/service-token`
- `GET /api/careers`

### API Responsibilities

- maps MySQL component rows into frontend-ready product objects
- returns category/manufacturer lists
- stores marketplace page tracking events
- creates service inquiry tokens for customer follow-up
- returns active career records

### Important Data Returned

The component API currently supports:

- `partNumber`
- `electavaPartNumber`
- `manufacturer`
- `description`
- `price`
- quantity-based `priceTiers`
- `stock`
- `specs`
- `assetLinks`
- `datasheet`
- image URL

## Internal Workspace

### Workspace Purpose

The workspace is the internal operations system for Electava. It supports role-based workflows for:

- core administration
- team/admin operations
- employee submissions and component entry
- vendor product management
- service team handling of service requests and service tokens

### Shared Workspace Features

- login-protected internal portal
- role-based dashboard routing
- shared left navigation and top bar
- dark/light mode toggle in header
- notifications
- audit logging
- login logging

## Workspace Roles

### Core Admin

Main files:

- [index.php](/D:/Electava/Codex/electava-1/workspace/core_admin/index.php)
- [employees.php](/D:/Electava/Codex/electava-1/workspace/core_admin/employees.php)
- [service_tokens.php](/D:/Electava/Codex/electava-1/workspace/core_admin/service_tokens.php)

Current core admin areas:

- employee management
- service team creation and management
- projects
- domains
- task templates
- audit logs
- login logs
- marketplace tracking
- marketplace users
- service tokens
- careers
- reports
- permissions
- settings

### Admin

Current admin pages:

- dashboard
- tasks
- approvals
- team
- vendors
- reports

### Employee

Current employee pages:

- dashboard
- tasks
- submissions
- components
- services

### Vendor

Current vendor pages:

- dashboard
- products
- inventory
- purchase orders
- company profile

### Service Team

New dedicated role added in the latest update.

Current service team pages:

- [index.php](/D:/Electava/Codex/electava-1/workspace/service_team/index.php)
- [requests.php](/D:/Electava/Codex/electava-1/workspace/service_team/requests.php)
- [tokens.php](/D:/Electava/Codex/electava-1/workspace/service_team/tokens.php)

Service team purpose:

- handle service requests from users/customers
- capture requirement notes
- coordinate with vendors where needed
- store verification notes
- reply to marketplace service token inquiries
- take ownership of requests/tokens and manage queue status

## Component Management Flow

Main file:

- [components.php](/D:/Electava/Codex/electava-1/workspace/employee/components.php)

### Current Employee Component Features

- full-page add/edit/view flow
- mandatory fields:
  - Part Number
  - Electava Part Number
  - Manufacturer
  - Description
- reusable manufacturer and category dropdown values
- quantity-based pricing rows
- stock tracking
- optional specifications with add/delete row controls
- assets support for:
  - documents
  - images
  - EDA/CAD models
- upload from local or add URL entries
- current assets block
- selected upload preview in current assets
- buttons:
  - View
  - Cancel
  - Save
  - Next

### Bulk Employee Submission

Employees can:

- create many draft components
- use bulk select on the list
- submit selected drafts for approval in one action

## Vendor Product Flow

Main files:

- [products.php](/D:/Electava/Codex/electava-1/workspace/vendor/products.php)
- [products_page.php](/D:/Electava/Codex/electava-1/workspace/vendor/products_page.php)
- [product_template.php](/D:/Electava/Codex/electava-1/workspace/vendor/product_template.php)

### Current Vendor Product Features

- separate `Single Product` and `Bulk Upload` entry modes
- single product entry page
- bulk upload page
- sample sheet download for bulk upload
- Excel-friendly CSV import flow
- manufacturer/category auto-create from uploaded bulk rows
- draft save flow before approval
- stock updates
- price updates
- submit draft products for approval

## Service Operations

### Employee Service Requests

Original service request handling exists in:

- [services.php](/D:/Electava/Codex/electava-1/workspace/employee/services.php)

### New Service Team Queue

The new service-team queue extends service handling with:

- request ownership
- service token ownership
- requirement notes
- vendor notes
- verification notes
- internal notes
- last-contact tracking
- customer reply flow on service tokens

### Current Service Token Workflow

Service tokens are created from the public site through:

- `POST /api/service-token`

Then handled in workspace by:

- core admin service token area
- new service team token queue

## User And Auth Notes

### Default Internal Logins

- Core Admin:
  - `admin@electava.com`
  - `Electava@2025`
- Service Team:
  - `service.team@electava.com`
  - `Electava@2025`
- Vendor:
  - `vendor1@electava.com`
  - `Electava@2025`

### Dashboard Routing

Role routing is managed in:

- [auth.php](/D:/Electava/Codex/electava-1/workspace/includes/auth.php)

Current dashboard mappings include:

- `core_admin -> /core_admin/`
- `admin -> /admin/`
- `service_team -> /service_team/`
- `employee -> /employee/`
- `vendor -> /vendor/`

## Latest Important Internal Updates

### Workspace Theme

- dark/light mode added to shared workspace header
- simplified theme toggle styled near notification bell

### Employee Components

- mandatory component fields enforced
- dynamic manufacturer/category creation
- multi-asset handling improved
- quantity pricing added
- bulk submit-for-approval added

### Vendor Products

- single and bulk product entry separated
- sample bulk upload template added
- CSV-based bulk vendor import added

### Service Team

- new `service_team` role added
- new service-team dashboard and queue pages added
- service request and service token notes/assignment tracking added

## Known Practical Notes

- local email reply flow in service-team tokens updates status and audit correctly
- real outbound mail still depends on local PHP mail/SMTP configuration
- the public API may return empty component results if active marketplace component data is not seeded yet
- this documentation reflects the current local implementation, not a final locked product spec

## Recommended Files To Edit If Features Change

- Public website routes and UI:
  - `user/src/app/`
  - `user/src/components/`
- API behavior:
  - `API/routes/api.js`
  - `API/server.js`
  - `API/config/`
- Workspace shared auth/layout:
  - `workspace/includes/auth.php`
  - `workspace/includes/header.php`
  - `workspace/includes/footer.php`
- Workspace role pages:
  - `workspace/core_admin/`
  - `workspace/admin/`
  - `workspace/employee/`
  - `workspace/vendor/`
  - `workspace/service_team/`
- Schema:
  - `workspace/install.sql`
  - `workspace/tracking.sql`

## Suggested Future Documentation Updates

If you want this document to stay current over time, update it whenever you change:

- routes
- user roles
- approval flows
- database fields
- bulk upload formats
- asset handling
- login accounts
- local startup commands

## Current Documentation Status

This document is now the main editable summary for the full Electava site and workspace as currently implemented in this repository.
