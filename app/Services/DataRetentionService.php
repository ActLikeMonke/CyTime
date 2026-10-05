<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DataRetentionService
{
    public function anonymizeExpiredAccounts(): int
    {
        $retentionYears = (int) config('data_retention.account_anonymization_years', 2);

        if ($retentionYears < 2) {
            throw new InvalidArgumentException('Account anonymization retention must be at least two years.');
        }

        $cutoff = now()->subYears($retentionYears);
        $anonymized = 0;

        User::query()
            ->whereNotNull('deactivated_at')
            ->whereNull('personal_data_anonymized_at')
            ->where('deactivated_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$anonymized): void {
                foreach ($users as $user) {
                    DB::transaction(function () use ($user, &$anonymized): void {
                        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

                        if ($lockedUser === null || $lockedUser->personal_data_anonymized_at !== null) {
                            return;
                        }

                        $oldEmail = $lockedUser->email;
                        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
                        DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
                        DB::table('passkeys')->where('user_id', $lockedUser->id)->delete();

                        $lockedUser->forceFill([
                            'first_name' => 'Former',
                            'last_name' => 'Employee',
                            'email' => "former-user-{$lockedUser->id}@invalid.cytime",
                            'email_verified_at' => null,
                            'password' => Hash::make(Str::random(64)),
                            'birth_date' => null,
                            'privacy_notice_acknowledged_at' => null,
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                            'remember_token' => null,
                            'personal_data_anonymized_at' => now(),
                        ])->save();

                        $anonymized++;
                    });
                }
            });

        return $anonymized;
    }
}
