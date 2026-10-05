<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NrwHolidayService
{
    /**
     * @return array<int, array{date: string, name: string}>
     */
    public function forYear(int $year): array
    {
        if ($year < 1900 || $year > 2100) {
            throw new InvalidArgumentException('Holiday generation supports years from 1900 through 2100.');
        }

        $easter = $this->easterSunday($year);

        return [
            ['date' => "{$year}-01-01", 'name' => 'New Year’s Day'],
            ['date' => $easter->subDays(2)->toDateString(), 'name' => 'Good Friday'],
            ['date' => $easter->addDay()->toDateString(), 'name' => 'Easter Monday'],
            ['date' => "{$year}-05-01", 'name' => 'Labour Day'],
            ['date' => $easter->addDays(39)->toDateString(), 'name' => 'Ascension Day'],
            ['date' => $easter->addDays(50)->toDateString(), 'name' => 'Whit Monday'],
            ['date' => $easter->addDays(60)->toDateString(), 'name' => 'Corpus Christi'],
            ['date' => "{$year}-10-03", 'name' => 'German Unity Day'],
            ['date' => "{$year}-11-01", 'name' => 'All Saints’ Day'],
            ['date' => "{$year}-12-25", 'name' => 'Christmas Day'],
            ['date' => "{$year}-12-26", 'name' => 'Second Day of Christmas'],
        ];
    }

    public function generate(int $year): int
    {
        $holidays = $this->forYear($year);
        DB::table('holidays')->upsert(
            array_map(fn (array $holiday): array => [
                ...$holiday,
                'created_at' => now(),
                'updated_at' => now(),
            ], $holidays),
            ['date'],
            ['name', 'updated_at'],
        );

        return count($holidays);
    }

    public function ensureYearGenerated(int $year): void
    {
        if ($year < 1900 || $year > 2100) {
            return;
        }

        $holidays = $this->forYear($year);
        $stored = DB::table('holidays')
            ->whereIn('date', array_column($holidays, 'date'))
            ->pluck('name', 'date');

        $complete = collect($holidays)->every(
            fn (array $holiday): bool => $stored->get($holiday['date']) === $holiday['name'],
        );

        if (! $complete) {
            $this->generate($year);
        }
    }

    private function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
