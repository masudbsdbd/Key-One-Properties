<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Modules\Customers\Services\CustomerService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return Ajax::success(null, ['html' => view('customers.addresses._list', ['customer' => $customer->load('addresses')])->render()]);
    }

    public function create(Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        return $this->form($customer, new CustomerAddress(['address_type' => 'present', 'country' => 'Bangladesh']), 'New Address');
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);
        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $data, $request) {
            $makePrimary = ($data['is_primary'] ?? false) || ! $customer->addresses()->exists();
            if ($makePrimary) {
                $customer->addresses()->update(['is_primary' => false]);
            }
            $customer->addresses()->create(['is_primary' => $makePrimary] + $data);
            CustomerService::log($customer, $request->user(), 'address_added', ['address' => $data['address_line']]);
        });

        return Ajax::success('Address added.');
    }

    public function edit(Customer $customer, CustomerAddress $address): JsonResponse
    {
        Gate::authorize('update', $customer);

        return $this->form($customer, $address, 'Edit Address');
    }

    public function update(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        Gate::authorize('update', $customer);
        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $address, $data, $request) {
            if ($data['is_primary'] ?? false) {
                $customer->addresses()->whereKeyNot($address->id)->update(['is_primary' => false]);
            } else {
                unset($data['is_primary']);
            }
            $old = $address->oneLine();
            $address->update($data);
            CustomerService::log($customer, $request->user(), 'address_updated', ['address' => $address->oneLine(), 'previous' => $old]);
        });

        return Ajax::success('Address updated.');
    }

    public function destroy(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        Gate::authorize('update', $customer);

        DB::transaction(function () use ($customer, $address, $request) {
            $address->delete();
            if ($address->is_primary) {
                $customer->addresses()->oldest('id')->first()?->update(['is_primary' => true]);
            }
            CustomerService::log($customer, $request->user(), 'address_removed', ['address' => $address->oneLine()]);
        });

        return Ajax::success('Address removed.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['is_primary' => $request->boolean('is_primary')]);

        return $request->validate([
            'address_type' => ['required', Rule::in(array_keys(CustomerAddress::TYPES))],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'is_primary' => ['boolean'],
        ]);
    }

    private function form(Customer $customer, CustomerAddress $address, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('customers.addresses._form', ['customer' => $customer, 'address' => $address])->render()]);
    }
}
