<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Services\Auth\SecurityLog;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.reset_password');

        $request->user()->unreadNotifications()->where('type', PasswordResetRequested::class)->update(['read_at' => now()]);

        return view('core.password-requests.index');
    }

    public function table(Request $request): JsonResponse
    {
        Gate::authorize('users.reset_password');

        $query = PasswordResetRequest::query()->with(['user', 'handler:id,name']);
        $status = (string) $request->input('status', 'pending');
        if (array_key_exists($status, PasswordResetRequest::STATUSES)) {
            $query->where('status', $status);
        }

        $requests = TableQuery::apply($query, $request, ['user.name', 'user.email', 'user.mobile'], ['requested' => 'created_at'], 'requested', 'desc');

        return Ajax::table(view('core.password-requests._rows', ['requests' => $requests])->render(), $requests);
    }

    public function dismiss(Request $request, PasswordResetRequest $passwordRequest): JsonResponse
    {
        Gate::authorize('users.reset_password');
        abort_unless($passwordRequest->status === 'pending', 409, 'This request is already closed.');

        $passwordRequest->update(['status' => 'dismissed', 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        SecurityLog::record('password_request_dismissed', $passwordRequest->user, $request, ['by' => $request->user()->name]);

        return Ajax::success('Request dismissed.');
    }

    public function edit(User $user): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        return Ajax::success(null, [
            'title' => 'Reset Password · '.$user->name,
            'html' => view('core.password-requests._form', ['user' => $user])->render(),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            $user->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'must_change_password' => $request->boolean('must_change_password', true),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            PasswordResetRequest::query()->where('user_id', $user->id)->where('status', 'pending')
                ->update(['status' => 'completed', 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        });

        SecurityLog::record('password_reset_by_admin', $user, $request, ['by' => $request->user()->name]);

        return Ajax::success('Password reset for '.$user->name.'.');
    }
}
