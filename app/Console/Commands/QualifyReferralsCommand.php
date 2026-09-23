<?php

namespace App\Console\Commands;

use App\Services\ReferralService;
use Illuminate\Console\Command;

class QualifyReferralsCommand extends Command
{
    protected $signature = 'referrals:qualify';

    protected $description = 'Grant referral credits for invitees who stayed active for a week';

    public function handle(ReferralService $referrals): int
    {
        $count = $referrals->qualifyDue();
        $this->info("Qualified {$count} referral(s).");

        return self::SUCCESS;
    }
}
