<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\Ajax;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(string $type, string $owner): JsonResponse
    {
        [$model, $config] = $this->owner($type, $owner);
        Gate::authorize($config['view'], $model);

        return Ajax::success(null, ['html' => view('attachments._list', [
            'owner' => $model,
            'attachments' => $model->attachments()->with('uploader:id,name')->latest()->get(),
            'canManage' => Gate::allows($config['manage'], $model),
        ])->render()]);
    }

    public function store(Request $request, string $type, string $owner): JsonResponse
    {
        [$model, $config] = $this->owner($type, $owner);
        Gate::authorize($config['manage'], $model);

        $max = config('keyone.attachments.max_kb');
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:'.implode(',', config('keyone.attachments.mimes')), 'max:'.$max],
        ], ['files.*.max' => 'Each file must be '.($max / 1024).' MB or smaller.', 'files.*.mimes' => 'Allowed: PDF, images, Word or Excel.']);

        foreach ($request->file('files') as $file) {
            $model->attachments()->create([
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                'disk' => 'local',
                'path' => $file->storeAs('attachments/'.$type.'/'.$model->public_id, Str::random(40).'.'.$file->guessExtension(), 'local'),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        activity($type)->event('attachment_added')->causedBy($request->user())->performedOn($model)
            ->tap(fn ($a) => $a->project_id = $model->project_id ?? null)
            ->withProperties(['attributes' => ['files' => count($request->file('files'))]])->log('attachment_added');

        return Ajax::success('File(s) attached.');
    }

    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $config = $this->configFor($attachment);
        Gate::authorize($config['view'], $attachment->attachable);

        $inline = $attachment->isImage() || $attachment->mime_type === 'application/pdf';

        return $inline && ! $request->boolean('download')
            ? Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type, 'Cache-Control' => 'private, max-age=600', 'Content-Disposition' => 'inline',
            ])
            : Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $config = $this->configFor($attachment);
        Gate::authorize($config['manage'], $attachment->attachable);

        $attachment->delete();
        Storage::disk($attachment->disk)->delete($attachment->path);

        return Ajax::success('File removed.');
    }

    /**
     * @return array{0: Model, 1: array{model: class-string<Model>, view: string, manage: string}}
     */
    private function owner(string $type, string $publicId): array
    {
        $config = AttachableRegistry::get($type) ?? abort(404);
        $model = $config['model']::query()->where('public_id', $publicId)->firstOrFail();

        return [$model, $config];
    }

    private function configFor(Attachment $attachment): array
    {
        $owner = $attachment->attachable ?? abort(404);

        return AttachableRegistry::get(AttachableRegistry::typeFor($owner) ?? '') ?? abort(404);
    }
}
