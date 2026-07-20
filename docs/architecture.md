# KOSPAL Architecture

KOSPAL is a desktop-first retail management application for small physical retailers in Kenya and Burundi (Laravel + Inertia/React + SQLite + optional Tauri). Shared-DB business/branch tenancy, catalog, inventory, stock transfers, sales/POS, expenses, analytics, and local licensing are implemented. Legacy web/SaaS subscription approval remains available when `KOSPAL_DEPLOYMENT_MODE=web`.

## Goals

- Local-first Laravel + Inertia + React stack
- Strong tenant data isolation
- Role-based access for store teams
- Localization for English, French, and Kirundi
- Currency support for KES, BIF, and USD
- Plan limits enforced per organization
- Deployment concerns isolated behind contracts (`App\Contracts\*`) so web/SaaS and desktop adapters can swap without rewriting retail services

## Deployment boundaries

Domain code depends on contracts, not packaging details. Bindings live in `DeploymentServiceProvider` and `config/deployment.php`.

| Contract | Desktop adapter (default) | Web adapter |
| --- | --- | --- |
| `LicensingService` | `LocalLicensingService` → `LicenseService` | `SubscriptionLicensingService` |
| `FeatureFlagService` | `PlanLimitChecker` → plan/edition features | same |
| `DocumentPrinter` | `BladeDomPdfDocumentPrinter` | same |
| `DatabaseRuntime` | `LaravelDatabaseRuntime` | same |
| `DatabaseToolkit` | `LaravelDatabaseToolkit` (SQLite prepare/diagnose/repair/export) | same (MySQL/pgsql-safe subset) |
| `BackupService` | `SqliteFileBackupService` | `UnsupportedBackupService` |
| `SynchronizationService` | `NoOpSynchronizationService` | same |
| `DesktopSettings` | `FileDesktopSettings` (JSON under `storage/app/desktop-settings.json`) | same |
| `UpdateService` | `LocalUpdateService` | `UnsupportedUpdateService` |

Default `KOSPAL_DEPLOYMENT_MODE=desktop`. Onboarding starts a **30-day trial** (`KOSPAL_LICENSE_TRIAL_DAYS`, edition from `KOSPAL_LICENSE_EDITION`). Owners activate licenses under **Settings → License** via online key or offline machine-bound code. Machine ID is stored per installation. After expiry the app stays readable but writes are blocked. Feature entitlements still come from the licensed plan via `FeatureFlagService`. Platform admin subscription approval remains for web mode only.

Desktop owners also manage installation ops under Settings (gated by `deployment.is_desktop`):

| Settings page | Capability |
| --- | --- |
| Local | Channel overview and machine preferences summary |
| Backup | Manual backups + automatic daily schedule (`backup:run`) |
| Restore | Multi-step restore wizard (pre-restore snapshot) |
| Health | Database diagnose + backup/update status |
| Updates | Channel + optional feed check (`KOSPAL_UPDATE_FEED_URL`) |
| Printer | Preferred printer name + receipt width |
| Database | Connection info, migrate / repair / export |
| Storage | Data directory for backups and exports |

Retail services (`SaleService`, `InventoryService`, etc.) stay free of DomPDF, dump paths, update channels, license-key logic, and raw SQL dialect — licensing lives in `LicenseService`; DB ops live in `DatabaseToolkit` / `DatabaseRuntime`.

## Database

- **Desktop default:** SQLite file (`database/database.sqlite` or `KOSPAL_DATA_DIRECTORY`), WAL + foreign keys via `DatabaseToolkit`.
- **Future engines:** Same migration files target MySQL/MariaDB/PostgreSQL (`DB_CONNECTION=mysql|pgsql`). Use `db:export` when moving data off SQLite.
- **Versioning:** Laravel `migrations` table plus app-level `database_versions` (`config('deployment.database.schema_version')`). Existing migration files are never rewritten; new changes are additive migrations only.
- **Ops commands:** `db:prepare`, `db:diagnose`, `db:repair`, `db:export`, `db:version`.

## Tenancy model

KOSPAL uses **organization-based multi-tenancy** (store/workspace tenancy) for branches and staff even on a single desktop install:

| Concept | Purpose |
| --- | --- |
| **Business** | Billing and ownership boundary for a retailer |
| **Branch** | Physical store location under a business |
| **Membership** | Links a user to a business with one role |
| **Platform** | Cross-tenant administration for KOSPAL operators |

Identifiers:

- `businesses.id`
- `branches.business_id`
- `business_memberships` with `role`
- `branch_user` for cashier/clerk branch assignments
- All business-owned rows carry `business_id` (and usually `branch_id` where location-specific)

There is one database. Isolation is enforced in application code via global scopes, policies, and request context — not separate databases per tenant.

## User roles

| Role | Scope | Capabilities (planned) |
| --- | --- | --- |
| `platform_super_admin` | Platform | Manage organizations, plans, global settings; read support context |
| `owner` | Organization | Full organization control, billing, branches, staff, all modules |
| `manager` | Organization / assigned branches | Day-to-day operations: catalog, stock, sales oversight, expenses, reports, staff (non-owner) |
| `cashier` | Assigned branches | POS / sales creation and basic lookup; limited inventory visibility |
| `inventory_clerk` | Assigned branches | Products, inventory movements, suppliers; no billing or staff admin |

