<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return Ajax::success(null, ['html' => view('projects.members._list', [
            'project' => $project,
            'members' => $project->members()->orderByDesc('project_user.is_primary')->orderBy('name')->get(),
        ])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        return Ajax::success(null, [
            'title' => 'Add Team Members',
            'html' => view('projects.members._form', ['project' => $project])->render(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $data = $request->validate([
            'users' => ['required', 'array', 'min:1', 'max:50'],
            'users.*' => ['string', Rule::exists('users', 'public_id')->whereNull('deleted_at')],
            'membership_role' => ['nullable', 'string', 'max:60'],
        ]);

        $users = User::query()->whereIn('public_id', $data['users'])->get();
        $project->members()->syncWithoutDetaching(
            $users->mapWithKeys(fn (User $u) => [$u->id => ['membership_role' => $data['membership_role'] ?? null]])->all()
        );
        $this->audit($request, $project, 'members_added', $users->pluck('name')->all());

        return Ajax::success('Team updated.');
    }

    public function primary(Request $request, Project $project, User $user): JsonResponse
    {
        Gate::authorize('manageMembers', $project);
        abort_unless($project->members()->whereKey($user->id)->exists(), 404);

        DB::transaction(function () use ($project, $user) {
            DB::table('project_user')->where('project_id', $project->id)->update(['is_primary' => false]);
            $project->members()->updateExistingPivot($user->id, ['is_primary' => true]);
        });
        $this->audit($request, $project, 'primary_contact_changed', [$user->name]);

        return Ajax::success('Primary contact set.');
    }

    public function destroy(Request $request, Project $project, User $user): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $project->members()->detach($user->id);
        $this->audit($request, $project, 'member_removed', [$user->name]);

        return Ajax::success('Member removed.');
    }

    private function audit(Request $request, Project $project, string $event, array $names): void
    {
        activity('projects')->event($event)->causedBy($request->user())->performedOn($project)
            ->tap(fn ($a) => $a->project_id = $project->id)
            ->withProperties(['attributes' => ['members' => $names]])
            ->log($event);
    }
}
