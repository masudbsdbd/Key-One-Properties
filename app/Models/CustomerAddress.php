<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasPublicId;

    public const TYPES = ['present' => 'Present', 'permanent' => 'Permanent', 'office' => 'Office', 'other' => 'Other'];

    protected $fillable = ['address_type', 'address_line', 'city', 'district', 'postal_code', 'country', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function oneLine(): string
    {
        return collect([$this->address_line, $this->city, $this->district, $this->postal_code, $this->country])->filter()->implode(', ');
    }
}
