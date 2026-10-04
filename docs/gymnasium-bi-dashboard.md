# Gymnasium BI dashboard

The existing authenticated `admin.dashboard` route (`/admin/dashboard`) and `role:admin` middleware remain in place. Staff and Requestor accounts cannot access this page or its CSV export. No migrations, packages, fake records, or reservation workflow changes were introduced. The existing sidebar remains in use.

## Changed files

- `app/Http/Requests/Admin/DashboardRequest.php`: validated year and month filters, current-year defaults, and compatibility with existing reporting date ranges/presets.
- `app/Services/DashboardAnalyticsService.php`: consistent filtered cohort, database-derived year/event/status options, most common event type, and utilization excluding blackouts and non-approved reservations.
- `app/Http/Controllers/Admin/DashboardController.php`: latest-five overview query while preserving existing CSV exports.
- `app/Http/Controllers/Admin/ReservationController.php`: existing listing accepts the filters passed by View All.
- `resources/views/dashboard/admin.blade.php`: five cards, reservation overview, accessible SVG doughnut and legend, filters, loading feedback, validation errors, and empty states.
- `resources/views/admin/reservations/index.blade.php`: preserve incoming BI filters during search/pagination and allow clearing them.
- `resources/css/app.css`: dashboard-scoped responsive styles using existing Tailwind components and Poppins.
- `tests/Feature/AdminDashboardTest.php`: updated defaults and utilization expectations, plus calendar combinations, leap days, All Years/export, overview ordering, blackout capacity, empty data, and validation coverage. Existing authorization and export tests remain.
- `docs/gymnasium-bi-dashboard.md` and `docs/bi-dashboard-review.md`: current calculation and local verification guidance.

Pre-existing edits to the sidebar and public layout were left in place.

## Filters and calculations

Every displayed count, card, doughnut segment, table record, and CSV record uses the same reservation cohort. Dates refer to `reservation_date`, not submission time. A specific year covers January 1 through December 31; a month limits it to that calendar month, including leap days. Query boundaries are inclusive start and exclusive next-day end. All Years spans the first recorded year through the last recorded year; an additional month selects that month in each recorded year. Available years come from actual reservation dates, with the current year always available. Reset returns to the current year and All Months. An empty database uses the current year, zero counts, and N/A event type/utilization when no facility exists. Historical-only data is not silently substituted for the current year.

The page uses `category` as the existing query parameter for Event Type and actual distinct stored `event_type` values. Status and event filters preserve their selections. The six operational statuses always appear in the legend; existing Completed or legacy Pending values appear when present in the cohort, so chart counts continue to equal the total. The filter can select these additional statuses when they exist in the database.

- **Total:** all matching reservations.
- **Approved:** matching records whose current status is `approved`.
- **Pending:** matching records with `new` or `validated` status. Waiting List and legacy Pending are excluded.
- **Most common event:** highest count of matching `event_type`, with ties sorted alphabetically; missing event types appear as Not specified and an empty cohort as N/A.
- **Utilization:** union of matching approved event intervals inside bookable operating windows / total bookable hours in the selected period × 100. Nested, duplicate, and overlapping approved intervals count once per facility and date. Separate facilities retain separate capacity. Rejected, Cancelled, Completed, and other statuses contribute no booked hours.

Daily operating windows come from the existing `gym.reservation.opening_time` and `closing_time` configuration (`GYM_OPENING_TIME` and `GYM_CLOSING_TIME`). The existing calendar has no weekday-specific schedule; configured hours apply to each calendar day. Global and facility-specific `schedule_blocks` are clipped to operating hours and subtracted from both available capacity and booked intervals. Overlapping blackouts count once. Missing blackout times extend to the relevant operating boundary; a block without either time closes the full day. Invalid, zero-length, and overnight booking intervals are excluded. Setup/cleanup are excluded because the existing conflict checks only block event start/end intervals. Missing or invalid operating hours, missing facilities, or zero bookable capacity produce N/A with an explanation.

Capacity covers existing facilities (or the selected facility for legacy facility-filter links). A current facility status does not establish historical closure dates; record closures through the existing blackout calendar. Status/event filters narrow the numerator without reducing the physical capacity denominator. This is scheduled approved occupancy, not measured attendance or actual event duration.

The table shows at most five records ordered by reservation date, start time, then ID descending. View All opens the existing authorized reservation listing with the same date, month, event, status, and other supported filters. Searches and pagination preserve those filters; Clear removes them. CSV export remains available and includes all matching records, not just the five shown.

The doughnut uses native SVG with a readable text legend containing actual counts and percentages. No Chart.js dependency is installed. Empty results show a neutral ring and explicit empty message. Server-rendered data needs no asynchronous fetch; Alpine shows Loading on filter submission. Validation errors use an accessible alert. The compact screen omits optional trend charts; the existing supporting calculations/export remain available.

## Local verification

1. Start Apache and MySQL in XAMPP using the existing database/configuration. Do not reseed the database.
2. Run `npm.cmd run build` (or use the existing `npm run dev` workflow).
3. Run `php artisan serve`, then sign in using an existing Gym Administrator account and open `http://127.0.0.1:8000/admin/dashboard`.
4. Check the current-year default, then combine Year, Month, Status, and Event Type. Compare cards and legend totals, latest five rows, View All results, and CSV. Try All Years with February to verify multi-year and leap-day behavior.
5. Choose an empty combination and verify zero counts, N/A event type, and no colored chart segments. Utilization is 0% if capacity exists and N/A if capacity is unavailable.
6. Verify existing blackout dates reduce bookable capacity. For a controlled test database with 08:00–12:00 hours, an approved 08:00–12:00 event and merged 09:00–10:30 blackout should give 2.5 booked / 2.5 available hours = 100%. A full-day closure should produce N/A.
7. Check desktop/tablet/mobile widths and keyboard access. Sign in as Staff and Requestor and verify `/admin/dashboard` is forbidden; guests are redirected to login.
8. Run `php artisan test --compact --filter=AdminDashboardTest` and `php artisan test --compact`. PHPUnit uses its in-memory SQLite database and does not seed or modify the local application database.

No screenshot was present in the supplied attachment; the layout follows the written visual specification. Browser-based screenshot comparison was not available in this session.
