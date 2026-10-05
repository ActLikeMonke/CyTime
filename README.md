# CyTime

CyTime is the company's time-tracking system for working hours, sickness and vacation.

## Requirements

- PHP 8.3 or newer with `pdo_sqlite`
- Composer
- Node.js and npm

## Local setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
# Set SEED_SUPERVISOR_EMAIL and SEED_SUPERVISOR_PASSWORD in .env before seeding.
php artisan migrate --seed
npm run build
php artisan serve
```

Before running `php artisan migrate --seed`, set `SEED_SUPERVISOR_EMAIL` and `SEED_SUPERVISOR_PASSWORD` in `.env`. The dedicated supervisor seeder refuses to run if these are missing or invalid. In non-production environments, the main seeder also creates three synthetic employee accounts using `SEED_EMPLOYEE_PASSWORD` (default: `password`). Do not use sample credentials in a deployed environment.

For password reset email delivery, configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` in `.env`. Use a real SMTP or approved mail-provider configuration in production; the default `log` mailer writes reset links to the application log and is only suitable for local development.

## Development

- Run tests with `php artisan test`.
- Check formatting with `php vendor/bin/pint --test`.
- Format PHP with `php vendor/bin/pint`.
- The `main` branch is the integration branch. Use short-lived branches named `feature/<description>` for changes.

## User and operations guides

- [Employee guide](docs/employee-guide.md)
- [Supervisor guide](docs/supervisor-guide.md)
- [Production deployment](docs/deployment.md)
- [Data retention policy](docs/data-retention.md)
- [Database backups](docs/backups.md)

## Stack

- Laravel 13
- Livewire 4
- Plain CSS design system with Blade UI components
- SQLite
- Pest and Laravel Pint

## Project data model

See [the ER diagram and schema notes](loaded/docs/data-model.md).

## UI design system

The interface uses plain CSS and no utility-first framework. Shared design tokens live in `resources/css/tokens.css`; `resources/css/app.css` declares the cascade layer order (`reset`, `tokens`, `base`, `layout`, `components`, `utilities`) and imports the corresponding stylesheets. Shared component styles live in `resources/css/components/components.css`, and page-specific styles live in `resources/css/components/pages.css`. Components use semantic custom properties such as `--surface`, `--text`, `--border` and `--accent`; add new raw palette colors only to the token file.

Use BEM-light component names (for example, `.calendar-day--today` and `.app-sidebar__nav`). Keep layout helpers limited to `.visually-hidden`, `.stack`, `.cluster` and `.grid-auto`; avoid adding one-off utility classes.

Reusable Blade components are in `resources/views/components/ui/` and are called with the `x-ui.*` prefix:

- `button`, `field`, `input`, `password-input`, `select`, `checkbox`, `textarea`, `radio-group`, `radio`, and `otp`
- `modal`, `modal-trigger`, `modal-close`, `dropdown`, and `tooltip`
- `alert`, `callout`, `badge`, `card`, `avatar`, `heading`, `subheading`, `text`, `link`, `separator`, `icon`, and `toast-region`

When adding a component, prefer semantic markup, forward Alpine and Livewire attributes, use semantic design tokens, and include accessible labels, keyboard interaction, focus states, and validation feedback. Keep interactive behavior in `resources/js/ui.js` and component styles in `resources/css/components/`.

### Adding a UI component

1. Add a Blade component under `resources/views/components/ui/` and declare its public options with `@props`.
2. Merge component classes with `$attributes->class(...)` so caller-supplied classes and Livewire/Alpine attributes pass through.
3. Add shared styles to `resources/css/components/components.css` or view-specific styles to `resources/css/components/pages.css`, inside the `components` cascade layer. Use semantic tokens rather than raw palette values.
4. Add a render test in `tests/Feature/UiComponentsTest.php` for its variant, forwarded attributes, and validation/accessibility behavior as applicable.
