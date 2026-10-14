email-verification config → config/fortify.php
user auth model → app/Models/User.php
email verification regression test → tests/Feature/Auth/EmailVerificationDisabledTest.php

- Project file map → `loaded/docs/agend/CODEMAP.md`.
- Project status → `docs/agent/STATUS.md`.
- Detailed backlog → `loaded/docs/Backlog.md`.
- Project setup and documentation links → `README.md`.
- Plain CSS migration inventory → `loaded/docs/css-migration-inventory.md`.
- Account deactivation → `app/Services/AccountDeactivationService.php`.
- Data retention and anonymization → `app/Services/DataRetentionService.php`.
- Retention command → `app/Console/Commands/AnonymizeExpiredAccounts.php`.
- Retention configuration → `config/data_retention.php`.
- SQLite backup service → `app/Services/SqliteDatabaseBackupService.php`.
- Database backup command → `app/Console/Commands/BackupDatabase.php`.
- Backup configuration → `config/backups.php`.
- User data export service → `app/Services/UserDataExportService.php`.
- User data export controller → `app/Http/Controllers/UserDataExportController.php`.
- Active-account protection → `app/Http/Middleware/EnsureUserIsActive.php`.
- Account deactivation metadata → `database/migrations/2026_10_05_000000_add_deactivation_metadata_to_users_table.php`.
- Retention tests → `tests/Feature/DataRetentionTest.php`.
- Backup tests → `tests/Feature/DatabaseBackupTest.php`.
- Export tests → `tests/Feature/UserDataExportTest.php`.
- Data retention policy → `docs/data-retention.md`.
- Backup policy → `docs/backups.md`.
- Deployment guide → `docs/deployment.md`.
- Employee guide → `docs/employee-guide.md`.
- Supervisor guide → `docs/supervisor-guide.md`.
- Shared work-period form behavior and defaults → `app/Livewire/WorkPeriodComponent.php`.
- Multiple-period form fields → `resources/views/components/ui/work-period-fields.blade.php`.
- Split-day regression tests → `tests/Feature/SplitTimeEntryTest.php`.
- Authenticated overdue-plan reconciliation → `app/Http/Middleware/FinalizeElapsedTimeEntries.php`.
- Supervisor dashboard and consolidated account/leave overview → `resources/views/pages/supervisor/⚡dashboard.blade.php`.
- Employee settings, schedule history and all absence dates → `resources/views/pages/supervisor/⚡user-detail.blade.php`.
- Employee access regression tests → `tests/Feature/EmployeeAccessTest.php`.
