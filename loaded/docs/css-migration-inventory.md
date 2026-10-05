# Plain CSS migration inventory and visual references

The pre-migration inventory was recovered from commit `5fba25a` and reviewed alongside the migrated app. A matched set of 84 screenshots (21 page states, pre/post migration, light/dark) is stored outside the repository in the session's `files/ui-reference/desktop-pairs` directory; it is intentionally not committed.

## Flux components in use before migration

Counts are occurrences in `resources/views`:

| Component | Count | Component | Count |
| --- | ---: | --- | ---: |
| `flux:avatar` | 2 | `flux:badge` | 2 |
| `flux:brand` | 1 | `flux:button` | 67 |
| `flux:callout` | 2 | `flux:checkbox` | 7 |
| `flux:dropdown` | 2 | `flux:header` | 2 |
| `flux:heading` | 56 | `flux:icon.check` | 1 |
| `flux:icon.chevron-down` | 1 | `flux:icon.chevron-right` | 1 |
| `flux:icon.document-duplicate` | 1 | `flux:icon.key` | 2 |
| `flux:icon.loading` | 2 | `flux:icon.lock-closed` | 1 |
| `flux:icon.qr-code` | 1 | `flux:input` | 42 |
| `flux:link` | 5 | `flux:main` | 1 |
| `flux:menu` | 2 | `flux:menu.item` | 4 |
| `flux:menu.radio.group` | 3 | `flux:menu.separator` | 3 |
| `flux:modal` | 4 | `flux:modal.close` | 2 |
| `flux:modal.trigger` | 2 | `flux:navbar` | 2 |
| `flux:navbar.item` | 4 | `flux:navlist` | 1 |
| `flux:navlist.item` | 3 | `flux:otp` | 2 |
| `flux:profile` | 1 | `flux:radio` | 3 |
| `flux:radio.group` | 1 | `flux:select` | 8 |
| `flux:separator` | 2 | `flux:sidebar` | 2 |
| `flux:sidebar.brand` | 1 | `flux:sidebar.collapse` | 2 |
| `flux:sidebar.group` | 2 | `flux:sidebar.header` | 2 |
| `flux:sidebar.item` | 12 | `flux:sidebar.nav` | 4 |
| `flux:sidebar.profile` | 1 | `flux:sidebar.toggle` | 2 |
| `flux:spacer` | 4 | `flux:subheading` | 7 |
| `flux:text` | 60 | `flux:toast` | 5 |
| `flux:toast.group` | 5 | `flux:tooltip` | 3 |

## Views using Tailwind classes before migration

The following Blade files contained Tailwind utility classes:

- `resources/views/welcome.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/components/app-logo.blade.php`
- `resources/views/components/auth-header.blade.php`
- `resources/views/components/auth-session-status.blade.php`
- `resources/views/components/desktop-user-menu.blade.php`
- `resources/views/components/passkey-registration.blade.php`
- `resources/views/components/passkey-verify.blade.php`
- `resources/views/flux/navlist/group.blade.php`
- `resources/views/layouts/app/header.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/layouts/auth/card.blade.php`
- `resources/views/layouts/auth/simple.blade.php`
- `resources/views/layouts/auth/split.blade.php`
- `resources/views/pages/absences/⚡index.blade.php`
- `resources/views/pages/auth/confirm-password.blade.php`
- `resources/views/pages/auth/forgot-password.blade.php`
- `resources/views/pages/auth/login.blade.php`
- `resources/views/pages/auth/register.blade.php`
- `resources/views/pages/auth/reset-password.blade.php`
- `resources/views/pages/auth/two-factor-challenge.blade.php`
- `resources/views/pages/auth/verify-email.blade.php`
- `resources/views/pages/settings/layout.blade.php`
- `resources/views/pages/settings/⚡appearance.blade.php`
- `resources/views/pages/settings/⚡delete-user-form.blade.php`
- `resources/views/pages/settings/⚡delete-user-modal.blade.php`
- `resources/views/pages/settings/⚡profile.blade.php`
- `resources/views/pages/settings/⚡security.blade.php`
- `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php`
- `resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php`
- `resources/views/pages/supervisor/⚡absences.blade.php`
- `resources/views/pages/supervisor/⚡dashboard.blade.php`
- `resources/views/pages/supervisor/⚡user-detail.blade.php`
- `resources/views/pages/supervisor/⚡users.blade.php`
- `resources/views/pages/time/⚡calendar.blade.php`
- `resources/views/pages/time/⚡index.blade.php`

## Tests asserting on CSS classes

No tests asserting legacy Tailwind utility classes were found. The pre-migration calendar feature tests assert visible content and permitted actions rather than styling classes; those behavior assertions remain covered by `tests/Feature/TimeCalendarTest.php`.

## Visual comparison coverage

The screenshot set covers the public landing, login, registration, password reset request, and password reset screens; employee dashboard, time overview, calendar, absences, and profile/appearance/security settings; and supervisor dashboard, employee list/detail, absence approvals, and shared dashboard/settings states. Each state was captured before and after migration in light and dark mode at desktop viewport size. Screens were checked for retained content and available actions, not pixel-identical styling.
