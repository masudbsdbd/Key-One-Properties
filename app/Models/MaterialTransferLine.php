<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialTransferLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_value' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function borrowLine(): BelongsTo
    {
        return $this->belongsTo(MaterialBorrowLine::class, 'borrow_line_id');
    }
}
