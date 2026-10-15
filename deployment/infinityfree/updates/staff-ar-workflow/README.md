# Corrected staff AR workflow

This replaces the earlier immediately-printable AR with Admin approves / For AR > Staff prepares > Staff reviews > Staff sends > Requestor opens and prints. No online action has been performed.

The reported online 500 error still needs its exact production.ERROR line; a screenshot alone cannot identify it. In particular, verify that views are in resources/views/reservations (directly under views) and controllers are directly under app/Http/Controllers. Do not enable APP_DEBUG publicly.

Back up online files and database. First run create-ar-table.sql in phpMyAdmin on the website database (keep SQL off the web root). For a migration-managed deployment, run the included migration instead; do not apply both without reconciling migration history.

Then upload to matching online htdocs paths, dependencies first:

1. app/Models/AcknowledgementReceipt.php
2. app/Services/AcknowledgementReceiptService.php
3. app/Mail/AcknowledgementReceiptReady.php
4. resources/views/emails/acknowledgement-ready.blade.php
5. resources/views/reservations/ar-workflow.blade.php
6. resources/views/reservations/acknowledgement.blade.php (replace old)
7. app/Http/Controllers/ReservationReceiptController.php (replace old)
8. app/Http/Middleware/StaffReadOnly.php (replace old)
9. routes/reservation-documents.php (replace old)
10. resources/views/admin/reservations/show.blade.php
11. resources/views/staff/reservation/show.blade.php
12. resources/views/staff/partials/reservations-table.blade.php

Online web.php already requires reservation-documents.php from the earlier upload. Keep that line once; no main routes file replacement is needed. If routes are cached, remove only the generated bootstrap/cache/routes-*.php file after backing it up. Keep existing .env, APP_KEY, private uploads and earlier letter-preview controller/view. No vendor or asset build is needed.

Staff can now POST only the AR preparation and send endpoints; other operations retain existing read-only restrictions. Each reservation has one AR. Staff notes, prepared-by name and booking data are saved as a snapshot. Re-preparation after a detail change produces a new version and invalidates older email links. Identical preparation is idempotent.

No email is sent by approval or preparation. Staff must click Send AR to Requestor; the email goes to the original saved contact_email and contains a signed printable link valid for seven days. The requestor needs no login. Staff can resend an expired link. Missing emails require an admin account-detail update. An SMTP failure leaves the saved AR unsent so staff can retry. Current cancellation, rejection, altered booking details, changed equipment allocations or expired signatures block old links.

Local migration applied and eight focused tests passed (70 assertions). No live email was sent during testing.
