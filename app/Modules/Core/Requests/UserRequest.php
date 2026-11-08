<?php

namespace App\Modules\Core\Requests;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user ? $this->user()->can('update', $user) : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
            'mobile' => User::normalizeMobile($this->input('mobile')),
            'two_factor_required' => $this->boolean('two_factor_required'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $ignore = $user?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:mobile', Rule::unique('users')->ignore($ignore)],
            'mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/', 'required_without:email', Rule::unique('users')->ignore($ignore)],
            'designation' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'string', Rule::exists('roles', 'public_id')],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', Rule::exists('departments', 'public_id')],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'two_factor_required' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Enter an email or a mobile number.',
            'mobile.required_without' => 'Enter a mobile number or an email.',
            'mobile.regex' => 'Enter a valid mobile number.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $role = Role::findByPublicId($this->input('role'));
            if ($role?->isSuperAdmin() && ! $this->user()->isSuperAdmin()) {
                $validator->errors()->add('role', 'Only a Super Admin can assign this role.');
            }

            $target = $this->route('user');
            if ($target && $target->is($this->user()) && $this->input('status') !== User::STATUS_ACTIVE) {
                $validator->errors()->add('status', 'You cannot deactivate your own account.');
            }
            if ($target && $target->is($this->user()) && $target->isSuperAdmin() && ! $role?->isSuperAdmin()) {
                $validator->errors()->add('role', 'You cannot remove your own Super Admin role.');
            }
        }];
    }

    public function role(): Role
    {
        return Role::findByPublicIdOrFail($this->input('role'));
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return Department::query()->whereIn('public_id', $this->input('departments', []))->pluck('id')->all();
    }
}
