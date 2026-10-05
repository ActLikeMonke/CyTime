<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeactivationService
{
    public function deactivate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->deactivated_at !== null) {
                return;
            }

            if ($lockedUser->isSupervisor()) {
                $activeSupervisors = User::query()
                    ->where('role', User::ROLE_SUPERVISOR)
                    ->whereNull('deactivated_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($activeSupervisors->count() <= 1) {
                    throw ValidationException::withMessages([
                        'account' => 'The last active supervisor cannot deactivate their account.',
                    ]);
                }
            }

            $deactivatedAt = now();
            DB::table('password_reset_tokens')->where('email', $lockedUser->email)->delete();
            DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            DB::table('passkeys')->where('user_id', $lockedUser->id)->delete();

            $lockedUser->forceFill([
                'deactivated_at' => $deactivatedAt,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();

            DB::table('audit_logs')->insert([
                'user_id' => $lockedUser->id,
                'auditable_type' => User::class,
                'auditable_id' => $lockedUser->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'deactivated_at' => $deactivatedAt->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $deactivatedAt,
            ]);
        });
    }
}
