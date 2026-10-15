# Dashboard quick filters, trends and forecast

Prepared locally. No online files or database were changed.

Back up matching online files. Upload to matching paths under htdocs in this order:

1. app/Services/DashboardTrendService.php (new)
2. app/Services/DashboardAnalyticsService.php
3. app/Http/Requests/Admin/DashboardRequest.php
4. resources/views/dashboard/partials/quick-filters.blade.php (new)
5. resources/views/dashboard/partials/trend-bars.blade.php (new)
6. resources/views/dashboard/partials/trends.blade.php (new)
7. app/Http/Controllers/Admin/DashboardController.php
8. resources/views/dashboard/admin.blade.php

Create the dashboard/partials directory if missing. The dashboard in this package follows the online template the user pasted, preserving its existing panels and removing the matching-reservations table and its link. The whole old filter form is replaced with Today, This Month and This Year. If live files have changed since that pasted template, merge instead of replacing them wholesale.

No database migration or asset build is needed. Keep the live .env, APP_KEY and uploads. Existing dependencies used here (including AuditLog, GymCapacity, Reservation, Facility, ScheduleBlock and layouts.app) must remain present; GymCapacity and Facility were included in earlier updates.

Summary quick filters use server-side current dates. Monthly trend shows all 12 months of the selected reporting year, including zero months; annual trend spans recorded years. Trends use reservation schedule dates, not submission dates. The most-scheduled months use records before this month and show how many distinct years have bookings; they do not claim that a month will always be busy.

The next-three-month forecast is a simple baseline: average of up to six completed calendar months since the first matching reservation month, including zeros. It requires at least three completed months, ignores current/future scheduled dates, and does not model seasonality. Estimates are explicitly distinguished from actual reservations. This is descriptive planning information, not an automatically created booking.
