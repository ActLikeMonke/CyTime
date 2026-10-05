<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSupervisor();
    }

    public function view(User $user, Absence $absence): bool
    {
        return $user->isSupervisor() || $user->is($absence->user);
    }

    public function create(User $user, User $owner): bool
    {
        return $user->isEmployee() && $user->is($owner);
    }

    public function delete(User $user, Absence $absence): bool
    {
        return $user->is($absence->user) && $absence->status === Absence::STATUS_PENDING;
    }

    public function review(User $user, Absence $absence): bool
    {
        return $user->isSupervisor() && $absence->status === Absence::STATUS_PENDING;
    }
}
