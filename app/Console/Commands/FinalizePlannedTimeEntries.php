<?php

namespace App\Console\Commands;

use App\Services\TimeEntryService;
use Illuminate\Console\Command;

class FinalizePlannedTimeEntries extends Command
{
    protected $signature = 'time-entries:finalize-planned';

    protected $description = 'Finalize all planned time entries whose recorded end date and time have passed';

    public function handle(TimeEntryService $timeEntries): int
    {
        $count = $timeEntries->finalizePlannedEntries();
        $this->info("Finalized {$count} planned time entries.");

        return self::SUCCESS;
    }
}
