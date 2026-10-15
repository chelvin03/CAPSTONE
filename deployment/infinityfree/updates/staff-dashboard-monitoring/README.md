# Staff operational dashboard

Prepared locally; not uploaded. No SQL, migrations, new tables, vendor upload, routes changes or frontend build are required.

## Behavior

- Four summary cards show approved/completed events today, approved reservations tomorrow through the next seven days, stored gym status with current schedule information, and equipment record counts.
- Today's schedule is chronological and includes available setup and cleanup times. Completed events remain visible alongside approved events.
- Upcoming approved events are ordered by date and start time and paginated five at a time. Only View Details is available per event.
- Year, Month and Day filters affect upcoming events and the status overview. A specific year and month are required for Day. Invalid dates are rejected. Cards and today's schedule always reflect current operations. Pagination retains the filters.
- The accessible status bar chart counts database records, including existing pending and pencil statuses. The all-years default shows all recorded history; future event lists still exclude today and earlier dates.
- The Admin reference stylesheet is copied unchanged. Staff styles are scoped to the staff dashboard; the existing sidebar, logo and application typography remain in place.

## Monitoring permissions and AR workflow

Staff dashboard, reservations, schedules and equipment remain read-only. Staff cannot approve, reject, cancel, reschedule or edit bookings, modify equipment/facilities, or generate/export BI reports. Per the user's clarification, the existing acknowledgement-receipt workflow is preserved: staff may prepare, review, print and send ARs for approved/completed bookings. Manage AR links remain on the existing reservation list and Prepare / Send AR remains on reservation details. The dashboard tables keep View Details as their only action; staff can access AR from the details page. Existing admin permissions and signed requestor receipt links are preserved.

## Upload order

Download backups of matching online files first. Then upload the htdocs files to their matching online paths:

1. public/css/dashboard-reference.css (unchanged Admin stylesheet dependency) and public/css/staff-dashboard.css.
2. app/Services/StaffMonitoringService.php.
3. resources/views/staff/partials/dashboard-events.blade.php.
4. app/Http/Controllers/Staff/DashboardController.php.
5. app/Http/Middleware/StaffReadOnly.php.
6. resources/views/staff/partials/reservations-table.blade.php and resources/views/staff/reservation/show.blade.php.
7. resources/views/staff/dashboard.blade.php last.

Preserve .env, APP_KEY and private uploads. With normal uncached views Laravel recompiles modified templates automatically. If online views are cached, clear only the generated compiled Blade PHP files inside storage/framework/views after backing them up; keep the directory and its .gitignore. No route cache change is required.

Verify online with an approved staff account: summary cards, empty states, month/day filtering, paginated event details, mobile layout, allowed AR preparation/sending, and forbidden booking edits/admin/report endpoints. Confirm the Admin dashboard still opens normally.

## Validation

26 focused tests passed (247 assertions), covering staff counts, filtering, ordering, pagination, date validation, permissions, privacy, report denial, AR preparation/review/sending, stale receipts, SMTP failure, existing receipt links, unchanged administrator summary counts and Admin day filters. PHP syntax checks passed. A rendered test-database page was checked in local headless Chrome at 320, 390, 768 and 1366 pixels with no page overflow; event tables scroll inside their panels on mobile.

The older AdminDashboardTest file also contains failed assertions expecting previous Admin interface labels such as All time and This Month. Those Admin production files were not modified by this update. The entire legacy test suite is not claimed to pass.
