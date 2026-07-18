# POS System — Technical Documentation

Developer-facing overview of the architecture, conventions, and setup for the POS
platform. Read this to get productive quickly and to understand the important
design decisions before changing anything.

---

## 1. High-level architecture

Two separate repositories, deployed independently:

```
┌──────────────────────┐        HTTPS / JSON        ┌──────────────────────────┐
│  pos-frontend         │  ───────────────────────▶  │  pos-api (pos-backend)    │
│  Angular 21 SPA       │   Bearer token (Sanctum)   │  Laravel 12 REST API      │
│  standalone comps     │  ◀───────────────────────  │  MySQL 8                  │
└──────────────────────┘   {success,message,data}    └──────────────────────────┘
```

- **Frontend** (`pos-frontend`): Angular 21, standalone components, lazy-loaded routes, RxJS. Talks only to the API over `/api`.
- **Backend** (`pos-api`, GitHub repo `pos-backend`): Laravel 12 REST API, MySQL, token auth via Laravel Sanctum, RBAC via spatie/laravel-permission.
- **Local host**: Laragon (Apache + MySQL 8) on Windows. The API is served under `http://localhost/pos/pos-api/public`; the SPA runs on `http://localhost:4200` and proxies `/api` to the API.

### Stack & versions
| Layer | Tech |
|-------|------|
| Backend | PHP 8.2+, Laravel 12, Sanctum 4, spatie/laravel-permission 6 |
| Database | MySQL 8 |
| Frontend | Angular 21, TypeScript 5.9, RxJS 7.8 |
| Auth | Sanctum personal access tokens (Bearer) |

### Rough size
~34 Eloquent models · ~32 API controllers · ~45 migrations · ~76 API routes · ~41 Angular components · ~32 Angular services.

---

## 2. Multi-tenancy (read this first)

The platform is **single-database, row-scoped multi-tenancy**.

- Most tables carry a `tenant_id`.
- Models use the `App\Models\Concerns\BelongsToTenant` trait, which:
  - adds a **global scope** filtering every query by the current tenant, and
  - **auto-stamps** `tenant_id` on insert.
- The "current tenant" comes from `App\Support\TenantContext`:
  - **Regular users** are pinned to their own `tenant_id`.
  - **Super-admin** with no tenant selected sees **all** tenants (scope bypassed); with a tenant picked (via `?tenant_id=` / the header UI) they "view as" that tenant.
- Creating rows as a super-admin without a selected tenant is rejected with a clear 422 (avoids orphan rows).

**Implication:** when writing queries/tests in tinker (no HTTP tenant context), use `Model::withoutGlobalScopes()` or set the tenant context explicitly, or you'll see cross-tenant or empty results.

Branches (`branch_id`) further scope data **within** a tenant (POS, orders, cash registers, staff). A user may be pinned to one branch or allowed to switch.

---

## 3. Auth & authorization

- **Authentication**: `POST /api/auth/login` returns a Sanctum token; send it as `Authorization: Bearer <token>`. `GET /api/auth/me` returns the current user (incl. `is_super_admin`, roles, permissions). Two-factor is supported.
- **Authorization**: spatie/laravel-permission. Permissions follow `{module}.{action}` (e.g. `orders.create`, `reports.view`). Routes are gated with `permission:{name}` middleware.
- Roles are seeded in `database/seeders/RolesAndPermissionsSeeder.php`:
  - `super-admin` — all permissions (platform).
  - `admin` — everything except `tenants.*` and `plans.*` (those are platform-only).
  - `cashier` — POS-relevant view/create.
  - `editor` — catalog + reports.
- The permission list is the single source of truth in `RolesAndPermissionsSeeder::MODULES`. **Add a module there** when you introduce new permissions, then re-seed.
- ⚠️ **Gotcha**: `AuthService.hasPermission()` (frontend) returns `true` for any user with the `admin` role. Platform-only screens (Tenants, Plans) are therefore gated on the real `is_super_admin` flag, **not** on a permission — see `SidebarComponent`.

---

## 4. Data model (key entities)

Core sales:
- **products** (+ `product_variants`, `product_modifier_group`, `modifier_groups`/`modifiers`, `deal_items`, `product_cost_history`, `branch_product` for per-branch stock)
- **orders** → **order_items** (→ `order_item_modifiers`) + **order_payments** (split tenders)
- **customers**, **categories**, **coupons**, **delivery_zones**

Operations & inventory:
- **branches**, **counters** (POS stations), **tables**, **waiters**, **riders**
- **cash_registers** (drawer shifts) — opening/expected/actual cash, per counter/branch
- **raw_materials** + **bom_items** (recipes) — auto stock consumption
- **suppliers**, **purchase_orders** (+ items), **stock_adjustments**

Platform / staff / compliance:
- **tenants**, **plans** (+ `plan_pricing_tiers`)
- **users**, roles/permissions (spatie tables), **audit_logs**
- **time_entries**, **payslips**, **fbr_submissions**

