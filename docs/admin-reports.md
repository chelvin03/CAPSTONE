# Admin reports

Open `/admin/reports` as an administrator. The existing role, verified-account, authentication, and Staff restrictions remain in force. The page retains the shared sidebar/navigation, Poppins, and Tailwind styling; no Bootstrap UI is introduced.

## Period and definitions

All five reports filter the **reservation/event date**, not submission, approval, or cancellation timestamps. Start and end dates are inclusive. Indexed queries use `reservation_date >= start` and `reservation_date < day after end`. Both dates are required, must be real `YYYY-MM-DD` dates, and end cannot precede start. Every quick report uses the current calendar month in the application's configured timezone, with its period displayed on the page.

- Reservation: matching reservation records, including reference, event, date, start/end time, facility, and current status.
- Utilization: only currently approved bookings. Reuses the dashboard's interval calculation: merge overlaps per facility/day, clip to configured `gym.reservation.opening_time` and `closing_time`, subtract applicable global/facility schedule closures from both booked time and capacity. Capacity covers all configured facilities and every calendar day in the period. Percentage = occupied bookable hours / available bookable hours × 100, rounded to one decimal. Hours are rounded to two decimals. Invalid/missing hours or missing facilities give unavailable hours/percentage; zero capacity gives unavailable percentage. This is scheduled usage, not measured attendance. Completed records are excluded, matching the dashboard implementation.
- Status: counts of New, Validated, Approved, Rejected, Waiting List, Cancelled, plus any other statuses actually stored (including Completed or legacy Pending). Does not rewrite project status values.
- Event type: counts grouped by actual `event_type`, not requestor `reservation_type`. Missing types display as Not specified.
- Cancellation: currently cancelled records whose reservation/event date falls in the period; does not measure when cancellations were submitted.

## Exports and performance

Preview and both downloads share the same report query, summaries, column definitions, and row formatter. Downloads include MCST, title, generation timestamp, selected period, calculation notes, and metrics. Downloads recalculate current database data; concurrent edits between requests can change totals.

Excel reuses PhpSpreadsheet and produces real `.xlsx` files with Summary and Records worksheets. Cell storage uses an isolated temporary disk cache, database records load in batches of 500, and very large results split before Excel's sheet row limit. Text values are explicitly typed as strings to prevent spreadsheet formulas. Cache and temporary downloads are cleaned up. PDF uses the installed `setasign/tfpdf` library with an embedded Unicode DejaVu font, wrapped table cells, repeated page/column headers, and page numbers. It consumes the same batched records without building an HTML document or loading all reservation models. PDF rendering retains compressed pages in memory, so output size still affects memory for exceptionally large reports.

Previews paginate record tables at 25 rows. Utilization retains only one day's booking intervals at a time. Requestor names, emails, telephone numbers, and internal notes are excluded. Blade escapes all displayed content. Existing legacy CSV/Word/Excel routes and historical submitted-report detail routes remain available to Admin; legacy Excel/Word also omit requestor contact information. No migrations or live records are changed.

## Verification

`tests/Feature/AdminReportsTest.php` covers all five previews and PDF/Excel exports, empty periods, required/invalid/reversed dates, inclusive boundaries, event dates versus creation dates, status/event group totals, utilization overlap/closure handling, unavailable hours, current-month defaults, Admin/Staff/guest access, formula prevention, HTML escaping, pagination, and exports beyond the 500-record batch boundary. Existing report, dashboard, Staff, public reservation, and workflow tests should also pass through `php artisan test`.

Implementation verification: the full suite passed with 169 tests and 1,171 assertions; four subsequent focused checks passed after combining missing event types and tightening legacy export escaping. The Vite production build and Blade compilation passed. Read-only checks against the existing MySQL database confirmed 14 reservations, four approved bookings, 14 total records in each grouped breakdown, and two cancellations across the stored reservation-date range. The 601-record fixture exists only in the isolated test database. No live sample records were inserted.
