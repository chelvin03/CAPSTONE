# BI dashboard implementation review

## Architecture and scope

The existing `admin.dashboard` GET route (`/admin/dashboard`) remains inside the authenticated administrator route group. The same route serves the filtered CSV with `export=csv`. Staff and public routes are unchanged. No authentication or database records were changed for this task.

- `app/Http/Requests/Admin/DashboardRequest.php`: administrator authorization, validated filters, inclusive date defaults and maximum three-year range.
- `app/Services/DashboardAnalyticsService.php`: shared filter query, aggregate calculations, bounded daily buckets, interval coverage, processing times and supporting analytics.
- `app/Http/Controllers/Admin/DashboardController.php`: renders the dashboard and streams the audited CSV report.
- `resources/views/dashboard/admin.blade.php`: existing Blade/CSS charts, filters, eight KPIs, accessible legends, supporting summaries, pagination and definitions.
- `resources/css/app.css`: dashboard-scoped Poppins typography, with system fallbacks. The existing font configuration was Figtree, not Poppins. Poppins is loaded from Bunny Fonts; offline environments use the fallback.
- `tests/Feature/AdminDashboardTest.php`: dashboard regression and metric coverage.

The existing blue palette, Tailwind components, sidebar, routes and admin reservation management remain. The dashboard's creation shortcut is replaced by Manage Reservations. The single-facility selector is hidden when only one facility exists; multi-facility datasets retain it. No chart library or package was added.

## Verified schema and metric availability

| Requirement | Existing data and implemented behavior |
| --- | --- |
| Scheduled reporting dates | `reservations.reservation_date`; indexed inclusive start / exclusive next-day end queries preserve end-date records in both SQLite and MariaDB. |
| Event types | `event_type`; separate from requestor type. Missing values display as Not specified. |
| Requestor groups | Public form stores groups in `reservation_type`. Existing school, student, organization and government values are preserved, not reclassified as unsupported groups. Options come from recorded values. |
| Pending / overdue | New + Validated only; overdue means created at or before now minus seven days. Legacy Pending remains visible separately if present. |
| Utilization | Approved + Completed scheduled intervals, merged per facility and date, clipped to `gym.reservation.opening_time` / `closing_time`. Invalid, zero-length and overnight intervals are excluded. |
| Capacity | Configured daily operating duration × inclusive calendar days × selected facility count. Existing schedule blocks/closures are not deducted, so this is explicitly scheduled utilization against configured calendar-day capacity. Missing/invalid hours yield N/A. |
| Processing | Earliest approval/rejection/cancellation from status history and the existing decision timestamp columns, minus submission. Missing or chronologically invalid decisions are excluded, and sample coverage is shown. |
| Cancellation reasons | Existing free text is grouped by disclosed keyword rules into Weather, Scheduling, Duplicate request, Other recorded reason, or Not recorded. Raw reasons are excluded from HTML/CSV. |
| Equipment | Existing `reservation_equipment` quantities, grouped by equipment. Top five allocations are shown; these are not actual use hours. |
| Satisfaction | Existing `feedback.rating`, aggregated by matching reservation IDs. No recorded responses means N/A, not zero satisfaction. No rating scale is inferred. |
| Waiting-list conversion | Matching reservations with waiting-list history that subsequently reached Approved. Same-second transitions use history IDs to determine order. Multiple approvals count once. |
| Completion | Current Completed status and coverage of `completed_at`. A completion timestamp is not an actual duration. |
| Audit | Existing immutable audit trail records `analytics.export`, validated filters, format and matching record count. |

All panels and CSV use the same reservation cohort. Prior comparison shifts the date window to the preceding equal number of days while preserving non-date filters. Empty prior periods have no percentage change. Daily/weekly/monthly/quarterly/annual charts retain empty buckets and label partial boundaries. Weeks begin Monday. Hourly demand counts every booking touching a slot; it is not the utilization numerator.

## Database and performance

No migrations, column changes, reseeding or additional indexes. Existing reservation date/status/schedule indexes and history foreign keys are reused. Aggregate queries return counts and groups, not the entire reservation collection. Daily buckets are bounded by the validated range. SQL window functions merge overlapping scheduled intervals, including nested bookings. CSV records stream in batches of 500; table records paginate by ten with eager-loaded facility names. Both the installed MariaDB 10.4 and SQLite test backend support the queries.

## Missing data and smallest recommended follow-ups

- **Actual attendance:** add an optional nonnegative `actual_attendees` to reservations and record it during authorized event completion.
- **Actual utilization:** add optional `actual_started_at` and `actual_ended_at`, validated together. Keep scheduled utilization until reliable actual durations are collected.
- **Public feedback collection:** the feedback table exists, but `user_id` is required and the public requestor flow has no account. A future change can make that relation nullable and add a single-use reservation feedback token; define a rating scale in configuration/validation before reporting a normalized satisfaction percentage. No requestor login is needed.
- **Structured cancellation reporting:** optionally add a nullable controlled `cancellation_reason_code` while retaining the original text. Current keyword groupings are descriptive, not authoritative categorization.
- **Equipment physical use:** allocation data exists; actual issue/return timestamps would be needed for use-hours. Ensure administrative allocation actions populate the existing pivot before expanding the schema.
- **Closure-adjusted capacity:** agree on which schedule blocks represent unavailable operating time before subtracting them. The implemented denominator is configured calendar-day capacity and states that limitation.
- **PDF:** no PDF renderer is installed or configured in the project. Existing PHPWord/PhpSpreadsheet exports are not a configured PDF generator. CSV was expanded; no PDF dependency was introduced.

## Validation and manual review

Automated checks cover administrator/staff/guest/requestor access, filter validation, all statuses, cancellation rates, empty data, prior comparison, exact overdue boundaries, scheduled dates, partial/zero trend periods, overlapping hours, processing history, filtered supporting summaries, privacy, CSV formula escaping/auditing and pagination.

Completed checks:

- `php artisan test --compact tests/Feature/AdminDashboardTest.php tests/Feature/Auth tests/Feature/ReportGenerationExportTest.php tests/Feature/StaffReportTest.php`: **78 passed, 553 assertions**, including 25 dashboard tests.
- Laravel Pint for the changed controller, request, service and dashboard tests: passed.
- `php artisan view:cache`: passed.
- `npm run build`: passed.
- `git diff --check` for the changed tracked dashboard files: passed.
- Final read-only MariaDB summary smoke check: 20 aggregate/lookup queries, approximately 47 ms on the current small local dataset. This is a local smoke check, not a production load benchmark.

The MariaDB service was also exercised read-only against the existing database. Blade compilation and the Vite build were checked. No browser was available to the browser tool, so visual desktop/mobile review remains a manual check; do not treat it as completed browser verification.

1. Sign in as the local test admin using the existing password; open `/admin/dashboard`.
2. Check at desktop width and 390px mobile width. The filter/KPI grids should stack, navigation should collapse, and wide chart/table content should scroll inside its panel.
3. Apply dates, event type, requestor type and status; confirm active filter summary, KPIs, chart totals and matching-record count reconcile. Click a chart period/status/category and use Reset.
4. Try an inverted range and an invalid query value. Confirm a visible validation message after the redirect.
5. Page through more than ten matches and verify filters remain in the URL.
6. Export CSV and check metadata, summary/aggregate sections, all matching rows, absent contact/document/free-text details, and the new audit entry.
7. Sign in as staff or visit while signed out; verify BI and its CSV are inaccessible.

Recommended next step: capture actual attendance and actual event start/end times during completion, then add an explicitly actual-utilization metric with data-coverage reporting.
