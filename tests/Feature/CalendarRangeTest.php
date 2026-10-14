<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\TimeCalendarService;
use App\Support\CalendarRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-06 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_range_spans_twenty_years_back_and_two_years_ahead(): void
    {
        $this->assertSame('2006-01-01', CalendarRange::minDateString());
        $this->assertSame('2028-12-31', CalendarRange::maxDateString());
        $this->assertSame('2006-01', CalendarRange::minMonth());
        $this->assertSame('2028-12', CalendarRange::maxMonth());
    }

    public function test_date_inputs_receive_range_limits_but_keep_stricter_limits_and_can_opt_out(): void
    {
        $date = Blade::render('<x-ui.input id="from" type="date" />');
        $month = Blade::render('<x-ui.input id="period" type="month" />');
        $stricter = Blade::render('<x-ui.input id="day" type="date" max="2026-10-06" />');
        $birthDate = Blade::render('<x-ui.input id="birth" type="date" :limit-range="false" max="2026-10-06" />');

        $this->assertStringContainsString('min="2006-01-01"', $date);
        $this->assertStringContainsString('max="2028-12-31"', $date);
        $this->assertStringContainsString('min="2006-01"', $month);
        $this->assertStringContainsString('max="2028-12"', $month);
        $this->assertStringContainsString('min="2006-01-01"', $stricter);
        $this->assertStringContainsString('max="2026-10-06"', $stricter);
        $this->assertStringNotContainsString('min=', $birthDate);
        $this->assertStringContainsString('max="2026-10-06"', $birthDate);
    }

    public function test_absences_outside_the_range_are_rejected(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        foreach ([['2029-01-01', '2029-01-02'], ['2005-12-30', '2006-01-02']] as [$start, $end]) {
            try {
                app(AbsenceService::class)->request($employee, [
                    'type' => Absence::TYPE_VACATION,
                    'start_date' => $start,
                    'end_date' => $end,
                ]);
                $this->fail("Absence {$start} – {$end} should be rejected.");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertSame(0, Absence::query()->count());
    }

    public function test_calendar_month_navigation_stops_at_the_range_bounds(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        Livewire::actingAs($employee)
            ->test('pages::time.calendar')
            ->set('month', '2028-12')
            ->call('nextMonth')
            ->assertSet('month', '2028-12')
            ->set('month', '2006-01')
            ->call('previousMonth')
            ->assertSet('month', '2006-01');

        $this->expectException(ValidationException::class);
        app(TimeCalendarService::class)->for($employee, '2029-01');
    }
}
