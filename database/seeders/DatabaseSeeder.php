<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SupervisorSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $employees = [
            [
                'email' => 'alex.employee@cytime.test',
                'first_name' => 'Alex',
                'last_name' => 'Employee',
                'birth_date' => '1990-04-12',
                'annual_leave_days' => 30,
                'weekly_hours' => 40,
                'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            ],
            [
                'email' => 'sam.parttime@cytime.test',
                'first_name' => 'Sam',
                'last_name' => 'Parttime',
                'birth_date' => '1995-09-23',
                'annual_leave_days' => 18,
                'weekly_hours' => 24,
                'work_days' => ['mon', 'tue', 'wed'],
            ],
            [
                'email' => 'jordan.employee@cytime.test',
                'first_name' => 'Jordan',
                'last_name' => 'Employee',
                'birth_date' => '1988-02-05',
                'annual_leave_days' => 24,
                'weekly_hours' => 32,
                'work_days' => ['mon', 'tue', 'wed', 'thu'],
            ],
        ];

        foreach ($employees as $employee) {
            $user = User::firstOrCreate(
                ['email' => $employee['email']],
                [
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'birth_date' => $employee['birth_date'],
                    'role' => User::ROLE_EMPLOYEE,
                    'annual_leave_days' => $employee['annual_leave_days'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(config('seeding.test_employee_password')),
                ],
            );

            if ($user->wasRecentlyCreated) {
                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'role' => User::ROLE_EMPLOYEE,
                        'annual_leave_days' => $employee['annual_leave_days'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }

            $effectiveDate = now()->startOfYear()->toDateString();

            if (! $user->workSchedules()->whereDate('valid_from', $effectiveDate)->exists()) {
                $schedule = $user->workSchedules()->create([
                    'weekly_hours' => $employee['weekly_hours'],
                    'work_days' => $employee['work_days'],
                    'valid_from' => $effectiveDate,
                ]);

                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'auditable_type' => WorkSchedule::class,
                    'auditable_id' => $schedule->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'weekly_hours' => $employee['weekly_hours'],
                        'work_days' => $employee['work_days'],
                        'valid_from' => $effectiveDate,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
