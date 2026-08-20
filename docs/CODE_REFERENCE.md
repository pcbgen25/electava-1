# Electava Code Reference

This reference explains the current code by file and responsibility. It is intended for developers who need to modify the project without first reverse-engineering the whole repository.

## 1. Root Project Files

| File | Responsibility |
|---|---|
| `README.md` | Short overview of the three product modules and local scripts. |
| `SITE_AND_WORKSPACE_DOCUMENTATION.md` | Existing product/workspace guide. |
| `PROJECT_PROMPT_AND_FLOW.md` | Prompt-style project context and key file map. |
| `GENERAL_SITE_WORKSPACE_FLOW.md` | General site and workspace flow notes. |
| `start-all.bat` | Batch wrapper for local start. |
| `setup-local.bat` | Batch wrapper for setup. |
| `stop-local.bat` | Batch wrapper for shutdown. |
| `validate-local.bat` | Batch wrapper for validation. |
| `package-handoff.bat` | Batch wrapper for handoff package creation. |
| `schema.txt`, `show_tables.txt` | Captured database inspection outputs. |
| `error_output.txt`, `error_full.txt` | Captured local error outputs. |

## 2. Scripts

| File | Responsibility |
|---|---|
| `scripts/setup-local.ps1` | Installs/prepares local dependencies and database. |
| `scripts/start-local.ps1` | Starts API, website, and PHP workspace. |
| `scripts/stop-local.ps1` | Stops local services. |
| `scripts/validate-local.ps1` | Runs local syntax and smoke validation. |
| `scripts/package-handoff.ps1` | Creates a handoff package. |

`scripts/start-local.ps1` is the most important operational script. It resolves PHP and npm, checks whether ports are already listening, optionally starts MySQL, and writes service output to `runtime-logs/`.

## 3. API Code

### 3.1 `API/package.json`

Defines the API package:

- `dev`: `node server.js`
- `start`: `node server.js`
- `check`: syntax checks `server.js`, `config/db.js`, and `routes/api.js`

Dependencies:

- `express`
- `cors`
- `dotenv`
- `mysql2`

### 3.2 `API/server.js`

Responsibilities:

- load environment variables
- create Express app
- configure CORS
- parse JSON bodies
- mount `API/routes/api.js` at `/api`
- expose root endpoint `/`
- expose health endpoint `/health`
- listen on configured port

Key routes:

- `GET /`
- `GET /health`
- all `/api/*` routes from `routes/api.js`

### 3.3 `API/config/db.js`

Responsibilities:

- load environment variables
- create a mysql2 promise pool
- export the pool to route modules

Important behavior:

- uses database `electava_workspace` by default
- uses a 2-second connection timeout
- no automatic schema initialization is done by the API; schema setup is handled through the workspace SQL/bootstrap flow

### 3.4 `API/routes/api.js`

This is the main API route module.

#### `mapComponent(row)`

Maps a joined database row into the frontend product structure.

It parses:

- `row.specifications` into `specs`
- `row.asset_links` into `assetLinks`
- `row.quantity_breaks` into `priceTiers`

It derives:

- `id` as `prod-` plus padded database id
- `category` and `subcategory` as slugs from category names
- fallback price tiers from `row.price`

#### `GET /api/components`

Reads:

- `components`
- `manufacturers`
- `categories`
- parent categories

Returns mapped product records.

Current filter:

```sql
WHERE c.status = 'active' OR c.status IS NULL OR c.status = 'draft'
```

#### `GET /api/components/:id`

Accepts:

- raw numeric database id
- `prod-001` style id

Returns one mapped component or `404`.

#### `GET /api/categories`

Reads all rows from `categories`, builds a parent/subcategory hierarchy, and returns an array shaped for the website filter UI.

Each parent category includes:

- `id`
- `db_id`
- `name`
- `icon`
- `description`
- `subcategories`

#### `GET /api/manufacturers`

Returns raw manufacturer rows from `manufacturers`.

#### `POST /api/tracking`

Inserts public website tracking data into `marketplace_tracking`.

Expected body:

```json
{
  "sessionId": "string",
  "deviceType": "string",
  "browser": "string",
  "pageVisited": "string"
}
```

The API adds IP address and user agent from the request.

#### `POST /api/service-token`

Creates a service token row in `service_tokens`.

Expected body:

```json
{
  "userEmail": "customer@example.com",
  "serviceType": "pcb-design",
  "details": "{\"fullName\":\"...\"}"
}
```

Response:

```json
{
  "success": true,
  "token": "SRV-2026-ABCD"
}
```

#### `GET /api/careers`

Reads active careers and returns:

- `id`
- `title`
- `team`
- `location`
- `type`
- `summary`
- `highlights`

