# Staff monitoring dashboard

## Completed scope

The existing staff dashboard now shows database-backed operational information, using the current layout, blue sidebar, MCST logo, Poppins typography and Bootstrap Icons already installed in the project. No Bootstrap framework, calendar package, migrations or login changes were added.

- Staff navigation: Dashboard, Reservations, Schedules, Facilities, Equipment and the existing CSRF-protected POST Logout.
- Actual authenticated staff name, compact read-only notice, and no duplicate dashboard Logout button.
- Today's Events: Approved and Completed reservations on today's scheduled date in `config('app.timezone')`.
- Upcoming Reservations count: Approved records from tomorrow through seven days after today, inclusive.
- Today's table: confirmed events ordered by start time, paginated by ten.
- Upcoming list: next five Approved records after today, ordered by date/time/ID. Explicitly allowed to extend beyond the seven-day summary window.
- Facility card: stored availability/maintenance status, with separate schedule-derived activity and current blackout indication. Actual physical occupancy is not inferred as fact.
- Equipment card: number of equipment records by stored status, not individual units or checked-out stock.
- Notices: facility/equipment maintenance, unavailable equipment, expected attendance at or above stored capacity, cancellations and rescheduling recorded in the past seven days, and today's schedule blocks.
- Searchable, date/status-filtered reservation list with fifteen-row pagination and read-only detail.
- Date-filtered read-only schedule, with paginated blocks and maintenance context. No new calendar dependency.
- Consistent colored/labeled statuses, horizontal table scrolling, responsive forms and cards.

## Permission corrections

Previously, staff could generate, submit and export reports. Staff reservation details also exposed contact persons and phone numbers. Those capabilities are removed from the active staff flow.

The staff route group keeps `role:staff` and adds `StaffReadOnly`. This middleware requires an approved account, GET/HEAD, and an explicit allowlist of operational route names. Existing `staff.reports.index`, `staff.reports.export` and `staff.reports.store` route names remain for compatibility, but return 403 before the report controller executes. Administrator routes retain their original role checks; administrator accounts still do not enter staff routes. The role-based `/dashboard` redirect is preserved.

Operational reservation queries select only reference, event/type, scheduled date/time, facility, expected attendees and status. Staff views do not receive contact data, documents, purpose text, administrator notes, raw rescheduling remarks or raw cancellation reasons. Schedule-block titles/reasons are also omitted. Existing account login, password management and Logout behavior are unchanged.

## Files and routes

New files:

- `app/Http/Controllers/Staff/DashboardController.php`
- `app/Http/Controllers/Staff/ReservationController.php`
- `app/Http/Controllers/Staff/ScheduleController.php`
- `app/Http/Middleware/StaffReadOnly.php`
- `app/Services/StaffMonitoringService.php`
- `resources/views/components/operational-status.blade.php`
- `resources/views/staff/partials/reservations-table.blade.php`
- `resources/views/staff/schedules/index.blade.php`
- `tests/Feature/StaffDashboardTest.php`

Updated: `routes/web.php`, shared sidebar/application layout/CSS, staff dashboard/reservation/facility/equipment views, `tests/Feature/StaffReportTest.php`, and the staff assertions in `tests/Feature/FileMaintenanceTest.php`.

Existing staff dashboard and reservation URLs now use dedicated staff controllers. Added `GET /staff/schedules` as `staff.schedules.index`. No database or schema changes.

## Validation

- Full `php artisan test --compact`: **124 passed, 885 assertions**.
- New staff dashboard and revised report tests: **10 passed, 114 assertions**.
- Laravel Pint passed for new staff PHP files and staff tests.
- `npm run build` and `php artisan view:cache`: passed.
- Read-only local MariaDB dashboard smoke check: successful, ten queries on the current empty-today dataset. This is not a production load benchmark.

Coverage includes role/approval access, forbidden admin writes/exports, next-seven-day boundaries, status exclusions, sorting/list limits, privacy, filters/pagination, notices, empty data and session-invalidating logout.

No browser session was available for visual verification. Responsive classes and compiled templates were reviewed, but desktop/tablet/mobile rendering still needs the manual checks below.

## Missing operational data

Actual arrival/departure and equipment issue/return quantities are not recorded. The dashboard therefore labels schedule-derived activity and equipment-record counts accurately. Reschedule notices rely on the existing `Reservation rescheduled:` history marker; older edits without this history cannot produce a reliable notice. Free time is described as absence of recorded bookings/blocks, subject to maintenance and approval, rather than a guaranteed booking slot.

## Manual checks and next step

1. Sign in as approved staff and open `/staff/dashboard`; confirm your name, summary values and read-only navigation.
2. Compare today's Approved/Completed bookings with the table. Compare tomorrow through day seven against the upcoming count; the five-row list may include later dates.
3. Try reservation search/date/status filters and pagination; inspect a detail page for absence of private contact/document/note fields.
4. Open Schedules, change date, and inspect recorded blocks and current maintenance status.
5. Attempt `/admin/dashboard`, `/staff/reports`, `/staff/reports/export` and administrative actions as staff; expect 403 for defined protected routes.
6. Check widths around 1440px, 768px and 390px, keyboard navigation, sidebar toggle and internal table scrolling.
7. Log out using the sidebar and confirm staff URLs redirect to login.

Recommended next step: perform this visual review with representative schedules and maintenance records, then decide whether actual occupancy and equipment issue/return tracking are required.
