<?php

namespace App\Modules\Projects\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['projects.view', 'projects.view_all']);
    }

    public function view(User $user, Project $project): bool
    {
        return $project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.update') && ! $project->trashed();
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.archive');
    }

    public function manageStages(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.manage_stages') && ! $project->trashed();
    }

    public function updateProgress(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.update_progress') && ! $project->trashed();
    }

    public function manageMedia(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.manage_media') && ! $project->trashed();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->allowed($user, $project, 'projects.manage_members') && ! $project->trashed();
    }

    private function allowed(User $user, Project $project, string $permission): bool
    {
        return $user->can($permission) && $project->isVisibleTo($user);
    }
}
