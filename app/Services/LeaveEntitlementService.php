<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LeaveEntitlementService
{
    public function update(User $actor, User $target, int|string $days): User
    {
        Gate::forUser($actor)->authorize('updateLeaveEntitlement', $target);
        $validated = Validator::make(['days' => $days], [
            'days' => ['required', 'integer', 'min:0', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($actor, $target, $validated): User {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $oldValue = $lockedTarget->annual_leave_days;
            $lockedTarget->annual_leave_days = (int) $validated['days'];
            $lockedTarget->save();
            DB::table('audit_logs')->insert([
                'user_id' => $actor->id,
                'auditable_type' => User::class,
                'auditable_id' => $lockedTarget->id,
                'old_values' => json_encode(['annual_leave_days' => $oldValue], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['annual_leave_days' => $lockedTarget->annual_leave_days], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $lockedTarget;
        });
    }
}
