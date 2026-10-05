<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\WeeklyBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeeklyBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_reduces_weekly_target_only_when_it_falls_on_a_workday(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 20, ['mon', 'wed', 'fri']);
        $this->createHoliday('2026-10-01', 'Thursday holiday');

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));
        $this->assertSame(1200, $balance['target_minutes']);

        $this->createHoliday('2026-09-28', 'Monday holiday');
        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));
        $this->assertSame(800, $balance['target_minutes']);
    }

    public function test_sick_days_count_while_pending_and_approved_but_rejected_sick_days_do_not(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createAbsence($user, 'sick', 'pending', '2026-09-28');
        $this->createAbsence($user, 'sick', 'rejected', '2026-09-29');
        $this->createAbsence($user, 'sick', 'approved', '2026-09-30');

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));

        $this->assertSame(1440, $balance['target_minutes']);
    }

    public function test_vacation_reduces_target_only_after_approval(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createAbsence($user, 'vacation', 'pending', '2026-09-28');

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));
        $this->assertSame(2400, $balance['target_minutes']);

        DB::table('absences')->where('user_id', $user->id)->update(['status' => 'approved']);
        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));
        $this->assertSame(1920, $balance['target_minutes']);
    }

    public function test_weekly_target_uses_part_time_workdays_and_counts_each_excluded_day_once(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 20, ['mon', 'wed', 'fri']);
        $this->createHoliday('2026-09-28', 'Monday holiday');
        $this->createAbsence($user, 'sick', 'pending', '2026-09-28');
        $this->createAbsence($user, 'sick', 'pending', '2026-09-29');

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));

        $this->assertSame(800, $balance['target_minutes']);
        $this->assertSame(['mon', 'wed', 'fri'], $balance['workdays']);
    }

    public function test_balance_uses_schedule_valid_for_the_week_and_sums_completed_entries(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createSchedule($user, '2026-03-04', 20, ['mon', 'wed', 'fri']);
        $this->createEntry($user, '2026-03-03', '08:00', '16:00', 30, TimeEntry::STATUS_FINAL);
        $this->createEntry($user, '2026-03-04', '08:00', '16:00', 30, TimeEntry::STATUS_PLANNED);

        $duringChangeWeek = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-03-02'));
        $this->assertSame(1760, $duringChangeWeek['target_minutes']);
        $this->assertSame(450, $duringChangeWeek['worked_minutes']);

        $followingWeek = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-03-09'));
        $this->assertSame(1200, $followingWeek['target_minutes']);
    }

    public function test_time_entry_weekly_target_behind_and_overtime_values_are_returned(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createEntry($user, '2026-09-28', '08:00', '17:00', 60, TimeEntry::STATUS_FINAL);

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-28'));

        $this->assertSame(2400, $balance['target_minutes']);
        $this->assertSame(480, $balance['worked_minutes']);
        $this->assertSame(0, $balance['remaining_minutes']);
        $this->assertSame(-1920, $balance['balance_minutes']);

        $tuesdayBalance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-29'));
        $this->assertSame(0, $tuesdayBalance['remaining_minutes']);
    }

    public function test_remaining_minutes_only_count_missed_workdays_before_the_reference_date(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);

        $service = app(WeeklyBalanceService::class);

        $mondayBalance = $service->for($user, CarbonImmutable::parse('2026-09-28'));
        $this->assertSame(0, $mondayBalance['remaining_minutes']);
        $this->assertSame(2400, $mondayBalance['target_minutes']);

        $tuesdayBalance = $service->for($user, CarbonImmutable::parse('2026-09-29'));
        $this->assertSame(480, $tuesdayBalance['remaining_minutes']);

        $wednesdayBalance = $service->for($user, CarbonImmutable::parse('2026-09-30'));
        $this->assertSame(960, $wednesdayBalance['remaining_minutes']);
    }

    public function test_remaining_minutes_do_not_accrue_for_past_holidays_or_absences(): void
    {
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-01-01', 40, ['mon', 'tue', 'wed', 'thu', 'fri']);
        $this->createHoliday('2026-09-28', 'Monday holiday');
        $this->createAbsence($user, 'sick', 'pending', '2026-09-29');

        $balance = app(WeeklyBalanceService::class)->for($user, CarbonImmutable::parse('2026-09-30'));

        $this->assertSame(1440, $balance['target_minutes']);
        $this->assertSame(0, $balance['remaining_minutes']);
    }

    public function test_overall_balance_accumulates_weekly_overtime_and_undertime_from_first_schedule(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $user = User::factory()->create();
        $this->createSchedule($user, '2026-09-14', 20, ['mon', 'wed', 'fri']);
        $this->createHoliday('2026-09-21', 'Monday holiday');
        $this->createEntry($user, '2026-09-14', '08:00', '18:00', 0, TimeEntry::STATUS_FINAL);
        $this->createEntry($user, '2026-09-23', '08:00', '16:00', 0, TimeEntry::STATUS_FINAL);
        $this->createEntry($user, '2026-09-25', '08:00', '16:00', 0, TimeEntry::STATUS_FINAL);
        $this->createEntry($user, '2026-09-28', '08:00', '16:00', 0, TimeEntry::STATUS_FINAL);

        $balance = app(WeeklyBalanceService::class)->cumulativeBalance($user, CarbonImmutable::today());

        $this->assertSame(-1160, $balance);
        CarbonImmutable::setTestNow();
    }

    private function createSchedule(User $user, string $date, int $hours, array $days): void
    {
        $user->workSchedules()->create([
            'weekly_hours' => $hours,
            'work_days' => $days,
            'valid_from' => $date,
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

    private function createAbsence(User $user, string $type, string $status, string $date): void
    {
        DB::table('absences')->insert([
            'user_id' => $user->id,
            'type' => $type,
            'start_date' => $date,
            'end_date' => $date,
            'status' => $status,
            'sick_note_submitted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEntry(User $user, string $date, string $start, string $end, int $break, string $status): void
    {
        TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $break,
            'status' => $status,
            'is_school_day' => false,
        ]);
    }
}
