<?php

namespace App\Modules\Construction\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Construction abilities are checked against a project: the named permission plus
 * visibility of the project (team membership or projects.view_all).
 */
class ConstructionAccess
{
    public const ABILITIES = [
        'construction:view' => 'construction.view',
        'construction:manage' => 'construction.manage',
        'construction:progress-submit' => 'construction.progress.submit',
        'construction:progress-approve' => 'construction.progress.approve',
        'construction:labour-manage' => 'construction.labour.manage',
        'construction:labour-approve' => 'construction.labour.approve',
        'construction:cost-view' => 'construction.cost.view',
        'construction:cost-export' => 'construction.cost.export',
    ];

    public static function register(): void
    {
        foreach (self::ABILITIES as $ability => $permission) {
            Gate::define($ability, function (User $user, Project $project) use ($permission, $ability) {
                $writes = ! in_array($ability, ['construction:view', 'construction:cost-view', 'construction:cost-export'], true);

                return $user->can($permission) && $project->isVisibleTo($user) && (! $writes || ! $project->trashed());
            });
        }
    }
}
