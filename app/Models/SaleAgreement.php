<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleAgreement extends Model
{
    use HasAttachments, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'signed' => 'Signed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signed_at' => 'date'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
