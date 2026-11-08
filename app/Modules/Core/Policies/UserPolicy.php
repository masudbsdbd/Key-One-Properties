<?php

namespace App\Modules\Core\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update') && $this->canTouch($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.delete') && $actor->isNot($user) && $this->canTouch($actor, $user);
    }

    public function manageTwoFactor(User $actor, User $user): bool
    {
        return $actor->can('users.manage_2fa') && $this->canTouch($actor, $user);
    }

    /**
     * Your own password is changed from the profile page, not reset by yourself.
     */
    public function resetPassword(User $actor, User $user): bool
    {
        return $actor->can('users.reset_password') && $actor->isNot($user) && $this->canTouch($actor, $user);
    }

    /**
     * Only a Super Admin may change another Super Admin.
     */
    private function canTouch(User $actor, User $user): bool
    {
        return ! $user->isSuperAdmin() || $actor->isSuperAdmin();
    }
}
