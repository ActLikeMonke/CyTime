<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function create(User $user, User $owner): bool
    {
        return $user->is($owner);
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        return $user->is($entry->user);
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        return $user->is($entry->user);
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $user->is($entry->user);
    }
}
