<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ComponentShowcaseController extends Controller
{
    public function index(): View
    {
        return view('dev.components');
    }

    public function table(Request $request): JsonResponse
    {
        $rows = collect(range(1, 42))->map(fn (int $i) => [
            'name' => 'Sample Customer '.$i,
            'phone' => '+880 17'.str_pad((string) ($i * 7919 % 100000000), 8, '0', STR_PAD_LEFT),
            'stage' => ['New', 'Contacted', 'Qualified', 'Won', 'Lost'][$i % 5],
        ]);

        if ($search = $request->string('search')->trim()->value()) {
            $rows = $rows->filter(fn ($r) => str_contains(strtolower($r['name']), strtolower($search)));
        }

        $rows = $request->input('dir') === 'desc' ? $rows->sortByDesc('name') : $rows->sortBy('name', SORT_NATURAL);
        $page = max(1, (int) $request->input('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page);

        return Ajax::table(view('dev.partials.table-rows', ['rows' => $paginator->items()])->render(), $paginator);
    }

    public function validateForm(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9 +]{10,16}$/'],
            'source' => ['sometimes', 'required'],
        ]);

        return Ajax::success('Saved successfully');
    }
}
