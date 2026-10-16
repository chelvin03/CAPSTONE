# MCST dashboard BI upgrade

Implemented in the existing `admin.dashboard` route with its existing administrator middleware, sidebar, navigation and theme. No migrations or reservation writes are involved.

## Files

- `app/Services/DashboardInsightService.php`: scoped monthly counts, forecast, weekday/time heatmap, completeness, attention references and peak months.
- `app/Services/DashboardAnalyticsService.php`: reuse existing overlap/blackout utilization calculation; load approved intervals once instead of querying every calendar day.
- `app/Http/Controllers/Admin/DashboardController.php`: supply insights and correct the utilization definition in CSV exports.
- `app/Http/Requests/Admin/DashboardRequest.php`: validate the heatmap demand selector.
- `resources/views/dashboard/admin.blade.php`: six KPIs, category/status/requestor controls, existing analytics in a compact expandable section.
- `resources/views/dashboard/partials/insights.blade.php`: requested BI panels and accessible tables/reference drilldowns.
- `resources/views/dashboard/partials/trends.blade.php`: retain annual/monthly comparisons; remove duplicate six-month forecast display.
- `public/css/dashboard-reference.css`: responsive panel/card grids and heatmap styles.
- `tests/Feature/DashboardInsightsTest.php`: focused new analytics tests.
- `tests/Feature/AdminDashboardTest.php` and `DashboardTrendTest.php`: align old UI assertions with calendar selectors and newly available event filters.

## Definitions

- Total: all matching status records by reservation event date. Approved: `approved` only. Pending: `new` + `validated`.
- Utilization: union of approved scheduled intervals, clipped to configured hours with blackout intervals removed, divided by similarly adjusted bookable facility hours × 100. Overlaps merge per facility/day. No configured hours or no bookable capacity produces N/A.
- Cancellation: `cancelled` / matching total × 100, rounded to one decimal. Rejected records are never cancellations. Empty monthly rates display 0%.
- Completeness: records passing all required workflow checks / matching total × 100. Required fields include event name, purpose, requestor type/facility, contact person/number, valid positive attendees up to 2,000 and ordered date/time. Public records (`user_id` is null) additionally require contact email and a recorded `permit` document with a file path. Administrator-created records follow their less restrictive existing document/email rules.
- Event type, setup and cleanup are optional in the existing controllers. Missing event type appears as an informational attention issue without lowering completeness. Optional setup/cleanup do not generate missing-field warnings.
- Forecast: arithmetic mean of exactly three completed calendar months preceding the cutoff, including zero months. Cutoff is the earlier of selected end date and now. For a past partial month that month is excluded; a past completed month is included. Upcoming horizon starts after the cutoff month. Calendar filters determine cutoff; category, status, facility and requestor filters determine historical scope. Fewer than three months since the first matching reservation produces insufficient history instead of an invented estimate.
- Heatmap: count each reservation once in every two-hour interval it overlaps, Monday–Sunday. Default is approved; All Statuses expands demand scope. An explicit status filter takes precedence. Intervals follow actual configured operating hours, with 6 AM–10 PM fallback. Peaks use earliest chronological entry to resolve ties.
- Main monthly/peak/cancellation charts follow the exact date scope. All-years selections aggregate the same calendar month across years. The compact full-year comparison explicitly extends beyond month/day filters.

## Validation

Read-only database verification on October 17, 2026 for calendar year 2026:

- Database count and dashboard/chart totals: **29** each.
- Cancelled: **2**, yielding **6.9%**.
- Complete/incomplete: **25 / 4**.
- Historical July/August/September counts: **1 / 7 / 1**, producing **3.0** estimated reservations in each of November, December and January.
- Top selected-scope months: October **15**, August **7**, December **3**.
- Approved occupied hours **24**; bookable hours **6,196**.

Tests run with SQLite in memory, without modifying the live database:

- Dashboard/admin/trend/day/insight suites: **46 passed, 337 assertions**.
- Public permit, gym-only, midnight, priority, staff dashboard and staff monitoring regressions: **57 passed, 444 assertions**.
- PHP syntax checks, Blade compilation, route registration and whitespace checks passed.

The layout includes desktop/tablet/mobile breakpoints, local horizontal scrolling for dense charts/heatmaps, and text equivalents. No browser automation tool was available; visual mobile inspection remains a manual check. Charts reuse the project's HTML/SVG approach without external chart dependencies or simulated data.

Forecasts are baseline estimates with limited confidence, not confirmed demand or a seasonal model. A recorded document path checks upload metadata, not physical file integrity. The schema has no explicit submission-origin column, so document/email requirements use the existing public-versus-admin `user_id` convention. Database date columns are required; date-scoped analytics cannot include legacy rows with null event dates.
