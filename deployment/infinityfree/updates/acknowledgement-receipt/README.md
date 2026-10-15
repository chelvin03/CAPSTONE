# Acknowledgement receipt and uploaded-letter preview

Prepared locally; nothing uploaded online. Back up matching files before replacing them.

Upload both new controllers, the updated StaffReadOnly middleware, routes/reservation-documents.php, the new reservations/acknowledgement.blade.php and admin/reservations/partials/attachments.blade.php before the three updated admin/staff views. Create missing folders.

Edit online routes/web.php and add this line once at the bottom:

```php
require __DIR__.'/reservation-documents.php';
```

The full web.php is intentionally not included, preserving the live routing. If an online bootstrap/cache/routes-*.php file exists, back it up then remove that generated route cache file so the new routes load. Preserve packages.php, services.php, .env, APP_KEY, storage and uploaded documents. Do not change existing approval routes.

Then replace admin/reservations/show.blade.php, staff/reservation/show.blade.php and staff/partials/reservations-table.blade.php from this package. Updated views use existing operational-status and equipment-review components from earlier updates. The receipt uses the previously uploaded public/images/admin-mcst-logo.jpg. No migration, SQL update, vendor upload or npm build is required.

Admin action reads Approve / AR (or Confirm Pencil Booking / AR). Approval uses the existing approval workflow. As soon as approved, the admin page shows AR ready for staff. Staff sees Print AR in reservation lists and View / Print AR on reservation details. Open the receipt and choose Print / Save as PDF; the page uses A4 print CSS and signature lines. AR number is deterministic from the reservation reference. Receipt reflects current approved booking details and is not a stored payment receipt or an immutable issuance record. No fees/payment totals are invented.

Receipt access requires an approved admin/staff account and approved or completed reservation status. Staff remains read-only. Cancelled/rejected/pencil/new bookings cannot print AR. Completed bookings retain their AR.

The admin reservation page lists uploaded letters with an eye button. Click to expand an inline PDF/image preview; click again to close. A new-tab link is available. Files are served from the configured private document disk after admin authorization and reservation ownership checks; unsupported types and missing files are rejected. Existing uploaded files must be present on the server at their saved paths. Do not move private letters into public/images or expose private storage.

Tests cover approval-to-staff printing, cancelled/unapproved/suspended access, document scope/privacy, missing files and unsupported content. The receipt references expected participants and only approved equipment quantities.
