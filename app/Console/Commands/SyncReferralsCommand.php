<?php

namespace App\Console\Commands;

use App\Services\ReferralService;
use Illuminate\Console\Command;

class SyncReferralsCommand extends Command
{
    protected $signature = 'referrals:sync';

    protected $description = 'Send desktop referral heartbeats to the central KOSPAL server';

    public function handle(ReferralService $referrals): int
    {
        $count = $referrals->syncDesktopAccounts();
        $this->info("Synced {$count} referral account(s).");

        return self::SUCCESS;
    }
}
