<?php

namespace Tests\Unit;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\WorkTimeRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkTimeRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_period_break_thresholds_use_the_whole_day_and_count_the_gap_once(): void
    {
        $adult = User::factory()->create(['birth_date' => '1990-01-01']);
        $rules = app(WorkTimeRuleService::class);
        $morning = $this->candidate('2026-09-28', '08:00', '11:00', 0);
        $exactlySix = $rules->evaluatePeriods($adult, [
            $morning, $this->candidate('2026-09-28', '11:00', '14:00', 0),
        ]);
        $this->assertNotContains('adult-break', $this->codes($exactlySix));
        $overSix = $rules->evaluatePeriods($adult, [
            $morning, $this->candidate('2026-09-28', '11:00', '14:01', 0),
        ]);
        $this->assertContains('adult-break', $this->codes($overSix));
        $morning = $this->candidate('2026-09-28', '08:00', '12:00', 0);
        $exactlyNine = $rules->evaluatePeriods($adult, [
            $morning, $this->candidate('2026-09-28', '12:30', '17:30', 0),
        ]);
        $this->assertNotContains('adult-long-break', $this->codes($exactlyNine));
        $overNine = $rules->evaluatePeriods($adult, [
            $morning, $this->candidate('2026-09-28', '12:30', '17:31', 0),
        ]);
        $this->assertContains('adult-long-break', $this->codes($overNine));
    }

    public function test_minor_continuous_limit_joins_touching_periods_but_resets_at_a_gap(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);
        $morning = $this->candidate('2026-09-28', '08:00', '10:00', 0);
        $exactlyLimit = $rules->evaluatePeriods($minor, [
            $morning, $this->candidate('2026-09-28', '10:00', '12:30', 0),
        ]);
        $this->assertNotContains('minor-continuous-work', $this->codes($exactlyLimit));
        $overLimit = $rules->evaluatePeriods($minor, [
            $morning, $this->candidate('2026-09-28', '10:00', '12:31', 0),
        ]);
        $this->assertContains('minor-continuous-work', $this->codes($overLimit));
        $withGap = $rules->evaluatePeriods($minor, [
            $morning, $this->candidate('2026-09-28', '11:00', '15:30', 0),
        ]);
        $this->assertNotContains('minor-continuous-work', $this->codes($withGap));
        $this->assertNotContains('minor-long-break', $this->codes($withGap));
    }

    public function test_adult_daily_break_rest_sunday_and_holiday_rules(): void
    {
        $adult = User::factory()->create(['birth_date' => '1990-01-01']);
        $rules = app(WorkTimeRuleService::class);
        $this->holiday('2026-09-28');
        $this->entry($adult, '2026-09-27', '08:00', '22:00', 0);

        $sixHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '08:00', '14:00', 0));
        $this->assertNotContains('adult-break', $this->codes($sixHours));

        $overSixHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '08:00', '14:01', 0));
        $this->assertContains('adult-break', $this->codes($overSixHours));
        $this->assertContains('adult-holiday-work', $this->codes($overSixHours));
        $this->assertContains('adult-rest-period', $this->codes($overSixHours));

        $nineHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '08:00', '17:30', 30));
        $this->assertNotContains('adult-long-break', $this->codes($nineHours));
        $this->assertContains('adult-extended-day', $this->codes($nineHours));

        $overTenHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '07:00', '18:00', 0));
        $this->assertContains('adult-daily-limit', $this->codes($overTenHours));

        $sunday = $rules->evaluate($adult, $this->candidate('2026-10-04', '08:00', '16:00', 0));
        $this->assertContains('adult-sunday-work', $this->codes($sunday));
    }

    public function test_adult_long_break_is_required_only_after_nine_hours(): void
    {
        $adult = User::factory()->create(['birth_date' => '1990-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $exactlyNineHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '08:00', '17:00', 0));
        $this->assertNotContains('adult-long-break', $this->codes($exactlyNineHours));

        $overNineHours = $rules->evaluate($adult, $this->candidate('2026-09-28', '08:00', '17:01', 0));
        $this->assertContains('adult-long-break', $this->codes($overNineHours));
    }

    public function test_minor_daily_break_week_rest_night_and_workday_rules(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $exactlyFourAndHalf = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '12:30', 0));
        $this->assertContains('minor-break', $this->codes($exactlyFourAndHalf));
        $this->assertNotContains('minor-continuous-work', $this->codes($exactlyFourAndHalf));

        $justOverFourAndHalf = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '12:31', 0));
        $this->assertContains('minor-continuous-work', $this->codes($justOverFourAndHalf));

        $exactlySix = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '14:30', 30));
        $this->assertNotContains('minor-long-break', $this->codes($exactlySix));
        $overSix = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '14:31', 30));
        $this->assertContains('minor-long-break', $this->codes($overSix));

        $dailyLimit = $rules->evaluate($minor, $this->candidate('2026-09-28', '07:00', '17:00', 60));
        $this->assertContains('minor-daily-limit', $this->codes($dailyLimit));
        $nightWork = $rules->evaluate($minor, $this->candidate('2026-09-28', '19:00', '21:00', 0));
        $this->assertContains('minor-night-work', $this->codes($nightWork));

        foreach ([
            ['2026-09-22', '07:00', '16:00', 60],
            ['2026-09-23', '07:00', '16:00', 60],
            ['2026-09-24', '07:00', '16:00', 60],
            ['2026-09-25', '07:00', '16:00', 60],
            ['2026-09-26', '07:00', '16:00', 60],
        ] as [$date, $start, $end, $break]) {
            $this->entry($minor, $date, $start, $end, $break);
        }
        $weeklyLimit = $rules->evaluate($minor, $this->candidate('2026-09-27', '07:00', '16:00', 60));
        $this->assertContains('minor-weekly-limit', $this->codes($weeklyLimit));
        $this->assertContains('minor-workdays-limit', $this->codes($weeklyLimit));

        $this->entry($minor, '2026-09-29', '19:00', '20:00', 0);
        $shortRest = $rules->evaluate($minor, $this->candidate('2026-09-30', '06:00', '07:00', 0));
        $this->assertContains('minor-rest-period', $this->codes($shortRest));
    }

    public function test_vocational_school_day_uses_the_configured_minor_credit(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);
        $entry = $this->candidate('2026-09-28', '08:00', '09:00', 0, true);

        $defaultCredit = $rules->evaluate($minor, $entry);
        $this->assertContains('minor-daily-limit', $this->codes($defaultCredit));

        config(['worktime.minor.vocational_school_day_credit_minutes' => 360]);
        $configuredCredit = $rules->evaluate($minor, $entry);
        $this->assertNotContains('minor-daily-limit', $this->codes($configuredCredit));
    }

    public function test_adult_rules_apply_on_the_exact_eighteenth_birthday(): void
    {
        $turnsEighteen = User::factory()->create(['birth_date' => '2008-09-29']);
        $stillSeventeen = User::factory()->create(['birth_date' => '2008-09-30']);
        $entry = $this->candidate('2026-09-29', '08:00', '17:00', 0);
        $rules = app(WorkTimeRuleService::class);

        $adultWarnings = $rules->evaluate($turnsEighteen, $entry);
        $minorWarnings = $rules->evaluate($stillSeventeen, $entry);

        $this->assertContains('adult-extended-day', $this->codes($adultWarnings));
        $this->assertNotContains('minor-daily-limit', $this->codes($adultWarnings));
        $this->assertContains('minor-daily-limit', $this->codes($minorWarnings));
    }

    public function test_minor_daily_weekly_and_rest_limits_are_checked_at_the_boundary(): void
    {
        $minor = User::factory()->create(['birth_date' => '2010-01-01']);
        $rules = app(WorkTimeRuleService::class);

        $exactlyEightHours = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '16:00', 0));
        $this->assertNotContains('minor-daily-limit', $this->codes($exactlyEightHours));

        $overEightHours = $rules->evaluate($minor, $this->candidate('2026-09-28', '08:00', '16:01', 0));
        $this->assertContains('minor-daily-limit', $this->codes($overEightHours));

        foreach (['2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $date) {
            $this->entry($minor, $date, '07:00', '16:00', 60);
        }
        $exactlyFortyHours = $rules->evaluate($minor, $this->candidate('2026-09-26', '07:00', '16:00', 60));
        $this->assertNotContains('minor-weekly-limit', $this->codes($exactlyFortyHours));
        $this->assertNotContains('minor-workdays-limit', $this->codes($exactlyFortyHours));

        $this->entry($minor, '2026-09-26', '07:00', '16:00', 60);
        $overFortyHours = $rules->evaluate($minor, $this->candidate('2026-09-27', '07:00', '08:00', 0));
        $this->assertContains('minor-weekly-limit', $this->codes($overFortyHours));
        $this->assertContains('minor-workdays-limit', $this->codes($overFortyHours));

        $restingMinor = User::factory()->create(['birth_date' => '2010-01-01']);
        $this->entry($restingMinor, '2026-09-27', '12:00', '20:00', 0);
        $exactlyTwelveHoursRest = $rules->evaluate(
            $restingMinor,
            $this->candidate('2026-09-28', '08:00', '16:00', 0),
        );
        $this->assertNotContains('minor-rest-period', $this->codes($exactlyTwelveHoursRest));
    }

    /**
     * @return array{date: string, start_time: string, end_time: string, break_minutes: int, is_school_day: bool}
     */
    private function candidate(string $date, string $start, string $end, int $break, bool $schoolDay = false): array
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

    private function entry(User $user, string $date, string $start, string $end, int $break): void
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

    private function holiday(string $date): void
    {
        DB::table('holidays')->insert([
            'date' => $date,
            'name' => 'Unit-test holiday',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
