<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_deactivated_users_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['deactivated_at' => now()])->save();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_unknown_email_receives_the_same_generic_error_as_an_invalid_password(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $wrongPasswordResponse = $this->post(route('login.store'), [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ]);
        $wrongPasswordResponse->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $unknownEmailResponse = $this->post(route('login.store'), [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);
        $unknownEmailResponse->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'known@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
        }

        $this->post(route('login.store'), [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->withSession(['private_session_value' => 'must be cleared']);
        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'))
            ->assertSessionMissing('private_session_value');

        $this->assertGuest();
    }
}
