# CyTime status

- Phase 0 and Epics 1–8: DONE.
- Epic 9 – Plain CSS migration: DONE, including inventory and light/dark comparison against recovered pre-migration screens.
- Weekly balance shows the accrued shortfall from completed workdays, excluding today, holidays, and applicable absences.
- Single-day vacation and sick requests from the time calendar submit correctly without enabling range selection.
- The time calendar generates NRW holidays on demand for viewed years.
- Verification: `php artisan test --compact` passes (149 passed, 5 skipped; 154 total); `php vendor/bin/pint --test` passes.
- Email verification remains intentionally disabled and is covered by a regression test.
- No remaining backlog items through Epic 9.
