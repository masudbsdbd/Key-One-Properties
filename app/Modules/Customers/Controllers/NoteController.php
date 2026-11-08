<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Modules\Customers\Services\CustomerService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return Ajax::success(null, ['html' => view('customers.notes._list', [
            'customer' => $customer,
            'notes' => $customer->notes()->with('author:id,name')->limit(100)->get(),
        ])->render()]);
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('addNote', $customer);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $customer->notes()->create(['body' => $data['body'], 'visibility' => 'internal', 'created_by' => $request->user()->id]);
        CustomerService::log($customer, $request->user(), 'note_added');

        return Ajax::success('Note added.');
    }

    public function destroy(Request $request, Customer $customer, CustomerNote $note): JsonResponse
    {
        Gate::authorize('view', $customer);
        abort_unless($note->created_by === $request->user()->id || $request->user()->can('update', $customer), 403);

        $note->delete();
        CustomerService::log($customer, $request->user(), 'note_deleted');

        return Ajax::success('Note deleted.');
    }
}
