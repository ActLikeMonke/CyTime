<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_record_today_and_past_work_with_net_time_and_audit_history(): void
    {
        $user = User::factory()->create();
        $entry = app(TimeEntryService::class)->create($user, [
            'date' => '2026-09-28',
            'start_time' => '08:00',
            'end_time' => '16:30',
            'break_minutes' => 30,
            'is_school_day' => true,
        ]);

        $this->assertSame(TimeEntry::STATUS_FINAL, $entry->status);
        $this->assertSame(480, $entry->netMinutes());
        $this->assertTrue($entry->is_school_day);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_today_end_time_in_the_future_is_planned_and_can_be_corrected_until_day_end(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);

        $entry = $service->create($user, [
            'date' => '2026-09-29',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 30,
        ]);

        $this->assertSame(TimeEntry::STATUS_PLANNED, $entry->status);
        $entry = $service->update($user, $entry, [
            'date' => '2026-09-29',
            'start_time' => '08:00',
            'end_time' => '15:00',
            'break_minutes' => 30,
        ]);

        $this->assertSame('15:00:00', $entry->end_time);
        $this->assertSame(2, DB::table('audit_logs')->where('auditable_id', $entry->id)->count());

        CarbonImmutable::setTestNow();
    }

    public function test_future_dates_future_start_times_and_invalid_work_intervals_are_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);

        foreach ([
            ['date' => '2026-09-30', 'start_time' => '08:00', 'end_time' => '16:00'],
            ['date' => '2026-09-29', 'start_time' => '09:00', 'end_time' => '17:00'],
            ['date' => '2026-09-29', 'start_time' => '09:00', 'end_time' => '08:00'],
        ] as $invalid) {
            try {
                $service->create($user, [
                    ...$invalid,
                    'break_minutes' => 0,
                ]);
                $this->fail('Expected invalid time entry data to be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('time_entries', 0);
            }
        }

        CarbonImmutable::setTestNow();
    }

    public function test_past_corrections_are_allowed_only_within_seven_days(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);

        $service->create($user, [
            'date' => '2026-09-22',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 0,
        ]);

        try {
            $service->create($user, [
                'date' => '2026-09-21',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 0,
            ]);
            $this->fail('Expected entries outside the correction window to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('time_entries', 1);
        }

        CarbonImmutable::setTestNow();
    }

    public function test_employees_cannot_change_another_users_time_entry(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $entry = app(TimeEntryService::class)->create($other, [
            'date' => today()->subDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 0,
        ]);

        $this->expectException(AuthorizationException::class);

        app(TimeEntryService::class)->delete($actor, $entry);
    }

    public function test_past_entries_outside_the_correction_window_cannot_be_updated(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 12:00:00');
        $user = User::factory()->create();
        $entry = TimeEntry::query()->create([
            'user_id' => $user->id,
            'date' => '2026-09-21',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 0,
            'status' => TimeEntry::STATUS_FINAL,
            'is_school_day' => false,
        ]);

        try {
            app(TimeEntryService::class)->update($user, $entry, [
                'date' => '2026-09-29',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 0,
            ]);
            $this->fail('An entry outside the correction window must not be moved into it.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'date' => '2026-09-21 00:00:00']);
        }

        try {
            $service = app(TimeEntryService::class);
            $service->delete($user, $entry);
            $this->fail('An entry outside the correction window must not be deleted.');
        } catch (ValidationException) {
            $this->assertNotSoftDeleted('time_entries', ['id' => $entry->id]);
        }

        CarbonImmutable::setTestNow();
    }

    public function test_break_cannot_exceed_work_interval(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();

        try {
            app(TimeEntryService::class)->create($user, [
                'date' => '2026-09-29',
                'start_time' => '08:00',
                'end_time' => '08:30',
                'break_minutes' => 31,
            ]);
            $this->fail('A break longer than the work interval must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('break_minutes', $exception->errors());
        }

        CarbonImmutable::setTestNow();
    }

    public function test_entries_are_soft_deleted_and_the_deletion_is_audited(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $entry = app(TimeEntryService::class)->create($user, [
            'date' => today()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'break_minutes' => 0,
        ]);

        app(TimeEntryService::class)->delete($user, $entry);

        $this->assertSoftDeleted('time_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TimeEntry::class,
            'auditable_id' => $entry->id,
        ]);
        CarbonImmutable::setTestNow();
    }

    public function test_clock_in_and_clock_out_records_an_audited_entry(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);

        $entry = $service->clockIn($user);
        $this->assertSame(TimeEntry::STATUS_IN_PROGRESS, $entry->status);
        $this->assertNull($entry->end_time);

        CarbonImmutable::setTestNow('2026-09-29 12:30:00');
        $entry = $service->clockOut($user, $entry);

        $this->assertSame(TimeEntry::STATUS_FINAL, $entry->status);
        $this->assertSame('12:30:00', $entry->end_time);
        $this->assertSame(270, $entry->netMinutes());
        $this->assertDatabaseCount('audit_logs', 2);

        CarbonImmutable::setTestNow();
    }

    public function test_clock_out_rejects_a_break_longer_than_elapsed_work_time(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $service = app(TimeEntryService::class);
        $entry = $service->clockIn($user);
        CarbonImmutable::setTestNow('2026-09-29 08:30:00');

        try {
            $service->clockOut($user, $entry, 31);
            $this->fail('A break cannot exceed time elapsed since clock-in.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('breakMinutes', $exception->errors());
        }

        $this->assertSame(TimeEntry::STATUS_IN_PROGRESS, $entry->fresh()->status);
        CarbonImmutable::setTestNow();
    }

    public function test_a_user_cannot_clock_in_twice_or_clock_out_another_users_entry(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = app(TimeEntryService::class);
        $entry = $service->clockIn($user);

        try {
            $service->clockIn($user);
            $this->fail('Expected a duplicate daily entry to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('time_entries', 1);
        }

        $this->expectException(AuthorizationException::class);
        $service->clockOut($other, $entry);
    }

    public function test_livewire_time_page_exposes_manual_entry_and_clock_controls(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->assertSeeText('Weekly target')
            ->assertSeeText('Hours behind this week')
            ->assertDontSeeText('Remaining this week')
            ->assertSeeText('Overall balance since first schedule')
            ->assertSeeText('Log working time')
            ->assertSeeText('Clock in')
            ->assertSeeText('Hours and absences');
    }

    public function test_my_time_page_has_no_top_bar_and_places_large_settings_icon_by_page_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('time.index'))
            ->assertOk();

        $html = $response->getContent();

        $response
            ->assertSee('ui-heading--xl')
            ->assertSee('aria-label="Settings"', false)
            ->assertSee('app-page-actions__settings', false)
            ->assertSee('time-page__title-row', false)
            ->assertSee('app-page-actions__menu', false)
            ->assertSee('<svg', false)
            ->assertDontSee('app-header', false);

        $this->assertLessThan(
            strpos($html, 'time-page__work-area'),
            strpos($html, 'class="time-page__overview"'),
        );
        $this->assertLessThan(
            strpos($html, 'class="time-page__entry-form"'),
            strpos($html, 'class="time-page__panel"'),
        );
    }

    public function test_livewire_net_time_preview_handles_incomplete_input(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('startTime', '')
            ->assertSeeText('Net time: 0.00 h');
    }

    public function test_livewire_net_time_preview_updates_when_times_change(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('startTime', '09:00')
            ->set('endTime', '17:00')
            ->set('breakMinutes', '30')
            ->assertSeeText('Net time: 7.50 h');
    }

    public function test_entry_times_are_preserved_after_save_and_while_changing_entry_date(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('entryDate', '2026-09-29')
            ->set('startTime', '07:45')
            ->set('endTime', '16:15')
            ->set('breakMinutes', '45')
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSet('startTime', '07:45')
            ->assertSet('endTime', '16:15')
            ->assertSet('breakMinutes', '45');

        $component
            ->call('previousEntryDate')
            ->assertSet('entryDate', '2026-09-28')
            ->assertSet('startTime', '07:45')
            ->assertSet('endTime', '16:15')
            ->call('nextEntryDate')
            ->assertSet('entryDate', '2026-09-29')
            ->call('nextEntryDate')
            ->assertSet('entryDate', '2026-09-29');

        CarbonImmutable::setTestNow();
    }

    public function test_livewire_user_can_log_and_edit_their_time_entry(): void
    {
        CarbonImmutable::setTestNow('2026-09-29 08:00:00');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->set('entryDate', '2026-09-29')
            ->set('startTime', '08:00')
            ->set('endTime', '16:00')
            ->set('breakMinutes', '30')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $entry = $user->timeEntries()->firstOrFail();
        $this->assertSame(TimeEntry::STATUS_PLANNED, $entry->status);

        Livewire::actingAs($user)
            ->test('pages::time.index')
            ->call('editEntry', $entry->id)
            ->set('endTime', '15:00')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $this->assertSame('15:00:00', $entry->fresh()->end_time);
        CarbonImmutable::setTestNow();
    }
}
