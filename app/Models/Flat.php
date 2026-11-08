<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Flat extends Model
{
    use HasFactory, HasPublicId, LogsActivity, LogsProjectActivity, SoftDeletes;

    public const STATUSES = [
        'available' => 'Available',
        'unavailable' => 'Unavailable',
        'maintenance' => 'Maintenance',
        'booked' => 'Booked',
        'sold' => 'Sold',
    ];

    /** Statuses a user may set by hand. Booked and sold come from the Phase 3 sales workflow. */
    public const MANUAL_STATUSES = ['available', 'unavailable', 'maintenance'];

    public const STATUS_TONES = ['available' => 'success', 'unavailable' => 'neutral', 'maintenance' => 'warning', 'booked' => 'info', 'sold' => 'violet'];

    public const TYPES = ['Studio' => 'Studio', '1 Bed' => '1 Bed', '2 Bed' => '2 Bed', '3 Bed' => '3 Bed', '4 Bed' => '4 Bed', 'Duplex' => 'Duplex', 'Penthouse' => 'Penthouse', 'Commercial' => 'Commercial', 'Parking' => 'Parking'];

    public const FACINGS = ['North' => 'North', 'South' => 'South', 'East' => 'East', 'West' => 'West', 'North-East' => 'North-East', 'North-West' => 'North-West', 'South-East' => 'South-East', 'South-West' => 'South-West'];

    protected $fillable = ['flat_no', 'flat_type', 'bedrooms', 'bathrooms', 'balconies', 'facing', 'saleable_area', 'area_unit', 'description'];

    protected function casts(): array
    {
        return [
            'saleable_area' => 'decimal:2',
            'list_price' => 'decimal:2',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'balconies' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('flats')
            ->logOnly(['flat_no', 'flat_type', 'bedrooms', 'bathrooms', 'balconies', 'facing', 'saleable_area', 'list_price', 'status'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(FlatPriceHistory::class)->latest('effective_at')->latest('id');
    }

    public function claim(): HasOne
    {
        return $this->hasOne(ActiveFlatClaim::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FlatBooking::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(FlatSale::class);
    }

    public function priceRevisions(): HasMany
    {
        return $this->hasMany(FlatPriceRevision::class);
    }

    public function investorAgreements(): HasMany
    {
        return $this->hasMany(InvestorFlatAgreement::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FlatStatusHistory::class)->latest('changed_at')->latest('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(FlatMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
    }

    public function isLockedBySales(): bool
    {
        return in_array($this->status, ['booked', 'sold'], true);
    }
}
