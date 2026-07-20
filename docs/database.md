# Database

KOSPAL is **SQLite-first** for desktop installs. The same Laravel migration files are portable to MySQL/MariaDB and PostgreSQL.

## Defaults

| Setting | Desktop | Web / server |
| --- | --- | --- |
| `DB_CONNECTION` | `sqlite` | `mysql` or `pgsql` |
| File | `database/database.sqlite` | Server database name `kospal` |
| Tooling | `DatabaseToolkit` + file backups | Same toolkit (subset) |

Do not rewrite historical migrations. Ship additive migrations only and bump `config('deployment.database.schema_version')` when releasing a meaningful schema watermark.

## Commands

```bash
php artisan db:prepare          # ensure file/pragmas, migrate, record version
php artisan db:diagnose         # integrity, FKs, pending migrations, table stats
php artisan db:diagnose --json
php artisan db:repair           # backup + REINDEX/VACUUM (SQLite) + pending migrations
php artisan db:export           # portable INSERT dump for engine moves
php artisan db:version          # database_versions history
```

## Moving to MySQL or PostgreSQL

1. `php artisan db:export --path=storage/app/exports/kospal.sql`
2. Create an empty target database.
3. Point `.env` at `mysql` or `pgsql` and run `php artisan migrate` (schema from migrations, not the dump).
4. Review and import data rows from the export (types/defaults may need adjustment).
5. Run `php artisan db:diagnose`.

## Version tracking

- Laravel: `migrations` table (which files ran)
- App: `database_versions` table + `deployment.database.schema_version`
