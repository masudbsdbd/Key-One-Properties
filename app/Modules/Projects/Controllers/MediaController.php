<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    private const DISK = 'local';

    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return Ajax::success(null, ['html' => view('projects.media._grid', [
            'project' => $project,
            'media' => $project->media()->get(),
        ])->render()]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMedia', $project);

        $max = config('keyone.uploads.image_max_kb');
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:'.implode(',', config('keyone.uploads.image_mimes')), 'max:'.$max],
            'visibility' => ['nullable', Rule::in(array_keys(ProjectMedia::VISIBILITIES))],
        ], ['images.*.max' => 'Each image must be '.($max / 1024).' MB or smaller.']);

        DB::transaction(function () use ($request, $project) {
            $order = (int) $project->media()->max('sort_order');
            foreach ($request->file('images') as $file) {
                $path = $file->storeAs('projects/'.$project->public_id, Str::random(40).'.'.$file->guessExtension(), self::DISK);
                $media = $project->media()->create([
                    'uploaded_by' => $request->user()->id,
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sort_order' => ++$order,
                    'visibility' => $request->input('visibility', 'internal'),
                ]);
                $project->cover_media_id ??= $media->id;
            }
            $project->saveQuietly();
        });

        return Ajax::success(count($request->file('images')).' image(s) uploaded.');
    }

    public function show(Project $project, ProjectMedia $media): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($media->project_id === $project->id, 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function edit(Project $project, ProjectMedia $media): JsonResponse
    {
        Gate::authorize('manageMedia', $project);
        abort_unless($media->project_id === $project->id, 404);

        return Ajax::success(null, [
            'title' => 'Edit Image',
            'html' => view('projects.media._form', ['project' => $project, 'media' => $media])->render(),
        ]);
    }

    public function update(Request $request, Project $project, ProjectMedia $media): JsonResponse
    {
        Gate::authorize('manageMedia', $project);
        abort_unless($media->project_id === $project->id, 404);

        $media->update($request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(array_keys(ProjectMedia::VISIBILITIES))],
        ]));

        return Ajax::success('Image updated.');
    }

    public function cover(Project $project, ProjectMedia $media): JsonResponse
    {
        Gate::authorize('manageMedia', $project);
        abort_unless($media->project_id === $project->id, 404);

        $project->forceFill(['cover_media_id' => $media->id])->saveQuietly();

        return Ajax::success('Cover image set.');
    }

    public function destroy(Project $project, ProjectMedia $media): JsonResponse
    {
        Gate::authorize('manageMedia', $project);
        abort_unless($media->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $media) {
            if ($project->cover_media_id === $media->id) {
                $project->forceFill(['cover_media_id' => null])->saveQuietly();
            }
            $media->delete();
        });
        Storage::disk($media->disk)->delete($media->path);

        return Ajax::success('Image deleted.');
    }
}
