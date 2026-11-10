<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabourType;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LabourTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.view'), 403);

        return Ajax::success(null, ['html' => view('construction.labour._types', [
            'types' => LabourType::query()->withCount('entries')->orderByDesc('is_active')->orderBy('name')->get(),
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.manage'), 403);
        $data = $this->validated($request, null);
        $type = new LabourType($data);
        $type->code = LabourType::codeFor($data['name']);
        $type->save();

        return Ajax::success($type->name.' added.');
    }

    public function edit(Request $request, LabourType $type): JsonResponse
    {
        abort_unless($request->user()->can('construction.manage'), 403);

        return Ajax::success(null, ['title' => 'Edit '.$type->name, 'html' => view('construction.labour._type-form', ['type' => $type])->render()]);
    }

    public function update(Request $request, LabourType $type): JsonResponse
    {
        abort_unless($request->user()->can('construction.manage'), 403);
        $type->update($this->validated($request, $type));

        return Ajax::success($type->name.' saved.');
    }

    public function destroy(Request $request, LabourType $type): JsonResponse
    {
        abort_unless($request->user()->can('construction.manage'), 403);
        if ($type->entries()->exists()) {
            throw ValidationException::withMessages(['type' => 'This type has labour entries. Mark it inactive instead.']);
        }
        $type->delete();

        return Ajax::success($type->name.' deleted.');
    }

    private function validated(Request $request, ?LabourType $type): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'default_rate' => str_replace(',', '', (string) $request->input('default_rate')) ?: null,
            'is_active' => $type ? $request->boolean('is_active') : true,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('labour_types', 'name')->ignore($type?->id)],
            'default_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'is_active' => ['boolean'],
        ], ['name.unique' => 'This labour type already exists.']);
    }
}
