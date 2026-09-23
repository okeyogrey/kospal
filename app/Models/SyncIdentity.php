<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncIdentity extends Model
{
    protected $fillable = [
        'business_id',
        'remote_uuid',
        'local_uuid',
    ];
}
