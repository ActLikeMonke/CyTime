<?php

namespace Tests\Feature\Settings;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_unrelated_profile_validation_errors_do_not_open_the_delete_account_modal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->assertDeleteAccountModalOpenState($response->getContent(), false);
    }

    public function test_delete_account_modal_opens_again_when_password_confirmation_fails(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deactivateAccount')
            ->assertHasErrors(['password'])
            ->assertSee('open: true');
    }

    private function assertDeleteAccountModalOpenState(string $html, bool $open): void
    {
        $this->assertSame(
            1,
            preg_match('/<dialog\b[^>]*data-ui-modal="confirm-user-deletion"[^>]*>/s', $html, $matches),
            'The account-deletion dialog should be rendered.',
        );

        $this->assertSame($open, str_contains($matches[0], 'x-data='));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test', $user->first_name);
        $this->assertEquals('User', $user->last_name);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_deactivate_their_account(): void
    {
        $user = User::factory()->create();
        $entry = TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:30',
            'break_minutes' => 30,
            'status' => TimeEntry::STATUS_FINAL,
            'is_school_day' => false,
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deactivateAccount');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNotNull($user->fresh());
        $this->assertNotNull($user->fresh()->deactivated_at);
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_deactivate_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deactivateAccount');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_last_active_supervisor_cannot_deactivate_their_account(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $response = Livewire::actingAs($supervisor)
            ->test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deactivateAccount')
            ->assertHasErrors(['account']);

        $this->assertNull($supervisor->fresh()->deactivated_at);
    }

    public function test_deactivated_authenticated_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
