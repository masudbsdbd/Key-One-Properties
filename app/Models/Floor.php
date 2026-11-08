<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Floor extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    protected $fillable = ['floor_number', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['floor_number' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('flats')->logOnly(['floor_number', 'label'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function activityProjectId(): ?int
    {
        return $this->building?->project_id;
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }

    public static function defaultLabel(int $number): string
    {
        if ($number < 0) {
            return 'Basement '.abs($number);
        }
        if ($number === 0) {
            return 'Ground Floor';
        }

        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix.' Floor';
    }
}
