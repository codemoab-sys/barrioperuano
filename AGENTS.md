# AGENTS.md

## What this is

Laravel 12 restaurant POS system ("Restaurante Barrio Peruano"). Handles orders, tables, kitchen display, billing (SUNAT/NubeFact electronic invoicing), delivery, reservations, and reporting. Backend: PHP 8.2+ / Laravel. Frontend: Vite + Tailwind CSS + Bootstrap 5 + Sass.

## Quick commands

- **Full dev stack:** `composer dev` — runs artisan serve, queue worker, pail logs, and vite concurrently.
- **Install from scratch:** `composer setup` — installs deps, copies .env, generates key, runs migrations, npm install + build.
- **Run tests:** `composer test` — clears config cache then runs `php artisan test`.
- **Single test:** `php artisan test --filter=TestClassName` or `php artisan test --filter=testMethodName`.
- **Frontend build:** `npm run build` | **Frontend dev:** `npm run dev`

## Code style

- 4 spaces, LF line endings, UTF-8 (see `.editorconfig`).
- Laravel Pint available for PHP formatting (`vendor/bin/pint`).
- No API routes — this is a web-only application (routes/web.php).

## Agent conduct

- Implement exactly what the user asks. Do not invent behavior or assume intent — if something is unclear, ask before coding.
- Only touch files necessary for the requested change. Avoid unrelated refactors.
- Apply good practices for both frontend and backend code at every step.
- After each change, verify no unrelated files were affected. If everything is correct, commit and push.
- Write commit messages in Spanish (e.g. `feat: añadir select de unidad en insumos de cocina`).
- Always preserve the user's manual changes. Never overwrite or revert customizations the user has made.

## Database

- **All domain tables use `bape_` prefix** (e.g. `bape_users`, `bape_orders`, `bape_products`).
- Standard Laravel tables (`sessions`, `cache`, `jobs`) do NOT have the prefix.
- Default connection is **MySQL** (remote shared hosting). `.env` points to `avicola1_bdsistema`.
- Migrations are the source of truth. See `database/migrations/`.

### Migration rules (CRITICAL)

The configured database may be production and may differ from the migration files. Treat it as production unless the user explicitly confirms otherwise. Protect existing restaurant data:

| Allowed | Prohibited without explicit, operation-specific user approval |
|---------|---------------------------------------------------------------|
| Read-only inspection only when authorized by the user | Running any migration against the configured database |
| Tests only when their database is confirmed isolated from production | `migrate:fresh`, `migrate:refresh`, rollback, reset, or any destructive schema command |
| | Running seeders or other commands that insert, update, or delete database records |
| | Direct SQL or application commands that write to the configured database |

Never assume `.env`, `phpunit.xml`, or migration files accurately identify the live database schema or make it safe to write. Do not execute migrations, seeds, resets, rollbacks, or database writes as part of implementation or verification unless the user explicitly approves the exact operation and confirms its target is a non-production database. A backup alone does not authorize a production database change. Creating migration files for later review does not authorize running them.

## Auth & roles

- Five roles: `admin`, `cashier`, `cashier_waiter`, `waiter`, `kitchen`.
- Middleware `role:admin,cashier` or `role:admin` guards route groups in `routes/web.php`.
- Custom middleware in `app/Http/Middleware/`:
  - **`CheckRole`** — enforces role-based access (variadic `...$roles`).
  - **`RequireOpenCashRegister`** — `admin`, `cashier`, `cashier_waiter` users must open a cash register before accessing POS routes.
  - **`SetTimezone`** — reads timezone from `bape_settings` and sets it at runtime.
- `DatabaseSeeder` is intentionally empty — no users or data are created by default.
- `DemoDataSeeder` is gated by `config('demo.allow_data')` which reads `ALLOW_DEMO_DATA` env var.

## Architecture

- **Models** use `bape_` table prefix explicitly via `$table` property on every model.
- **Electronic billing:** `app/Services/Sunat/` — NubeFact API integration (REST JSON), not SOAP/XML. Config via `SunatConfig` class. Documentation: `GUIA-FACTURACION-ELECTRONICA-NUBEFACT-DOC-API JSON V1.txt`.
- **POS flow:** Tables → Order → OrderDetails → Checkout. Cash register must be open first.
- **Product recipes:** Products can have ingredient dependencies (via `bape_product_ingredients`). Stock validation happens at add-to-order and checkout.

## Testing

- PHPUnit 11 with in-memory SQLite (`phpunit.xml`).
- Tests use `Tests\TestCase` base class (refreshes DB per test).
- Existing tests: `DefaultAdminCredentialsTest` (verifies seeder creates no data), `ProductRecipeTest` (recipe CRUD + stock validation).

## Environment

- Key env vars: `DB_CONNECTION`, `DB_DATABASE`, `ALLOW_DEMO_DATA`, `TOKEN_BUSCAR_CLIENTES`.
- `.env.example` is the reference. Copy to `.env` (handled by `composer setup`).
- `config/demo.php` controls demo data gating.
