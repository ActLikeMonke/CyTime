<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Selectable calendar window: 20 years back and 2 years ahead (whole years).
 */
final class CalendarRange
{
    public const YEARS_BACK = 20;

    public const YEARS_AHEAD = 2;

    public static function minDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->subYears(self::YEARS_BACK)->startOfYear();
    }

    public static function maxDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addYears(self::YEARS_AHEAD)->endOfYear()->startOfDay();
    }

    public static function minDateString(): string
    {
        return self::minDate()->toDateString();
    }

    public static function maxDateString(): string
    {
        return self::maxDate()->toDateString();
    }

    public static function minMonth(): string
    {
        return self::minDate()->format('Y-m');
    }

    public static function maxMonth(): string
    {
        return self::maxDate()->format('Y-m');
    }

    /**
     * Validation rules for a Y-m-d date inside the calendar window.
     *
     * @return list<string>
     */
    public static function dateRules(): array
    {
        return ['after_or_equal:'.self::minDateString(), 'before_or_equal:'.self::maxDateString()];
    }

    /**
     * Validation rules for a Y-m month inside the calendar window.
     *
     * @return list<string>
     */
    public static function monthRules(): array
    {
        return ['after_or_equal:'.self::minMonth(), 'before_or_equal:'.self::maxMonth()];
    }

    public static function clampMonth(CarbonImmutable $month): CarbonImmutable
    {
        $min = self::minDate()->startOfMonth();
        $max = self::maxDate()->startOfMonth();

        return $month->lessThan($min) ? $min : ($month->greaterThan($max) ? $max : $month);
    }
}
