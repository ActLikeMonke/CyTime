<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TimeCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_shows_monday_first_month_grid_today_and_only_the_users_data(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $other = User::factory()->create();
        $this->createEntry($employee, '2026-09-28', TimeEntry::STATUS_FINAL);
        $this->createEntry($other, '2026-09-29', TimeEntry::STATUS_FINAL);

        $calendar = app(TimeCalendarService::class)->for($employee, '2026-09');
        $dates = collect($calendar['weeks'])->flatten(1);
        $first = $dates->first();
        $last = $dates->last();
        $today = $dates->firstWhere('date', '2026-09-29');

        $this->assertSame('2026-08-31', $first['date']);
        $this->assertSame(1, CarbonImmutable::parse($first['date'])->dayOfWeekIso);
        $this->assertSame('2026-10-04', $last['date']);
        $this->assertTrue($today['is_today']);
        $this->assertSame('empty', $today['status']);
        $this->assertSame('booked', $dates->firstWhere('date', '2026-09-28')['status']);
        CarbonImmutable::setTestNow();
    }

    public function test_color_precedence_is_holiday_then_sick_then_vacation_then_booked_time(): void
    {
        $employee = User::factory()->create();
        DB::table('holidays')->insert(['date' => '2026-09-29', 'name' => 'Test holiday']);
        $this->createAbsence($employee, Absence::TYPE_SICK, Absence::STATUS_PENDING, '2026-09-29', '2026-09-29');
        $this->createAbsence($employee, Absence::TYPE_SICK, Absence::STATUS_APPROVED, '2026-09-30', '2026-09-30');
        $this->createAbsence($employee, Absence::TYPE_VACATION, Absence::STATUS_PENDING, '2026-10-01', '2026-10-01');
        $this->createAbsence($employee, Absence::TYPE_VACATION, Absence::STATUS_APPROVED, '2026-10-02', '2026-10-02');
        $this->createEntry($employee, '2026-09-29', TimeEntry::STATUS_FINAL);
        $this->createEntry($employee, '2026-09-30', TimeEntry::STATUS_FINAL);
        $this->createEntry($employee, '2026-10-01', TimeEntry::STATUS_PLANNED);
        $this->createEntry($employee, '2026-10-02', TimeEntry::STATUS_FINAL);
        $this->createEntry($employee, '2026-10-04', TimeEntry::STATUS_PLANNED);

        $days = collect(app(TimeCalendarService::class)->for($employee, '2026-09')['weeks'])
            ->flatten(1)
            ->keyBy('date');

        $this->assertSame('holiday', $days['2026-09-29']['status']);
        $this->assertSame('Test holiday', $days['2026-09-29']['holiday_name']);
        $this->assertSame('sick', $days['2026-09-30']['status']);
        $this->assertSame('vacation_pending', $days['2026-10-01']['status']);
        $this->assertSame('vacation', $days['2026-10-02']['status']);
        $this->assertSame('booked', $days['2026-10-04']['status']);
    }

    public function test_calendar_generates_and_shows_holidays_for_the_viewed_month(): void
    {
        $employee = User::factory()->create();

        $days = collect(app(TimeCalendarService::class)->for($employee, '2026-10')['weeks'])
            ->flatten(1)
            ->keyBy('date');

        $this->assertSame('holiday', $days['2026-10-03']['status']);
        $this->assertSame('German Unity Day', $days['2026-10-03']['holiday_name']);
        $this->assertSame('Holiday – German Unity Day', $days['2026-10-03']['details']);
        $this->assertDatabaseHas('holidays', [
            'date' => '2026-10-03',
            'name' => 'German Unity Day',
        ]);

        Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->set('month', '2026-10')
            ->assertSeeText('Holiday – German Unity Day');
    }

    public function test_calendar_tooltip_contains_work_time_and_rule_warnings(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create();
        TimeEntry::query()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-29',
            'start_time' => '08:00',
            'end_time' => '18:30',
            'break_minutes' => 0,
            'status' => TimeEntry::STATUS_FINAL,
            'is_school_day' => false,
        ]);

        $day = collect(app(TimeCalendarService::class)->for($employee, '2026-09')['weeks'])
            ->flatten(1)
            ->firstWhere('date', '2026-09-29');

        $this->assertStringContainsString('08:00–18:30', $day['details']);
        $this->assertNotEmpty($day['warnings']);
        $this->assertStringContainsString('Warning:', $day['details']);
        CarbonImmutable::setTestNow();
    }

    public function test_month_navigation_and_time_entry_addition_preserve_warning_confirmation_and_audit(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $component = Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->assertSeeText('September 2026')
            ->call('nextMonth')
            ->assertSet('month', '2026-10')
            ->call('goToToday')
            ->assertSet('month', '2026-09')
            ->call('selectDate', '2026-09-29')
            ->call('beginAction', 'time')
            ->set('startTime', '08:00')
            ->set('endTime', '18:30')
            ->set('breakMinutes', '0')
            ->call('submitTime')
            ->assertSeeText('Working-time rule warnings');

        $this->assertDatabaseCount('time_entries', 0);
        $component->call('confirmAndSubmitTime')->assertHasNoErrors();
        $this->assertDatabaseHas('time_entries', [
            'user_id' => $employee->id,
            'date' => '2026-09-29 00:00:00',
            'status' => TimeEntry::STATUS_PLANNED,
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
        CarbonImmutable::setTestNow();
    }

    public function test_calendar_date_actions_respect_past_correction_window_future_booking_and_vacation_workdays(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create();
        $employee->workSchedules()->create([
            'weekly_hours' => 40,
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'valid_from' => '2026-01-01',
        ]);
        $this->createAbsence($employee, Absence::TYPE_VACATION, Absence::STATUS_PENDING, '2026-10-02', '2026-10-02');
        $component = Livewire::actingAs($employee)->test('pages::time.calendar');
        $component->call('selectDate', '2026-10-01')
            ->assertSet('selectedDate', '2026-10-01')
            ->assertDontSeeText('Log time')
            ->assertSeeText('Request vacation');
        $component->call('selectDate', '2026-09-10')
            ->assertSet('selectedDate', '2026-09-10')
            ->assertDontSeeText('Log time')
            ->assertDontSeeText('Request vacation')
            ->assertSeeText('Report sick day');
        $component->call('selectDate', '2026-10-02')
            ->assertDontSeeText('Request vacation')
            ->assertDontSeeText('Report sick day');
        CarbonImmutable::setTestNow();
    }

    public function test_calendar_can_edit_existing_entries_and_submit_a_date_range_absence(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create();
        $entry = $this->createEntry($employee, '2026-09-28', TimeEntry::STATUS_FINAL);

        Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->call('selectDate', '2026-09-28')
            ->assertSeeText('Edit time entry')
            ->call('beginAction', 'time')
            ->set('endTime', '15:00')
            ->call('submitTime')
            ->assertHasNoErrors();
        $this->assertSame('15:00:00', $entry->fresh()->end_time);

        Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->call('toggleRangeSelection')
            ->call('selectDate', '2026-09-24')
            ->assertSet('rangeStart', '2026-09-24')
            ->call('selectDate', '2026-09-23')
            ->assertSet('rangeStart', '2026-09-23')
            ->assertSet('rangeEnd', '2026-09-24')
            ->assertSet('selectedAction', 'range')
            ->set('rangeType', Absence::TYPE_SICK)
            ->set('sickNoteSubmitted', true)
            ->call('submitRangeAbsence')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('absences', [
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-23 00:00:00',
            'end_date' => '2026-09-24 00:00:00',
            'sick_note_submitted' => true,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        CarbonImmutable::setTestNow();
    }

    public function test_calendar_can_submit_single_day_vacation_and_sick_requests(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $employee = User::factory()->create();
        $employee->workSchedules()->create([
            'weekly_hours' => 40,
            'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'valid_from' => '2026-01-01',
        ]);

        $component = Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->call('selectDate', '2026-09-29')
            ->call('beginAction', 'vacation')
            ->assertSet('calendarModalOpen', true)
            ->assertSeeText('Submit request')
            ->call('submitAbsence')
            ->assertSet('calendarModalOpen', false)
            ->assertHasNoErrors()
            ->call('selectDate', '2026-09-30')
            ->call('beginAction', 'sick')
            ->assertSet('calendarModalOpen', true)
            ->assertSeeText('Sick note (AU) submitted')
            ->set('sickNoteSubmitted', true)
            ->call('submitAbsence')
            ->assertSet('calendarModalOpen', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('absences', [
            'user_id' => $employee->id,
            'type' => Absence::TYPE_VACATION,
            'start_date' => '2026-09-29 00:00:00',
            'end_date' => '2026-09-29 00:00:00',
        ]);
        $this->assertDatabaseHas('absences', [
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK,
            'start_date' => '2026-09-30 00:00:00',
            'end_date' => '2026-09-30 00:00:00',
            'sick_note_submitted' => true,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        CarbonImmutable::setTestNow();
    }

    public function test_calendar_route_is_employee_only_and_date_inputs_cannot_escape_visible_calendar(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->actingAs($employee)->get(route('time.calendar'))->assertOk();
        $this->actingAs($supervisor)->get(route('time.calendar'))->assertForbidden();

        Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->call('selectDate', '2025-01-01')
            ->assertStatus(404);
    }

    private function createEntry(User $user, string $date, string $status): TimeEntry
    {
        return TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '16:30',
            'break_minutes' => 30,
            'status' => $status,
            'is_school_day' => false,
        ]);
    }

    private function createAbsence(User $user, string $type, string $status, string $start, string $end): Absence
    {
        return Absence::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'sick_note_submitted' => false,
        ]);
    }
}
