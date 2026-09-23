<?php

namespace App\Console\Commands;

use App\Models\SyncLink;
use App\Services\Sync\ShopSyncService;
use Illuminate\Console\Command;

class SyncShopsCommand extends Command
{
    protected $signature = 'sync:run';

    protected $description = 'Push and pull shop changes for every linked computer';

    public function handle(ShopSyncService $sync): int
    {
        $links = SyncLink::query()->get();

        if ($links->isEmpty()) {
            $this->info('No linked shops.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($links as $link) {
            try {
                $sync->run($link);
                $this->info('Synced business '.$link->business_id.'.');
            } catch (\Throwable $exception) {
                $failed = true;
                $this->error('Business '.$link->business_id.' did not sync: '.$exception->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
