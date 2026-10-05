# Code map

## Project setup
- `README.md` → local setup, development commands, stack, and branch strategy.
- `.env.example` → application, SQLite, and local seeder environment defaults.
- `composer.json` / `composer.lock` → PHP dependencies, Laravel scripts, Blade Heroicons, Pest, and Pint.
- `package.json` / `package-lock.json` → Vite and frontend build dependencies; no Tailwind or Flux package.
- `vite.config.js`, `resources/css/app.css`, `resources/js/app.js` → Vite entry points and plain-CSS/JavaScript imports.
- `resources/css/tokens.css`, `reset.css`, `base.css`, `layout.css`, `utilities.css` → palette and semantic tokens, global styles, app layout, and limited helpers in cascade layers.
- `resources/css/components/components.css`, `resources/css/components/pages.css` → shared UI component styles and page-specific/responsive styles.
- `resources/js/ui.js` → appearance, sidebar, dropdown, modal, password, and toast interactions.
- `resources/views/components/app-page-actions.blade.php` → shared page-level navigation and settings icon controls, replacing the authenticated top bar.
- `artisan` → Laravel CLI entry point.
- `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php` → test runner configuration and base test cases.
- `pint.json` → Laravel Pint formatting configuration.
- `phpstan.neon` → PHPStan / Larastan analysis configuration.
- `.editorconfig`, `.gitattributes`, `.gitignore`, `.npmrc` → editor, Git, and npm defaults.
- `.github/workflows/tests.yml`, `.github/dependabot.yml` → CI and dependency update configuration.

## Application
- `app/Actions/Fortify/CreateNewUser.php` → validates registration, creates the user and initial schedule, calculates leave entitlement, and records audit entries.
- `app/Actions/Fortify/ResetUserPassword.php` → password reset action.
- `app/Http/Middleware/EnsureUserHasRole.php` → restrict routes to authenticated users with configured roles.
- `app/Policies/UserPolicy.php` → restrict user listing and profile visibility by role and ownership, including leave-entitlement updates.
- `app/Policies/AbsencePolicy.php` → enforce employee ownership and supervisor-only absence review.
- `app/Concerns/PasswordValidationRules.php` → shared password validation.
- `app/Concerns/ProfileValidationRules.php` → shared name and email validation.
- `app/Http/Controllers/Controller.php` → base HTTP controller.
- `app/Livewire/Actions/Logout.php` → Livewire logout action.
- `app/Models/User.php` → user identity, role, leave allowance, absence and schedule relationships, and authentication behavior.
- `app/Models/Absence.php` → soft-deletable sick/vacation requests, review metadata, and relationships.
- `app/Models/WorkSchedule.php` → effective-dated work schedule records and user relationship.
- `app/Models/TimeEntry.php` → soft-deletable work entries and net-minute calculation.
- `app/Policies/TimeEntryPolicy.php` → owner-only time-entry access.
- `app/Services/UserRoleService.php` → audited role changes and last-supervisor protection.
- `app/Services/WorkScheduleService.php` → validated, authorized, historical, audited work schedule changes.
- `app/Services/TimeEntryService.php` → validated manual entries, corrections, clock controls, finalization, and audit history.
- `app/Services/WorkTimeRuleService.php` → config-driven adult/minor working-time warnings, school credit, recent violations, and rule evaluation.
- `app/Services/WeeklyBalanceService.php` → schedule-effective weekly targets, elapsed-workday shortfall, approved vacation, immediate sick-day deductions, holidays, and cumulative balances.
- `app/Services/AbsenceService.php` → validated, authorized, audited absence requests, cancellation, and supervisor review.
- `app/Services/LeaveBalanceService.php` → schedule- and NRW-holiday-aware leave usage, remaining days, and one-year carry-over.
- `app/Services/LeaveEntitlementService.php` → supervisor-authorized and audited leave allowance overrides.
- `app/Services/NrwHolidayService.php` → idempotent generation and on-demand year completion of statutory NRW holidays.
- `app/Services/SupervisorDashboardService.php` → employee status overview, filtered/sorted summaries, monthly details, and monthly-hours export data.
- `app/Services/TimeCalendarService.php` → owner-scoped Monday-first monthly calendar data, on-demand NRW holidays, day-status precedence, work-entry details, and rule warnings.
- `app/Services/TimeOverviewService.php` → owner-scoped day, week, and month views of entries and absences.
- `app/Providers/AppServiceProvider.php` → application service provider and time-entry/absence policy registration.
- `app/Console/Commands/FinalizePlannedTimeEntries.php` → finalize elapsed planned time entries.
- `app/Console/Commands/GenerateNrwHolidays.php` → generate NRW public holidays with `holidays:generate {year}`.
- `app/Providers/FortifyServiceProvider.php` → Fortify authentication setup.
- `bootstrap/app.php` → application bootstrap and role middleware alias.
- `bootstrap/providers.php` → application service providers.

