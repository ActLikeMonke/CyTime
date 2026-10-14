# CyTime – Backlog

Official time tracking tool for the company (location: Aachen, NRW) with login/registration, working-time rule checks (ArbZG / JArbSchG), absence management with supervisor approval, and GDPR (DSGVO) compliance.

**Tech stack:** Laravel 13 + Livewire 4 · plain CSS and reusable Blade UI components · SQLite · PHP 8.3+
**Priority legend:** 🔴 Must · 🟡 Should · 🟢 Could
**Status legend:** `TODO` · `IN PROGRESS` · `DONE`

---

## Decisions

| ID | Decision |
|----|----------|
| D-01 | Stack: newest Laravel (13.x) + Livewire (4.x) + Tailwind CSS, SQLite database |
| D-02 | Pre-booking: only the end time of **today** can be booked in advance. Users may work less on a day, but must reach their **40 h in the current week** in total. |
| D-03 | Supervisors approve sick days and vacation and have an overview of all registered employees (leave, status, hour balance). |
| D-04 | CyTime is the **official time record** of the company → audit trail, retention periods and backups are mandatory. |
| D-05 | Public holidays: Germany, **NRW**. |
| D-06 | Rule violations are **only warned about**, never blocked. |
| D-07 | No integration with other systems (no SSO). |
| D-08 | Part-time is supported: employees enter their weekly hours when registering. |
| D-09 | Default leave entitlement is **30 days/year**; supervisors can change it per employee. |
| D-10 | The first supervisor is created by seeder; supervisors can promote other users to supervisor. |
| D-11 | A reported sick day counts as absent **immediately**, even while approval is pending. |
| D-12 | Hosting and backup location are decided later. |
| D-13 | Part-time can mean fewer hours **and/or** fewer workdays. Employees choose their workdays (Mon–Fri) at registration. |
| D-14 | Weekly hours and workdays can be changed after registration by the employee and by a supervisor. Changes apply from a start date; past weeks keep their old schedule. |
| D-15 | Leave days are reduced pro rata for fewer workdays: `30 × workdays / 5`, rounded up (e.g. 3 days → 18 leave days). Supervisors can still override. |
| D-16 | Calendar colours: holiday = **blue**, sick day = **red**, vacation = **purple**, booked time = **green**, nothing = **grey**. If several apply to one day, the colour follows the precedence holiday → sick → vacation → booked time. |

---

## Phase 0 – Setup

| ID | Task | Prio | Status |
|----|------|------|--------|
| S-01 | Tech stack decided (D-01) | 🔴 | DONE |
| S-02 | Create Git repository, README, branch strategy (`main` + feature branches) | 🔴 | DONE |
| S-03 | New Laravel 13 project with the official Livewire starter kit (Livewire 4, Flux UI, Tailwind, auth screens), configure SQLite | 🔴 | DONE |
| S-04 | Design data model as ER diagram (see below) and create migrations | 🔴 | DONE |
| S-05 | Seeder for a first supervisor account and test employees | 🔴 | DONE |
| S-06 | Set up Laravel Pint (formatting) and Pest/PHPUnit (tests) | 🟡 | DONE |
| S-07 | Clarify remaining open questions (bottom of file) | 🔴 | DONE |

### Data model (draft)

- **users** – id, first_name, last_name, email, password, birth_date, role (`employee` / `supervisor`), annual_leave_days (calculated from workdays, editable by supervisor)
- **work_schedules** – id, user_id, weekly_hours, work_days (e.g. `["mon","tue","wed"]`), valid_from – history of working schedules, the entry valid on a date is used for that date
- **time_entries** – id, user_id, date, start_time, end_time, break_minutes, status (`planned` / `final`), is_school_day
- **absences** – id, user_id, type (`sick` / `vacation`), start_date, end_date, status (`pending` / `approved` / `rejected`), sick_note_submitted, reviewed_by, reviewed_at, comment
- **audit_logs** – id, user_id (who changed), auditable_type, auditable_id, old_values, new_values, created_at
- **holidays** – date, name (NRW, generated per year)

---

## Epic 1 – Authentication & Roles

