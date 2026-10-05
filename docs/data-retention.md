# Data retention

CyTime retains official working-time records and the audit history needed to verify changes. This policy describes the application retention behavior; it does not cover payroll records held in other systems.

| Data | Retention |
| --- | --- |
| Time entries, including soft-deleted entries | At least two years, measured from the work date |
| Sick and vacation absence records, including soft-deleted records | At least two years, measured from the recorded absence |
| Work schedules and audit history | At least two years |
| Active account and contact data | While the account is active |
| Deactivated account personal data | Anonymized after two years from deactivation |
| Anonymized official records | Retained; CyTime does not hard-delete time entries or absences |
| Authentication sessions, password-reset tokens and passkeys | Removed immediately when an account is deactivated |

The `data-retention:anonymize-expired-accounts` command runs daily at 02:00 (Europe/Berlin). It anonymizes eligible deactivated accounts without deleting associated schedules, time entries, absences, or audit records. `ACCOUNT_ANONYMIZATION_YEARS` can increase the account retention period; the application rejects values below two years.

Configure Laravel's scheduler in the hosting environment so scheduled commands run. The application does not delete official time records when their minimum retention period ends.
