<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatHandover extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'ready' => 'Ready', 'completed' => 'Completed'];

    public const CHECKLIST = ['keys' => 'Keys handed over', 'meters' => 'Utility meters noted', 'snags' => 'Snag list cleared', 'documents' => 'Documents delivered'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'date', 'completed_at' => 'date', 'checklist_json' => 'array'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
