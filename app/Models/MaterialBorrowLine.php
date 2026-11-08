<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialBorrowLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_qty' => 'decimal:3', 'approved_qty' => 'decimal:3', 'issued_qty' => 'decimal:3',
            'returned_qty' => 'decimal:3', 'converted_qty' => 'decimal:3', 'locked_unit_cost' => 'decimal:4',
        ];
    }

    public function borrow(): BelongsTo
    {
        return $this->belongsTo(MaterialBorrow::class, 'borrow_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** Issued and neither returned nor converted. */
    public function outstanding(): string
    {
        return Money::qty(Money::sub(Money::sub($this->issued_qty, $this->returned_qty), $this->converted_qty));
    }
}
