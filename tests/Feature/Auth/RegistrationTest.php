<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSeeText('Date of birth')
            ->assertSeeText('Weekly working hours')
            ->assertSeeText('Privacy notice (Article 13 GDPR)')
            ->assertSee('work_days[]', false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), $this->validRegistrationData([
            'work_days' => ['mon', 'wed', 'fri'],
            'weekly_hours' => 24,
            'role' => User::ROLE_SUPERVISOR,
        ]));

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => 'employee',
            'annual_leave_days' => 18,
        ]);
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('1990-01-15', $user->birth_date->toDateString());
        $this->assertNotNull($user->privacy_notice_acknowledged_at);
        $this->assertTrue(Hash::check('ComplexPass123', $user->password));

        $schedule = DB::table('work_schedules')->where('user_id', $user->id)->first();
        $this->assertSame(24.0, (float) $schedule->weekly_hours);
        $this->assertSame(['mon', 'wed', 'fri'], json_decode($schedule->work_days, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(today()->toDateString(), $schedule->valid_from);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_registration_accepts_the_maximum_weekly_hours_and_calculates_full_leave_entitlement(): void
    {
        $this->post(route('register.store'), $this->validRegistrationData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'annual_leave_days' => 30,
        ]);

        $this->assertDatabaseHas('work_schedules', [
            'weekly_hours' => 40,
            'valid_from' => today()->toDateString(),
        ]);
    }

    public function test_registration_requires_valid_working_time_details_and_privacy_acknowledgment(): void
    {
        $invalidData = [
            ['weekly_hours' => 0],
            ['weekly_hours' => 40.01],
            ['work_days' => []],
            ['work_days' => ['saturday']],
            ['birth_date' => today()->addDay()->toDateString()],
            ['privacy_notice_acknowledged' => '0'],
            ['password' => 'lettersOnly', 'password_confirmation' => 'lettersOnly'],
        ];

        foreach ($invalidData as $overrides) {
            $this->post(route('register.store'), $this->validRegistrationData($overrides))
                ->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('work_schedules', 0);
    }

    public function test_registration_rejects_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->post(route('register.store'), $this->validRegistrationData(['email' => 'TEST@example.com']))
            ->assertSessionHasErrors('email');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRegistrationData(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'birth_date' => '1990-01-15',
            'weekly_hours' => 40,
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'privacy_notice_acknowledged' => '1',
            'password' => 'ComplexPass123',
            'password_confirmation' => 'ComplexPass123',
        ], $overrides);
    }
}
