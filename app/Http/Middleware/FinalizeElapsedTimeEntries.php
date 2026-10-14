<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TimeEntryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinalizeElapsedTimeEntries
{
    public function __construct(private readonly TimeEntryService $timeEntries) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->deactivated_at === null) {
            $this->timeEntries->finalizePlannedEntries(
                $user->role === User::ROLE_SUPERVISOR ? null : $user,
            );
        }

        return $next($request);
    }
}
