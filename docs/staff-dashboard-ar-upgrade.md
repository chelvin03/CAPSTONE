# Staff dashboard and AR management

Implemented in the existing Laravel project. The sidebar, MCST logo/watermark, authentication, administrator BI dashboard and reservation approval/cancellation workflows remain in place.

## Dashboard

- Seven equal CSS Grid columns on desktop (1024px and wider), four/three on tablets and two/one on mobile. Equal-height pastel cards show today's approved/completed events, next-seven-day approved events excluding today, scheduled availability, available equipment records, pending AR, completed AR and approved events.
- Full-width approved-reservation AR queue directly below the KPIs. Pending/in-progress work precedes completed receipts; upcoming dates sort nearest first, followed by past dates most recent first.
- Today's schedule and the actual-status donut appear beside one another, stacking on mobile.
- Year/month/day filters apply to AR counts/list, approved events, upcoming events and status breakdown. Current operational KPIs and today's schedule remain independent of reporting filters, as explained in the interface.
- Existing equipment/facility readiness, operational attention notices and upcoming bookings remain below the requested sections. No Recent Notifications section was added.
- Pending AR includes both pending and in-progress tasks for currently approved bookings. Completed AR counts actual prepared receipts explicitly marked completed within the selected event-date reporting scope, including retained historical records.

## Database and lifecycle

Migration `2026_10_17_000000_create_acknowledgement_tasks_table.php` was applied locally. It adds `acknowledgement_tasks` with a unique reservation foreign key, preparation status, completion actor/time and timestamps. The existing `acknowledgement_receipts` table, unique receipt numbers, snapshots and emailed signed links are reused without replacing its schema.

The migration backfilled **7 tasks for 7 existing approved reservations**, without duplicates. Existing prepared receipts become in-progress tasks; completion is never inferred from email delivery. No reservation records or approval statuses were changed. Running `php artisan ar:backfill-tasks` again created **0** additional tasks.

Reservation model save events create missing tasks when approval is recorded, including priority approvals. Normal administrator approvals and task creation participate in the same existing transaction. Backfill locks each reservation and checks its current status; unique indexes prevent duplicate tasks. New, validated, pencil, rejected, waiting-list and cancelled reservations do not receive new automatic tasks.

Staff can start preparation, preview actual reservation/requestor/approved-equipment data, generate the existing snapshot receipt, print, download a real PDF using the installed tFPDF library and explicitly mark it completed. Generating a receipt does not send email or mark it completed. Existing email actions remain available.

Booking changes and approved-equipment allocation changes reopen tasks. Completion requires the current generated version, preventing stale forms from completing outdated receipts. Repeated start/generate/complete requests are idempotent where their content has not changed. Cancellation retains task/receipt records and disables further AR actions; AR Management offers an inactive-history filter.

PDFs are generated on demand after server authorization and returned with private/no-store headers. There is no publicly served PDF directory or new persisted file path to expose. The durable receipt source remains its existing database snapshot. No fees, financial amounts or payment confirmations are generated.

Existing snapshots without the newly captured event-type field retain compatibility with their existing signed links. Printable and PDF documents include MCST branding, unique AR number, date issued, booking details, acknowledgement statement, prepared-by staff and signatures.

## Modified files

- `app/Models/Reservation.php`, new `app/Models/AcknowledgementTask.php`: relationships and approval task creation/reset.
- `app/Services/AcknowledgementReceiptService.php`, new `AcknowledgementPdfService.php`: task backfill, ordering, snapshots and PDF output.
- `app/Http/Controllers/ReservationReceiptController.php`: management list, preparation start, completion and authorized PDF download.
- `app/Services/StaffMonitoringService.php`: real KPI/task queries, availability and recorded-status counts.
- `app/Http/Middleware/StaffReadOnly.php`: narrowly allow AR-specific actions while retaining all reservation-edit restrictions.
- `app/Services/EquipmentRequestService.php`, `app/Http/Controllers/EquipmentRequestController.php`: invalidate AR preparation when approved allocation changes; equipment rules remain unchanged.
- `routes/reservation-documents.php`, `routes/console.php`: additive AR routes and idempotent backfill command.
- `resources/views/staff/dashboard.blade.php`, `staff/ar/index.blade.php`, `staff/partials/ar-table.blade.php`, `public/css/staff-dashboard.css`: dashboard, task management and responsive presentation.
- `resources/views/reservations/ar-workflow.blade.php`, `ar-preview.blade.php`, `acknowledgement.blade.php`: preview, generate/complete/download controls and receipt fields.
- New migration above; new `tests/Feature/StaffArManagementTest.php`.
- `tests/Feature/EquipmentRequestTest.php`: align old fixture gym names and the schedule-conflict assertion with the existing gym-only workflow.

## Validation

**101 tests passed, 739 assertions** across AR management/workflow, receipt authorization, staff dashboard/monitoring, equipment requests, priority approvals, requestor cancellation, public reservation permits and dashboard insights. Tests use SQLite in memory and fake mail; no production receipt emails were sent.

New focused tests cover automatic approval tasks, rollback, duplicate prevention, backfill, unique AR numbers, preview/print/PDF, completion KPI changes, stale versions, reservation/equipment changes, cancellation history, staff restrictions, queue ordering and legacy snapshot compatibility.

Three older equipment tests initially failed because their fixtures did not configure the named MCST gym. The same failures were reproduced against the original production files. Fixtures now use the required MCST Gymnasium name; a second stale assertion now expects the existing gym-only schedule conflict to be rejected before equipment validation. All selected tests pass without changing those production reservation rules.

Headless Chrome checks used an actual database-backed Blade render with local CSS/assets and the mobile sidebar represented in its normal closed state:

- Desktop viewport 1422px: **7 cards, 1 row, equal 144px widths**, document width 1422px.
- Mobile viewport 390px: **7 cards, 4 rows (2 columns)**, document width 390px; no page-wide horizontal overflow. Dense tables scroll within their own containers.
- PHP syntax, route registration, Blade compilation and `git diff --check` passed.

Live read-only verification after migration: pending AR **7**, completed AR **0**, approved events **7**, upcoming within seven days **1**, today's events **0**, duplicate reservation tasks **0**. No existing receipt was marked completed just to populate a card.
