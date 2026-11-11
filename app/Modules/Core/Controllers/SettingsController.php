<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Investors\Services\ProfitPolicy;
use App\Support\Ajax;
use App\Support\Branding;
use App\Support\PageSize;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Branding $branding): View
    {
        Gate::authorize('settings.manage');

        return view('core.settings.edit', ['branding' => $branding]);
    }

    public function updateBranding(Request $request, Settings $settings, Branding $branding): JsonResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
        ]);

        $before = $settings->all();
        $values = [
            'branding.app_name' => $data['app_name'],
            'branding.tagline' => $data['tagline'] ?? null,
            'branding.primary_color' => strtolower($data['primary_color']),
            'branding.sidebar_color' => strtolower($data['sidebar_color']),
        ];

        foreach (['logo', 'favicon'] as $asset) {
            $key = "branding.{$asset}_path";
            if ($request->hasFile($asset)) {
                $values[$key] = $this->store($request->file($asset), $before[$key] ?? null);
            } elseif ($request->boolean("remove_{$asset}")) {
                $this->delete($before[$key] ?? null);
                $values[$key] = null;
            }
        }

        $settings->put($values);

        $changed = collect($values)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v);
        if ($changed->isNotEmpty()) {
            activity('settings')->event('branding_updated')->causedBy($request->user())
                ->withProperties([
                    'name' => 'Branding',
                    'old' => $changed->keys()->mapWithKeys(fn ($k) => [Str::after($k, 'branding.') => $before[$k] ?? null])->all(),
                    'attributes' => $changed->mapWithKeys(fn ($v, $k) => [Str::after($k, 'branding.') => $v])->all(),
                ])->log('branding_updated');
        }

        return Ajax::success('Branding saved.', ['redirect' => route('settings.edit')]);
    }

    public function updateProjectStages(Request $request, Settings $settings): JsonResponse
    {
        Gate::authorize('settings.manage');
        $clean = fn ($values) => array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $values), fn ($v) => $v !== '' && $v !== null));
        $groups = (array) $request->input('packages', []);
        $stages = $packages = [];
        // Work packages arrive by stage position; blank stage rows are dropped together with theirs.
        foreach ((array) $request->input('stages', []) as $i => $name) {
            if ($clean([$name]) !== []) {
                $stages[] = $clean([$name])[0];
                $packages[] = $clean($groups[$i] ?? []);
            }
        }
        $request->merge(['stages' => $stages, 'packages' => $packages]);
        $request->validate([
            'stages' => ['array', 'max:20'],
            'stages.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'packages' => ['array', 'max:20'],
            'packages.*' => ['array', 'max:30'],
            'packages.*.*' => ['required', 'string', 'max:160', 'distinct:ignore_case'],
        ], [
            'stages.*.distinct' => 'Each stage name must be different.',
            'packages.*.*.distinct' => 'Each work package name must be different.',
            'packages.*.max' => 'A stage can have at most 30 work packages.',
        ]);

        $before = ['stages' => Project::defaultStages(), 'work_packages' => array_sum(array_map('count', Project::defaultWorkPackages()))];
        $beforeList = Project::defaultWorkPackages();
        $list = array_combine($stages, $packages);
        $settings->put([
            'projects.default_stages' => json_encode($stages),
            'projects.default_work_packages' => json_encode((object) $list),
        ]);
        if ($before['stages'] !== $stages || $beforeList !== $list) {
            activity('settings')->event('default_stages_updated')->causedBy($request->user())
                ->withProperties(['name' => 'Default project stages', 'old' => $before, 'attributes' => ['stages' => $stages, 'work_packages' => array_sum(array_map('count', $list))]])
                ->log('default_stages_updated');
        }

        return Ajax::success('Default stages saved.');
    }

    public function updateProfitPolicy(Request $request, Settings $settings): JsonResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'revenue_recognition' => ['nullable', Rule::in(array_keys(ProfitPolicy::RECOGNITION))],
            'cost_policy' => ['nullable', Rule::in(array_keys(ProfitPolicy::COST_POLICIES))],
        ]);
        $before = $settings->all();
        $values = ['investors.revenue_recognition' => $data['revenue_recognition'] ?? null, 'investors.cost_policy' => $data['cost_policy'] ?? null];
        $changed = collect($values)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v);
        $settings->put($values);
        if ($changed->isNotEmpty()) {
            activity('settings')->event('profit_policy_updated')->causedBy($request->user())
                ->withProperties([
                    'name' => 'Investor profit policy',
                    'old' => $changed->keys()->mapWithKeys(fn ($k) => [Str::after($k, 'investors.') => $before[$k] ?? null])->all(),
                    'attributes' => $changed->mapWithKeys(fn ($v, $k) => [Str::after($k, 'investors.') => $v])->all(),
                ])->log('profit_policy_updated');
        }

        return Ajax::success('Profit policy saved.');
    }

    public function updateTables(Request $request, Settings $settings): JsonResponse
    {
        Gate::authorize('settings.manage');
        $raw = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('page_sizes'))), fn ($v) => $v !== ''));
        $error = match (true) {
            $raw === [] => 'Enter at least one size.',
            count($raw) > PageSize::MAX_OPTIONS => 'Use at most '.PageSize::MAX_OPTIONS.' sizes.',
            collect($raw)->contains(fn ($v) => ! ctype_digit($v) || (int) $v < 1) => 'Sizes must be whole numbers.',
            collect($raw)->contains(fn ($v) => (int) $v > PageSize::MAX) => 'A size cannot exceed '.number_format(PageSize::MAX).'.',
            count(array_unique(array_map('intval', $raw))) !== count($raw) => 'Each size must be different.',
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['page_sizes' => $error]);
        }
        $data = ['page_sizes' => array_map('intval', $raw)] + $request->validate(
            ['default_page_size' => ['required', 'integer', Rule::in(array_map('intval', $raw))]],
            ['default_page_size.*' => 'The default must be one of the sizes.'],
        );

        $before = ['page_sizes' => PageSize::options(), 'default_page_size' => PageSize::default()];
        $after = ['page_sizes' => PageSize::clean($data['page_sizes']), 'default_page_size' => (int) $data['default_page_size']];
        $settings->put(['tables.page_sizes' => json_encode($after['page_sizes']), 'tables.default_page_size' => (string) $after['default_page_size']]);
        if ($before !== $after) {
            activity('settings')->event('tables_updated')->causedBy($request->user())
                ->withProperties(['name' => 'Rows per page', 'old' => $before, 'attributes' => $after])->log('tables_updated');
        }

        return Ajax::success('Rows per page saved.', ['redirect' => route('settings.edit')]);
    }

    private function store(UploadedFile $file, ?string $previous): string
    {
        $this->delete($previous);

        return $file->storeAs('branding', Str::random(32).'.'.$file->guessExtension(), 'public');
    }

    private function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
