<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'weekly_hours' => ['required', 'numeric', 'gt:0', 'lte:40'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', 'string', 'distinct', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri'])],
            'privacy_notice_acknowledged' => ['required', 'accepted'],
            'password' => ['required', 'string', Password::default()->letters()->numbers(), 'confirmed'],
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'birth_date' => $validated['birth_date'],
                'privacy_notice_acknowledged_at' => now(),
                'role' => 'employee',
                'annual_leave_days' => (int) ceil(30 * count($validated['work_days']) / 5),
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $scheduleId = DB::table('work_schedules')->insertGetId([
                'user_id' => $user->id,
                'weekly_hours' => $validated['weekly_hours'],
                'work_days' => json_encode(array_values($validated['work_days']), JSON_THROW_ON_ERROR),
                'valid_from' => today()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $createdAt = now();
            DB::table('audit_logs')->insert([
                [
                    'user_id' => $user->id,
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'old_values' => null,
                    'new_values' => json_encode(['role' => $user->role], JSON_THROW_ON_ERROR),
                    'created_at' => $createdAt,
                ],
                [
                    'user_id' => $user->id,
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'old_values' => null,
                    'new_values' => json_encode(['annual_leave_days' => $user->annual_leave_days], JSON_THROW_ON_ERROR),
                    'created_at' => $createdAt,
                ],
                [
                    'user_id' => $user->id,
                    'auditable_type' => 'App\\Models\\WorkSchedule',
                    'auditable_id' => $scheduleId,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'weekly_hours' => $validated['weekly_hours'],
                        'work_days' => array_values($validated['work_days']),
                        'valid_from' => today()->toDateString(),
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $createdAt,
                ],
            ]);

            return $user;
        });
    }
}
