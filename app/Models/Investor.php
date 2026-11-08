<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Investor extends Model
{
    use HasAttachments, HasPublicId, LogsActivity, SoftDeletes;

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    protected $fillable = ['name', 'mobile', 'email', 'address', 'tax_id', 'status'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('investors')->logOnly($this->fillable)->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(InvestorFlatAgreement::class, 'investor_id');
    }

    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(InvestorCapitalTransaction::class, 'investor_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InvestorLedgerEntry::class, 'investor_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(InvestorProfitDistribution::class, 'investor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextCode(): string
    {
        $next = (int) static::withTrashed()->max('id') + 1;

        do {
            $code = 'INV-'.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('investor_code', $code)->exists());

        return $code;
    }
}