## Configuration and data
- `config/app.php` → application defaults.
- `config/auth.php` → authentication guards and providers.
- `config/cache.php`, `config/filesystems.php`, `config/logging.php`, `config/mail.php`, `config/queue.php`, `config/services.php`, `config/session.php` → Laravel service configuration.
- `config/database.php` → SQLite default connection and database connections.
- `config/fortify.php` → authentication features.
- `config/seeding.php` → supervisor and local test-user seed settings.
- `config/time_tracking.php` → seven-day time-entry correction window.
- `config/worktime.php` → configurable ArbZG/JArbSchG limits, age of majority, vocational-school credit, and supervisor warning window.
- `database/factories/UserFactory.php` → synthetic user factory.
- `database/migrations/0001_01_01_000000_create_users_table.php` → users and authentication tables.
- `database/migrations/0001_01_01_000001_create_cache_table.php` → cache tables.
- `database/migrations/0001_01_01_000002_create_jobs_table.php` → queue tables.
- `database/migrations/2024_01_01_000000_create_passkeys_table.php` → passkey credentials.
- `database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php` → two-factor authentication fields.
- `database/migrations/2026_09_29_000000_create_cytime_tables.php` → schedules, time entries, absences, audit logs, and NRW holiday table.
- `database/migrations/2026_09_29_000001_add_privacy_notice_acknowledged_at_to_users_table.php` → persisted timestamp for registration privacy-notice acknowledgment.
- `database/migrations/2026_09_29_000002_allow_open_time_entries.php` → nullable clock-out time for in-progress entries.
- `database/migrations/2026_09_29_000003_add_absence_review_comment.php` → nullable vacation-review comment field; sick reports cannot store comments.
- `database/seeders/DatabaseSeeder.php` → invokes supervisor seeder and creates local synthetic employees without overwriting existing records.
- `database/seeders/SupervisorSeeder.php` → environment-configured first supervisor account and initial schedule.
- `database/.gitignore` → local database artifact exclusions.

## Routes and views
- `routes/web.php`, `routes/settings.php`, `routes/console.php` → web, settings, and console routes, including employee time/calendar/absence pages, supervisor review, and planned-entry finalization.
- `resources/views/welcome.blade.php`, `resources/views/dashboard.blade.php` → public landing and authenticated dashboard.
- `resources/views/pages/auth/` → login, registration, password, email verification, and two-factor screens.
- `resources/views/pages/settings/` → profile, appearance, account deletion, security, and two-factor settings.
- `resources/views/pages/supervisor/` → supervisor-only employee role/schedule/leave management and absence review.
- `resources/views/pages/supervisor/⚡dashboard.blade.php` → sortable/filterable employee status overview and monthly-hours CSV export.
- `resources/views/pages/supervisor/⚡user-detail.blade.php` → one employee’s monthly time entries, absences, and weekly balances.
- `resources/views/pages/absences/⚡index.blade.php` → employee sick reports, vacation requests, request statuses, and leave balance.
- `resources/views/pages/supervisor/⚡absences.blade.php` → pending absence approval/rejection queue.
- `resources/views/pages/time/⚡index.blade.php` → employee time entry, correction, clock controls, balance, period overview, and confirmable work-rule warnings.
- `resources/views/pages/time/⚡calendar.blade.php` → employee monthly calendar with day details, entry create/edit, rule-warning confirmation, and single/range absence requests; preserves the open action dialog while Livewire updates its content.
- `resources/views/pages/supervisor/⚡users.blade.php` → employee access, schedules, leave balances/entitlement overrides, and recent working-time rule warnings.
- `resources/views/layouts/`, `resources/views/partials/` → application/auth layouts and shared page partials.
- `resources/views/components/` → reusable branding, auth, passkey, and shared interface components.
- `resources/views/components/ui/*.blade.php` → reusable `x-ui.*` controls, form fields, dialogs, dropdowns, status messaging, icons, and layout primitives.
- `resources/js/passkeys.js` → browser passkey integration.
- `public/index.php`, `public/.htaccess`, `public/robots.txt`, `public/favicon.*`, `public/apple-touch-icon.png` → public web entry and static assets.

