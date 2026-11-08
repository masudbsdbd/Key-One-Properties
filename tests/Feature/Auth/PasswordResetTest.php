<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private function admin(): User
    {
        return $this->userWith(['users.view', 'users.reset_password']);
    }

    public function test_request_notifies_admins_and_does_not_reveal_accounts(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $bystander = $this->userWith(['users.view']);
        $user = User::factory()->create(['mobile' => '01712345678']);

        $known = $this->ajax()->post(route('password.request.store'), ['login' => '+880 1712-345678'])->assertOk()->json('message');
        $unknown = $this->ajax()->post(route('password.request.store'), ['login' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        $this->assertSame(1, PasswordResetRequest::query()->where('user_id', $user->id)->where('status', 'pending')->count());
        Notification::assertSentTo($admin, PasswordResetRequested::class);
        Notification::assertNotSentTo($bystander, PasswordResetRequested::class);
        Notification::assertNotSentTo($user, PasswordResetRequested::class);
    }

    public function test_repeated_requests_keep_a_single_pending_request(): void
    {
        Notification::fake();
        $this->admin();
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->ajax()->post(route('password.request.store'), ['login' => 'staff@example.com']);
        $this->ajax()->post(route('password.request.store'), ['login' => 'staff@example.com']);

        $this->assertSame(1, PasswordResetRequest::query()->where('user_id', $user->id)->count());
        Notification::assertCount(1);
    }

    public function test_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->ajax()->post(route('password.request.store'), ['login' => "x{$i}@example.com"])->assertOk();
        }

        $this->ajax()->post(route('password.request.store'), ['login' => 'y@example.com'])->assertStatus(429);
    }

    public function test_admin_resets_password_user_must_change_it_and_sessions_end(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $request = PasswordResetRequest::create(['user_id' => $user->id, 'status' => 'pending']);
        DB::table('sessions')->insert(['id' => 'old', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->ajax()->post(route('users.password.update', $user), [
            'password' => 'Temp12345', 'password_confirmation' => 'Temp12345', 'must_change_password' => '1',
        ])->assertOk();

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->handled_by);
        $this->assertDatabaseMissing('sessions', ['id' => 'old']);
        $this->assertDatabaseHas('activity_log', ['event' => 'password_reset_by_admin', 'subject_id' => $user->id]);

        auth()->logout();
        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'Temp12345'])->assertOk();
        $this->ajax()->get(route('users.index'))->assertForbidden()->assertJsonPath('message', 'Change your password to continue.');
        $this->flushHeaders()->get(route('dashboard'))->assertRedirect(route('profile.edit').'#security');

        $this->ajax()->put(route('profile.password'), [
            'current_password' => 'Temp12345', 'password' => 'Mine12345', 'password_confirmation' => 'Mine12345',
        ])->assertOk();
        $this->flushHeaders()->get(route('dashboard'))->assertOk();
    }

    public function test_reset_needs_permission_and_cannot_target_self_or_super_admin(): void
    {
        $plain = $this->userWith(['users.view']);
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($plain)->ajax()->post(route('users.password.update', $target), ['password' => 'Temp12345', 'password_confirmation' => 'Temp12345'])->assertForbidden();
        $this->actingAs($plain)->get(route('password-requests.index'))->assertForbidden();
        $this->actingAs($admin)->ajax()->post(route('users.password.update', $admin), ['password' => 'Temp12345', 'password_confirmation' => 'Temp12345'])->assertForbidden();
        $this->actingAs($admin)->ajax()->post(route('users.password.update', $this->superAdmin()), ['password' => 'Temp12345', 'password_confirmation' => 'Temp12345'])->assertForbidden();
    }

    public function test_admin_can_dismiss_and_list_requests(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Waiting User']);
        $request = PasswordResetRequest::create(['user_id' => $user->id, 'status' => 'pending']);

        $html = $this->actingAs($admin)->ajax()->get(route('password-requests.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString('Waiting User', $html);

        $this->actingAs($admin)->ajax()->post(route('password-requests.dismiss', $request))->assertOk();
        $this->assertSame('dismissed', $request->fresh()->status);
        $this->actingAs($admin)->ajax()->post(route('password-requests.dismiss', $request))->assertStatus(409);
    }
}
