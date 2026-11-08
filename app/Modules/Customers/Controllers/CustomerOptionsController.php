<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        [$results, $more] = TableQuery::options(
            Customer::query()->visibleTo($request->user())->orderBy('full_name'),
            $request,
            ['full_name', 'customer_code', 'primary_mobile'],
            fn (Customer $c) => ['id' => $c->public_id, 'text' => $c->full_name.' · '.$c->customer_code],
        );

        return Ajax::options($results, $more);
    }
}
