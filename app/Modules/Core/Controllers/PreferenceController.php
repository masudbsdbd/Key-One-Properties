<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Ajax;
use App\Support\Preferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', Rule::in(array_keys(Preferences::ALLOWED))]]);
        $value = $request->validate(['value' => ['required', 'string', Rule::in(Preferences::ALLOWED[$data['key']])]])['value'];
        Preferences::set($request->user(), $data['key'], $value);

        return Ajax::success(null);
    }
}
