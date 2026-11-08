<?php

namespace App\Modules\Projects\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project ? $this->user()->can('update', $project) : $this->user()->can('create', Project::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? mb_strtoupper(trim($this->input('code'))) : null,
            'estimated_budget' => $this->filled('estimated_budget') ? str_replace(',', '', (string) $this->input('estimated_budget')) : null,
        ]);
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('projects')->ignore($project?->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_text' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'start_date' => ['required', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_completion_date' => ['nullable', 'date', 'after_or_equal:start_date', Rule::requiredIf($this->input('status') === 'completed')],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers and dashes only.',
            'actual_completion_date.required' => 'Enter the completion date for a completed project.',
        ];
    }
}