**US-1.1 Register** 🔴 — DONE
As a new employee I want to create an account so that I can track my time.
- Fields: first name, last name, email, password, date of birth (needed for youth protection rules), **weekly working hours**
- Weekly hours: number > 0 and ≤ 40 (full-time = 40, anything less = part-time)
- Workdays: checkboxes Mon–Fri, at least one day (default: all five)
- First `work_schedules` entry is created with `valid_from` = registration date
- Email must be unique; password has a minimum length/complexity
- Password is hashed (Laravel default: bcrypt)
- Privacy notice (DSGVO Art. 13) is shown and must be acknowledged
- New accounts get the role `employee`; leave days are calculated as `30 × workdays / 5`, rounded up

**US-1.2 Login / Logout** 🔴 — DONE
- Wrong credentials show a generic error
- Rate limiting after several failed attempts (starter kit default)

**US-1.3 Roles** 🔴 — DONE
- Roles: `employee`, `supervisor`
- Employees only see their own data
- Supervisors see all employees (Epic 5)
- Middleware/policies protect supervisor routes

**US-1.5 First supervisor via seeder** 🔴 — DONE
- `SupervisorSeeder` creates the first supervisor account (credentials from `.env`, not hard-coded)

**US-1.6 Promote to supervisor** 🔴 — DONE
As a supervisor I want to make another user a supervisor.
- Action in the employee overview/detail view
- Change is written to the audit log
- Removing the supervisor role again 🟡 – the last remaining supervisor cannot be demoted

**US-1.7 Change working schedule** 🔴 — DONE
As an employee or supervisor I want to change weekly hours and/or workdays.
- Employee can change their own schedule; supervisor can change any employee's schedule
- Change requires a start date (`valid_from`); earlier weeks keep the old schedule so the official record stays correct
- Change is written to the audit log
- Leave entitlement is **not** recalculated automatically mid-year – supervisor adjusts it manually if needed 🟢 (automatic pro-rata recalculation)

**US-1.4 Password reset** 🟡 — DONE
- Reset via email link (included in the starter kit, needs mail configuration)

---

## Epic 2 – Time Tracking

**US-2.1 Log working time for today** 🔴 — DONE
As an employee I want to enter start time, end time and break for today.
- Net working time is calculated automatically
- Entry can be marked as vocational school day
- New manual-entry forms default to 08:00–16:30 with a 30-minute break (8 net hours)
- Add multiple non-overlapping work periods for the same day in My Time and the calendar; each period remains individually editable and audited
- Gaps automatically count as breaks for daily rule checks, but are not worked time; only additional breaks within a period are entered manually

**US-2.2 Pre-book today's end time** 🔴 — DONE
As an employee I want to enter my end time in advance (e.g. at 08:00 enter 16:00).
- Only possible for the **current day**, never for future days
- Entry has status `planned` until the end time has passed, then becomes `final` (scheduled job)
- Finalization catches up earlier dates after downtime; authenticated requests reconcile elapsed plans before displaying records and balances
- Employee can correct the end time until end of day (e.g. if leaving earlier)

**US-2.3 Weekly balance (individual weekly target)** 🔴 — DONE
As an employee I want to see how many hours I still need this week.
- Weekly target = `weekly_hours` of the schedule valid in that week (e.g. 40 h, 20 h)
- Daily share = `weekly_hours / number of workdays` (e.g. 40 h / 5 = 8 h, 20 h / 3 = 6.67 h)
- Target is reduced by one daily share for every public holiday, approved vacation day and sick day (pending or approved, see D-11) – **only if it falls on one of the user's workdays**
- Example: 20 h on Mon/Tue/Wed → holiday on Thursday reduces nothing, holiday on Monday reduces 6.67 h
- If a sick report is rejected, the reduction is removed again
- Shows: worked hours, hours behind this week (the accrued target for past workdays minus hours worked, never below zero), overtime/undertime against the full weekly target
- At the start of Monday the employee is 0 hours behind; on Tuesday, a missed 8-hour Monday shows 8 hours behind
- Days with fewer hours are allowed as long as the week adds up

**US-2.4 Correct past entries** 🟡 — DONE
- Corrections of past days allowed within a defined window (e.g. 7 days)
- Every change is written to the audit log (Epic 7)

**US-2.5 Overview** 🔴 — DONE
- Day, week and month view of worked hours and absences
- Overall hour balance (overtime/undertime) across weeks 🟡

**US-2.6 Clock in / Clock out button** 🟢 — DONE
- Alternative to manual entry

---

## Epic 3 – Working-Time Rule Checks (warnings only)

