# KOSPAL

KOSPAL is a responsive multi-tenant retail-management SaaS for small physical retailers in Kenya and Burundi. It is built on the Laravel React starter kit (Laravel, Inertia, React, TypeScript, Tailwind, shadcn/ui) and is developed for **local use only** in this repository.

Supported languages: English, French, Kirundi  
Supported currencies: KES, BIF, USD  
Business timezones: `Africa/Nairobi` (Kenya), `Africa/Bujumbura` (Burundi)

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- MySQL 8+ (SQLite works for quick automated tests; MySQL is the supported local app database)
- Laravel Herd (recommended on Windows) or another local PHP host

## Exact local installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for Herd/MySQL:

```env
APP_NAME=KOSPAL
APP_ENV=local
APP_DEBUG=true
APP_URL=http://kospal.test

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kospal
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### MySQL setup

1. Create the database (Herd MySQL / CLI):

```sql
CREATE DATABASE kospal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Confirm credentials in `.env`, then:

```bash
php artisan migrate
npm install
npm run build
```

3. Open `http://kospal.test` (Herd) or your configured `APP_URL`.

Composer one-shot alternative:

```bash
composer run setup
```

Frontend development server:

```bash
npm run dev
```

Or full local process stack:

```bash
composer run dev
```

## Seed data

```bash
php artisan migrate:fresh --seed
```

Demo accounts (password: `password`):

| Email | Role |
| --- | --- |
| `admin@kospal.test` | Platform super admin |
| `owner@kospal.test` | Business owner (Pro) |
| `manager@kospal.test` | Manager |
| `cashier@kospal.test` | Cashier |
| `clerk@kospal.test` | Inventory clerk |
| `other@kospal.test` | Owner of a second isolated business |

After registration, users without a business are redirected to `/onboarding` to create the business and first branch.

Locale persistence:

- Session locale for the browser session
- `users.preferred_locale` updated when an authenticated user changes language
- `businesses.default_locale` used as fallback (and can be updated by owners via `persist_business`)

## Tests

```bash
php artisan test
```

Or the Composer quality gate (Pint check + PHPStan + Pest):

```bash
composer test
```

## Frontend checks and production build

```bash
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

PHP formatter:

```bash
vendor/bin/pint --parallel
# or
composer run lint
```

## Local backup and restore (MySQL)

Backup:

```bash
mysqldump -h 127.0.0.1 -u root kospal > backups/kospal-$(date +%Y%m%d).sql
```

PowerShell example:

```powershell
New-Item -ItemType Directory -Force backups | Out-Null
mysqldump -h 127.0.0.1 -u root kospal | Out-File -Encoding utf8 backups/kospal-$(Get-Date -Format yyyyMMdd).sql
```

Restore into a scratch database:

```sql
CREATE DATABASE kospal_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -h 127.0.0.1 -u root kospal_restore < backups/kospal-YYYYMMDD.sql
```

Point a temporary `.env` `DB_DATABASE=kospal_restore` (or import over `kospal` only if you intentionally overwrite) and run:

```bash
php artisan migrate:status
```

Also back up private attachments if used:

```bash
# default local disk root is storage/app/private (see filesystems + kospal.attachments.disk)
```

## Project docs

- Architecture: [`docs/architecture.md`](docs/architecture.md)
- Manual role checklist: [`docs/local-testing-checklist.md`](docs/local-testing-checklist.md)

## Current local scope

Tenancy, catalog, inventory, stock transfers, sales/POS, customers, expenses, analytics/reports, subscriptions (offline approval), audit logs, en/fr/rn interface copy, KES/BIF/USD money helpers, and business-timezone analytics bounds are implemented for local use. Deployment/hosting configuration is intentionally out of scope in this repository.
