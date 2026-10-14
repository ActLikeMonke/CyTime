<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_replaces_employee_access_and_links_all_roles_to_details(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create();

        $response = $this->actingAs($supervisor)->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee(route('supervisor.users.show', $employee))
            ->assertDontSeeText('Employee access')
            ->assertDontSeeText('Recent rule warnings')
            ->assertDontSeeText('flagged entries')
            ->assertDontSeeText('Change annual leave entitlement')
            ->assertDontSeeText('Change working schedule')
            ->assertDontSeeText('Promote to supervisor');

        preg_match_all('/<th>(.*?)<\/th>/s', $response->getContent(), $headers);
        $this->assertSame(['Name', 'Leave balance', 'Leave days used', 'Status', 'Hour balance'], $headers[1]);
        $this->get(route('supervisor.users.index'))->assertRedirect(route('supervisor.dashboard'));
        Livewire::actingAs($supervisor)->test('pages::supervisor.dashboard')
            ->assertSee(route('supervisor.users.show', $employee))
            ->assertDontSee(route('supervisor.users.show', $supervisor))
            ->set('filter', 'accounts')
            ->assertSee(route('supervisor.users.show', $supervisor))
            ->assertSee(route('supervisor.users.show', $employee))
            ->assertSeeText('Supervisor');
    }

    public function test_details_prefill_current_settings_and_show_all_target_absence_dates(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['annual_leave_days' => 24]);
        $other = User::factory()->create();
        $employee->workSchedules()->create([
            'weekly_hours' => 24,
            'work_days' => ['mon', 'wed', 'fri'],
            'valid_from' => '2026-01-01',
        ]);
        $employee->workSchedules()->create([
            'weekly_hours' => 32,
            'work_days' => ['mon', 'tue', 'wed', 'thu'],
            'valid_from' => '2026-11-01',
        ]);
        $past = $this->absence($employee, '2026-07-08', Absence::STATUS_REJECTED);
        $future = $this->absence($employee, '2026-12-14', Absence::STATUS_APPROVED);
        $pending = $this->absence($employee, '2027-02-15', Absence::STATUS_PENDING);
        $deleted = $this->absence($employee, '2026-12-21', Absence::STATUS_PENDING);
        $deleted->delete();
        $this->absence($other, '2026-12-28', Absence::STATUS_PENDING);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->assertSet('annualLeaveDays', '24')
            ->assertSet('weeklyHours', '24.00')
            ->assertSet('workDays', ['mon', 'wed', 'fri'])
            ->assertSeeText('Change annual leave entitlement')
            ->assertSeeText('Change working schedule')
            ->assertSeeText('Working schedule history')
            ->assertSeeText('2026-01-01')
            ->assertSeeText('2026-11-01')
            ->assertSeeText($past->start_date->toDateString())
            ->assertSeeText($future->start_date->toDateString())
            ->assertSeeText($pending->start_date->toDateString())
            ->assertDontSeeText('2026-12-21')
            ->assertDontSeeText('2026-12-28')
            ->set('month', '2026-08')
            ->assertSeeText('2026-12-14');
    }

    public function test_dashboard_leave_balance_shows_used_and_pending_without_deducting_pending(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['annual_leave_days' => 24]);
        $employee->workSchedules()->create([
            'weekly_hours' => 40,
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'valid_from' => '2026-01-01',
        ]);
        $this->absence($employee, '2026-09-28', Absence::STATUS_APPROVED);
        $this->absence($employee, '2026-09-30', Absence::STATUS_PENDING);
        $this->absence($employee, '2026-10-01', Absence::STATUS_REJECTED);

        $component = Livewire::actingAs($supervisor)->test('pages::supervisor.dashboard')
            ->assertSeeText('Leave balance')
            ->assertSeeText('23 days remaining')
            ->assertSeeText('1 used · 1 pending')
            ->set('filter', 'open')
            ->assertSeeText($employee->name)
            ->set('sort', 'remaining_leave_days')
            ->assertHasNoErrors();
        $row = $component->instance()->employees[0];
        $this->assertSame(23, $row['remaining_leave_days']);
        $this->assertSame(1, $row['used_leave_days']);
        $this->assertSame(1, $row['pending_leave_days']);
    }

    public function test_management_refreshes_balance_and_schedule_history_for_only_the_target(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['annual_leave_days' => 30]);
        $other = User::factory()->create(['annual_leave_days' => 30]);

        $component = Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->set('annualLeaveDays', '25')
            ->call('updateLeaveEntitlement')
            ->assertHasNoErrors()
            ->assertSeeText('25 days')
            ->set('weeklyHours', '24')
            ->set('workDays', ['mon', 'wed', 'fri'])
            ->set('scheduleValidFrom', '2026-10-01')
            ->call('updateSchedule')
            ->assertHasNoErrors()
            ->assertSeeText('2026-10-01');

        $this->assertSame(25, $employee->fresh()->annual_leave_days);
        $this->assertSame(30, $other->fresh()->annual_leave_days);
        $this->assertSame(1, $component->instance()->schedules->count());
        $this->assertSame(0, $other->workSchedules()->count());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_detail_forms_surface_invalid_entitlement_and_schedule_errors(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create();
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->set('annualLeaveDays', '-1')
            ->call('updateLeaveEntitlement')
            ->assertHasErrors(['days'])
            ->set('weeklyHours', '41')
            ->set('workDays', [])
            ->call('updateSchedule')
            ->assertHasErrors(['weeklyHours', 'workDays']);
        $this->assertSame(0, $employee->workSchedules()->count());
    }

    public function test_promoted_accounts_remain_manageable_and_can_be_demoted(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create();
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->call('promote')
            ->assertHasNoErrors()
            ->assertSeeText('Demote to employee')
            ->assertSeeText('Change working schedule')
            ->call('demote')
            ->assertHasNoErrors()
            ->assertSeeText('Promote to supervisor');
        $this->assertTrue($employee->fresh()->isEmployee());
        $this->actingAs($supervisor)->get(route('supervisor.users.show', $supervisor))->assertOk();
    }

    public function test_last_supervisor_is_protected_and_can_demote_self_when_another_exists(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $supervisor])
            ->call('demote')
            ->assertHasErrors(['role'])
            ->assertSeeText('The last supervisor cannot be demoted.');
        $this->assertTrue($supervisor->fresh()->isSupervisor());

        User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $supervisor])
            ->call('demote')
            ->assertRedirect(route('time.index'));
        $this->assertTrue($supervisor->fresh()->isEmployee());
    }

    public function test_detail_target_cannot_be_changed_by_a_livewire_update(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $component = Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('employeeId', $other->id);
    }

    public function test_employees_cannot_mount_dashboard_or_detail_components(): void
    {
        $employee = User::factory()->create();
        Livewire::actingAs($employee)->test('pages::supervisor.dashboard')->assertForbidden();
        Livewire::actingAs($employee)
            ->test('pages::supervisor.user-detail', ['user' => $employee])->assertForbidden();
    }

    public function test_management_actions_reauthorize_after_supervisor_access_is_revoked(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create();

        foreach (['promote', 'demote', 'updateLeaveEntitlement', 'updateSchedule'] as $action) {
            $supervisor->update(['role' => User::ROLE_SUPERVISOR]);
            $component = Livewire::actingAs($supervisor)
                ->test('pages::supervisor.user-detail', ['user' => $employee]);
            $supervisor->update(['role' => User::ROLE_EMPLOYEE]);
            $component->call($action)->assertForbidden();
        }
    }

    private function absence(User $user, string $date, string $status): Absence
    {
        return $user->absences()->create([
            'type' => Absence::TYPE_VACATION,
            'start_date' => $date,
            'end_date' => $date,
            'status' => $status,
            'sick_note_submitted' => false,
        ]);
    }
}
