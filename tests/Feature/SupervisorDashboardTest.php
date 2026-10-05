<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_filters_pending_requests_and_hides_supervisors(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $pendingEmployee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Alex',
            'last_name' => 'Pending',
        ]);
        $availableEmployee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Casey',
            'last_name' => 'Available',
        ]);
        $this->createSchedule($pendingEmployee);
        $this->createSchedule($availableEmployee);
        Absence::query()->create([
            'user_id' => $pendingEmployee->id,
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-30',
            'status' => Absence::STATUS_PENDING,
            'sick_note_submitted' => false,
        ]);

        $this->actingAs($supervisor)->get(route('supervisor.dashboard'))->assertOk();
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.dashboard')
            ->assertSeeText($pendingEmployee->name)
            ->assertSeeText($availableEmployee->name)
            ->assertSeeText('Sick – approval required')
            ->assertDontSeeText($supervisor->name)
            ->set('filter', 'open')
            ->assertSeeText($pendingEmployee->name)
            ->assertDontSeeText($availableEmployee->name)
            ->set('filter', 'all')
            ->set('sort', 'name')
            ->set('direction', 'desc')
            ->assertSeeInOrder([$availableEmployee->name, $pendingEmployee->name]);
        CarbonImmutable::setTestNow();
    }

    public function test_employee_cannot_access_supervisor_dashboard_or_employee_detail(): void
    {
        $employee = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($employee)->get(route('supervisor.dashboard'))->assertForbidden();
        $this->actingAs($employee)->get(route('supervisor.users.show', $target))->assertForbidden();
    }

    public function test_employee_detail_shows_monthly_time_entries_absences_and_weekly_balances(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $this->createSchedule($employee);
        $this->createEntry($employee, '2026-09-28');
        Absence::query()->create([
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
            'status' => Absence::STATUS_PENDING,
            'sick_note_submitted' => true,
        ]);

        $this->actingAs($supervisor)
            ->get(route('supervisor.users.show', $employee))
            ->assertOk()
            ->assertSeeText($employee->name);
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->assertSeeText('Weekly balances')
            ->assertSeeText('Time entries')
            ->assertSeeText('Absences')
            ->assertSeeText('Sick');
        CarbonImmutable::setTestNow();
    }

    public function test_monthly_hours_csv_exports_final_hours_by_employee(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => 'Jordan',
            'last_name' => 'Hours',
        ]);
        $this->createEntry($employee, '2026-09-28');
        $this->createEntry($employee, '2026-08-28');

        $component = Livewire::actingAs($supervisor)
            ->test('pages::supervisor.dashboard')
            ->set('exportMonth', '2026-09');
        $component->call('exportMonthlyHours')->assertFileDownloaded('cytime-monthly-hours-2026-09.csv');

        $this->assertSame([
            [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => '2026-09',
                'worked_hours' => '8.00',
            ],
        ], app(SupervisorDashboardService::class)->monthlyHoursRows('2026-09'));
        CarbonImmutable::setTestNow();
    }

    private function createSchedule(User $user): void
    {
        $user->workSchedules()->create([
            'weekly_hours' => 40,
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'valid_from' => '2026-01-01',
        ]);
    }

    private function createEntry(User $user, string $date): void
    {
        TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '16:30',
            'break_minutes' => 30,
            'status' => TimeEntry::STATUS_FINAL,
            'is_school_day' => false,
        ]);
    }
}
