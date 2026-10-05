<?php

namespace Tests\Feature;

use App\Services\NrwHolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NrwHolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_generator_creates_all_eleven_nrw_holidays_for_2026(): void
    {
        $count = app(NrwHolidayService::class)->generate(2026);

        $this->assertSame(11, $count);
        $this->assertDatabaseCount('holidays', 11);
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-03', 'name' => 'Good Friday']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-06', 'name' => 'Easter Monday']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-05-14', 'name' => 'Ascension Day']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-05-25', 'name' => 'Whit Monday']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-06-04', 'name' => 'Corpus Christi']);

        app(NrwHolidayService::class)->generate(2026);
        $this->assertDatabaseCount('holidays', 11);
    }

    public function test_holiday_generator_adds_each_year_without_overwriting_other_years(): void
    {
        $service = app(NrwHolidayService::class);
        $service->generate(2025);
        $service->generate(2026);

        $this->assertSame(22, DB::table('holidays')->count());
        $this->assertDatabaseHas('holidays', ['date' => '2025-04-18', 'name' => 'Good Friday']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-03', 'name' => 'Good Friday']);
    }

    public function test_holiday_generation_command_requires_a_supported_integer_year(): void
    {
        $this->artisan('holidays:generate', ['year' => '2026'])
            ->expectsOutput('Generated 11 NRW public holidays for 2026.')
            ->assertSuccessful();

        $this->artisan('holidays:generate', ['year' => '2101'])
            ->expectsOutput('Holiday generation supports years from 1900 through 2100.')
            ->assertFailed();
    }
}