Notable order columns: `service_type` (dine_in/take_away/delivery), `counter_id`, `created_by_user_id` (cashier), `payment_method`, `status` (pending/completed/held/voided/refunded/cancelled), tax/service/discount/tip amounts. Order items carry `discount_percent`/`discount_amount` (per-line) and `unit_cost_at_sale` (COGS snapshot).

---

## 5. API conventions

- **Base**: all routes live in `routes/api.php` under the `/api` prefix.
- **Response envelope** (always):
  ```json
  { "success": true, "message": "…", "data": { … } }
  ```
  Lists are paginated Laravel paginators inside `data` (`data.data`, `current_page`, …). Many index endpoints accept `?all=1` to return a flat array (used by POS dropdowns), plus `?search=`, `?status=`, `?branch_id=`, `?per_page=`.
- **Validation**: FormRequests in `app/Http/Requests/Api/` (or inline `$request->validate()` for smaller controllers). Validation failures return `422` with errors in `data`.
- **Reports**: `GET /api/reports/sales` and `GET /api/reports/cash` accept `date_from`, `date_to`, `branch_id`, `counter_id`.

### Key domain flows
- **Order creation** (`OrderController@store`): resolves variant + modifier pricing, applies per-line discount, tax/service/order-discount/coupon/tip, writes order + items + payments, then **deducts product stock** and **consumes BOM raw materials**. Held orders defer stock/BOM until resumed.
- **Void / refund / cancel**: restore product stock **and** BOM raw materials.
- **Cash register** (`CashRegisterController`): `open` (float, one per counter/branch), `close` (count → expected vs actual variance), `zReport`.
- **BOM** (`BomController`): `GET /products/{product}/bom`, `PUT /products/{product}/bom` (replace recipe with `items[]`).

---

## 6. Frontend structure

```
src/app/
  core/
    services/      # one service per resource, wrap ApiService (unwraps the envelope)
    models/        # TypeScript interfaces mirroring API payloads
    guards/        # authGuard, guestGuard, permissionGuard
    interceptors/  # attaches the Bearer token
  features/        # one folder per screen (pos, reports, customers, admin/*, …)
  layout/          # header (tenant/branch pickers), sidebar (role-gated nav)
  shared/          # pipes (money), toast, confirm dialog, spinners, bulk-select
```

- **Standalone components** (no NgModules); routes are lazy `loadComponent` in `app.routes.ts`, most guarded by `permissionGuard` with `data.permission`.
- **`ApiService`** centralises HTTP and unwraps `{success,message,data}` → returns `data`.
- **Design tokens** live in `src/styles.scss` (`:root` CSS variables); the theme is a violet-teal palette (`--brand-*`). Prefer tokens over hardcoded hex.
- **API base** is `/api` (see `environment.ts`), proxied in dev by `proxy.conf.json` to the Laravel app. Product image URLs are absolute and served by Apache from `storage/`.

---

## 7. Local setup (Laragon / Windows)

**Backend**
```bash
cd pos-api
composer install
cp .env.example .env   # if needed
php artisan key:generate
# .env: set DB_* and APP_URL to include the sub-path, e.g.
#   APP_URL=http://localhost/pos/pos-api/public
php artisan migrate
php artisan db:seed          # roles/permissions + demo data
php artisan storage:link     # REQUIRED for product images
```

**Frontend**
```bash
cd pos-frontend
npm install
npm start        # ng serve on http://localhost:4200 (proxies /api)
```

Make sure **Apache** (port 80) and **MySQL** (port 3306) are running in Laragon.

### ⚠️ Environment gotchas (bit us before)
- **`public/storage` symlink**: `php artisan storage:link` must point at *this* project's `storage/app/public`. If the project folder was moved, the old symlink dangles and **all product images 404/403** — delete and re-run `storage:link`.
- **`APP_URL`** must include the full sub-path (`/pos/pos-api/public`). `Storage::disk('public')->url()` builds image URLs from it; a wrong `APP_URL` yields broken image links. Run `php artisan config:clear` after changing it.
- **Line endings**: repo is committed with LF; on Windows Git converts to CRLF on checkout — the "LF will be replaced by CRLF" warnings are harmless.
- Manual MySQL start outside Laragon may fail on a missing component DLL — prefer Laragon's "Start All".

---

## 8. Conventions & tips

- Keep new API resources consistent: envelope response, tenant-scoped model, permission-gated routes, a matching Angular service + model.
- When adding a permission-guarded module: add to `RolesAndPermissionsSeeder::MODULES`, re-seed, add route + guard, add sidebar entry (role-gated), and remember admins auto-inherit non-platform permissions.
- Financial/audit records (completed/voided/refunded orders) are protected from casual deletion — use void/refund to preserve the trail.
- Test end-to-end without a browser by driving the HTTP kernel in `php artisan tinker` (build a `Request`, `app(Kernel::class)->handle($request)` with a Bearer token) — this exercises real routes, middleware, and controllers.

---

## 9. Repos

- Frontend: `github.com/Awaisshabbir08/pos-frontend`
- Backend: `github.com/Awaisshabbir08/pos-backend`
