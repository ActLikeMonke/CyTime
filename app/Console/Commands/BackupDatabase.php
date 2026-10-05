<?php

namespace App\Console\Commands;

use App\Services\SqliteDatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Create a consistent private backup of the SQLite database';

    public function handle(SqliteDatabaseBackupService $backups): int
    {
        $path = $backups->create();
        $this->info('SQLite backup created: '.basename($path));

        return self::SUCCESS;
    }
}
