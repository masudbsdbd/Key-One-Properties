<?php

namespace App\Modules\Flats\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\FlatMedia;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FlatMediaController extends Controller
{
    public function index(Flat $flat): JsonResponse
    {
        Gate::authorize('view', $flat);

        return Ajax::success(null, ['html' => view('flats.partials._media', ['flat' => $flat->load('media')])->render()]);
    }

    public function store(Request $request, Flat $flat): JsonResponse
    {
        Gate::authorize('manageMedia', $flat);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:'.implode(',', config('keyone.uploads.image_mimes')), 'max:'.config('keyone.uploads.image_max_kb')],
        ]);

        $order = (int) $flat->media()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $flat->media()->create([
                'disk' => 'local',
                'path' => $file->storeAs('flats/'.$flat->public_id, Str::random(40).'.'.$file->guessExtension(), 'local'),
                'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => ++$order,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        activity('flats')->event('media_uploaded')->causedBy($request->user())->performedOn($flat)
            ->tap(fn ($a) => $a->project_id = $flat->project_id)
            ->withProperties(['attributes' => ['images' => count($request->file('images'))]])->log('media_uploaded');

        return Ajax::success('Image(s) uploaded.');
    }

    public function show(Flat $flat, FlatMedia $media): StreamedResponse
    {
        Gate::authorize('view', $flat);
        abort_unless($media->flat_id === $flat->id, 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroy(Request $request, Flat $flat, FlatMedia $media): JsonResponse
    {
        Gate::authorize('manageMedia', $flat);
        abort_unless($media->flat_id === $flat->id, 404);

        $media->delete();
        Storage::disk($media->disk)->delete($media->path);

        return Ajax::success('Image deleted.');
    }
}
