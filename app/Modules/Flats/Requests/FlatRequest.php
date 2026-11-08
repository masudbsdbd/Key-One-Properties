<?php

namespace App\Modules\Flats\Requests;

use App\Models\Flat;
use App\Models\Floor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FlatRequest extends FormRequest
{
    private ?Floor $resolvedFloor = null;

    public function authorize(): bool
    {
        /** @var Flat|null $flat */
        $flat = $this->route('flat');

        // Creation is additionally authorised against the resolved project in after().
        return $flat ? $this->user()->can('update', $flat) : $this->user()->can('flats.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'flat_no' => mb_strtoupper(trim((string) $this->input('flat_no'))),
            'list_price' => $this->filled('list_price') ? str_replace(',', '', (string) $this->input('list_price')) : null,
            'saleable_area' => str_replace(',', '', (string) $this->input('saleable_area')),
        ]);
    }

    public function rules(): array
    {
        $creating = ! $this->route('flat');

        return [
            'project' => [$creating ? 'required' : 'nullable', 'string', 'size:16'],
            'building' => [$creating ? 'required' : 'nullable', 'string', 'size:16'],
            'floor' => ['required', 'string', 'size:16'],
            'flat_no' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-\/]+$/'],
            'flat_type' => ['nullable', Rule::in(array_keys(Flat::TYPES))],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'integer', 'between:0,20'],
            'balconies' => ['nullable', 'integer', 'between:0,20'],
            'facing' => ['nullable', Rule::in(array_keys(Flat::FACINGS))],
            'saleable_area' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'area_unit' => ['required', Rule::in(['sqft', 'sqm'])],
            'list_price' => $creating ? ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'] : ['prohibited'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_no.regex' => 'Use letters, numbers, dashes or slashes only.',
            'saleable_area.gt' => 'Saleable area must be greater than zero.',
            'list_price.prohibited' => 'Change the price with the price action so it is recorded in history.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Flat|null $flat */
            $flat = $this->route('flat');
            $floor = Floor::query()->with('building.project')->where('public_id', $this->input('floor'))->first();

            if ($flat) {
                if (! $floor || $floor->building_id !== $flat->building_id) {
                    $validator->errors()->add('floor', 'Select a floor of this building.');

                    return;
                }
                $buildingId = $flat->building_id;
            } else {
                $project = Project::query()->where('public_id', $this->input('project'))->first();
                if (! $project || ! $this->user()->can('createIn', [Flat::class, $project])) {
                    $validator->errors()->add('project', 'Select a project you can add units to.');

                    return;
                }
                $building = $project->buildings()->where('public_id', $this->input('building'))->first();
                if (! $building) {
                    $validator->errors()->add('building', 'Select a building of this project.');

                    return;
                }
                if (! $floor || $floor->building_id !== $building->id) {
                    $validator->errors()->add('floor', 'Select a floor of this building.');

                    return;
                }
                $buildingId = $building->id;
            }

            $taken = Flat::withTrashed()->where('building_id', $buildingId)->where('flat_no', $this->input('flat_no'))
                ->when($flat, fn ($q) => $q->whereKeyNot($flat->id))->exists();
            if ($taken) {
                $validator->errors()->add('flat_no', 'This unit number already exists in the building.');
            }

            $this->resolvedFloor = $floor;
        }];
    }

    public function floor(): Floor
    {
        return $this->resolvedFloor;
    }

    /**
     * @return array<string, mixed>
     */
    public function flatAttributes(): array
    {
        return $this->safe()->only(['flat_no', 'flat_type', 'bedrooms', 'bathrooms', 'balconies', 'facing', 'saleable_area', 'area_unit', 'description']);
    }
}
