<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalizePlannedTimeEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_planned_entries_finalize_after_the_end_time_passes_and_are_audited(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        app(TimeEntryService::class)->create($user, [
            'date' => '2026-09-29',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 30,
        ]);

        $this->artisan('time-entries:finalize-planned')
            ->expectsOutput('Finalized 0 planned time entries.')
            ->assertSuccessful();

        CarbonImmutable::setTestNow('2026-09-29 17:00:00');
        $this->artisan('time-entries:finalize-planned')
            ->expectsOutput('Finalized 1 planned time entries.')
            ->assertSuccessful();

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'status' => 'final',
        ]);
        $this->assertDatabaseCount('audit_logs', 2);

        CarbonImmutable::setTestNow();
    }
}
