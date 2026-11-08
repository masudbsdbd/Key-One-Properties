<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_reset_link_response_does_not_reveal_whether_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $known = $this->ajax()->post(route('password.email'), ['email' => 'staff@example.com'])->assertOk()->json('message');
        $unknown = $this->ajax()->post(route('password.email'), ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return ! str_contains($url, 'email=') && ! str_contains($url, urlencode($user->email));
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $token = Password::createToken($user);

        $this->ajax()->post(route('password.update'), [
            'token' => $token,
            'email' => 'staff@example.com',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertOk();

        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'NewPassword9'])->assertOk();
    }
}
