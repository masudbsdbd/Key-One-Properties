<?php

namespace App\Modules\Flats\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\Project;
use App\Modules\Flats\Requests\FlatRequest;
use App\Modules\Flats\Services\FlatService;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class FlatController extends Controller
{
    public function __construct(private FlatService $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Flat::class);

        return view('flats.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $project = Project::findByPublicId($request->input('project'));
        $flats = TableQuery::paginate($query, $request);
        $cards = $request->input('view') === 'cards';
        $response = Ajax::table(view($cards ? 'flats._cards' : 'flats._rows', [
            'flats' => $flats,
            'showProject' => ! $project,
        ])->render(), $flats);

        $counts = $this->filtered($request, withStatus: false)->toBase()->selectRaw("COUNT(*) AS total,
            SUM(status = 'available') AS available, SUM(status = 'booked') AS booked, SUM(status = 'sold') AS sold,
            SUM(status IN ('unavailable', 'maintenance')) AS unavailable")->first();
        $data = $response->getData(true);
        $data['data']['stats'] = view('flats._stats', ['counts' => array_map('intval', (array) $counts)])->render();

        return $response->setData($data);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('flats.export'), 403);

        return Report::make('Units')->meta(['Project' => Project::findByPublicId($request->input('project'))?->name, 'Status' => Flat::STATUSES[(string) $request->input('status')] ?? null, 'Type' => $request->input('type')])
            ->columns(['Project' => 'text', 'Building' => 'text', 'Floor' => 'text', 'Unit' => 'text', 'Type' => 'text', 'Area' => 'qty', 'Unit of Area' => 'text', 'List Price' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (Flat $f) => [$f->project->code, $f->building->code, $f->floor->label, $f->flat_no, $f->flat_type, $f->saleable_area, $f->area_unit, $f->list_price, Flat::STATUSES[$f->status] ?? $f->status]))
            ->respond($request);
    }

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        Gate::authorize('viewAny', Flat::class);
        $query = Flat::query()->visibleTo($request->user());
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($building = Building::findByPublicId($request->input('building'))) {
            $query->where('building_id', $building->id);
        }
        if ($floor = Floor::findByPublicId($request->input('floor'))) {
            $query->where('floor_id', $floor->id);
        }
        if ($withStatus && array_key_exists($status = (string) $request->input('status'), Flat::STATUSES)) {
            $query->where('status', $status);
        }
        if (array_key_exists($type = (string) $request->input('type'), Flat::TYPES)) {
            $query->where('flat_type', $type);
        }

        return $query;
    }

    private function listing(Request $request): Builder
    {
        $query = $this->filtered($request)->with(['project:id,public_id,name,code', 'building:id,public_id,code,name', 'floor:id,public_id,label,floor_number']);

        return TableQuery::prepare($query, $request, ['flat_no', 'flat_type'], [
            'flat_no' => fn ($q, $dir) => $q->orderBy('building_id', $dir)->orderBy('flat_no', $dir),
            'floor' => fn ($q, $dir) => $q->orderBy(Floor::select('floor_number')->whereColumn('floors.id', 'flats.floor_id'), $dir),
            'area' => 'saleable_area',
            'price' => 'list_price',
            'status' => 'status',
        ], 'flat_no');
    }

    public function create(Request $request): JsonResponse
    {
        $project = Project::findByPublicId($request->input('project'));
        abort_unless($request->user()->can('flats.create'), 403);
        if ($project) {
            Gate::authorize('createIn', [Flat::class, $project]);
        }

        return Ajax::success(null, [
            'title' => 'New Unit',
            'html' => view('flats._form', ['flat' => new Flat(['area_unit' => 'sqft']), 'project' => $project])->render(),
        ]);
    }

    public function store(FlatRequest $request): JsonResponse
    {
        $flat = $this->service->create($request->floor(), $request->flatAttributes(), $request->input('list_price'), $request->user());

        return Ajax::success('Unit '.$flat->flat_no.' created.');
    }

    public function show(Flat $flat): View
    {
        Gate::authorize('view', $flat);
        $flat->load(['project', 'building', 'floor', 'creator', 'priceHistory.changer', 'statusHistory.changer', 'media']);

        $activity = Activity::query()->where('subject_type', $flat->getMorphClass())->where('subject_id', $flat->id)
            ->with('causer')->latest()->latest('id')->limit(50)->get();

        return view('flats.show', ['flat' => $flat, 'activity' => $activity]);
    }

    public function history(Request $request, Flat $flat): JsonResponse
    {
        Gate::authorize('view', $flat);

        $html = $request->input('type') === 'status'
            ? view('flats.partials._status-history', ['history' => $flat->statusHistory()->with('changer')->get()])->render()
            : view('flats.partials._price-history', ['history' => $flat->priceHistory()->with('changer')->get()])->render();

        return Ajax::success(null, ['html' => $html]);
    }

    public function edit(Flat $flat): JsonResponse
    {
        Gate::authorize('update', $flat);
        $flat->load(['project', 'building', 'floor']);

        return Ajax::success(null, [
            'title' => 'Edit Unit '.$flat->flat_no,
            'html' => view('flats._form', ['flat' => $flat, 'project' => $flat->project])->render(),
        ]);
    }

    public function update(FlatRequest $request, Flat $flat): JsonResponse
    {
        $this->service->update($flat, $request->floor(), $request->flatAttributes());

        return Ajax::success('Unit updated.');
    }

    public function destroy(Flat $flat): JsonResponse
    {
        Gate::authorize('delete', $flat);
        $flat->delete();

        return Ajax::success('Unit deleted.');
    }

    public function priceForm(Flat $flat): JsonResponse
    {
        Gate::authorize('changePrice', $flat);

        return Ajax::success(null, ['title' => 'Change Price · '.$flat->flat_no, 'html' => view('flats._price-form', ['flat' => $flat])->render()]);
    }

    public function price(Request $request, Flat $flat): JsonResponse
    {
        Gate::authorize('changePrice', $flat);
        $request->merge(['list_price' => str_replace(',', '', (string) $request->input('list_price'))]);
        $data = $request->validate([
            'list_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        app(PriceRevisionService::class)->request($flat, (string) $data['list_price'], $data['reason'], $request->user());

        return Ajax::success('Price change sent for approval.');
    }

    public function statusForm(Flat $flat): JsonResponse
    {
        Gate::authorize('changeStatus', $flat);
        abort_if($flat->isLockedBySales(), 409, 'This unit is managed by the sales workflow.');

        return Ajax::success(null, ['title' => 'Change Status · '.$flat->flat_no, 'html' => view('flats._status-form', ['flat' => $flat])->render()]);
    }

    public function status(Request $request, Flat $flat): JsonResponse
    {
        Gate::authorize('changeStatus', $flat);
        $data = $request->validate([
            'status' => ['required', Rule::in(Flat::MANUAL_STATUSES)],
            'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('status') !== 'available')],
        ], ['status.in' => 'Booked and sold are set by the sales workflow.']);

        $this->service->changeStatus($flat, $data['status'], $data['reason'] ?? null, $request->user());

        return Ajax::success('Status updated.');
    }
}