Rules depend on the user's age (from date of birth). Violations **never block** saving (D-06); they show a warning.

**US-3.1 Rules for adults (ArbZG)** 🔴 — DONE
- Max. 8 h/day, extendable to 10 h (§3 ArbZG)
- Breaks: > 6 h → min. 30 min, > 9 h → min. 45 min (§4)
- Rest period min. 11 h between working days (§5)
- Warning on Sunday/holiday work (§9)

**US-3.2 Rules for minors (JArbSchG)** 🔴 — DONE
- Max. 8 h/day, max. 40 h/week, 5-day week (§8, §15)
- Breaks: 4.5–6 h → 30 min, > 6 h → 60 min; max. 4.5 h without break (§11)
- Rest period min. 12 h (§13)
- No work between 20:00 and 06:00 (§14, exceptions possible)
- Automatic switch to adult rules on the 18th birthday

**US-3.3 Vocational school days** 🟡 — DONE
- Minor vocational-school days receive an 8-hour work-time credit by default; the value is configurable. HR/legal validation is still required before production use.

**US-3.4 Warning UI** 🔴 — DONE
- Warning is shown before saving, user can confirm and save anyway
- Message names the rule and explains the problem
- Entries with violations are marked in the overview

**US-3.5 Violations visible to supervisor** 🟢 — DONE
- Supervisor overview shows a warning icon for employees with recent violations

**US-3.6 Configurable limits** 🟢 — DONE
- Limits stored in `config/worktime.php` instead of hard-coded

---

## Epic 4 – Absences (Sick Days & Vacation)

**US-4.1 Report sick days** 🟢 — DONE
As an employee I want to report sick days.
- Period (from – to), type `sick`, status `pending`
- Counts as absent **immediately** (weekly target reduced), even before approval
- **No diagnosis or reason field** (health data, DSGVO Art. 9)
- Flag "sick note (AU) submitted" yes/no – no document upload

**US-4.2 Request vacation** 🟢 — DONE
As an employee I want to request vacation.
- Period (from – to), status `pending`
- Only the user's own workdays are counted as vacation days; weekends, days off and NRW holidays are not
- Warning if request exceeds remaining leave days

**US-4.3 Supervisor approval** 🟢 — DONE
As a supervisor I want to approve or reject sick days and vacation requests.
- List of all pending requests
- Approve / reject with optional comment
- `reviewed_by` and `reviewed_at` are stored
- Optional supervisor comments are available only for vacation requests; comments on sick reports are rejected
- Vacation only reduces the weekly target and leave balance once approved
- Sick days count immediately; a rejection removes the absence again

**US-4.4 Status for employee** 🟢 — DONE
- Employee sees status of their requests (pending / approved / rejected)

**US-4.5 NRW public holidays** 🟢 — DONE
- Generate NRW holidays per year (incl. movable ones based on Easter): New Year, Good Friday, Easter Monday, 1 May, Ascension Day, Whit Monday, Corpus Christi, 3 October, All Saints' Day, 25 + 26 December
- Artisan command to generate holidays for a given year

**US-4.6 Leave balance** 🟢 — DONE
- Annual leave days per user (default `30 × workdays / 5`, rounded up), used days, remaining days
- Supervisor can change the entitlement of any employee to any amount (logged in audit trail)
- Carry-over of remaining days into the following year; carried-over days expire after that year

---

## Epic 5 – Supervisor Dashboard

**US-5.1 Employee overview** 🟢 — DONE
As a supervisor I want an overview of all registered employees.

| Column | Content |
|--------|---------|
| Name | First + last name |
| Leave balance | Remaining vacation days this year, with used and pending days |
| Leave days used | Approved vacation days this year |
| Status | e.g. "Sick – approval required", "Leave request pending", "Sick", "On vacation", "OK" |
| Hour balance | Overtime / undertime (e.g. +6.5 h / −3 h), colour-coded |

- Sortable and filterable (e.g. only open requests)
- Employees are shown by default; an All accounts filter includes supervisors for account management.
- Click on employee opens detail view

**US-5.2 Employee detail view** 🟢 — DONE
- Time entries, absences and weekly balances of one employee
- The supervisor dashboard replaces the separate Employee access list; names open the detail page.
- Role, annual leave and working schedule changes live on the detail page with schedule history and all absence dates, including future requests.

