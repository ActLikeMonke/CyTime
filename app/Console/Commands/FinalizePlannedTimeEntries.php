<?php

namespace App\Console\Commands;

use App\Services\TimeEntryService;
use Illuminate\Console\Command;

class FinalizePlannedTimeEntries extends Command
{
    protected $signature = 'time-entries:finalize-planned';

    protected $description = 'Finalize today’s planned time entries whose end time has passed';

    public function handle(TimeEntryService $timeEntries): int
    {
        $count = $timeEntries->finalizePlannedEntries();
        $this->info("Finalized {$count} planned time entries.");

        return self::SUCCESS;
    }
}