`highlights` is parsed from `highlights_json`.

## 4. Website Code

### 4.1 Configuration

| File | Responsibility |
|---|---|
| `user/package.json` | Next.js scripts and dependencies. |
| `user/next.config.mjs` | Next runtime/build config. |
| `user/jsconfig.json` | Path aliases, including `@/`. |
| `user/src/app/layout.js` | Root HTML, metadata, providers, header/footer shell. |
| `user/src/app/globals.css` | Global design tokens and base styles. |

### 4.2 API Helper

`user/src/lib/api.js`

Exports:

- `getApiBaseUrl()`
- `getApiUrl(path)`

Use this helper for all calls to the Node API. It normalizes the base URL and ensures the route is under `/api`.

### 4.3 Context Providers

#### `user/src/context/CartContext.js`

Client-only cart state.

Use when a component needs:

- current cart items
- add/remove/update item actions
- subtotal or total calculation

Important limitation:

- cart state is memory-only and resets on page refresh.

#### `user/src/context/MarketplaceAuthContext.js`

Client-only demo marketplace auth state.

Use when a component needs:

- local demo user profile
- sign in/register actions
- profile update
- sign out

Important limitation:

- this is not PHP workspace auth and does not persist to MySQL.

### 4.4 Shared Components

| File | Responsibility |
|---|---|
| `user/src/components/Header/Header.jsx` | Site header, navigation, search interactions, auth display, cart count. |
| `user/src/components/Footer/Footer.jsx` | Site footer and shared footer links/content. |
| `user/src/components/ProductCard/ProductCard.jsx` | Product card used in listing/home/related products. |
| `user/src/components/BlogCard/BlogCard.jsx` | Blog listing card. |
| `user/src/components/ThemeProvider/ThemeProvider.jsx` | Theme provider wrapper. |
| `user/src/components/ThemeToggle/ThemeToggle.jsx` | Theme toggle UI. |
| `user/src/components/ClientTracker/ClientTracker.jsx` | Sends page tracking to `POST /api/tracking`. |
| `user/src/components/BrandWordmark/BrandWordmark.jsx` | Brand wordmark component. |

### 4.5 Website Route Files

| Route | File | Data Source | Notes |
|---|---|---|---|
| `/` | `user/src/app/page.js` | API | Server component. Fetches components and categories. |
| `/products` | `user/src/app/products/page.js` | API | Client component. Filters/sorts/paginates live API data. |
| `/products/[id]` | `user/src/app/products/[id]/page.js` | Local `products.js` | Client component. Uses cart context. |
| `/cart` | `user/src/app/cart/page.js` | Cart context | Client cart UI. |
| `/checkout` | `user/src/app/checkout/page.js` | Cart context | Front-end checkout flow. |
| `/quotation` | `user/src/app/quotation/page.js` | Form + API | Posts service token requests. |
| `/careers` | `user/src/app/careers/page.js` | API | Fetches active careers. |
| `/contact` | `user/src/app/contact/page.js` | Static/form UI | Uses `ContactInquiryForm`. |
| `/login` | `user/src/app/login/page.js` | MarketplaceAuthContext | Demo login. |
| `/register` | `user/src/app/register/page.js` | MarketplaceAuthContext | Demo registration. |
| `/account` | `user/src/app/account/page.js` | MarketplaceAuthContext | Demo account profile. |
| `/search` | `user/src/app/search/page.js` | Local `products.js` | Client search. |
| `/blog` | `user/src/app/blog/page.js` | Local `blogPosts.js` | Blog index. |
| `/blog/[slug]` | `user/src/app/blog/[slug]/page.js` | Local `blogPosts.js` | Blog detail. |
| `/about` | `user/src/app/about/page.js` | Static | About page. |
| `/sell` | `user/src/app/sell/page.js` | Static | Informational seller page. |
| `/manufacturers` | `user/src/app/manufacturers/page.jsx` | Static/client | Manufacturer page. |
| `/resources` | `user/src/app/resources/page.jsx` | Static | Resources page. |
| `/forgot-password` | `user/src/app/forgot-password/page.js` | Form UI | Front-end form. |

### 4.6 Local Website Data Files

| File | Responsibility |
|---|---|
| `user/src/data/products.js` | Static product catalog used by product detail and search. Also exports stock and formatting helpers. |
| `user/src/data/categories.js` | Static category data and lookup helpers. |
| `user/src/data/blogPosts.js` | Static blog posts, slug lookup, and category helper. |
| `user/src/data/localeOptions.js` | Local locale/country options and lookup helper. |

## 5. Workspace Code

### 5.1 Shared Workspace Includes

#### `workspace/includes/db.php`

Responsibilities:

