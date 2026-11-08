<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasPublicId;

    protected $fillable = ['code', 'name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'category_id');
    }
}
