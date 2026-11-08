<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    private function userWithTwoFactor(): array
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        $user = User::factory()->create(['email' => 'secure@example.com']);
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    public function test_password_alone_does_not_sign_in_when_two_factor_is_enabled(): void
    {
        $this->userWithTwoFactor();

        $this->ajax()->post(route('login.store'), ['login' => 'secure@example.com', 'password' => 'Password123'])
            ->assertOk()
            ->assertJsonPath('data.redirect', route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_valid_code_completes_sign_in(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $this->ajax()->post(route('login.store'), ['login' => 'secure@example.com', 'password' => 'Password123']);

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->ajax()->post(route('two-factor.verify'), ['code' => $code])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_code_is_rejected(): void
    {
        $this->userWithTwoFactor();
        $this->ajax()->post(route('login.store'), ['login' => 'secure@example.com', 'password' => 'Password123']);

        $this->ajax()->post(route('two-factor.verify'), ['code' => '000000'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->ajax()->post(route('login.store'), ['login' => 'secure@example.com', 'password' => 'Password123']);
        $this->ajax()->post(route('two-factor.verify'), ['recovery_code' => 'aaaaa-bbbbb'])->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->assertSame(['ccccc-ddddd'], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_without_pending_login_redirects(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_user_can_enable_two_factor_from_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->ajax()->post(route('profile.two-factor.enable'))->assertOk()->assertJsonStructure(['data' => ['qr', 'secret']]);
        $secret = decrypt(session('two_factor.pending_secret'));

        $this->ajax()->post(route('profile.two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertNotSame($secret, $user->fresh()->getRawOriginal('two_factor_secret'), 'secret must be stored encrypted');
    }

    public function test_required_two_factor_forces_setup_before_other_pages(): void
    {
        $user = $this->userWith(['users.view'], ['two_factor_required' => true]);

        $this->actingAs($user)->get('/')->assertRedirect(route('profile.edit').'#security');
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }
}
