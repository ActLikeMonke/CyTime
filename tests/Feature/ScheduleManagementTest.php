<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\WorkScheduleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_change_their_own_schedule_with_a_valid_from_date(): void
    {
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'annual_leave_days' => 30,
        ]);
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);

        $newSchedule = app(WorkScheduleService::class)->change(
            $employee,
            $employee,
            24,
            ['mon', 'wed', 'fri'],
            '2026-03-01',
        );

        $this->assertSame('2026-03-01', $newSchedule->valid_from->toDateString());
        $this->assertSame(2, $employee->workSchedules()->count());
        $this->assertSame(40.0, (float) $employee->workSchedules()->whereDate('valid_from', '2026-01-01')->value('weekly_hours'));
        $this->assertSame(30, $employee->fresh()->annual_leave_days);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $employee->id,
            'auditable_type' => WorkSchedule::class,
            'auditable_id' => $newSchedule->id,
        ]);
    }

    public function test_supervisor_can_change_an_employee_schedule(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $schedule = app(WorkScheduleService::class)->change(
            $supervisor,
            $employee,
            32,
            ['mon', 'tue', 'wed', 'thu'],
            '2026-04-01',
        );

        $this->assertSame(32.0, (float) $schedule->weekly_hours);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'auditable_type' => WorkSchedule::class,
            'auditable_id' => $schedule->id,
        ]);
    }

    public function test_employee_cannot_change_another_users_schedule(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $otherEmployee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $this->expectException(AuthorizationException::class);

        app(WorkScheduleService::class)->change(
            $employee,
            $otherEmployee,
            32,
            ['mon', 'tue', 'wed', 'thu'],
            '2026-04-01',
        );
    }

    public function test_schedule_validation_and_duplicate_effective_dates_do_not_create_records(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);

        foreach ([
            [0, ['mon'], '2026-02-01'],
            [40.01, ['mon'], '2026-02-02'],
            [20, [], '2026-02-03'],
            [20, ['sat'], '2026-02-04'],
            [20, ['mon'], '2026-01-01'],
        ] as [$hours, $days, $date]) {
            try {
                app(WorkScheduleService::class)->change($employee, $employee, $hours, $days, $date);
                $this->fail('Expected invalid schedule data to be rejected.');
            } catch (ValidationException) {
                $this->assertSame(1, $employee->workSchedules()->count());
            }
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_employee_profile_contains_schedule_change_controls(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        $this->actingAs($employee)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeText('Working schedule')
            ->assertSeeText('Effective from')
            ->assertSeeText('Save schedule');
    }

    public function test_employee_can_submit_a_schedule_change_from_profile_settings(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        Livewire::actingAs($employee)
            ->test('pages::settings.profile')
            ->set('weeklyHours', '32')
            ->set('workDays', ['mon', 'tue', 'wed', 'thu'])
            ->set('scheduleValidFrom', '2026-05-01')
            ->call('updateWorkingSchedule')
            ->assertHasNoErrors();

        $this->assertSame('2026-05-01', $employee->workSchedules()->firstOrFail()->valid_from->toDateString());
    }

    public function test_supervisor_can_submit_a_schedule_change_from_employee_management(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.users')
            ->set('selectedUserId', (string) $employee->id)
            ->set('weeklyHours', '32')
            ->set('workDays', ['mon', 'tue', 'wed', 'thu'])
            ->set('scheduleValidFrom', '2026-05-01')
            ->call('updateSchedule')
            ->assertHasNoErrors();

        $this->assertSame('2026-05-01', $employee->workSchedules()->firstOrFail()->valid_from->toDateString());
    }

    /**
     * @param  array<int, string>  $workDays
     */
    private function createSchedule(User $user, string $validFrom, int $weeklyHours, array $workDays): void
    {
        DB::table('work_schedules')->insert([
            'user_id' => $user->id,
            'weekly_hours' => $weeklyHours,
            'work_days' => json_encode($workDays, JSON_THROW_ON_ERROR),
            'valid_from' => $validFrom,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
