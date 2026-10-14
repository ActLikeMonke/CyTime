<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\SupervisorDashboardService;
use App\Services\TimeCalendarService;
use App\Services\TimeEntryService;
use App\Services\TimeOverviewService;
use App\Services\UserDataExportService;
use App\Services\WeeklyBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SplitTimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-29 18:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_multiple_periods_have_consistent_calendar_overview_weekly_and_supervisor_totals(): void
    {
        $user = User::factory()->create();
        app(TimeEntryService::class)->savePeriods($user, [
            $this->period('08:00', '11:00'),
            $this->period('13:00', '17:00', 15),
        ]);

        $this->assertSame(405, app(TimeOverviewService::class)->for($user, 'day', '2026-09-29')['total_minutes']);
        $this->assertSame(405, app(WeeklyBalanceService::class)->for($user, CarbonImmutable::today())['worked_minutes']);
        $this->assertSame(405, app(SupervisorDashboardService::class)->employeeMonth($user, '2026-09')['total_minutes']);
        $row = collect(app(SupervisorDashboardService::class)->monthlyHoursRows('2026-09'))->firstWhere('employee_id', $user->id);
        $this->assertSame('6.75', $row['worked_hours']);
        $day = collect(app(TimeCalendarService::class)->for($user, '2026-09')['weeks'])->flatten(1)->firstWhere('date', '2026-09-29');
        $this->assertCount(2, $day['entries']);
        $this->assertSame(405, $day['entry_net_minutes']);
        $this->assertStringContainsString('08:00–11:00', $day['details']);
        $this->assertStringContainsString('13:00–17:00', $day['details']);
        $this->assertStringContainsString('Total net: 6.75 h', $day['details']);
        $export = app(UserDataExportService::class)->forUser($user);
        $this->assertCount(2, $export['time_entries']);
        $this->assertCount(2, $export['audit_history']);
    }

    public function test_calendar_can_add_a_split_day_edit_either_period_and_add_more(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test('pages::time.calendar')
            ->call('selectDate', '2026-09-29')
            ->call('beginNewTime')
            ->assertSet('endTime', '16:30')
            ->set('endTime', '11:00')
            ->set('breakMinutes', '0')
            ->call('addWorkPeriod')
            ->assertSet('calendarModalOpen', true)
            ->set('additionalPeriods.0.start_time', '13:00')
            ->set('additionalPeriods.0.end_time', '17:00')
            ->call('submitTime')
            ->assertHasNoErrors()
            ->assertSet('calendarModalOpen', false)
            ->assertSeeText('Total net: 7.00 h');

        $second = $user->timeEntries()->orderBy('start_time')->get()->last();
        $component->call('selectDate', '2026-09-29')
            ->assertSeeText('Add another work period')
            ->call('editWorkPeriod', $second->id)
            ->assertSet('startTime', '13:00')
            ->set('endTime', '16:00')
            ->call('submitTime')
            ->assertHasNoErrors()
            ->assertSeeText('Total net: 6.00 h')
            ->call('selectDate', '2026-09-29')
            ->call('beginNewTime')
            ->set('startTime', '17:00')
            ->set('endTime', '18:00')
            ->set('breakMinutes', '0')
            ->call('submitTime')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('time_entries', 3);
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_overlapping_updates_are_rejected_on_the_same_date_and_deleted_periods_do_not_block_rebooking(): void
    {
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);
        $first = $service->create($user, $this->period('08:00', '11:00'));
        $second = $service->create($user, $this->period('13:00', '17:00'));
        try {
            $service->update($user, $second, $this->period('10:00', '17:00'));
            $this->fail('Overlapping periods must not be saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
            $this->assertSame('13:00:00', $second->fresh()->start_time);
            $this->assertDatabaseCount('audit_logs', 2);
        }
        $service->delete($user, $first);
        $service->create($user, $this->period('08:00', '11:00'));
        $this->assertSame(2, $user->timeEntries()->count());
    }

    public function test_clock_in_can_resume_after_clock_out_but_cannot_overlap_a_planned_or_open_period(): void
    {
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $first = $service->clockIn($user);
        CarbonImmutable::setTestNow('2026-09-29 11:00:00');
        $service->clockOut($user, $first);
        CarbonImmutable::setTestNow('2026-09-29 13:00:00');
        $second = $service->clockIn($user);
        try {
            $service->clockIn($user);
            $this->fail('Only one open work period is allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
        }
        CarbonImmutable::setTestNow('2026-09-29 17:00:00');
        $service->clockOut($user, $second);
        $this->assertSame(420, $user->timeEntries()->get()->sum(fn (TimeEntry $entry): int => $entry->netMinutes()));
        $service->create($user, $this->period('17:00', '19:00'));
        $this->expectException(ValidationException::class);
        $service->clockIn($user);
    }

    public function test_batch_future_start_or_invalid_break_fails_without_saving_any_period(): void
    {
        $user = User::factory()->create();
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        foreach ([
            $this->period('13:00', '17:00'),
            $this->period('11:00', '12:00', 61),
        ] as $invalid) {
            try {
                app(TimeEntryService::class)->savePeriods($user, [$this->period('08:00', '10:00'), $invalid]);
                $this->fail('Invalid work periods must not be partially saved.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('time_entries', 0);
                $this->assertDatabaseCount('audit_logs', 0);
            }
        }
    }

    public function test_warnings_for_unsaved_split_periods_need_confirmation_and_refresh_after_changes(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-01-01']);
        $component = Livewire::actingAs($user)->test('pages::time.index')
            ->set('startTime', '06:00')
            ->set('endTime', '11:00')
            ->set('breakMinutes', '0')
            ->call('addWorkPeriod')
            ->set('additionalPeriods.0.start_time', '12:00')
            ->set('additionalPeriods.0.end_time', '18:00')
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSeeText('Working-time rule warnings');
        $this->assertDatabaseCount('time_entries', 0);
        $component->set('additionalPeriods.0.end_time', '15:00')
            ->assertSet('ruleWarnings', [])
            ->assertSet('confirmRuleWarnings', false)
            ->set('additionalPeriods.0.end_time', '18:00')
            ->call('saveEntry')
            ->call('confirmAndSaveEntry')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('time_entries', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_calendar_cannot_edit_another_users_period_or_a_period_on_another_day(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $entry = app(TimeEntryService::class)->create($other, $this->period('08:00', '11:00'));
        Livewire::actingAs($user)->test('pages::time.calendar')
            ->call('selectDate', '2026-09-29')->call('editWorkPeriod', $entry->id)->assertStatus(404);
        $ownEntry = app(TimeEntryService::class)->create($user, [
            ...$this->period('08:00', '11:00'),
            'date' => '2026-09-28',
        ]);
        Livewire::actingAs($user)->test('pages::time.calendar')
            ->call('selectDate', '2026-09-29')->call('editWorkPeriod', $ownEntry->id)->assertStatus(404);
    }

    public function test_removing_a_middle_period_preserves_the_remaining_values_and_net_preview(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test('pages::time.index')
            ->set('endTime', '10:00')
            ->set('breakMinutes', '0')
            ->call('addWorkPeriod')
            ->set('additionalPeriods.0.start_time', '11:00')
            ->set('additionalPeriods.0.end_time', '12:00')
            ->call('addWorkPeriod')
            ->set('additionalPeriods.1.start_time', '13:00')
            ->set('additionalPeriods.1.end_time', '17:00')
            ->call('removeWorkPeriod', 0)
            ->assertSet('additionalPeriods.0.start_time', '13:00')
            ->assertSeeText('Net time: 6.00 h')
            ->call('saveEntry')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('time_entries', 2);
    }

    /** @return array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool} */
    private function period(string $start, string $end, int $break = 0): array
    {
        return [
            'date' => '2026-09-29',
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $break,
            'is_school_day' => false,
        ];
    }
}
