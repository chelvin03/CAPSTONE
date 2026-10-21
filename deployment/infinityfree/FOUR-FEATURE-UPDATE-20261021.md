# Four-feature update — 21 October 2026

Source: `C:\xampp\htdocs\mcst-gym-reservation-system`. This document describes the changes made for this request, on top of the previously staged system updates. Nothing has been deployed or pushed to GitHub.

## Behavior

1. **Cancellation:** an owner verified through the existing submission session or email-verification session can cancel an active reservation until exactly 72 hours before its scheduled start. Older submissions remain eligible. Inside that deadline, tracking and validation direct the requestor to the administrator. Existing admin cancellation remains available. Cancellation records status, reason, actor, timestamps, history, audit, a persistent notification, and an email attempt after commit. Failed email does not undo cancellation; administrators see the delivery failure on the reservation. Availability is calculated from current approved/completed bookings, so cancellation releases the slot immediately.
2. **Waiting list:** the existing request form now has an explicit opt-in for an occupied schedule. The server rechecks conflicts while holding the facility lock, assigns `waiting_list`, preserves the approval-letter upload and verification requirements, and records history. It still rejects closures and invalid hours. Cancellation checks waiting requests against the released interval and records provisional eligible IDs in the audit log. The cancelled reservation's admin detail page lists currently eligible requests, in submission order, with links to review them. Existing approval controls recheck conflicts, cleanup gaps, closures, operating hours, requestor restrictions, equipment, and the configured daily limit before approval. Approval updates tracking/calendar and sends a persistent notification plus email. This review does not hold slots or automatically approve requests. The existing signed waiting-list schedule-offer flow also checks the daily limit on acceptance.
3. **Completion/attendance:** the existing implementation is retained and verified: approved admins and the already-authorized staff can record ended events as completed or no-show; only admins can correct an existing outcome with a reason and current revision. Actual attendance, expected attendance snapshot, recorder, schedule snapshot, timestamps, status history, and audit remain stored. The existing BI outcome panel and CSV remain intact. Reservation report previews, PDF, and Excel now also include expected/recorded attendance and completed totals. Legacy records without attendance show `Not recorded`.
4. **PDF:** the existing tFPDF renderer is retained. Its generated `dejavusans.mtx.php` stores an absolute font path. The local cache contains a Windows path; copying that cache to another host can make final font embedding fail. A regression test reproduces the failure with an obsolete path and verifies the fix, which resolves the font on the current host. The PDF table now fits landscape A4 and long rows continue across pages. Export exceptions return to Reports with the selected dates/type and a clear error. Sensitive exception details are not exposed.

The exact incident on the live host has not been inspected. The font-cache failure is reproduced locally; other missing dependencies or host restrictions must be checked on that account.

## Database

Read-only local schema inspection confirmed the existing `reservations`, `reservation_status_histories`, `reservation_event_outcomes`, `schedule_proposals`, and `notifications` tables and relevant columns. No new migration is introduced by this update, and no local application records were reset or edited. Functional tests use an isolated in-memory SQLite database.

The host must already have the schema from the prior staged updates. In particular, verify the existing migrations:

- `2026_07_21_020331_create_notifications_table.php`
- `2026_10_18_000000_create_requestor_restrictions_table.php`
- `2026_10_18_100000_create_schedule_proposals_table.php`
- `2026_10_18_110000_create_reservation_event_outcomes_table.php`
- `2026_10_18_120000_add_follow_up_and_equipment_returns.php`

Also verify the earlier reservation, equipment review, acknowledgement, and audit migrations required by the current application. Do not rerun table-creation SQL against tables that already exist. Do not use `migrate:fresh`, reset, seeds, or import a replacement local database over hosting records. If hosting is behind, prepare and review additive SQL from the missing migrations against a private backup first; there is no blanket schema import in this update.

## Files changed for this request

Modified application files:

```text
app/Http/Controllers/Admin/ReportController.php
app/Http/Controllers/Admin/ReservationController.php
app/Http/Controllers/PublicReservationController.php
app/Models/Reservation.php
app/Services/AdminReportExportService.php
app/Services/ReservationCancellationService.php
app/Services/ReservationReportService.php
app/Services/ScheduleProposalService.php
public/js-reservation-calendar.js
resources/views/admin/reservations/show.blade.php
resources/views/public/reservations/create.blade.php
resources/views/public/reservations/partials/equipment-updates.blade.php
resources/views/public/reservations/track.blade.php
resources/views/emails/reservation-submitted.blade.php
```

New application files:

```text
app/Mail/ReservationApproved.php
app/Notifications/ReservationStatusUpdate.php
app/Services/ReportPdf.php
app/Services/ReservationStatusNotificationService.php
app/Services/WaitingListService.php
resources/views/emails/reservation-approved.blade.php
```

Modified tests: `tests/Feature/AdminReportsTest.php`, `tests/Feature/ApprovalAvailabilityTest.php`, `tests/Feature/RequestorCancellationTest.php`. Two report/submission fixtures were updated to satisfy the existing attendance minimum and required event type; cancellation tests now assert the requested three-day policy.

