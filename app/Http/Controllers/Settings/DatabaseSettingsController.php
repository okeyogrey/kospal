<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\DatabaseRuntime;
use App\Contracts\DatabaseToolkit;
use App\Contracts\DesktopSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesDesktopSettings;
use App\Http\Requests\Desktop\RepairDatabaseRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DatabaseSettingsController extends Controller
{
    use AuthorizesDesktopSettings;

    public function edit(
        TenantContext $tenant,
        DatabaseRuntime $runtime,
        DatabaseToolkit $toolkit,
        DesktopSettings $settings,
    ): Response {
        $this->desktopBusiness($tenant);

        return Inertia::render('settings/database', [
            'connection' => [
                'name' => $runtime->connectionName(),
                'driver' => $runtime->driver(),
                'path' => $runtime->databasePath(),
                'is_sqlite' => $runtime->isSqlite(),
            ],
            'schema' => [
                'current' => $toolkit->currentSchemaVersion(),
                'expected' => $toolkit->expectedSchemaVersion(),
            ],
            'sqlite' => [
                'journal_mode' => (string) config('deployment.database.sqlite.journal_mode'),
                'synchronous' => (string) config('deployment.database.sqlite.synchronous'),
                'busy_timeout' => (int) config('deployment.database.sqlite.busy_timeout'),
                'foreign_keys' => (bool) config('deployment.database.sqlite.foreign_keys'),
            ],
            'supported_drivers' => $toolkit->supportedDrivers(),
            'data_directory' => $settings->get('data_directory'),
        ]);
    }

    public function repair(
        RepairDatabaseRequest $request,
        TenantContext $tenant,
        DatabaseToolkit $toolkit,
    ): RedirectResponse {
        $this->desktopBusiness($tenant);

        try {
            $result = $toolkit->repair($request->validated());

            if ($result['ok']) {
                return back()->with('success', 'Database repair completed. '.implode(' ', $result['messages']));
            }

            return back()->with('error', 'Repair finished with issues: '.implode(' ', $result['messages']));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function migrate(TenantContext $tenant, DatabaseToolkit $toolkit): RedirectResponse
    {
        $this->desktopBusiness($tenant);

        try {
            $result = $toolkit->migrate();
            $ran = count($result['ran']);

            return back()->with(
                'success',
                $ran === 0
                    ? 'Schema is already up to date (version '.$result['schema_version'].').'
                    : "Applied {$ran} migration(s). Schema version ".$result['schema_version'].'.',
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function export(TenantContext $tenant, DatabaseToolkit $toolkit): RedirectResponse
    {
        $this->desktopBusiness($tenant);

        try {
            $result = $toolkit->exportSql();

            return back()->with(
                'success',
                "Exported {$result['tables']} tables ({$result['bytes']} bytes) to {$result['path']}.",
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
