<?php

namespace App\Modules\Flats\Policies;

use App\Models\Flat;
use App\Models\Project;
use App\Models\User;

class FlatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('flats.view');
    }

    public function view(User $user, Flat $flat): bool
    {
        return $user->can('flats.view') && $flat->project->isVisibleTo($user);
    }

    public function createIn(User $user, Project $project): bool
    {
        return $user->can('flats.create') && $project->isVisibleTo($user) && ! $project->trashed();
    }

    public function bulkCreateIn(User $user, Project $project): bool
    {
        return $user->can('flats.bulk_create') && $project->isVisibleTo($user) && ! $project->trashed();
    }

    public function update(User $user, Flat $flat): bool
    {
        return $this->allowed($user, $flat, 'flats.update');
    }

    public function delete(User $user, Flat $flat): bool
    {
        return $this->allowed($user, $flat, 'flats.delete') && ! $flat->isLockedBySales();
    }

    public function changePrice(User $user, Flat $flat): bool
    {
        return $this->allowed($user, $flat, 'flats.change_price');
    }

    public function changeStatus(User $user, Flat $flat): bool
    {
        return $this->allowed($user, $flat, 'flats.change_status');
    }

    public function manageMedia(User $user, Flat $flat): bool
    {
        return $this->allowed($user, $flat, 'flats.manage_media');
    }

    public function manageBuildings(User $user, Project $project): bool
    {
        return $user->can('buildings.manage') && $project->isVisibleTo($user) && ! $project->trashed();
    }

    private function allowed(User $user, Flat $flat, string $permission): bool
    {
        return $user->can($permission) && $flat->project->isVisibleTo($user);
    }
}
