<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SupervisorSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.supervisor_email');
        $password = config('seeding.supervisor_password');

        $weakProductionPassword = app()->isProduction()
            && (! is_string($password)
                || strlen($password) < 12
                || preg_match('/[a-z]/', $password) !== 1
                || preg_match('/[A-Z]/', $password) !== 1
                || preg_match('/[0-9]/', $password) !== 1
                || preg_match('/[^A-Za-z0-9]/', $password) !== 1);

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || $password === '' || $weakProductionPassword) {
            throw new RuntimeException('Set valid SEED_SUPERVISOR_EMAIL and strong SEED_SUPERVISOR_PASSWORD values before seeding.');
        }

        $supervisor = User::firstOrNew(['email' => mb_strtolower($email)]);
        $previousRole = $supervisor->exists ? $supervisor->role : null;

        if (! $supervisor->exists) {
            $supervisor->fill([
                'first_name' => 'CyTime',
                'last_name' => 'Supervisor',
                'birth_date' => '1980-01-01',
                'role' => User::ROLE_SUPERVISOR,
                'annual_leave_days' => 30,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ]);
        } else {
            $supervisor->role = User::ROLE_SUPERVISOR;
        }

        $supervisor->save();

        if ($previousRole !== User::ROLE_SUPERVISOR) {
            DB::table('audit_logs')->insert([
                'user_id' => $supervisor->id,
                'auditable_type' => User::class,
                'auditable_id' => $supervisor->id,
                'old_values' => $previousRole === null ? null : json_encode(['role' => $previousRole], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['role' => User::ROLE_SUPERVISOR], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }

        $effectiveDate = now()->startOfYear()->toDateString();

        if (! $supervisor->workSchedules()->whereDate('valid_from', $effectiveDate)->exists()) {
            $schedule = $supervisor->workSchedules()->create([
                'weekly_hours' => 40,
                'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'valid_from' => $effectiveDate,
            ]);

            DB::table('audit_logs')->insert([
                'user_id' => $supervisor->id,
                'auditable_type' => WorkSchedule::class,
                'auditable_id' => $schedule->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'weekly_hours' => 40,
                    'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'valid_from' => $effectiveDate,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }
    }
}
