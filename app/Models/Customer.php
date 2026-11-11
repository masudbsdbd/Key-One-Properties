<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, HasPublicId, LogsActivity, SoftDeletes;

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    public const SOURCES = [
        'website' => 'Website',
        'facebook' => 'Facebook',
        'referral' => 'Referral',
        'event' => 'Event',
        'walk_in' => 'Walk-in',
        'phone' => 'Phone Call',
        'agent' => 'Agent',
        'other' => 'Other',
    ];

    public const SOURCE_TONES = ['website' => 'info', 'facebook' => 'violet', 'referral' => 'success', 'event' => 'warning', 'walk_in' => 'orange', 'phone' => 'accent', 'agent' => 'neutral', 'other' => 'neutral'];

    protected $fillable = [
        'full_name', 'email', 'primary_mobile', 'alternate_mobile', 'date_of_birth',
        'nid_or_passport', 'occupation', 'status', 'source',
    ];

    protected $hidden = ['id', 'nid_or_passport'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'nid_or_passport' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // The identity number is never written to the log; CustomerService records only that it changed.
        return LogOptions::defaults()->useLogName('customers')
            ->logOnly(['full_name', 'email', 'primary_mobile', 'alternate_mobile', 'date_of_birth', 'occupation', 'status', 'source'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest()->latest('id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FlatBooking::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(FlatSale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(CustomerStatementEntry::class);
    }

    public function lateFeePolicies(): HasMany
    {
        return $this->hasMany(CustomerLateFeePolicy::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class)->latest()->latest('id');
    }

    /**
     * Customers a user may see: all with customers.view_all, otherwise the ones they created.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('customers.view_all')) {
            return $query;
        }

        if ($user->can('customers.view')) {
            return $query->where('created_by', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('customers.view_all') || ($user->can('customers.view') && $this->created_by === $user->id);
    }

    public function maskedIdentity(): ?string
    {
        $value = $this->nid_or_passport;

        return $value ? str_repeat('•', max(0, mb_strlen($value) - 4)).mb_substr($value, -4) : null;
    }

    public static function nextCode(): string
    {
        $next = (int) static::withTrashed()->max('id') + 1;

        do {
            $code = 'CUS-'.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('customer_code', $code)->exists());

        return $code;
    }

    /** Same word the other parties (supplier, contractor, investor) use, so shared views need no special case. */
    public function getNameAttribute(): ?string
    {
        return $this->full_name;
    }
}
