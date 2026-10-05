<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSupervisor();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSupervisor() || $user->is($target);
    }

    public function changeRole(User $user, User $target): bool
    {
        return $user->isSupervisor();
    }

    public function updateSchedule(User $user, User $target): bool
    {
        return $user->isSupervisor() || ($user->isEmployee() && $user->is($target));
    }

    public function updateLeaveEntitlement(User $user, User $target): bool
    {
        return $user->isSupervisor();
    }
}
