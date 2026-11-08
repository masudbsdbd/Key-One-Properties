<?php

namespace App\Support\Attachments;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentStore
{
    /**
     * Validation rules for a multi-file field.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(string $field, bool $required = false): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array', 'max:10'],
            $field.'.*' => ['file', 'mimes:'.implode(',', config('keyone.attachments.mimes')), 'max:'.config('keyone.attachments.max_kb')],
        ];
    }

    public static function store(Model $owner, string $type, UploadedFile $file, User $user): Attachment
    {
        return $owner->attachments()->create([
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'disk' => 'local',
            'path' => $file->storeAs('attachments/'.$type.'/'.$owner->public_id, Str::random(40).'.'.$file->guessExtension(), 'local'),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }
}
