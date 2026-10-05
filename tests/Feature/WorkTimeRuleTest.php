<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\WorkTimeRuleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WorkTimeRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_adult_break_thresholds_are_strict_and_configurable(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $sixHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '14:00', 0));
        $this->assertNotContains('adult-break', $this->codes($sixHours));

        $overSixHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '14:01', 0));
        $this->assertContains('adult-break', $this->codes($overSixHours));

        $nineHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '17:30', 30));
        $this->assertNotContains('adult-long-break', $this->codes($nineHours));

        $overNineHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '17:31', 30));
        $this->assertContains('adult-long-break', $this->codes($overNineHours));

        config(['worktime.adult.daily_absolute_minutes' => 540]);
        $customLimit = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '18:01', 60));
        $this->assertContains('adult-daily-limit', $this->codes($customLimit));
    }

    public function test_adult_eight_hour_extension_and_ten_hour_limit_are_warnings_only(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $extended = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '17:00', 0));
        $this->assertContains('adult-extended-day', $this->codes($extended));

        $aboveAbsolute = $rules->evaluate($user, $this->entry('2026-09-28', '07:00', '18:00', 0));
        $this->assertContains('adult-daily-limit', $this->codes($aboveAbsolute));
    }

    public function test_adult_sunday_holiday_and_short_rest_periods_are_reported(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-01-01']);
        $this->createHoliday('2026-09-28');
        $this->createTimeEntry($user, '2026-09-27', '08:00', '20:00', 0);

        $warnings = app(WorkTimeRuleService::class)->evaluate(
            $user,
            $this->entry('2026-09-28', '06:00', '14:00', 0),
        );

        $this->assertContains('adult-holiday-work', $this->codes($warnings));
        $this->assertContains('adult-rest-period', $this->codes($warnings));

        $exactAdultRest = app(WorkTimeRuleService::class)->evaluate(
            $user,
            $this->entry('2026-09-28', '07:00', '15:00', 0),
        );
        $this->assertNotContains('adult-rest-period', $this->codes($exactAdultRest));

        $sunday = app(WorkTimeRuleService::class)->evaluate(
            $user,
            $this->entry('2026-10-04', '08:00', '16:00', 0),
        );
        $this->assertContains('adult-sunday-work', $this->codes($sunday));
    }

    public function test_minor_break_rules_cover_exact_45_and_6_hour_boundaries_and_long_days(): void
    {
        $user = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $exactlyFourAndHalfHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '12:30', 0));
        $this->assertContains('minor-break', $this->codes($exactlyFourAndHalfHours));
        $this->assertNotContains('minor-continuous-work', $this->codes($exactlyFourAndHalfHours));

        $justOverFourAndHalf = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '12:31', 0));
        $this->assertContains('minor-break', $this->codes($justOverFourAndHalf));
        $this->assertContains('minor-continuous-work', $this->codes($justOverFourAndHalf));
        $brokenWorkday = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '13:01', 30));
        $this->assertNotContains('minor-continuous-work', $this->codes($brokenWorkday));

        $exactlySixHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '14:30', 30));
        $this->assertNotContains('minor-long-break', $this->codes($exactlySixHours));

        $overSixHours = $rules->evaluate($user, $this->entry('2026-09-28', '08:00', '14:31', 30));
        $this->assertContains('minor-long-break', $this->codes($overSixHours));
    }

    public function test_minor_daily_weekly_rest_night_and_workday_limits_are_reported(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);
        $weekEntries = [
            ['2026-09-22', '07:00', '16:00', 60],
            ['2026-09-23', '07:00', '16:00', 60],
            ['2026-09-24', '07:00', '16:00', 60],
            ['2026-09-25', '07:00', '16:00', 60],
            ['2026-09-26', '07:00', '16:00', 60],
        ];
        foreach ($weekEntries as [$date, $start, $end, $break]) {
            $this->createTimeEntry($minor, $date, $start, $end, $break);
        }

        $warnings = $rules->evaluate($minor, $this->entry('2026-09-27', '07:00', '16:00', 60));
        $this->assertContains('minor-weekly-limit', $this->codes($warnings));
        $this->assertContains('minor-workdays-limit', $this->codes($warnings));

        $daily = $rules->evaluate($minor, $this->entry('2026-09-28', '07:00', '17:00', 60));
        $this->assertContains('minor-daily-limit', $this->codes($daily));

        $night = $rules->evaluate($minor, $this->entry('2026-09-28', '19:00', '21:00', 0));
        $this->assertContains('minor-night-work', $this->codes($night));
        $allowedWindow = $rules->evaluate($minor, $this->entry('2026-09-28', '06:00', '20:00', 60));
        $this->assertNotContains('minor-night-work', $this->codes($allowedWindow));

        $this->createTimeEntry($minor, '2026-09-29', '19:00', '20:00', 0);
        $rest = $rules->evaluate($minor, $this->entry('2026-09-30', '06:00', '07:00', 0));
        $this->assertContains('minor-rest-period', $this->codes($rest));
        $exactMinorRest = $rules->evaluate($minor, $this->entry('2026-09-30', '08:00', '09:00', 0));
        $this->assertNotContains('minor-rest-period', $this->codes($exactMinorRest));
    }

    public function test_minor_working_exactly_forty_hours_over_five_days_has_no_week_or_workday_warning(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        foreach ([
            ['2026-09-21', 'mon'],
            ['2026-09-22', 'tue'],
            ['2026-09-23', 'wed'],
            ['2026-09-24', 'thu'],
        ] as [$date, $weekday]) {
            $this->createTimeEntry($minor, $date, '07:00', '16:00', 60);
        }

        $warnings = app(WorkTimeRuleService::class)->evaluate(
            $minor,
            $this->entry('2026-09-25', '07:00', '16:00', 60),
        );

        $this->assertNotContains('minor-weekly-limit', $this->codes($warnings));
        $this->assertNotContains('minor-workdays-limit', $this->codes($warnings));
    }

    public function test_age_changes_to_adult_rules_on_the_exact_eighteenth_birthday(): void
    {
        $turningEighteen = User::factory()->create(['birth_date' => '2008-09-29']);
        $stillSeventeen = User::factory()->create(['birth_date' => '2008-09-30']);
        $entry = $this->entry('2026-09-29', '08:00', '17:00', 0);
        $rules = app(WorkTimeRuleService::class);

        $adultWarnings = $rules->evaluate($turningEighteen, $entry);
        $minorWarnings = $rules->evaluate($stillSeventeen, $entry);

        $this->assertContains('adult-extended-day', $this->codes($adultWarnings));
        $this->assertNotContains('minor-daily-limit', $this->codes($adultWarnings));
        $this->assertContains('minor-daily-limit', $this->codes($minorWarnings));
        $this->assertNotContains('adult-extended-day', $this->codes($minorWarnings));
    }

    public function test_minor_vocational_school_day_receives_configured_weekly_credit(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        config(['worktime.minor.vocational_school_day_credit_minutes' => 360]);

        $warnings = app(WorkTimeRuleService::class)->evaluate(
            $minor,
            $this->entry('2026-09-28', '08:00', '09:00', 0, true),
        );

        $this->assertNotContains('minor-daily-limit', $this->codes($warnings));
        config(['worktime.minor.vocational_school_day_credit_minutes' => 600]);
        $creditedOverLimit = app(WorkTimeRuleService::class)->evaluate(
            $minor,
            $this->entry('2026-09-28', '08:00', '09:00', 0, true),
        );
        $this->assertContains('minor-daily-limit', $this->codes($creditedOverLimit));
        config(['worktime.minor.vocational_school_day_credit_minutes' => 480]);
        $defaultCredit = app(WorkTimeRuleService::class)->evaluate(
            $minor,
            $this->entry('2026-09-28', '08:00', '09:00', 0, true),
        );
        $this->assertContains('minor-daily-limit', $this->codes($defaultCredit));
    }

    public function test_violations_are_confirmable_without_blocking_saving_and_marked_in_employee_overview(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create(['birth_date' => '2010-01-01']);

        $component = Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('entryDate', '2026-09-29')
            ->set('startTime', '08:00')
            ->set('endTime', '17:00')
            ->set('breakMinutes', '0')
            ->call('saveEntry')
            ->assertSeeText('Working-time rule warnings')
            ->assertSeeText('JArbSchG');
        $this->assertDatabaseCount('time_entries', 0);

        $component
            ->call('confirmAndSaveEntry')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('time_entries', 1);

        $this->createTimeEntry($user, '2026-09-27', '08:00', '17:00', 0);
        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('period', 'week')
            ->assertSeeText('Rule warning');

        CarbonImmutable::setTestNow();
    }

    public function test_changing_entry_input_requires_rule_warnings_to_be_confirmed_again(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create(['birth_date' => '2010-01-01']);
        $component = Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('entryDate', '2026-09-29')
            ->set('startTime', '08:00')
            ->set('endTime', '17:00')
            ->set('breakMinutes', '0')
            ->call('saveEntry')
            ->assertSeeText('Working-time rule warnings')
            ->set('endTime', '16:00')
            ->call('saveEntry')
            ->assertSeeText('Working-time rule warnings');

        $this->assertDatabaseCount('time_entries', 0);
        $component->call('confirmAndSaveEntry')->assertHasNoErrors();
        $this->assertDatabaseCount('time_entries', 1);
        CarbonImmutable::setTestNow();
    }

    public function test_clock_out_rule_warning_can_be_confirmed_without_finalizing_early(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $component = Livewire::actingAs($minor)
            ->test('pages::time.index')
            ->call('clockIn')
            ->set('clockBreakMinutes', '0');

        CarbonImmutable::setTestNow('2026-09-29 17:00:00');
        $component
            ->call('clockOut')
            ->assertSeeText('Working-time rule warnings')
            ->assertSeeText('JArbSchG');
        $this->assertDatabaseHas('time_entries', ['user_id' => $minor->id, 'status' => TimeEntry::STATUS_IN_PROGRESS]);

        $component->call('confirmAndClockOut')->assertHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['user_id' => $minor->id, 'status' => TimeEntry::STATUS_FINAL]);
        CarbonImmutable::setTestNow();
    }

    public function test_supervisor_sees_recent_violations_and_employee_route_stays_protected(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $employee = User::factory()->create(['birth_date' => '1990-01-01']);
        $this->createTimeEntry($employee, '2026-09-27', '08:00', '17:00', 0);

        Livewire::actingAs($supervisor)
            ->test('pages::supervisor.users')
            ->assertSeeText('Recent rule warnings')
            ->assertSeeText('flagged entries')
            ->assertSeeText('ArbZG');

        $this->actingAs($employee)
            ->get(route('supervisor.users.index'))
            ->assertForbidden();
        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}
     */
    private function entry(string $date, string $start, string $end, int $break, bool $schoolDay = false): array
    {
        return [
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $break,
            'is_school_day' => $schoolDay,
        ];
    }

    /**
     * @param  array<int, array{code: string, rule: string, message: string}>  $warnings
     * @return array<int, string>
     */
    private function codes(array $warnings): array
    {
        return array_column($warnings, 'code');
    }

    private function createHoliday(string $date): void
    {
        DB::table('holidays')->insert([
            'date' => $date,
            'name' => 'Test holiday',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTimeEntry(User $user, string $date, string $start, string $end, int $break): void
    {
        TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $break,
            'status' => TimeEntry::STATUS_FINAL,
            'is_school_day' => false,
        ]);
    }
}
