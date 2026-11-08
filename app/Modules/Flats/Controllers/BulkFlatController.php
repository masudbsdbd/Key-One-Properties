<?php

namespace App\Modules\Flats\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Project;
use App\Modules\Flats\Services\FlatService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BulkFlatController extends Controller
{
    public function __construct(private FlatService $service) {}

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('bulkCreateIn', [Flat::class, $project]);

        return Ajax::success(null, [
            'title' => 'Bulk Create Units',
            'html' => view('flats.bulk._form', [
                'project' => $project,
                'buildings' => $project->buildings()->pluck('name', 'public_id'),
                'idempotencyKey' => (string) Str::uuid(),
            ])->render(),
        ]);
    }

    public function preview(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('bulkCreateIn', [Flat::class, $project]);
        [$building, $plan] = $this->plan($request, $project);

        return Ajax::success(null, [
            'html' => view('flats.bulk._preview', [
                'plan' => $plan,
                'building' => $building,
                'conflicts' => $plan->filter(fn ($r) => $r['exists'] || $r['duplicate'])->count(),
            ])->render(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('bulkCreateIn', [Flat::class, $project]);
        $request->validate(['idempotency_key' => ['required', 'uuid']]);

        // A retried or double-clicked submit with the same key is ignored.
        if (! Cache::add('bulk-flats:'.$request->input('idempotency_key'), true, now()->addHour())) {
            return Ajax::error('This batch was already submitted.', 409);
        }

        try {
            [, $plan] = $this->plan($request, $project);
            $count = $this->service->bulkCreate($plan, $this->attributes($request), $request->input('list_price'), $request->user());
        } catch (\Throwable $e) {
            Cache::forget('bulk-flats:'.$request->input('idempotency_key'));
            throw $e;
        }

        activity('flats')->event('bulk_created')->causedBy($request->user())->performedOn($project)
            ->tap(fn ($a) => $a->project_id = $project->id)
            ->withProperties(['attributes' => ['units' => $count]])->log('bulk_created');

        return Ajax::success($count.' units created.');
    }

    /**
     * @return array{0: Building, 1: Collection}
     */
    private function plan(Request $request, Project $project): array
    {
        $request->merge([
            'saleable_area' => str_replace(',', '', (string) $request->input('saleable_area')),
            'list_price' => $request->filled('list_price') ? str_replace(',', '', (string) $request->input('list_price')) : null,
        ]);

        $data = $request->validate([
            'building' => ['required', 'string', Rule::exists('buildings', 'public_id')->where('project_id', $project->id)],
            'floors' => ['required', 'array', 'min:1', 'max:210'],
            'floors.*' => ['string', 'size:16'],
            'per_floor' => ['required', 'integer', 'between:1,50'],
            'start_index' => ['nullable', 'integer', 'between:1,500'],
            'pattern' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/{}]+$/', 'regex:/\{(n|L)\}/'],
            'flat_type' => ['nullable', Rule::in(array_keys(Flat::TYPES))],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'integer', 'between:0,20'],
            'saleable_area' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'area_unit' => ['required', Rule::in(['sqft', 'sqm'])],
            'list_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ], [
            'pattern.regex' => 'The pattern must contain {n} or {L} so each unit gets a different number.',
            'saleable_area.gt' => 'Saleable area must be greater than zero.',
        ]);

        $building = $project->buildings()->where('public_id', $data['building'])->firstOrFail();
        $floors = $building->floors()->whereIn('public_id', $data['floors'])->get();
        abort_if($floors->count() !== count(array_unique($data['floors'])), 422, 'Select floors of the chosen building.');

        $plan = $this->service->bulkPlan($building, $floors, (int) $data['per_floor'], $data['pattern'], (int) ($data['start_index'] ?? 1));

        return [$building, $plan];
    }

    private function attributes(Request $request): array
    {
        return $request->only(['flat_type', 'bedrooms', 'bathrooms', 'saleable_area', 'area_unit']);
    }
}
