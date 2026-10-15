# Reservation reference number update

Prepared locally; not uploaded. All 46 focused checks passed (305 assertions), including priority bookings, public submissions, pencil bookings, gym selection, reference boundaries and transaction rollback.

References follow submission order: R1-A through R100-A, then R1-B through R100-B. After R100-Z comes R1-AA. Public and admin bookings share one counter. Existing reservations keep their current references and count toward the initial counter. For example, 15 existing reservations means the next reference is R16-A. Cancelled reservations still count; deleting a reservation after this update does not reuse its number. Failed booking transactions do not consume a number.

## Upload steps

1. Back up the online database and both controller files listed below. Pause new bookings while applying the SQL and uploading the files so the initial count remains accurate.
2. In phpMyAdmin, select the website database and run create-reference-sequence.sql. Confirm it returns one row with id 1 and last_number equal to the existing reservation count. Do not upload this SQL file or README to online htdocs.
3. Upload htdocs/app/Services/ReservationReferenceService.php into online htdocs/app/Services first.
4. Replace online htdocs/app/Http/Controllers/PublicReservationController.php and htdocs/app/Http/Controllers/Admin/ReservationController.php with their matching packaged files. These controllers include the current local calendar and pencil-booking changes, so use this package with the previously prepared updates already installed.
5. Resume bookings. Check an existing reference still opens its tracking page. Submit the next genuine reservation and confirm its reference follows the counter. Avoid submitting dummy reservations to the live database.

No vendor upload, frontend build, environment change or route change is needed. Preserve the existing .env and APP_KEY.

The database/migrations file is included as an alternative for hosts with Artisan. On those hosts use php artisan migrate --force instead of manually applying the SQL. InfinityFree manual deployment needs only the service and two controllers after the SQL; the migration file need not be uploaded there.

If deployment must be rolled back, restore both original controllers together. Keep the sequence table so its numbers are preserved for a later retry. Do not reset its counter.
