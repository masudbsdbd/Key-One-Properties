<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetRequest extends Model
{
    use HasPublicId;

    public const STATUSES = ['pending' => 'Pending', 'completed' => 'Completed', 'dismissed' => 'Dismissed'];

    public const STATUS_TONES = ['pending' => 'warning', 'completed' => 'success', 'dismissed' => 'neutral'];

    protected $fillable = ['user_id', 'status', 'requested_ip', 'user_agent', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
