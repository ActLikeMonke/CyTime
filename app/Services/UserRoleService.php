<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserRoleService
{
    public function changeRole(User $actor, User $target, string $role): void
    {
        Gate::forUser($actor)->authorize('changeRole', $target);

        if (! in_array($role, [User::ROLE_EMPLOYEE, User::ROLE_SUPERVISOR], true)) {
            throw ValidationException::withMessages(['role' => 'Choose a valid role.']);
        }

        DB::transaction(function () use ($actor, $target, $role): void {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($lockedTarget->role === $role) {
                return;
            }

            if (
                $lockedTarget->deactivated_at === null
                && $lockedTarget->isSupervisor()
                && $role === User::ROLE_EMPLOYEE
            ) {
                $supervisors = User::query()
                    ->where('role', User::ROLE_SUPERVISOR)
                    ->whereNull('deactivated_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if (count($supervisors) <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'The last supervisor cannot be demoted.',
                    ]);
                }
            }

            $oldRole = $lockedTarget->role;
            $lockedTarget->role = $role;
            $lockedTarget->save();

            DB::table('audit_logs')->insert([
                'user_id' => $actor->id,
                'auditable_type' => User::class,
                'auditable_id' => $lockedTarget->id,
                'old_values' => json_encode(['role' => $oldRole], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['role' => $role], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }
}
