<?php

namespace App\Console\Commands;

use App\Enums\Plan;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class IssueLicenseKeyCommand extends Command
{
    protected $signature = 'license:issue
                            {--edition=pro : Plan edition (starter, pro, enterprise)}
                            {--days=365 : Validity in days from today}
                            {--expires= : Explicit expiry date (YYYY-MM-DD, overrides --days)}
                            {--machine= : Machine ID for offline activation codes}';

    protected $description = 'Issue a signed KOSPAL license key or offline activation code';

    public function handle(LicenseService $licenses): int
    {
        $edition = (string) $this->option('edition');

        if (Plan::tryFrom($edition) === null) {
            $this->error('Invalid edition. Use starter, pro, or enterprise.');

            return self::FAILURE;
        }

        $machine = $this->option('machine');
        $machine = is_string($machine) && $machine !== '' ? $machine : null;

        $key = $licenses->issueKey([
            'edition' => $edition,
            'days' => $this->option('expires') ? null : (int) $this->option('days'),
            'expires_at' => $this->option('expires') ?: null,
            'machine_id' => $machine,
        ]);

        $this->info($machine ? 'Offline activation code:' : 'Online license key:');
        $this->line($key);

        return self::SUCCESS;
    }
}
