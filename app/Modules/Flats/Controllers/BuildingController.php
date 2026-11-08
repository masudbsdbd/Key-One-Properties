<?php

namespace App\Modules\Flats\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\Project;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuildingController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $buildings = $project->buildings()->withCount(['floors', 'flats'])
            ->withCount(['flats as available_count' => fn ($q) => $q->where('status', 'available')])->get();

        return Ajax::success(null, ['html' => view('flats.buildings._list', ['project' => $project, 'buildings' => $buildings])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);

        return $this->form($project, new Building(['status' => 'active']), 'New Building');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($project, $data) {
            $building = $project->buildings()->create($data);
            if (isset($data['floor_from'], $data['floor_to'])) {
                self::createFloors($building, (int) $data['floor_from'], (int) $data['floor_to']);
            }
        });

        return Ajax::success('Building created.');
    }

    public function edit(Project $project, Building $building): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);

        return $this->form($project, $building, 'Edit Building');
    }

    public function update(Request $request, Project $project, Building $building): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);
        $building->update(collect($this->validated($request, $project, $building))->except(['floor_from', 'floor_to'])->all());

        return Ajax::success('Building updated.');
    }

    public function destroy(Project $project, Building $building): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);

        if (Flat::withTrashed()->where('building_id', $building->id)->exists()) {
            return Ajax::error('Delete the units of this building first.', 409);
        }

        $building->delete();

        return Ajax::success('Building deleted.');
    }

    public function floors(Request $request, Project $project, Building $building): JsonResponse
    {
        Gate::authorize('view', $project);

        return Ajax::success(null, [
            'title' => 'Floors · '.$building->name,
            'html' => view($request->boolean('partial') ? 'flats.buildings._floors-body' : 'flats.buildings._floors', [
                'project' => $project,
                'building' => $building,
                'floors' => $building->floors()->withCount('flats')->get(),
            ])->render(),
        ]);
    }

    public function storeFloors(Request $request, Project $project, Building $building): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);
        $data = $request->validate([
            'floor_from' => ['required', 'integer', 'between:-10,200'],
            'floor_to' => ['required', 'integer', 'between:-10,200', 'gte:floor_from'],
        ]);

        $created = DB::transaction(fn () => self::createFloors($building, (int) $data['floor_from'], (int) $data['floor_to']));

        return Ajax::success($created ? $created.' floor(s) added.' : 'All floors already exist.');
    }

    public function updateFloor(Request $request, Project $project, Building $building, Floor $floor): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);
        abort_unless($floor->building_id === $building->id, 404);
        $floor->update($request->validate(['label' => ['required', 'string', 'max:60']]));

        return Ajax::success('Floor renamed.');
    }

    public function destroyFloor(Project $project, Building $building, Floor $floor): JsonResponse
    {
        Gate::authorize('manageBuildings', [Flat::class, $project]);
        abort_unless($floor->building_id === $building->id, 404);

        if (Flat::withTrashed()->where('floor_id', $floor->id)->exists()) {
            return Ajax::error('This floor has units.', 409);
        }
        $floor->delete();

        return Ajax::success('Floor deleted.');
    }

    public static function createFloors(Building $building, int $from, int $to): int
    {
        if ($to - $from > 210) {
            throw ValidationException::withMessages(['floor_to' => 'Too many floors in one go.']);
        }

        $existing = $building->floors()->pluck('floor_number')->all();
        $created = 0;
        for ($n = $from; $n <= $to; $n++) {
            if (in_array($n, $existing, true)) {
                continue;
            }
            $building->floors()->create(['floor_number' => $n, 'label' => Floor::defaultLabel($n), 'sort_order' => $n]);
            $created++;
        }

        return $created;
    }

    private function validated(Request $request, Project $project, ?Building $building = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('buildings')->where('project_id', $project->id)->ignore($building?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(Building::STATUSES))],
            'floor_from' => [$building ? 'prohibited' : 'nullable', 'integer', 'between:-10,200', 'required_with:floor_to'],
            'floor_to' => [$building ? 'prohibited' : 'nullable', 'integer', 'between:-10,200', 'gte:floor_from', 'required_with:floor_from'],
        ]);
    }

    private function form(Project $project, Building $building, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('flats.buildings._form', ['project' => $project, 'building' => $building])->render()]);
    }
}
