<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectMedia extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    public const VISIBILITIES = ['internal' => 'Internal', 'investor' => 'Investor visible'];

    protected $fillable = ['uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'file_size', 'caption', 'sort_order', 'visibility'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('projects')->logOnly(['original_name', 'caption', 'visibility'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
