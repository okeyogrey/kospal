# KOSPAL

KOSPAL is a **desktop-first** retail management application for small physical retailers in Kenya and Burundi. It runs locally with Laravel, React/Inertia, SQLite, and an optional Tauri Windows shell. Businesses own their data on the machine; local licenses replace cloud subscriptions.

Supported languages: English, French, Kirundi  
Supported currencies: KES, BIF, USD  
Business timezones: `Africa/Nairobi` (Kenya), `Africa/Bujumbura` (Burundi)

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- SQLite (default). MySQL remains optional for legacy web mode.
- Laravel Herd or `php artisan serve`
- Optional desktop shell: Rust + Tauri 2 (`npm run tauri:dev`)

## Exact local installation

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan db:prepare
npm install
npm run build
```

`.env.example` defaults to desktop mode + SQLite:

```env
KOSPAL_DEPLOYMENT_MODE=desktop
KOSPAL_LICENSE_EDITION=enterprise
KOSPAL_LICENSE_TRIAL_DAYS=30
DB_CONNECTION=sqlite
APP_URL=http://127.0.0.1:8000
QUEUE_CONNECTION=sync
```

Start:

```bash
php artisan serve
```

Or with Vite HMR:

```bash
composer run dev
```

Desktop shell (Rust toolchain required):

```bash
npm run tauri:dev
```

### Legacy MySQL / web mode

```env
KOSPAL_DEPLOYMENT_MODE=web
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kospal
DB_USERNAME=root
DB_PASSWORD=
APP_URL=http://kospal.test
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

```sql
CREATE DATABASE kospal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
npm install
npm run build
```

Composer one-shot alternative:

```bash
composer run setup
```

## Seed data

```bash
php artisan migrate:fresh --seed
```

Demo accounts (password: `password`):

| Email | Role |
| --- | --- |
| `admin@kospal.test` | Platform super admin (web mode) |
| `owner@kospal.test` | Business owner (Pro) |
| `manager@kospal.test` | Manager |
| `cashier@kospal.test` | Cashier |
| `clerk@kospal.test` | Inventory clerk |
| `other@kospal.test` | Owner of a second isolated business |

On desktop, register/onboard locally — a **30-day trial** starts automatically. Activate a license under **Settings → License** (online key or offline machine-bound code). After expiry the app is read-only until reactivation.

## Desktop vs web

| Concern | Desktop (default) | Web (`KOSPAL_DEPLOYMENT_MODE=web`) |
| --- | --- | --- |
| Database | SQLite file | MySQL |
| Entitlements | Trial + signed license / machine ID | Subscription + platform approval |

| Platform admin UI | Hidden | Enabled |
| Backups | SQLite file copy service | Not configured |

See `docs/architecture.md` for contracts and adapters.

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

## SQLite backup and database tools

Desktop defaults to SQLite (`DB_CONNECTION=sqlite`). The same Laravel migrations remain portable for MySQL/PostgreSQL.

Owners manage installation ops in **Settings** (desktop mode): Backup, Restore wizard, Health, Updates, Printer, Database, and Storage.

| Command | Purpose |
| --- | --- |
| `php artisan backup:run` | Create a backup (`--force` ignores auto_backup flag) |
| `php artisan db:prepare` | Create SQLite file if needed, apply pragmas, migrate, record schema version |
| `php artisan db:diagnose` | Integrity, foreign keys, pending migrations, table stats |
| `php artisan db:repair` | Backup (when supported), REINDEX/VACUUM, apply pending migrations |
| `php artisan db:export` | Portable SQL dump for moving to MySQL/PostgreSQL |
| `php artisan db:version` | Show `database_versions` history |

Desktop backups use `SqliteFileBackupService` (file copy under `storage/app/backups` or `KOSPAL_DATA_DIRECTORY/backups`). You can also copy `database/database.sqlite` manually.

## Project docs

- Architecture: [`docs/architecture.md`](docs/architecture.md)
- Database: [`docs/database.md`](docs/database.md)
- Manual role checklist: [`docs/local-testing-checklist.md`](docs/local-testing-checklist.md)
