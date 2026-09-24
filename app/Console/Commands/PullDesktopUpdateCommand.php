<?php

namespace App\Console\Commands;

use App\Contracts\UpdateService;
use App\Support\Deployment;
use Illuminate\Console\Command;

class PullDesktopUpdateCommand extends Command
{
    protected $signature = 'updates:pull';

    protected $description = 'Download a published desktop update for the next launch';

    public function handle(UpdateService $updates): int
    {
        if (! Deployment::isDesktop() || ! $updates->isSupported()) {
            return self::SUCCESS;
        }

        $result = $updates->pull();
        $notes = $result['notes'] ?? '';

        if (is_string($notes) && $notes !== '') {
            $this->line($notes);
        }

        return self::SUCCESS;
    }
}
