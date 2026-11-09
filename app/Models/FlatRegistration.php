<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatRegistration extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'in_process' => 'In Process', 'registered' => 'Registered'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registered_at' => 'date', 'registration_cost' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }
}
