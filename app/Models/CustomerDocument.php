<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    use HasPublicId;

    public const CATEGORIES = [
        'nid' => 'National ID',
        'passport' => 'Passport',
        'tin' => 'TIN Certificate',
        'photo' => 'Photo',
        'agreement' => 'Agreement',
        'other' => 'Other',
    ];

    protected $fillable = ['category', 'original_name', 'disk', 'path', 'mime_type', 'file_size', 'uploaded_by'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
