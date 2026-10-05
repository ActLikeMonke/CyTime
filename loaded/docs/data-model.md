# CyTime data model

```mermaid
erDiagram
    USERS ||--o{ WORK_SCHEDULES : has
    USERS ||--o{ TIME_ENTRIES : records
    USERS ||--o{ ABSENCES : reports
    USERS o|--o{ ABSENCES : reviews
    USERS o|--o{ AUDIT_LOGS : performs

    USERS {
        bigint id PK
        string first_name
        string last_name
        string email UK
        string password
        date birth_date
        timestamp privacy_notice_acknowledged_at
        string role
        tinyint annual_leave_days
    }
    WORK_SCHEDULES {
        bigint id PK
        bigint user_id FK
        decimal weekly_hours
        json work_days
        date valid_from
    }
    TIME_ENTRIES {
        bigint id PK
        bigint user_id FK
        date date
        time start_time
        time end_time "nullable while clocked in"
        smallint break_minutes
        string status
        boolean is_school_day
        timestamp deleted_at
    }
    ABSENCES {
        bigint id PK
        bigint user_id FK
        string type
        date start_date
        date end_date
        string status
        boolean sick_note_submitted
        bigint reviewed_by FK
        timestamp reviewed_at
        string review_comment "vacation review comments only"
        timestamp deleted_at
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string auditable_type
        bigint auditable_id
        json old_values
        json new_values
        timestamp created_at
    }
    HOLIDAYS {
        date date PK
        string name
    }
```

## Notes

- `work_schedules` is historical: `(user_id, valid_from)` is unique, and calculations must select the schedule valid on the date being evaluated.
- Time entries and absences use soft deletion. Their user foreign keys restrict hard deletion so related official records cannot be removed as a side effect.
- Clock-in creates an open time entry with a null `end_time`; clock-out fills it before the entry can count as worked time.
- Employee-submitted free text is not stored on absence reports; unstructured text on sickness records could capture protected health data.
- Optional supervisor review comments are stored only for vacation requests; comments on sick reports are rejected and never included in the audit snapshot.
- `audit_logs.auditable_type` and `auditable_id` form a polymorphic reference; snapshots of changed values are JSON.
- Leave-day defaults are stored on the user so supervisors can override the calculated entitlement.
- Registration stores the timestamp when the employee acknowledges the displayed privacy notice.
- Registration audits the initial employee role, calculated leave entitlement, and work schedule.
- Holidays use their date as the primary key; only NRW holidays should be inserted.