**US-5.3 Export** 🟢 — DONE
- Export monthly hours per employee as CSV (e.g. for payroll)

---

## Epic 6 – My Time: Calendar View

A second "My Time" page that shows the employee's month as a calendar. Days are colour-coded (D-16), show details on hover and can be used to add time, vacation or sick days directly. All existing rules still apply (pre-booking D-02, correction window US-2.4, rule warnings Epic 3, approval flow Epic 4).

**US-6.1 Calendar page** 🟢 — DONE
As an employee I want a calendar view of my time so that I can see my month at a glance.
- New page "My Time – Calendar" next to the existing My Time page, reachable from the navigation
- Month view, weeks start on Monday, today is highlighted
- Buttons for previous / next month and "Today"
- Only the logged-in user's own data is shown (US-1.3)

**US-6.2 Colour coding** 🟢 — DONE
As an employee I want each day coloured by what happened on it.

| Colour | Meaning |
|--------|---------|
| 🔵 Blue | NRW public holiday |
| 🔴 Red | Sick day (pending or approved, see D-11) |
| 🟣 Purple | Vacation |
| 🟢 Green | Working time booked |
| ⚪ Grey | Nothing entered |

- Precedence if several apply: holiday → sick → vacation → booked time (D-16)
- Pending vacation is shown in a lighter purple than approved vacation; rejected absences are not shown
- Planned entries (`status = planned`, US-2.2) are shown green like final entries
- NRW holidays for the viewed calendar year are generated automatically if they have not been generated yet
- A legend explaining the colours is shown below the calendar
- Colours must also work in dark mode

**US-6.3 Hover details** 🟢 — DONE
As an employee I want to hover over a day to see what it is.
- Tooltip shows the type of the day: holiday (with name, e.g. "Holiday – Corpus Christi"), vacation (with status), sick day (with status), work day (with start, end, break and net hours) or "No entry"
- Days with rule warnings (Epic 3) show the warning in the tooltip
- On touch devices the details are shown on tap, since hover does not exist there

**US-6.4 Add entries from the calendar** 🟢 — DONE
As an employee I want to click a day and add time, vacation or a sick day for it.
- Clicking a day opens a modal with the options: **Log time**, **Request vacation**, **Report sick day**
- Only options that are allowed for that day are offered:
  - Log time: today (incl. pre-booking, US-2.2) and past days within the correction window (US-2.4)
  - Vacation: future days (and today); follows US-4.2
  - Sick day: follows US-4.1
  - Single-day vacation and sick requests submit the selected calendar date and type without requiring range selection
- Reuses the existing validation and logic – no duplicated rules
- Rule warnings (Epic 3) are shown before saving, as in US-3.4
- Every change is written to the audit log (US-7.1)
- Calendar updates immediately after saving (Livewire, no page reload)

**US-6.5 Edit existing entries from the calendar** 🟢 — DONE
- Clicking a day with an existing entry opens it for editing (within the allowed windows)

**US-6.6 Select a date range for absences** 🟢 — DONE
- Select several days (e.g. click start day and end day) to request vacation or report a sick period in one step

---

## Epic 7 – Official Record & Data Protection (DSGVO)

**US-7.1 Audit trail** 🔴 — DONE
- Every create/update/delete of time entries and absences is logged with old/new values, user and timestamp
- Audit log cannot be edited through the application

**US-7.2 No hard delete of time records** 🔴 — DONE
- Time entries are soft-deleted only (official record)

**US-7.3 Retention & deletion concept** 🔴 — DONE
- Working-time records kept at least 2 years (§16 ArbZG)
- Document which data is stored how long; automatic cleanup/anonymisation after expiry (scheduled job)

**US-7.4 Backups** 🔴 — DONE
- Regular automatic backup of the SQLite database file

**US-7.5 Data access & export (DSGVO Art. 15/20)** 🟡 — DONE
- Employee can download all their data as CSV/JSON

**US-7.6 Account deletion (DSGVO Art. 17)** 🟡 — DONE
- Account is deactivated; personal data anonymised once retention periods have expired

**US-7.7 Security basics** 🔴 — DONE
- HTTPS, CSRF protection, validation of all inputs, authorization policies on every Livewire component

---

## Epic 8 – Quality & Delivery

