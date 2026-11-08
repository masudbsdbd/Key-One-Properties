<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    use HasAttachments, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'reversed' => 'Reversed', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'approved_at' => 'datetime', 'reversed_at' => 'datetime',
            'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierBillLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidAmount(): string
    {
        return Money::amount((string) $this->allocations()->whereHas('payment', fn ($q) => $q->where('status', 'approved'))->sum('amount'));
    }

    public function outstanding(): string
    {
        return in_array($this->status, ['approved', 'paid'], true) ? Money::amount(Money::sub($this->total_amount, $this->paidAmount())) : '0.00';
    }
}
