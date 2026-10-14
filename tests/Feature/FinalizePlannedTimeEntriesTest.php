<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FinalizePlannedTimeEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_command_catches_up_after_midnight_without_finalizing_future_or_open_entries(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);
        $old = $service->create($user, $this->period('2026-09-29'));
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');
        $future = $service->create($user, $this->period('2026-09-30'));
        $open = $service->clockIn(User::factory()->create());

        $this->artisan('time-entries:finalize-planned')
            ->expectsOutput('Finalized 1 planned time entries.')
            ->assertSuccessful();
        $this->assertSame(TimeEntry::STATUS_FINAL, $old->fresh()->status);
        $this->assertSame(TimeEntry::STATUS_PLANNED, $future->fresh()->status);
        $this->assertSame(TimeEntry::STATUS_IN_PROGRESS, $open->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 4);
        $this->artisan('time-entries:finalize-planned')
            ->expectsOutput('Finalized 0 planned time entries.')
            ->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_opening_my_time_catches_up_own_entries_without_a_running_scheduler(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $other = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $service = app(TimeEntryService::class);
        $old = $service->create($user, $this->period('2026-09-29'));
        $otherEntry = $service->create($other, $this->period('2026-09-29'));
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');
        $future = $service->create($user, $this->period('2026-09-30'));

        $this->actingAs($user)->get(route('time.index'))->assertOk()->assertSee('8.00 h');
        $this->assertSame(TimeEntry::STATUS_FINAL, $old->fresh()->status);
        $this->assertSame(TimeEntry::STATUS_PLANNED, $otherEntry->fresh()->status);
        $this->assertSame(TimeEntry::STATUS_PLANNED, $future->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 4);
        $this->get(route('time.index'))->assertOk();
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_supervisor_requests_catch_up_employee_entries(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $entry = app(TimeEntryService::class)->create($user, $this->period('2026-09-29'));
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($supervisor)->get(route('supervisor.dashboard'))->assertOk();
        $this->assertSame(TimeEntry::STATUS_FINAL, $entry->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_opening_calendar_and_later_livewire_requests_finalize_elapsed_plans(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $service = app(TimeEntryService::class);
        $old = $service->create($user, $this->period('2026-09-29'));
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');
        $today = $service->create($user, $this->period('2026-09-30'));

        $this->actingAs($user)->get(route('time.calendar'))->assertOk();
        $this->assertSame(TimeEntry::STATUS_FINAL, $old->fresh()->status);
        $component = Livewire::actingAs($user)->test('pages::time.index');
        CarbonImmutable::setTestNow('2026-09-30 16:30:00');
        $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($component->snapshot, JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'previousPeriod', 'params' => []]],
            ]],
        ])->assertOk();
        $this->assertSame(TimeEntry::STATUS_FINAL, $today->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_catch_up_processes_multiple_chunks_and_preserves_future_dated_records(): void
    {
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');
        $user = User::factory()->create();
        $rows = array_fill(0, 105, [
            'user_id' => $user->id,
            ...$this->period('2026-09-29'),
            'status' => TimeEntry::STATUS_PLANNED,
        ]);
        DB::table('time_entries')->insert($rows);
        $future = $user->timeEntries()->create([
            ...$this->period('2026-10-01'),
            'status' => TimeEntry::STATUS_PLANNED,
        ]);
        $this->assertSame(105, app(TimeEntryService::class)->finalizePlannedEntries());
        $this->assertSame(105, $user->timeEntries()->where('status', TimeEntry::STATUS_FINAL)->count());
        $this->assertSame(TimeEntry::STATUS_PLANNED, $future->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 105);
    }

    public function test_guest_requests_and_deleted_plans_do_not_finalize_entries(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);
        $deleted = $service->create($user, $this->period('2026-09-29'));
        $service->delete($user, $deleted);
        $active = $service->create($user, $this->period('2026-09-29'));
        CarbonImmutable::setTestNow('2026-09-30 08:00:00');

        $this->get(route('time.index'))->assertRedirect(route('login'));
        $this->assertSame(TimeEntry::STATUS_PLANNED, $active->fresh()->status);
        $this->assertSame(1, $service->finalizePlannedEntries());
        $this->assertSame(TimeEntry::STATUS_PLANNED, TimeEntry::withTrashed()->findOrFail($deleted->id)->status);
        $this->assertDatabaseCount('audit_logs', 4);
    }

    /** @return array{date: string, start_time: string, end_time: string, break_minutes: int} */
    private function period(string $date): array
    {
        return ['date' => $date, 'start_time' => '08:00', 'end_time' => '16:30', 'break_minutes' => 30];
    }

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
