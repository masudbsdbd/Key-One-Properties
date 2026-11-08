<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatMedia extends Model
{
    use HasPublicId;

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'caption', 'sort_order', 'uploaded_by'];

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
