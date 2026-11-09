<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Investors\Services\ProfitPolicy;
use App\Support\Ajax;
use App\Support\Branding;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
