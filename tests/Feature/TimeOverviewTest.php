<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimeOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_week_and_month_overviews_include_only_the_authenticated_users_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createEntry($user, '2026-09-28');
        $this->createEntry($user, '2026-09-29');
        $this->createEntry($user, '2026-09-01');
        $this->createEntry($other, '2026-09-29');
        DB::table('absences')->insert([
            'user_id' => $user->id,
            'type' => 'sick',
            'start_date' => '2026-09-29',
            'end_date' => '2026-09-29',
            'status' => 'pending',
            'sick_note_submitted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(TimeOverviewService::class);

        $day = $service->for($user, 'day', '2026-09-29');
        $this->assertCount(1, $day['entries']);
        $this->assertCount(1, $day['absences']);

        $week = $service->for($user, 'week', '2026-09-29');
        $this->assertCount(2, $week['entries']);
        $this->assertSame('2026-09-28', $week['start']->toDateString());

        $month = $service->for($user, 'month', '2026-09-29');
        $this->assertCount(3, $month['entries']);
        $this->assertSame(1440, $month['total_minutes']);
    }

    public function test_future_dates_and_unknown_periods_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(TimeOverviewService::class);

        try {
            $service->for($user, 'year', '2026-09-29');
            $this->fail('Expected unsupported overview periods to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period', $exception->errors());
        }

        try {
            $service->for($user, 'day', today()->addDay()->toDateString());
            $this->fail('Expected future overview dates to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('referenceDate', $exception->errors());
        }
    }

    public function test_time_tracking_route_requires_authentication(): void
    {
        $this->get(route('time.index'))->assertRedirect(route('login'));
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
