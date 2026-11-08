<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_user_can_sign_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->ajax()->post(route('login.store'), ['login' => 'STAFF@example.com', 'password' => 'Password123'])
            ->assertOk()
            ->assertJsonPath('data.redirect', route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_sign_in_with_mobile_in_any_format(): void
    {
        $user = User::factory()->create(['mobile' => '01712345678']);

        $this->ajax()->post(route('login.store'), ['login' => '+880 1712-345678', 'password' => 'Password123'])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_returns_inline_error(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('login');

        $this->assertGuest();
    }

    public function test_five_failures_lock_the_ip_for_five_minutes_with_retry_after(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'bad'])->assertStatus(422);
        }

        $response = $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'bad'])
            ->assertStatus(429);

        $this->assertGreaterThan(290, $response->json('retry_after'));
        $this->assertLessThanOrEqual(300, $response->json('retry_after'));

        // Correct password is still refused while locked.
        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'Password123'])->assertStatus(429);
        $this->assertGuest();

        // The sign-in page renders the countdown.
        $this->get(route('login'))->assertOk()->assertSee('data-locked-for="', false)->assertDontSee('data-locked-for="0"', false);
    }

    public function test_lock_applies_to_other_usernames_from_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->ajax()->post(route('login.store'), ['login' => "user{$i}@example.com", 'password' => 'bad']);
        }

        $user = User::factory()->create(['email' => 'real@example.com']);
        $this->ajax()->post(route('login.store'), ['login' => 'real@example.com', 'password' => 'Password123'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_lock_applies_to_same_username_from_other_ips(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        foreach (range(1, 5) as $i) {
            $this->ajax()->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'bad']);
        }

        $this->ajax()->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'Password123'])
            ->assertStatus(429);
    }

    public function test_spoofed_forwarded_for_header_is_ignored_without_trusted_proxy(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->ajax()->withHeaders(['X-Forwarded-For' => "203.0.113.{$i}"])
                ->post(route('login.store'), ['login' => "u{$i}@example.com", 'password' => 'bad']);
        }

        $this->ajax()->withHeaders(['X-Forwarded-For' => '203.0.113.200'])
            ->post(route('login.store'), ['login' => 'x@example.com', 'password' => 'bad'])
            ->assertStatus(429);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->ajax()->post(route('login.store'), ['login' => 'off@example.com', 'password' => 'Password123'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['status' => User::STATUS_INACTIVE]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_failed_and_successful_logins_are_audited(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);
        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'bad']);
        $this->ajax()->post(route('login.store'), ['login' => 'staff@example.com', 'password' => 'Password123']);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'security', 'event' => 'login_failed']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'security', 'event' => 'login']);
    }
}