| ID | Task | Prio | Status |
|----|------|------|--------|
| Q-01 | Unit tests for the rule engine (every rule incl. edge cases) | 🔴 | DONE |
| Q-02 | Tests for weekly balance calculation (holidays, sick days, vacation, part-time with fewer days, schedule changes mid-year) | 🔴 | DONE |
| Q-03 | Feature tests for auth, time entry, approval flow, role access | 🟡 | DONE |
| Q-04 | User documentation for employees and supervisors | 🟡 | DONE |
| Q-05 | Deployment setup | 🟡 | DONE |
| Q-06 | Tests for the calendar view (colour per day type, precedence, allowed actions per day) | 🟡 | DONE |

---

## Epic 9 – Css Rework

Decisions (add to table)
ID	Decision
D-17	Styling uses plain CSS (custom properties, native nesting, cascade layers). No Tailwind CSS and no Flux UI. Vite stays as the bundler.
D-18	The brand palette is the --color-* token set in US-9.2. The primary colour is the orange/brown primary scale.
D-19	The calendar status colours (D-16) are separate status tokens outside the brand palette.
D-20	Dark mode uses the .dark class on <html>, set by a custom script that replaces Flux's appearance switcher. Light / dark / system options stay.
D-21	Components only use semantic tokens (--accent, --surface …). Raw palette values appear only in tokens.css.
Epic 9 – Plain CSS instead of Tailwind

Replace Tailwind CSS and Flux UI (which is built on Tailwind) with a plain-CSS design system based on the company colour palette. All existing features, rules and behaviour stay unchanged; only the frontend styling and UI components are replaced. Light and dark mode must both keep working (US-6.2).

US-9.1 Inventory & reference screenshots 🔴 — DONE (inventory and matched pre/post migration screenshots in light and dark mode; see `loaded/docs/css-migration-inventory.md`)
As a developer I want to know exactly what needs replacing so that nothing is forgotten during the migration.

List of all Flux components in use: grep -rhoE "<flux:[a-z.-]+" resources/views | sort | uniq -c
List of all views and Livewire components that use Tailwind classes
Screenshots of every page in light and dark mode, stored as a visual reference (not committed to main)
List of tests that assert on CSS class names (e.g. calendar tests Q-06)

US-9.2 Design tokens 🔴 — DONE
As a developer I want all colours, spacing and radii defined once as CSS custom properties so that the design stays consistent.

resources/css/tokens.css contains the brand palette (D-18):
css
--color-white: hsl(0 0% 100%);
--color-black: hsl(0 0% 0%);
--color-light: hsl(0 0% 100%);
--color-subtle-light: hsl(210 20% 98%);
--color-dark: hsl(0 0% 16%);
--color-subtle-dark: hsl(0 0% 12%);
--color-primary-25: hsl(22 81% 94%);
--color-primary-50: hsl(18 100% 86%);
--color-primary-100: hsl(18 90% 79%);
--color-primary-200: hsl(18 96% 69%);
--color-primary-300: hsl(18 80% 57%);
--color-primary-400: hsl(21 81% 42%);
--color-primary-500: hsl(25 96% 33%);
--color-primary-600: hsl(24 98% 25%);
--color-primary-700: hsl(26 94% 20%);
--color-primary-800: hsl(26 94% 13%);
--color-primary-900: hsl(26 94% 7%);
The semantic tokens are --bg, --surface, --text, --text-muted, --border, --accent, --accent-hover, --accent-text, --accent-soft and --focus-ring.
Light mode uses --accent = primary-500 with white text (about 6 : 1 contrast).
Dark mode (:root.dark) uses --accent = primary-300 with black text. Its background is subtle-dark and its surfaces are dark.
--text-muted and --border are derived from the palette with color-mix(), so no extra grey values are needed.
primary-300 is never used as a text colour on light backgrounds (about 3 : 1, fails WCAG AA).
The status tokens (D-19) are --status-holiday, --status-sick, --status-vacation, --status-vacation-pending, --status-booked and --status-empty.
Spacing (--space-1 … --space-8), --radius and --font-sans tokens are defined.

US-9.3 CSS architecture & build 🔴 — DONE
As a developer I want a clear CSS file structure so that styles are easy to find and don't override each other by accident.

