<?php

namespace App\Modules\Customers\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['customers.view', 'customers.view_all']);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $customer->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $customer->isVisibleTo($user) && ! $customer->trashed();
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->can('customers.archive') && $customer->isVisibleTo($user);
    }

    public function manageDocuments(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage_documents') && $customer->isVisibleTo($user);
    }

    public function viewSensitive(User $user, Customer $customer): bool
    {
        return $user->can('customers.view_sensitive') && $customer->isVisibleTo($user);
    }

    public function addNote(User $user, Customer $customer): bool
    {
        return $customer->isVisibleTo($user) && ! $customer->trashed();
    }
}
