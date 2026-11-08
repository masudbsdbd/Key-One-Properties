<?php

namespace App\Modules\Customers\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = new Customer($data);
            $customer->customer_code = Customer::nextCode();
            $customer->created_by = $user->id;
            $customer->save();

            if (! empty($data['address_line'])) {
                $customer->addresses()->create([
                    'address_type' => $data['address_type'] ?? 'present',
                    'address_line' => $data['address_line'],
                    'city' => $data['city'] ?? null,
                    'district' => $data['district'] ?? null,
                    'is_primary' => true,
                ]);
            }

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $customer->fill($data);
            $identityChanged = $customer->isDirty('nid_or_passport');
            $customer->save();

            if ($identityChanged) {
                activity('customers')->event('identity_updated')->causedBy($user)->performedOn($customer)->log('identity_updated');
            }

            return $customer;
        });
    }

    public static function log(Customer $customer, User $user, string $event, array $attributes = []): void
    {
        activity('customers')->event($event)->causedBy($user)->performedOn($customer)
            ->withProperties($attributes ? ['attributes' => $attributes] : [])->log($event);
    }
}
