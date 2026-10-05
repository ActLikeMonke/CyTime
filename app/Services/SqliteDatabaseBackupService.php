<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SqliteDatabaseBackupService
{
    public function create(): string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Database backups currently support SQLite only.');
        }

        $sourcePath = $connection->getDatabaseName();
        if ($sourcePath === ':memory:' || ! is_file($sourcePath)) {
            throw new RuntimeException('A file-backed SQLite database is required for backups.');
        }

        $directory = (string) config('backups.path');
        if ($directory === '') {
            throw new RuntimeException('The database backup directory is not configured.');
        }

        File::ensureDirectoryExists($directory, 0700);
        $directory = realpath($directory);
        $publicDirectory = realpath(public_path());

        if ($directory === false || $publicDirectory === false) {
            throw new RuntimeException('The database backup directory could not be resolved.');
        }

        if (PHP_OS_FAMILY !== 'Windows' && ! chmod($directory, 0700)) {
            throw new RuntimeException('The database backup directory permissions could not be restricted.');
        }

        $normalizedDirectory = rtrim(strtolower($directory), DIRECTORY_SEPARATOR);
        $normalizedPublicDirectory = rtrim(strtolower($publicDirectory), DIRECTORY_SEPARATOR);
        if (
            $normalizedDirectory === $normalizedPublicDirectory
            || str_starts_with($normalizedDirectory, $normalizedPublicDirectory.DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Database backups must not be stored in the public directory.');
        }

        $destination = $directory.DIRECTORY_SEPARATOR
            .'cytime-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.sqlite';
        $pdo = $connection->getPdo();
        $quotedDestination = $pdo->quote($destination);

        if ($quotedDestination === false) {
            throw new RuntimeException('The database backup destination could not be quoted safely.');
        }

        try {
            $pdo->exec("VACUUM INTO {$quotedDestination}");
        } catch (Throwable $exception) {
            if (is_file($destination)) {
                File::delete($destination);
            }

            throw $exception;
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            File::delete($destination);
            throw new RuntimeException('SQLite did not create a valid database backup.');
        }

        if (PHP_OS_FAMILY !== 'Windows' && ! chmod($destination, 0600)) {
            File::delete($destination);
            throw new RuntimeException('The database backup file permissions could not be restricted.');
        }

        return $destination;
    }
}
