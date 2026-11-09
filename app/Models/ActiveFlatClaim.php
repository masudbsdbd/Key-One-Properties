<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActiveFlatClaim extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function claimant(): MorphTo
    {
        return $this->morphTo();
    }
}
