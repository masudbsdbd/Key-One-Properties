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

class Supplier extends Model
{
    use HasAttachments, HasPublicId, LogsActivity, SoftDeletes;

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    protected $fillable = ['name', 'contact_person', 'mobile', 'email', 'address', 'tax_id', 'status'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('purchasing')->logOnly(['name', 'contact_person', 'mobile', 'email', 'address', 'tax_id', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    /** What we owe the supplier (negative = advance paid). */
    public function balance(): string
    {
        $row = $this->ledgerEntries()->selectRaw('COALESCE(SUM(charge_amount),0) c, COALESCE(SUM(payment_amount),0) p')->first();

        return Money::amount(Money::sub((string) $row->c, (string) $row->p));
    }
}
