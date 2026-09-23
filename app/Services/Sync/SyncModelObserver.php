<?php

namespace App\Services\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncModelObserver
{
    public function saved(Model $model): void
    {
        app(SyncRecorder::class)->record($model, 'upsert');
    }

    public function deleted(Model $model): void
    {
        app(SyncRecorder::class)->record($model, 'delete');
    }
}
