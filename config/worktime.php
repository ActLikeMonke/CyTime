<?php

return [
    'adult' => [
        'daily_regular_minutes' => 480,
        'daily_absolute_minutes' => 600,
        'break_after_minutes' => 360,
        'break_after_minutes_required' => 30,
        'long_day_after_minutes' => 540,
        'long_day_break_minutes_required' => 45,
        'minimum_rest_minutes' => 660,
        'warn_sunday_work' => true,
        'warn_holiday_work' => true,
    ],
    'minor' => [
        'adult_age' => 18,
        'maximum_daily_minutes' => 480,
        'maximum_weekly_minutes' => 2400,
        'maximum_workdays_per_week' => 5,
        'break_after_minutes' => 270,
        'long_break_after_minutes' => 360,
        'break_minutes_required' => 30,
        'long_break_minutes_required' => 60,
        'maximum_continuous_minutes' => 270,
        'minimum_rest_minutes' => 720,
        'night_work_start' => '20:00',
        'night_work_end' => '06:00',
        'vocational_school_day_credit_minutes' => 480,
    ],
    'supervisor_recent_violation_days' => 7,
];
