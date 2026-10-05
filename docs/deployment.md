# Production deployment

This is a provider-neutral deployment checklist for CyTime. The hosting provider, TLS termination, and off-site backup destination remain deployment decisions; validate the steps against the selected platform before going live.

## Runtime and build

- Use PHP 8.3 or newer with `pdo_sqlite` and the Laravel-required PHP extensions, Composer 2, Node.js 22, and npm.
- Deploy the application with a production web server whose document root is the repository's `public/` directory. Do not expose the repository root, `.env`, SQLite database, logs, or private storage through the web server.
- Build frontend assets with `npm ci` and `npm run build`.
- Install production PHP dependencies with `composer install --no-dev --optimize-autoloader`.
- Configure the web server to send requests through `public/index.php`, enable TLS, redirect HTTP to HTTPS, and enable HSTS after HTTPS has been verified end to end.

## Environment and first initialization

Create `.env` outside source control and set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-cytime-host.example
# Generate once with php artisan key:generate and preserve across deployments.
APP_KEY=
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/private/path/cytime.sqlite
SESSION_SECURE_COOKIE=true
SEED_SUPERVISOR_EMAIL=supervisor@your-company.example
SEED_SUPERVISOR_PASSWORD= # strong, unique secret; do not use a sample password
MAIL_MAILER= # approved production mail transport
MAIL_FROM_ADDRESS= # approved company address
```

Use the actual company domain and an approved mail provider. Keep `.env`, `APP_KEY`, supervisor credentials, and mail credentials out of source control and logs. Store the SQLite file and backup destination outside `public/`; grant write access only to the application service account. The `storage/` and `bootstrap/cache/` directories must also be writable by that account.

For a new installation, create the private SQLite file with restricted filesystem permissions, then run:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

The production seeder creates the first supervisor from the two `SEED_SUPERVISOR_*` variables and does not create sample employees. Back up `.env` secrets through the deployment platform's secret-management process; never include them in the database backup.

## Scheduled work and queues

Run Laravel's scheduler every minute using the hosting platform's scheduler or a cron entry:

```cron
* * * * * cd /path/to/cytime && php artisan schedule:run
```

This runs planned-entry finalization, the daily private SQLite backup, and expired-account anonymization. Check `php artisan schedule:list` after deployment and monitor scheduler output through the host's standard service monitoring.

If queued jobs are introduced or enabled for the deployment, run `php artisan queue:work` under the platform's process manager and configure restart behavior for deploys. Do not run a development queue listener in production.

## Backups and operational checks

Set `DB_BACKUP_PATH` to a private writable local directory and choose a separate-volume or approved off-site destination for production copies. The built-in backup command writes a local SQLite snapshot; it is not by itself a disaster-recovery strategy. Follow [the backup guide](backups.md), protect copies with the same access controls as the production database, and rehearse restoring a copy.

After deployment, verify the `/up` health endpoint, sign in with the initial supervisor, confirm employee and supervisor access, test password-reset delivery, and check that scheduled commands run. Apply later schema changes with `php artisan migrate --force`; do not run destructive reset or refresh commands against production data.
