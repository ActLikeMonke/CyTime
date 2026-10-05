# CyTime status

- Project: Laravel 13, Livewire 4, plain CSS, SQLite, Pest, and Pint.
- Phase 0 and Epics 1–8: DONE, including Official Record & Data Protection and Quality & Delivery.
- Epic 9 – Plain CSS migration: DONE. US-9.1 and Q-10 were completed using the recovered pre-migration source and matched light/dark visual references; see `loaded/docs/css-migration-inventory.md`.
- Weekly balance now shows the employee's accrued shortfall from completed workdays, excluding today, holidays, and applicable absences.
- Calendar single-day vacation and sick requests now keep the action dialog open through Livewire updates and submit the selected date.
- The time calendar generates NRW holidays on demand for viewed years.
- Verification: `php artisan test --compact` passes (149 passed, 5 skipped; 154 total); `php vendor/bin/pint --test` passes.
- No remaining backlog items through Epic 9.