## Tests and storage
- `tests/Feature/Auth/AuthenticationTest.php` → login/logout, generic credential failures, failed-login rate limiting, and two-factor authentication coverage.
- `tests/Feature/Auth/EmailVerificationTest.php`, `PasswordConfirmationTest.php`, `PasswordResetTest.php`, `RegistrationTest.php`, `TwoFactorChallengeTest.php` → email verification, password flows, registration, and two-factor feature coverage.
- `tests/Feature/DashboardTest.php`, `ExampleTest.php` → dashboard and smoke coverage.
- `tests/Feature/Settings/ProfileUpdateTest.php`, `SecurityTest.php` → account settings coverage.
- `tests/Feature/UiComponentsTest.php` → shared UI component rendering, variants, attribute forwarding, labels, and errors.
- `tests/Feature/SetupTest.php` → seeded account, schedules, and soft-delete schema coverage.
- `tests/Feature/Authorization/RoleAuthorizationTest.php` → role policy and supervisor-route authorization coverage.
- `tests/Feature/Authorization/UserRoleManagementTest.php` → audited role changes, last-supervisor protection, and management-page authorization.
- `tests/Feature/ScheduleManagementTest.php` → schedule history, authorization, validation, auditing, and employee/supervisor UI coverage.
- `tests/Feature/Seeder/SupervisorSeederTest.php` → environment credential use, idempotency, and required configuration.
- `tests/Feature/TimeEntryTest.php` → entry validation, corrections, ownership, audit, clock controls, and Livewire time-entry behavior.
- `tests/Feature/WeeklyBalanceTest.php` → workday targets, holiday and absence deductions, schedule changes, and cumulative balance.
- `tests/Feature/TimeOverviewTest.php` → overview periods, absence listing, ownership isolation, and authentication.
- `tests/Feature/TimeCalendarTest.php` → month grid and ownership, color precedence, details/warnings, permitted actions, time edits, and single/range absence submissions.
- `tests/Feature/FinalizePlannedTimeEntriesTest.php` → scheduled transition from planned to final and its audit record.
- `tests/Feature/WorkTimeRuleTest.php` → rule-warning confirmation, entry warning badges, and supervisor violation visibility.
- `tests/Feature/AbsenceManagementTest.php` → sick/vacation workflows, review authorization, privacy constraints, leave balances, carry-over, and audited overrides.
- `tests/Feature/NrwHolidayTest.php` → NRW holiday dates, idempotent generation, and command validation.
- `tests/Feature/SupervisorDashboardTest.php` → supervisor overview filtering/sorting, employee detail authorization/data, and monthly CSV export.
- `tests/Unit/WorkTimeRuleServiceTest.php` → adult/minor rule boundaries, schedule credit, and eighteenth-birthday transition.
- `tests/Unit/ExampleTest.php` → unit-test smoke coverage.
- `storage/` → application cache, session, log, and uploaded-file directories.

## Documentation
- `loaded/docs/Backlog.md` → user stories, decisions, and phase status.
- `loaded/docs/css-migration-inventory.md` → recovered Flux/Tailwind inventory and light/dark visual-reference coverage for the CSS migration.
- `loaded/docs/data-model.md` → CyTime ER diagram and schema notes.
- `loaded/docs/agend/STATUS.md` → current phase and next work.
- `loaded/docs/agend/CODEMAP.md` → project file map.
- `docs/agent/STATUS.md` → concise project completion status.
- `docs/agent/CODEMAP.md` → pointers to key deliverables and new files.
- `docs/backups.md` → database backup policy.
- `docs/data-retention.md` → data retention policy.
- `docs/deployment.md` → production deployment checklist and security configuration.
- `docs/employee-guide.md` → employee workflow guide.
- `docs/supervisor-guide.md` → supervisor workflow guide.

## Data protection and exports
- `app/Services/AccountDeactivationService.php` → account deactivation, authentication revocation, and last-supervisor protection.
- `app/Services/DataRetentionService.php` → scheduled anonymization of expired deactivated accounts while preserving official records.
- `app/Services/SqliteDatabaseBackupService.php` → safe SQLite snapshot creation and backup destination validation.
- `app/Services/UserDataExportService.php` → owner-scoped CSV and JSON exports, including official records and audit history.
- `app/Http/Controllers/UserDataExportController.php` → authenticated user export download endpoints.
- `app/Http/Middleware/EnsureUserIsActive.php` → blocks deactivated accounts from authenticated application routes.
- `app/Console/Commands/AnonymizeExpiredAccounts.php` → scheduled retention command.
- `app/Console/Commands/BackupDatabase.php` → scheduled SQLite backup command.
- `config/data_retention.php` → retention duration setting.
- `config/backups.php` → private backup destination settings.
- `database/migrations/2026_10_05_000000_add_deactivation_metadata_to_users_table.php` → account deactivation and anonymization timestamps.
- `tests/Feature/DataRetentionTest.php` → account retention and anonymization coverage.
- `tests/Feature/DatabaseBackupTest.php` → SQLite backup creation and integrity coverage.
- `tests/Feature/UserDataExportTest.php` → self-scoped export coverage.