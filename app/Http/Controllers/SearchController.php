<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Flat;
use App\Models\Project;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const LIMIT = 10;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $term = mb_substr(trim((string) $request->input('q')), 0, 100);
        $results = ['projects' => collect(), 'flats' => collect(), 'customers' => collect()];

        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '%_\\').'%';

            if ($user->canAny(['projects.view', 'projects.view_all'])) {
                $results['projects'] = Project::query()->visibleTo($user)
                    ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('location_text', 'like', $like))
                    ->orderBy('name')->limit(self::LIMIT)->get();
            }

            if ($user->can('flats.view')) {
                $results['flats'] = Flat::query()->visibleTo($user)->with(['project:id,public_id,name', 'building:id,code'])
                    ->where('flat_no', 'like', $like)->orderBy('flat_no')->limit(self::LIMIT)->get();
            }

            if ($user->canAny(['customers.view', 'customers.view_all'])) {
                $mobile = preg_match('/^[\d\s+\-]{6,}$/', $term) ? '%'.Phone::normalize($term).'%' : $like;
                $results['customers'] = Customer::query()->visibleTo($user)
                    ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('customer_code', 'like', $like)
                        ->orWhere('email', 'like', $like)->orWhere('primary_mobile', 'like', $mobile)->orWhere('alternate_mobile', 'like', $mobile))
                    ->orderBy('full_name')->limit(self::LIMIT)->get();
            }
        }

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
