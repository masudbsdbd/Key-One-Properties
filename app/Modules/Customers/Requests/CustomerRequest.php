<?php

namespace App\Modules\Customers\Requests;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer ? $this->user()->can('update', $customer) : $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'primary_mobile' => Phone::normalize($this->input('primary_mobile')),
            'alternate_mobile' => Phone::normalize($this->input('alternate_mobile')),
            'nid_or_passport' => $this->filled('nid_or_passport') ? mb_strtoupper(preg_replace('/\s+/', '', $this->input('nid_or_passport'))) : null,
        ]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $creating = ! $customer;

        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:191'],
            'primary_mobile' => ['required', 'string', 'regex:/^\+?\d{10,15}$/', Rule::unique('customers')->ignore($customer?->id)],
            'alternate_mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/', 'different:primary_mobile'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'nid_or_passport' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'source' => ['nullable', Rule::in(array_keys(Customer::SOURCES))],

            // Optional first address on create.
            'address_line' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'address_type' => ['nullable', Rule::in(array_keys(CustomerAddress::TYPES))],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_mobile.regex' => 'Enter a valid mobile number.',
            'alternate_mobile.regex' => 'Enter a valid mobile number.',
            'alternate_mobile.different' => 'The alternate number must differ from the primary number.',
            'nid_or_passport.regex' => 'Use letters and numbers only.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('primary_mobile')) {
                return;
            }

            // Name the existing record only when the user may see it.
            $existing = Customer::withTrashed()->where('primary_mobile', $this->input('primary_mobile'))->first();
            if ($existing && $existing->isVisibleTo($this->user())) {
                $validator->errors()->forget('primary_mobile');
                $validator->errors()->add('primary_mobile', 'Already registered to '.$existing->full_name.' ('.$existing->customer_code.')'.($existing->trashed() ? ', archived.' : '.'));
            } elseif ($existing) {
                $validator->errors()->forget('primary_mobile');
                $validator->errors()->add('primary_mobile', 'This mobile number is already registered.');
            }
        }];
    }

    /**
     * Customers sharing a number with this request, for a non-blocking warning.
     *
     * @return array<int, string>
     */
    public function possibleDuplicates(): array
    {
        $numbers = array_filter([$this->input('primary_mobile'), $this->input('alternate_mobile')]);
        $customer = $this->route('customer');

        return Customer::query()->visibleTo($this->user())
            ->when($customer, fn ($q) => $q->whereKeyNot($customer->id))
            ->where(fn ($q) => $q->whereIn('alternate_mobile', $numbers)->orWhereIn('primary_mobile', array_slice($numbers, 1)))
            ->limit(3)->get()
            ->map(fn (Customer $c) => $c->full_name.' ('.$c->customer_code.')')->all();
    }
}