Structure: app.css, tokens.css, reset.css, base.css, layout.css, utilities.css, components/*.css
app.css declares the layer order @layer reset, tokens, base, layout, components, utilities; followed by the imports
Every file wraps its rules in its own layer
Vite builds the CSS without the Tailwind plugin
Naming convention: BEM-light (.btn, .btn--primary, .card__header)
Only a few layout utilities (.visually-hidden, .stack, .cluster, .grid-auto); Tailwind is not rebuilt

US-9.4 Base styles & typography 🔴 — DONE
As a user I want readable, consistent text and page styles in both themes.

Minimal modern reset (box-sizing, margins, media elements)
Body uses --bg, --text and --font-sans
Heading scale for h1–h3, paragraphs, links, small, code
Visible focus style using --focus-ring on all interactive elements (:focus-visible)
color-scheme is set per theme so native controls (scrollbars, date pickers) match

US-9.5 Button component 🔴 — DONE
As a developer I want a reusable button so that all actions look and behave the same.

<x-ui.button> with the variants primary, secondary, ghost and danger, and the sizes sm and md
Renders as <a> when href is set, otherwise as <button> (default type="button")
Disabled and loading state (wire:loading)
Optional leading icon
Passes through all attributes (wire:click, x-on:…)

US-9.6 Form components 🔴 — DONE
As a user I want clear form fields with labels and error messages.

<x-ui.field> (label, hint, error), <x-ui.input>, <x-ui.select>, <x-ui.checkbox>, <x-ui.textarea>
wire:model works through attribute passthrough
Validation errors are shown below the field and linked via aria-describedby
Required fields are marked
The weekday checkboxes Mon–Fri (US-1.1, US-1.7) are shown as a grouped fieldset

US-9.7 Modal component 🔴 — DONE
As a user I want dialogs that are easy to use by mouse and keyboard.

<x-ui.modal> is based on the native <dialog> element and opened/closed with Alpine or Livewire events
It supports focus trapping, closing with Esc and a styled backdrop
It has title, body and footer slots
It is used for the calendar actions (US-6.4 / 6.5) and the rule warning confirmation (US-3.4)

US-9.8 Dropdown / menu component 🟡 — DONE
As a user I want menus (e.g. the user menu) that open and close reliably.

<x-ui.dropdown> is built with the Popover API or Alpine
It closes on outside click and on Esc
It can be used with the arrow keys

US-9.9 Alerts & badges 🔴 — DONE
As a user I want warnings and statuses to be clearly recognisable.

<x-ui.alert type="info|success|warning|error">, used for rule warnings (US-3.4) and flash messages
The message names the rule, as before
<x-ui.badge> is used for request status (pending / approved / rejected), employee status (US-5.1) and violation markers (US-3.5)
Status is never shown by colour alone; there is always text or an icon too

US-9.10 Card & table components 🔴 — DONE
As a supervisor I want readable tables and overviews.

.card for content blocks
.table with header, zebra/hover rows and number alignment
Sortable column headers show the sort direction (US-5.1)
Wide tables scroll horizontally in their own container on small screens
Overtime and undertime are colour-coded with text signs (+6.5 h / −3 h)

US-9.11 Icons 🟡 — DONE
As a developer I want icons without Flux.

Install blade-ui-kit/blade-heroicons; Flux uses Heroicons too, so the icons look the same
Replace every flux:icon usage
Icons use currentColor, and decorative icons get aria-hidden="true"

US-9.12 App layout & navigation 🔴 — DONE
As a user I want the same navigation as before, working on desktop and mobile.

App shell with sidebar, header and content area
Navigation items: My Time, My Time – Calendar, Absences, and supervisor items only for supervisors (US-1.3)
The active page is highlighted
On mobile the sidebar opens and closes with a toggle button (Alpine)
Separate guest layout for the auth screens

US-9.13 Appearance setting 🔴 — DONE
As a user I want to choose light, dark or system appearance.

The settings page offers light / dark / system options
The choice is stored in localStorage
An inline script in <head> sets .dark before the page renders, so there is no flash
"System" follows prefers-color-scheme and reacts to changes live
It replaces $flux.appearance completely

US-9.14 Migrate auth & settings screens 🔴 — DONE
As a user I want login, registration and settings to work exactly as before.

Migrate the login, registration (incl. weekly hours, workdays and privacy notice), password reset, email verification and profile/password/schedule settings screens
No functional changes (US-1.1 – US-1.7)

US-9.15 Migrate time tracking pages 🔴 — DONE
As an employee I want the time tracking pages in the new design.

Migrate the My Time page, today's entry, pre-booking, clock in/out, weekly balance, day/week/month overview and the correction of past entries
Rule warnings are shown with <x-ui.alert> in the confirmation modal (US-3.4)
Entries with violations are still marked in the overview

US-9.16 Migrate absence pages 🔴 — DONE
As an employee or supervisor I want the absence pages in the new design.

Migrate the sick report, vacation request, request status, leave balance, approval list with approve/reject and the optional comment for vacation only
No diagnosis field is added (DSGVO Art. 9)

US-9.17 Migrate supervisor dashboard 🔴 — DONE
As a supervisor I want the dashboard in the new design.

Migrate the employee overview table (US-5.1) with sorting and filters, the detail view (US-5.2), promote/demote supervisor, the leave entitlement edit and the CSV export button

US-9.18 Migrate calendar view 🔴 — DONE
As an employee I want the calendar to keep its colours and behaviour.

Day classes: .calendar-day--holiday, --sick, --vacation, --vacation-pending, --booked, --empty, --today
Precedence holiday → sick → vacation → booked stays in the PHP logic, not in CSS (D-16)
Status colours are checked against the brand colour and in dark mode; final values are chosen here and documented in tokens.css
The legend uses the same tokens
Tooltip in components/tooltip.css, shown on hover and focus, and on tap for touch devices (US-6.3)
Range selection is clearly visible (US-6.6)

US-9.19 Remove Tailwind & Flux 🔴 — DONE
As a developer I want no leftover Tailwind or Flux code in the project.

npm uninstall tailwindcss @tailwindcss/vite and remove the plugin from vite.config.js
composer remove livewire/flux and remove the Flux CSS import and @fluxScripts / @fluxAppearance
Remove any Tailwind-related entries from the config files and the README
The build runs without warnings

US-9.20 Accessibility 🔴 — DONE
As a user I want the application to be usable with the keyboard and readable for everyone.

Text contrast is at least 4.5 : 1 in both themes (WCAG AA), and at least 3 : 1 for UI elements and focus rings
All functions work by keyboard only (modal, dropdown, calendar day selection)
Form fields have labels and errors are announced
prefers-reduced-motion disables transitions

US-9.21 Responsive layout 🟡 — DONE
As a user I want to use CyTime on a phone.

Layout works from 360 px width upwards
On small screens the calendar shows shortened weekday names and stays tappable
Tables scroll horizontally instead of breaking the layout

US-9.22 Style guide 🟢 — DONE
As a developer I want documentation so that new pages follow the design system.

README section covering the tokens, layer structure, naming convention, list of x-ui.* components and how to add a new component
Optional internal page /styleguide (local environment only) that shows all components in both themes
Epic 8 – Quality & Delivery (add rows)
ID	Task	Prio	Status
Q-07	Update tests that assert on Tailwind class names (Q-06 calendar tests, feature tests) to use the new semantic classes	🔴	DONE
Q-08	Feature tests for the x-ui.* components (render variants, attribute passthrough, error display)	🟡	DONE
Q-09	Leftover check in CI: grep -rE "<flux:|dark:|\b(px|py|mx|my|gap)-[0-9]|\bbg-[a-z]+-[0-9]{2,3}" resources/views must return nothing	🟡	DONE
Q-10	Visual comparison against the reference screenshots (US-9.1) in light and dark mode	🟡	DONE

## Sprint Plan

1. **Sprint 1:** Phase 0 + Epic 1 (setup, auth, roles)
2. **Sprint 2:** Epic 2 (time entry, pre-booking, weekly balance) + audit trail (US-7.1)
3. **Sprint 3:** Epic 3 (rule engine + warnings) + tests Q-01
4. **Sprint 4:** Epic 4 (absences, approval, NRW holidays, leave balance) + tests Q-02
5. **Sprint 5:** Epic 5 (supervisor dashboard)
6. **Sprint 6:** Epic 6 (calendar view) + tests Q-06
7. **Sprint 7:** Rest of Epic 7 (retention, backups, DSGVO export/deletion) + docs & deployment

**MVP = Sprint 1–5**

---

## Open Questions

None right now – all questions are answered (see Decisions).

> ⚠️ Legal rules (ArbZG, JArbSchG, DSGVO) listed here are a starting point and must be verified with HR and the data protection officer before going live.