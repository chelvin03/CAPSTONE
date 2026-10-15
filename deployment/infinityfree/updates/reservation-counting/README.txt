Reservation totals verification and All time dashboard update

Cause: the screenshot periods differ. The deployed Reports Reservations card uses Reservation::count() (no date, status or facility predicates). Its all-time count is 32. The BI dashboard uses reservation_date >= 2025-11-01 and reservation_date < 2026-11-01, plus the selected status, facility, event-type and requestor filters. It counts 31. Reservation MCST-20261005-JADZVE is scheduled on 2026-11-12, outside that range. No reservation-counting error was found.

Evidence is the supplied online SQL export, not a fresh direct connection to the hosted database. Local MySQL was unavailable during this task. The exported reservation fields were loaded into an isolated in-memory database: dashboard/report/database counts agreed in 20 period/status/facility scopes. The saved deployed dashboard and report queries also agreed in 10 period/status scopes. The current local Reports page differs from the deployed Staff Reports page, so this package preserves the deployed dashboard design and default date range. No Reports files, reservation data, audit data or totals are changed.

Upload (back up each online file first)
Replace these four files in the same paths under the online htdocs:
- app/Http/Requests/Admin/DashboardRequest.php
- app/Services/DashboardAnalyticsService.php
- app/Http/Controllers/Admin/DashboardController.php
- resources/views/dashboard/admin.blade.php
No SQL import, migration, new credentials or asset build is required.

The dashboard now offers an All time checkbox. When checked, the reservation count has no date bounds and preserves status, facility, event type, requestor type and trend grouping. Trend/utilization calculations span the earliest through latest reservation dates. CSV output names the reporting scope. Choosing a trend bar exits All time to apply that bar's date range. Unchecking All time retains the normal selected date-range behavior. Historical totals are never forced to match differently scoped totals.

Chapter 4 screenshots
Option A: comparable all-time cards. Check All time on the BI dashboard; select All statuses, All event types, All requestor types, All facilities, then Apply filters. Capture its All time label and total 32. Open Reports and capture its Reservations 32 / All time card. Other metrics are different measures and need not match.
Option B: period comparison. Uncheck All time; enter 2025-11-01 to 2026-10-31 and use the same unrestricted other filters. Capture the dashboard reporting period and Total Requests 31. On Reports, Generate Database Report: Reservation Detail, the same From and To dates, All statuses, All categories, All users. Export Excel/Word and capture its 31 reservation records. The Reports page's all-time summary card stays 32; export form filters do not change that card.
The Reports Category filter is requestor/reservation type; the BI Event type filter is event_type. For these comparison screenshots, keep both at All. The exported data has one facility; retain All facilities. For a facility-specific comparison, use an export that supports the same facility predicate rather than assuming the Reports card is facility-filtered.

Suggested caption
The BI dashboard contains 31 reservation records scheduled from November 1, 2025 through October 31, 2026. The Reports all-time summary contains 32 records because one additional reservation is scheduled for November 12, 2026. Under the same all-time scope and unrestricted filters, both totals are 32.

Regression validation: 53 dashboard/report/export tests passed (417 assertions). Packaged deployed Blade syntax and dashboard controller syntax passed. The package has not been uploaded to the live site.