New tests: `tests/Feature/FourFeatureWorkflowTest.php`, `tests/Feature/PdfHostingCompatibilityTest.php`, `tests/js/booking-calendar.test.cjs`. This deployment note is also new. Local inspection scripts and diagnostic logs are under ignored `.tmp/` and are not deployment files.

## Verification

Final focused run: **70 passed, 0 failed, 429 assertions** across the six Laravel test files in the command below. Calendar tests: **4 passed, 0 failed**. Separate schedule-proposal regression run: **28 passed**, including overlapping workflow/PDF cases. PHP syntax passed for 24 changed/new PHP or Blade files; Blade compilation passed. The broader-suite run reported **443 passed, 73 failed** before the last two focused test additions; the entire suite is not green.

- **Passed:** cancellation ownership, required reason, exactly-three-day boundary, late refusal, old pending submissions, cancelled status/history and calendar release, cancellation notification.
- **Passed:** HTTP submission into waiting list, tracking status, blocked premature approval, cancellation/released-slot review, approval/notification/calendar update, competing approval rejected, closure/hours/daily-limit enforcement.
- **Passed:** ended-event completion, attendance/no-show validation, corrections, authorization, history and existing BI coverage; attendance included in reservation reports.
- **Passed:** all five report types, empty reports, date validation, authorization, selected-period totals, Excel consistency, PDF signatures/page objects/end markers, multi-page exports beyond 600 records, obsolete hosted font-path regression, and graceful export failure.
- **Passed:** Node calendar scenarios for consent, fully occupied dates, administrator closures, hours/duration/half-hour boundaries, and availability-loading failures.
- **Not tested:** real browser walkthrough, visual PDF inspection in a desktop reader, live InfinityFree execution, real SMTP inbox delivery, and simultaneous MySQL requests. SMTP is faked for application tests; production mail must be configured and verified. MySQL locking is present but SQLite does not exercise row-lock contention.
- **Remaining broader-suite failures:** the full regression run still reports failures outside the focused fixes, commonly fixtures below 50 expected attendees or missing required event type. Do not describe the entire system test suite as passing. See the final verification totals provided with this change.

Reproduce focused checks:

```powershell
php artisan test tests/Feature/FourFeatureWorkflowTest.php tests/Feature/RequestorCancellationTest.php tests/Feature/ApprovalAvailabilityTest.php tests/Feature/EventOutcomeTest.php tests/Feature/AdminReportsTest.php tests/Feature/PdfHostingCompatibilityTest.php --compact
node --test tests/js/booking-calendar.test.cjs
```

## Safe InfinityFree deployment

1. Keep a private backup of the hosted database, application files, uploaded documents, and current `.env`/APP_KEY. Keep this backup outside public web paths. Test the current source in a separate staging installation first, including the remaining broader-suite failures relevant to your deployment.
2. Compare hosted schema with the prerequisites above. Preserve current IDs, accounts, reservations, relationships, status history, notifications, and uploads. Apply only missing additive schema changes after backup and review. This update itself adds no migration.
3. Build from this XAMPP source and the current lockfile. Include all required files from the previously staged updates if they are not already on the host; the 20 production files listed here are a patch for an otherwise up-to-date installation, not a complete older-release upgrade. Do not use an older ZIP or a different project copy.
4. Verify PHP and extensions match `composer.json`/`composer.lock`; PDF needs tFPDF, `mbstring`, the bundled `vendor/setasign/tfpdf/font/unifont/DejaVuSans.ttf`, and PHP-writable temporary storage. Preserve the complete vendor package; the fix requires no headless browser, shell command, worker, or new Composer dependency.
5. If the deployed Composer loader uses an authoritative class map, regenerate the production autoloader locally from this source with the existing project workflow and upload the corresponding generated Composer files. Otherwise existing PSR-4 autoloading resolves the new classes. Do not run Composer setup on the hosting database.
6. Upload the listed production files to their matching paths in the existing Laravel installation. Preserve host-specific `.env`, APP_KEY, private uploads, routing files, and access-protection files. Do not upload tests, `.tmp`, Git metadata, database dumps, local sessions, or logs. Do not copy local configuration/view caches or Windows-generated bootstrap caches onto the host.
7. Clear only generated Laravel config/view caches using the existing deployment procedure; preserve runtime storage and user data. Ensure the host's PHP process can write Laravel runtime and temporary directories. Do not create a public migration or diagnostic endpoint.
8. Configure and verify the production mailer. Perform controlled cancellation, late-cancellation advisory, waiting submission/review/approval, competing approval, completion/attendance, report date filters, and PDF-download tests using dedicated test bookings. Open downloaded PDFs, including a long/multi-page report, in a reader. Verify delivery in the actual recipient inbox and check the new email-failure advisory when delivery fails.
9. Retain the backup until those checks succeed. If application rollback is necessary, restore the prior code while retaining reservation/history/attendance records. Do not drop populated tables as a rollback shortcut.