- load `.env`
- connect to MySQL with PDO
- create missing database
- run `install.sql` during first initialization
- normalize schema for `employees`

Do not bypass this include on workspace pages that need database access.

#### `workspace/includes/auth.php`

Responsibilities:

- session start
- login check
- role gate
- dashboard URL mapping
- current user normalization
- audit logging
- login logging
- notification helpers
- status/priority badge helpers
- domain access helper

Protected pages should use `requireRole(...)` near the top.

#### `workspace/includes/header.php`

Responsibilities:

- shared workspace shell
- role-aware navigation
- topbar/sidebar markup
- shared CSS/JS includes

#### `workspace/includes/footer.php`

Responsibilities:

- closing layout markup
- toast helper
- confirm helper
- small fetch wrapper for workspace API calls

### 5.2 Workspace Entry And Auth Files

| File | Responsibility |
|---|---|
| `workspace/login.php` | Login form and session creation. |
| `workspace/logout.php` | Logout/session clearing. |
| `workspace/register.php` | Registration-related workspace entry. |
| `workspace/index.php` | Workspace index/redirect. |
| `workspace/notifications.php` | Notification listing/interaction. |

### 5.3 Core Admin Files

| File | Responsibility |
|---|---|
| `workspace/core_admin/index.php` | Core admin dashboard. |
| `workspace/core_admin/employees.php` | Create/manage internal users across roles. |
| `workspace/core_admin/domains.php` | Domain management. |
| `workspace/core_admin/permissions.php` | Module permission management. |
| `workspace/core_admin/settings.php` | System settings UI. |
| `workspace/core_admin/reports.php` | Reports area. |
| `workspace/core_admin/logs.php` | Audit log view. |
| `workspace/core_admin/login_logs.php` | Login/attendance reporting. |
| `workspace/core_admin/sessions.php` | Session-related management. |
| `workspace/core_admin/users.php` | Marketplace user/service token related view and replies. |
| `workspace/core_admin/marketplace_tracking.php` | Marketplace tracking reports. |
| `workspace/core_admin/service_tokens.php` | Service token management. |
| `workspace/core_admin/service_token_reply.php` | Service token reply handler. |
| `workspace/core_admin/careers.php` | Careers management. |
| `workspace/core_admin/projects.php` | Project management. |
| `workspace/core_admin/templates.php` | Task/template management. |

### 5.4 Admin Files

| File | Responsibility |
|---|---|
| `workspace/admin/index.php` | Admin dashboard. |
| `workspace/admin/team.php` | Team management. |
| `workspace/admin/tasks.php` | Task creation/assignment. |
| `workspace/admin/approvals.php` | Task/component approval operations. |
| `workspace/admin/reports.php` | Admin reports. |
| `workspace/admin/vendors.php` | Vendor management. |

### 5.5 Employee Files

| File | Responsibility |
|---|---|
| `workspace/employee/index.php` | Employee dashboard. |
| `workspace/employee/components.php` | Main component draft/create/edit/upload/submit workflow. |
| `workspace/employee/tasks.php` | Employee assigned task work. |
| `workspace/employee/submissions.php` | Employee submission history. |
| `workspace/employee/services.php` | Employee service work page. |
| `workspace/employee/profile.php` | Employee profile. |
| `workspace/employee/login_times.php` | Employee login/attendance view. |

#### `workspace/employee/components.php`

This is one of the largest and most important workspace files.

Major PHP helper groups:

- component schema extension helpers
- specification JSON parsing
- asset links parsing
- quantity break parsing
- upload path and deletion helpers
- file metadata insert/delete helpers
- AJAX detection
- component URL builder

Major UI/client behavior:

- dynamic specification rows
- dynamic price tier rows
- dynamic document/image/CAD link rows
- upload preview
- current asset rendering
- component form mode switching
- bulk draft selection
- modal category/manufacturer creation

### 5.6 Vendor Files

| File | Responsibility |
|---|---|
| `workspace/vendor/index.php` | Vendor dashboard. |
| `workspace/vendor/products.php` | Vendor product route entry. |
| `workspace/vendor/products_page.php` | Vendor single product and bulk CSV upload workflow. |
| `workspace/vendor/product_template.php` | Downloadable product template. |
| `workspace/vendor/inventory.php` | Vendor inventory view. |
| `workspace/vendor/orders.php` | Vendor orders view. |
| `workspace/vendor/profile.php` | Vendor profile management. |

#### `workspace/vendor/products_page.php`

Major helpers:

- `normalizeBulkHeader`
- `detectCsvDelimiter`
- `buildBulkColumnMap`
- `findOrCreateManufacturerId`
- `findOrCreateCategoryId`
- `createVendorComponent`

This file creates component rows for vendor-submitted products and supports both single entry and CSV upload.

