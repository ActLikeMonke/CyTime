<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class AnonymizeExpiredAccounts extends Command
{
    protected $signature = 'data-retention:anonymize-expired-accounts';

    protected $description = 'Anonymize deactivated accounts after their retention period expires';

    public function handle(DataRetentionService $retention): int
    {
        $count = $retention->anonymizeExpiredAccounts();
        $this->info("Anonymized {$count} expired accounts.");

        return self::SUCCESS;
    }
}
