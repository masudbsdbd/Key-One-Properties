<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialUnit extends Model
{
    use HasPublicId;

    protected $fillable = ['code', 'name', 'precision'];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'unit_id');
    }
}