### 5.7 Service Team Files

| File | Responsibility |
|---|---|
| `workspace/service_team/index.php` | Service team dashboard. |
| `workspace/service_team/requests.php` | Service request queue/details/status handling. |
| `workspace/service_team/tokens.php` | Service token queue/details/status/reply handling. |

### 5.8 Workspace API Files

| File | Responsibility |
|---|---|
| `workspace/api/tasks.php` | Workspace task API actions. |
| `workspace/api/approvals.php` | Workspace approval API actions. |
| `workspace/api/dashboard/stats.php` | Dashboard stats endpoint. |
| `workspace/api/vendor/orders.php` | Vendor orders endpoint. |

These are PHP endpoints for workspace interactions, separate from the Node API in `API/`.

## 6. SQL And Database Files

| File | Responsibility |
|---|---|
| `workspace/install.sql` | Main database creation, tables, and seed data. |
| `workspace/tracking.sql` | Marketplace tracking, service token, and careers tables. |
| `workspace/db_refactor.sql` | Additional customer/refactor tables. |
| `workspace/fix_password.sql` | Password repair SQL. |
| `workspace/schema.txt` | Captured schema output. |
| `workspace/schema_out.txt` | Captured schema output. |
| `workspace/employees_schema.txt` | Captured employees schema details. |
| `workspace/test_db.php` | Database test helper. |
| `workspace/test_login.php` | Login test helper. |
| `workspace/check_env.php` | Environment inspection helper. |
| `workspace/check_hash_full.php` | Password hash inspection helper. |
| `workspace/generate_test_data.php` | Test data generation helper. |
| `workspace/db_fix.php` | Database repair helper. |
| `workspace/final_db_fix.php` | Database repair helper. |

## 7. Code Change Notes By Area

### 7.1 Changing Product Listing

Check these files:

- `user/src/app/products/page.js`
- `user/src/components/ProductCard/ProductCard.jsx`
- `user/src/lib/api.js`
- `API/routes/api.js`
- `workspace/employee/components.php`
- `workspace/vendor/products_page.php`
- `workspace/install.sql`

Preserve these response fields unless all consumers are updated:

- `id`
- `db_id`
- `name`
- `manufacturer`
- `partNumber`
- `electavaPartNumber`
- `category`
- `subcategory`
- `price`
- `priceTiers`
- `stock`
- `specs`
- `assetLinks`
- `image`
- `datasheet`

### 7.2 Changing Product Detail

Current product detail uses local data:

- `user/src/app/products/[id]/page.js`
- `user/src/data/products.js`
- `user/src/data/categories.js`

If converting it to live API data, also check:

- `API/routes/api.js`
- `user/src/lib/api.js`
- cart item shape in `CartContext`

### 7.3 Changing Quotation Or Service Token Behavior

Check:

- `user/src/app/quotation/QuotationRequestForm.jsx`
- `API/routes/api.js`
- `workspace/tracking.sql`
- `workspace/service_team/tokens.php`
- `workspace/core_admin/service_tokens.php`
- `workspace/core_admin/service_token_reply.php`

### 7.4 Changing Workspace Auth Or Roles

Check:

- `workspace/includes/auth.php`
- `workspace/includes/db.php`
- `workspace/login.php`
- every top-level `requireRole(...)`
- `workspace/install.sql`

Be careful with role naming:

- legacy SQL may mention `core` and `sub_core`
- runtime code normalizes to `core_admin` and `admin`

### 7.5 Changing Checkout Or Orders

Check:

- `user/src/app/checkout/page.js`
- `user/src/context/CartContext.js`
- `workspace/vendor/orders.php`
- `workspace/api/vendor/orders.php`
- `workspace/install.sql` table `purchase_orders`

Current checkout does not create persistent orders. Adding persistence will require either a Node API endpoint, a PHP endpoint, or a new shared order service design.

### 7.6 Changing Careers

Check:

- `user/src/app/careers/page.js`
- `user/src/app/careers/CareerApplicationForm.jsx`
- `API/routes/api.js`
- `workspace/core_admin/careers.php`
- `workspace/tracking.sql`

Current career listings are database-backed. Career applications are not clearly persisted by the current public site flow.

## 8. Testing And Verification Reference

After API changes:

```powershell
cd API
npm.cmd run check
```

After website changes:

```powershell
cd user
npm.cmd run build
```

After workspace PHP changes, at minimum:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\validate-local.ps1
```

Useful smoke URLs:

- `http://127.0.0.1:3000`
- `http://127.0.0.1:3000/products`
- `http://127.0.0.1:3000/quotation`
- `http://127.0.0.1:5000/health`
- `http://127.0.0.1:5000/api/components`
- `http://127.0.0.1:8000/login.php`

