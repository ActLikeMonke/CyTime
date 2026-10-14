<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\LeaveBalanceService;
use App\Services\LeaveEntitlementService;
use App\Services\WeeklyBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AbsenceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sick_report_counts_immediately_and_rejection_removes_weekly_absence(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create();
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $sickReport = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-30',
            'sick_note_submitted' => true,
        ]);

        $this->assertSame(Absence::STATUS_PENDING, $sickReport->status);
        $this->assertTrue($sickReport->sick_note_submitted);
        $this->assertSame(1440, app(WeeklyBalanceService::class)->for($employee, CarbonImmutable::today())['target_minutes']);

        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        app(AbsenceService::class)->review($supervisor, $sickReport, Absence::STATUS_REJECTED);
        $this->assertSame(2400, app(WeeklyBalanceService::class)->for($employee, CarbonImmutable::today())['target_minutes']);
        $this->assertDatabaseHas('absences', [
            'id' => $sickReport->id,
            'status' => Absence::STATUS_REJECTED,
            'reviewed_by' => $supervisor->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        CarbonImmutable::setTestNow();
    }

    public function test_sick_report_never_persists_free_text_review_comment(): void
    {
        $employee = User::factory()->create();
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $absence = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
        ]);

        try {
            app(AbsenceService::class)->review($supervisor, $absence, Absence::STATUS_APPROVED, 'Diagnosis details');
            $this->fail('Sick reports must not accept free-text comments.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
        }

        $approved = app(AbsenceService::class)->review($supervisor, $absence, Absence::STATUS_APPROVED);
        $this->assertNull($approved->review_comment);
        $this->assertNull(json_decode(DB::table('audit_logs')->where('auditable_id', $absence->id)->latest('id')->value('new_values'), true)['review_comment']);
    }

    public function test_vacation_days_count_only_scheduled_nonholiday_workdays_and_warn_without_blocking(): void
    {
        CarbonImmutable::setTestNow('2026-06-01 12:00:00');
        $employee = User::factory()->create(['annual_leave_days' => 18]);
        $this->createSchedule($employee, '2026-01-01', 24, ['mon', 'wed', 'fri']);
        $this->createHoliday('2026-06-04', 'Corpus Christi');

        $request = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_VACATION,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ]);
        $this->assertSame(3, app(LeaveBalanceService::class)->daysForRequest(
            $employee,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-05'),
        ));
        $this->assertSame(0, app(LeaveBalanceService::class)->for($employee, 2026)['used']);

        $component = Livewire::actingAs($employee)
            ->test('pages::absences.index')
            ->set('type', 'vacation')
            ->set('startDate', '2026-06-08')
            ->set('endDate', '2026-07-31')
            ->assertSeeText('above your remaining leave balance');
        $component->call('submit')->assertHasNoErrors();
        $this->assertSame(Absence::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(2, Absence::query()->where('user_id', $employee->id)->count());
        CarbonImmutable::setTestNow();
    }

    public function test_approved_vacation_uses_leave_and_pending_vacation_does_not(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create(['annual_leave_days' => 20]);
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $vacation = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_VACATION,
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-29',
        ]);

        $pending = app(LeaveBalanceService::class)->for($employee, 2026);
        $this->assertSame(20, $pending['remaining']);
        $this->assertSame(2, $pending['pending']);

        $approved = app(AbsenceService::class)->review($supervisor, $vacation, Absence::STATUS_APPROVED, 'Approved for the requested dates.');
        $balance = app(LeaveBalanceService::class)->for($employee, 2026);
        $this->assertSame(18, $balance['remaining']);
        $this->assertSame(2, $balance['used']);
        $this->assertSame('Approved for the requested dates.', $approved->review_comment);
        $this->assertSame($supervisor->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        CarbonImmutable::setTestNow();
    }

    public function test_carry_over_is_available_for_the_next_year_only(): void
    {
        $employee = User::factory()->create(['annual_leave_days' => 20]);
        $this->createSchedule($employee, '2024-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createAbsence($employee, Absence::STATUS_APPROVED, '2025-06-02', '2025-06-13');
        $this->createAbsence($employee, Absence::STATUS_APPROVED, '2024-06-03', '2024-06-24');

        $current = app(LeaveBalanceService::class)->for($employee, 2026);
        $followingYear = app(LeaveBalanceService::class)->for($employee, 2027);

        $this->assertSame(14, $current['carry_over']);
        $this->assertSame(34, $current['remaining']);
        $this->assertSame(20, $followingYear['carry_over']);
    }

    public function test_pending_absence_can_be_cancelled_softly_and_cancel_is_audited(): void
    {
        $employee = User::factory()->create();
        $absence = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
        ]);

        Livewire::actingAs($employee)
            ->test('pages::absences.index')
            ->call('cancel', $absence->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('absences', ['id' => $absence->id]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_supervisor_approval_and_employee_absence_pages_enforce_ownership_and_roles(): void
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->createSchedule($employee, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $request = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_VACATION,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
        ]);

        Livewire::actingAs($employee)
            ->test('pages::absences.index')
            ->assertSeeText('My absence requests')
            ->assertDontSeeText($other->email);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.absences')
            ->assertSeeText($employee->name)
            ->set("comments.{$request->id}", 'Approved')
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $this->actingAs($employee)->get(route('supervisor.absences.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('absences.index'))->assertOk();
        $this->assertSame(Absence::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_supervisor_can_change_entitlement_from_employee_management_page(): void
    {
        $employee = User::factory()->create(['annual_leave_days' => 18]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->createSchedule($employee, '2026-01-01', 24, ['mon', 'wed', 'fri']);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.user-detail', ['user' => $employee])
            ->assertSeeText('Leave balance')
            ->assertSeeText('Change annual leave entitlement')
            ->set('annualLeaveDays', '21')
            ->call('updateLeaveEntitlement')
            ->assertHasNoErrors();

        $this->assertSame(21, $employee->fresh()->annual_leave_days);
    }

    public function test_employee_cannot_cancel_another_users_or_already_reviewed_absence(): void
    {
        $employee = User::factory()->create();
        $other = User::factory()->create();
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $absence = app(AbsenceService::class)->request($employee, [
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
        ]);

        try {
            app(AbsenceService::class)->cancel($other, $absence);
            $this->fail('Employees may only cancel their own absence reports.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('absences', ['id' => $absence->id, 'deleted_at' => null]);
        }

        app(AbsenceService::class)->review($supervisor, $absence, Absence::STATUS_APPROVED);
        try {
            app(AbsenceService::class)->cancel($employee, $absence->fresh());
            $this->fail('Reviewed absences cannot be cancelled.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('absences', ['id' => $absence->id, 'status' => Absence::STATUS_APPROVED]);
        }
    }

    public function test_leave_entitlement_override_is_authorized_validated_and_audited(): void
    {
        $employee = User::factory()->create(['annual_leave_days' => 18]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherEmployee = User::factory()->create();
        $changed = app(LeaveEntitlementService::class)->update($supervisor, $employee, '22');

        $this->assertSame(22, $changed->annual_leave_days);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
        ]);

        try {
            app(LeaveEntitlementService::class)->update($otherEmployee, $employee, 30);
            $this->fail('Employees cannot change leave entitlement.');
        } catch (AuthorizationException) {
            $this->assertSame(22, $employee->fresh()->annual_leave_days);
        }

        try {
            app(LeaveEntitlementService::class)->update($supervisor, $employee, 256);
            $this->fail('Entitlements must fit the stored unsigned tiny integer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('days', $exception->errors());
        }
    }

    public function test_absence_schema_has_no_diagnosis_or_reason_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('absences', 'review_comment'));
        $this->assertFalse(Schema::hasColumn('absences', 'diagnosis'));
        $this->assertFalse(Schema::hasColumn('absences', 'reason'));
        $this->assertFalse(Schema::hasColumn('absences', 'medical_document'));
    }

    public function test_overlapping_absences_invalid_dates_and_non_workday_only_vacation_are_rejected(): void
    {
        $employee = User::factory()->create();
        $this->createSchedule($employee, '2026-01-01', 24, ['mon', 'wed', 'fri']);
        $service = app(AbsenceService::class);
        $existing = $service->request($employee, [
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-30',
        ]);

        foreach ([
            [
                'type' => Absence::TYPE_VACATION,
                'start_date' => '2026-09-28',
                'end_date' => '2026-09-28',
            ],
            [
                'type' => Absence::TYPE_VACATION,
                'start_date' => '2026-09-29',
                'end_date' => '2026-09-29',
            ],
        ] as $invalid) {
            try {
                $service->request($employee, $invalid);
                $this->fail('Overlapping or non-workday-only vacation requests must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(1, $employee->absences()->count());
            }
        }

        $this->assertSame(Absence::STATUS_PENDING, $existing->fresh()->status);
    }

    private function createSchedule(User $user, string $validFrom, int $hours, array $days): void
    {
        $user->workSchedules()->create([
            'weekly_hours' => $hours,
            'work_days' => $days,
            'valid_from' => $validFrom,
        ]);
    }

    private function createHoliday(string $date, string $name): void
    {
        DB::table('holidays')->insert([
            'date' => $date,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAbsence(User $user, string $status, string $start, string $end): void
    {
        $user->absences()->create([
            'type' => Absence::TYPE_VACATION,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'sick_note_submitted' => false,
        ]);
    }
}
