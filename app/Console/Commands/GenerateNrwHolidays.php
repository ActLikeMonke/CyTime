<?php

namespace App\Console\Commands;

use App\Services\NrwHolidayService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class GenerateNrwHolidays extends Command
{
    protected $signature = 'holidays:generate {year : Calendar year to generate NRW public holidays for}';

    protected $description = 'Generate the statutory public holidays for North Rhine-Westphalia';

    public function handle(NrwHolidayService $holidays): int
    {
        $year = filter_var($this->argument('year'), FILTER_VALIDATE_INT);
        if ($year === false) {
            $this->error('Year must be an integer.');

            return self::INVALID;
        }

        try {
            $count = $holidays->generate($year);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info("Generated {$count} NRW public holidays for {$year}.");

        return self::SUCCESS;
    }
}
