<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_command_creates_a_valid_snapshot_of_a_file_backed_sqlite_database(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'cytime-db-');
        $backupDirectory = $sourcePath.'-backups';
        $originalDatabase = config('database.connections.sqlite.database');

        try {
            config([
                'database.connections.sqlite.database' => $sourcePath,
                'backups.path' => $backupDirectory,
            ]);
            DB::purge('sqlite');

            DB::connection('sqlite')->getPdo()->exec(
                "CREATE TABLE backup_probe (value TEXT NOT NULL); INSERT INTO backup_probe VALUES ('snapshot-ok');"
            );

            $this->artisan('database:backup')->assertSuccessful();

            $backups = glob($backupDirectory.DIRECTORY_SEPARATOR.'cytime-*.sqlite');
            $this->assertIsArray($backups);
            $this->assertCount(1, $backups);
            $backup = new PDO('sqlite:'.$backups[0]);
            $this->assertSame('snapshot-ok', $backup->query('SELECT value FROM backup_probe')->fetchColumn());
            $this->assertSame('ok', $backup->query('PRAGMA integrity_check')->fetchColumn());
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $originalDatabase]);
            DB::purge('sqlite');
            File::delete($sourcePath);
            File::deleteDirectory($backupDirectory);
        }
    }
}
