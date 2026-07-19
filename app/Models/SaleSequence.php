<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class SaleSequence extends Model
{
    use BelongsToBusiness;

    protected $primaryKey = 'business_id';

    public $incrementing = false;

    protected $fillable = [
        'business_id',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
