# Database backups

CyTime creates a consistent SQLite snapshot daily at 01:00 (Europe/Berlin) with Laravel's scheduler. The `database:backup` command uses SQLite `VACUUM INTO` so the output is a valid snapshot even while the application is running.

Backups are stored by default in `storage/app/private/backups`, outside the public web root. Set `DB_BACKUP_PATH` to another private, writable local directory when configuring the deployment. Do not point it to `public/` or a public filesystem disk: backups contain employee and official time-record data.

The scheduled command requires a file-backed SQLite database and fails explicitly for in-memory databases or other database engines. Configure the host to run Laravel's scheduler continuously. Production deployments must also decide an approved off-site or separate-volume backup destination and access policy; a local backup on the same host is not disaster recovery.