Shell navigation is driven by `App\Support\Navigation` and the resolved membership role from `TenantContext`. Users without a business are redirected to onboarding. `platform_super_admin` is authorized separately via `is_platform_super_admin` and must never inherit business owner powers implicitly.

## Plan limits

| Plan | Active branches | Staff seats | Notable features |
| --- | --- | --- | --- |
| Starter | 1 | 5 | Core |
| Pro | 3 | 25 | Advanced reports, CSV export, stock transfers |
| Enterprise | 10 | Configurable (`max_staff_override` or unlimited) | Audit logs, consolidated reports |

Rules:

1. Limits belong to the **business**, not the user.
2. Branch/staff checks run in services (`PlanLimitChecker`) before mutating writes.
3. Over-limit writes fail closed (HTTP 422).
4. Browser-supplied `business_id`, `role`, or `plan` values are ignored for authorization.
5. Pending invitations consume staff seats.

## Data isolation rules

1. **Every tenant query is organization-scoped.** No unbounded Eloquent queries on business models.
2. **Branch context is optional but narrow.** Cashiers and clerks operate in a selected branch; managers/owners may switch among allowed branches.
3. **Policies authorize both action and tenant.** Matching IDs in the URL is not enough; the actor must belong to that organization (and branch when required).
4. **Jobs and notifications carry organization context** explicitly; never infer tenant from only `user_id`.
5. **No cross-tenant joins or “search all stores”** except platform admin tooling with audited access.
6. **File uploads** are stored under organization-prefixed paths and authorized via the same policies.
7. **Exports/reports** must apply the same scopes as interactive queries.

## Localization and currency

- Locales: `en`, `fr`, `rn` (copy in `lang/{locale}/kospal.php`, shared to Inertia as `translations`)
- Locale resolution order: session → `users.preferred_locale` → `businesses.default_locale` → `app.locale`
- Changing language via `locale.update` updates the session and authenticated user's preferred locale; owners may also persist business default with `persist_business`
- Laravel `fallback_locale` is `en` (Kirundi may intentionally retain English for a few secondary strings)
- Currencies: `KES`, `BIF`, `USD` (configured in `config/kospal.php`); amounts stored as integer minor units
- Business timezones: Kenya `Africa/Nairobi`, Burundi `Africa/Bujumbura` (app storage remains UTC; analytics day bounds use `BusinessClock`)

## Frontend shell

Authenticated UI:

- Responsive sidebar + sticky top bar
- Profile menu, notification placeholder, language selector, branch selector placeholder
- Role-filtered navigation
- Shared loading / empty / error / unauthorized states

Settings (profile, security, appearance) remain under the existing Fortify/settings routes. Desktop owners also see License plus installation pages (Local, Backup, Restore, Health, Updates, Printer, Database, Storage).

## Catalog and inventory

Catalog tables are tenant-scoped (`business_id`). Stock is branch-scoped via `inventory_balances` and immutable `stock_movements`. Money amounts are stored as integer minor units and displayed using the business currency. Inventory mutations must go through `InventoryService` inside database transactions; negative stock is rejected.

Inter-branch stock transfers (`stock_transfers` / `stock_transfer_items`) are a Pro/Enterprise feature. Lifecycle is `draft → dispatched → received` or `draft → cancelled`. Drafts do not change stock; dispatch writes `transfer_out` movements at the source; receive writes `transfer_in` at the destination. Mutations go through `StockTransferService` and always audit lifecycle actions.

Cashiers cannot manage products, suppliers, categories, inventory, or transfers. Owners, managers, and inventory clerks can; clerks are limited to assigned branches and must have access to both source and destination to manage a transfer.

## Sales / POS

Sales are tenant- and branch-scoped. Completing a sale runs in a single database transaction that creates the `sales`, `sale_items`, and `payments` rows, decrements stock via `InventoryService` (`sale` movements), allocates a per-business `sale_number` from `sale_sequences`, and writes audit logs. Duplicate POS submissions are blocked with a unique `client_request_id` per business. Voiding (owner/manager only) restores stock with `sale_void` movements and requires a reason. Discounts require owner/manager permission. Receipts support thermal/A4 print HTML; PDF invoices use DomPDF.

Subscriptions (web mode) use offline payment instructions and manual platform approval. Desktop uses trial + signed license activation instead (see Settings → License). Owners on web pick Starter/Pro/Enterprise, submit a transaction code, and platform super-admins approve/reject or directly change plan/status. Pending, expired, and suspended businesses stay readable but mutating actions are blocked server-side.

## Out of scope for this milestone

- Paid payment gateway integrations
- Fake seed data for unbuilt domains
- Paid third-party APIs, Docker, or deployment automation

## Next implementation milestones

1. Organizations, branches, memberships, and role persistence — done
2. Tenant middleware + policy scaffolding — done
3. Catalog and inventory domain — done
4. Sales / POS — done
5. Expenses and reporting — done
6. Subscriptions and plan-limit enforcement depth — done
