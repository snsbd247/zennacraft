# Zenna Craft

A Laravel-based commerce & operations platform for Bangladeshi e-commerce sellers — a storefront plus a full back office (Studio) for orders, inventory, purchasing, finance, marketing, fraud/RTO risk, and courier & payment integrations.

## Stack

- **Backend:** Laravel 13, PHP 8.3, Sanctum for API auth
- **Frontend:** Blade + Tailwind CSS v4 + Vite (server-rendered, no SPA framework)
- **Database:** MySQL in production, SQLite for local dev
- **Queue/Schedule:** database queue driver, driven by cron (`schedule:run` + `queue:work` — no persistent worker required, see [docs/queue-setup.md](docs/queue-setup.md))

## Architecture

A modular monolith: every domain lives under `app/Modules/<Name>` with its own `Http/Controllers`, `Models`, `Services`, and (where needed) `Repositories` — 45+ modules, from `Order` and `Product` to `Fraud`, `RTO`, `Courier`, `Finance`, and `RBAC`. A shared `Modules/Shared` module holds base classes (`BaseService`, `BaseRepository`, `BaseDTO`) that most modules build on, keeping the modules consistent with each other.

Routes are registered directly in `routes/web.php` / `routes/api.php` (no per-module route files or service providers) and grouped by `permission:*` middleware tied to the RBAC module. The admin panel ("Studio") lives under a configurable path (`ADMIN_PATH` env, default `studio`).

## Key features

- **Storefront** — catalog, cart, checkout, customer OTP login/dashboard, order tracking, landing pages, CMS pages
- **Studio (admin)** — Orders (incl. POS), Products, Purchasing, Inventory, Accounts/Finance, Marketing automation, Coupons/Offers, Fraud & RTO risk holds, Audit log, RBAC-managed staff roles
- **Courier integrations** — live API push to **Pathao** and **Steadfast** (auto-creates the courier order on assignment, webhook-driven status sync); see [docs/courier-payment-providers.md](docs/courier-payment-providers.md)
- **Payments** — Cash on Delivery by default; **bKash** Checkout wired end-to-end (create → redirect → callback → execute)
- **Marketing** — Facebook Pixel + Conversions API (deduplicated client+server event tracking), SMS gateways (Alpha SMS, MiMSMS, BD Bulk SMS — see [docs/sms-providers.md](docs/sms-providers.md)), automation workflows, customer segments
- **Licensing** — optional central license/update-server client (dormant unless `LICENSE_SERVER_URL` is set)

## Local setup

```bash
composer install
npm install

cp .env.example .env   # if present — otherwise create one; see below
php artisan key:generate
touch database/database.sqlite   # for local SQLite; skip if using MySQL

php artisan migrate
php artisan db:seed              # roles/permissions/owner + demo data

npm run build                    # or `npm run dev` while developing
php artisan serve
```

`.env` essentials for local dev:

```
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
ADMIN_PATH=studio
```

Third-party credentials (couriers, payment gateways, SMS, Facebook Pixel/CAPI, SMTP) are **not** set via `.env` — they're entered in Studio → Settings & Configuration and stored (encrypted where sensitive) in the `settings` table. Env vars only provide fallback base URLs for a couple of API clients.

## Tests

```bash
php artisan test
```

Feature tests cover most modules under `tests/Feature/<Module>`; `phpunit.xml` runs against an in-memory SQLite database, so no local DB setup is needed to run the suite.

## Deployment

Deployed on shared hosting (cPanel) — PHP is invoked via the `ea-phpXX` alternate PHP binary, not the account's default `php`. Cron drives both the scheduler and the queue (see [docs/queue-setup.md](docs/queue-setup.md)):

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --tries=3 --max-time=55 >> /dev/null 2>&1
```

Before going live, work through [docs/production-security-checklist.md](docs/production-security-checklist.md). Multiple people/processes may deploy to the same environment — always `git pull` before making changes and `git push` after, so work never gets silently overwritten.

## Docs

- [docs/courier-payment-providers.md](docs/courier-payment-providers.md) — Pathao/Steadfast/bKash API specs and integration notes
- [docs/queue-setup.md](docs/queue-setup.md) — cron/queue configuration
- [docs/sms-providers.md](docs/sms-providers.md) / [docs/sms-setup.md](docs/sms-setup.md) — SMS gateway details
- [docs/global-design-system.md](docs/global-design-system.md) — Studio UI design tokens/components
- [docs/profit-definitions.md](docs/profit-definitions.md) — how gross profit/COGS are calculated across the app
- [docs/production-security-checklist.md](docs/production-security-checklist.md) — pre-launch hardening checklist
- [docs/api/README.md](docs/api/README.md) — public API (`/api/v1/*`) reference
