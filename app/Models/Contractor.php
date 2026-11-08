<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contractor extends Model
{
    use HasAttachments, HasPublicId, LogsActivity, SoftDeletes;

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    protected $fillable = ['name', 'trade', 'contact_person', 'mobile', 'email', 'address', 'tax_id', 'status'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('contractors')->logOnly($this->fillable)->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(ContractorWorkOrder::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(ContractorBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractorPayment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ContractorLedgerEntry::class);
    }

    /** What we owe the contractor (negative = advance paid). */
    public function balance(): string
    {
        $row = $this->ledgerEntries()->selectRaw('COALESCE(SUM(charge_amount),0) c, COALESCE(SUM(payment_amount),0) p')->first();

        return Money::amount(Money::sub((string) $row->c, (string) $row->p));
    }
}
