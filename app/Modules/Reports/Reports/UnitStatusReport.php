<?php

namespace App\Modules\Reports\Reports;

use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UnitStatusReport extends BaseReport
{
    public function key(): string
    {
        return 'unit-status';
    }

    public function name(): string
    {
        return 'Unit Status';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function basis(): string
    {
        return 'Current unit status; sold value is the net contract of active sales';
    }

    public function filters(): array
    {
        return ['project'];
    }

    public function columns(): array
    {
        return ['Project' => 'text', 'Building' => 'text', 'Units' => 'number', 'Not Released' => 'number', 'Available' => 'number', 'Booked' => 'number', 'Sold' => 'number', 'Unavailable' => 'number',
            'Available List Value' => 'money', 'Sold Value' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $sold = FlatSale::query()->whereColumn('flat_id', 'flats.id')->whereIn('status', FlatSale::ACTIVE)->selectRaw('COALESCE(SUM(net_contract_value),0)')->toRawSql();
        $query = Flat::query()->visibleTo($user)->join('projects', 'projects.id', '=', 'flats.project_id')->join('buildings', 'buildings.id', '=', 'flats.building_id')
            ->selectRaw("projects.code AS project_code, buildings.name AS building_name, COUNT(*) AS units,
                SUM(flats.status = 'not_released') AS not_released, SUM(flats.status = 'available') AS available, SUM(flats.status = 'booked') AS booked, SUM(flats.status = 'sold') AS sold,
                SUM(flats.status IN ('unavailable', 'maintenance')) AS unavailable,
                SUM(CASE WHEN flats.status = 'available' THEN flats.list_price ELSE 0 END) AS available_value,
                SUM(({$sold})) AS sold_value")
            ->groupBy('projects.code', 'buildings.name');
        if ($project = self::project($request, $user)) {
            $query->where('flats.project_id', $project->id);
        }

        return $query->orderBy('projects.code')->orderBy('buildings.name');
    }

    public function row(mixed $r): array
    {
        return [$r->project_code, $r->building_name, (int) $r->units, (int) $r->not_released, (int) $r->available, (int) $r->booked, (int) $r->sold, (int) $r->unavailable,
            Money::amount((string) $r->available_value), Money::amount((string) $r->sold_value)];
    }
}
