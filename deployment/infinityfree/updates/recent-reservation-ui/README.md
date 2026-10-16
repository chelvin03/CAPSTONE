# Recent reservation and calendar interface update

Prepared from commit 57a4f57. Not uploaded to hosting.

This update contains 13 current application files for the changes made in this chat:
- Requestor and admin calendars: light green available, yellow partly reserved, light red unavailable.
- Reservation details first, requestor information/email verification second, confirmation third.
- Equipment dropdowns and quantities for internal requestors, with blue Add and red Remove buttons.
- Agreement acceptance through Accept and Continue instead of an agreement checkbox.
- No browser reservation history list; tracking uses one colored current status badge.
- Improved red cancellation section and removal of Back to Home / Make Another Reservation links.
- Operating hours: 07:00 to 24:00.

## Existing installation required

This is an update for the existing MCST Laravel application, not a full installation. The hosted site must already have the current equipment request workflow, public email verification, reservation cancellation route/model support, reservation reference service, schedule blocks, and the existing live-tracking/equipment partials. If these earlier features have not been uploaded, stop and compare the hosted files before replacing the controller. This package contains current complete versions of changed files, so it also carries any earlier code already present in those files locally.

No migrations, database dumps, vendor packages, credentials, private uploads, or compiled build files are included. The recent changes require no database migration or npm build on an installation with those existing features.

## Upload with FTP or File Manager

1. Download backups of the matching hosted files and keep them on your computer.
2. Extract recent-reservation-ui-update.zip on your computer.
3. Upload the CONTENTS of its htdocs folder into your existing online htdocs, preserving every relative path. Merge folders and replace only the matching files; do not create htdocs/htdocs. Upload app/Services and config first, then the controller, public assets, and resources/views. Upload all 13 files as one update during a quiet period.
4. Preserve the hosted .env, APP_KEY, database, storage, uploads, vendor, public/build, and hosting .htaccess files. Do not upload your local .env or replace the online database.
5. Edit only these values in the existing HOSTED .env:

GYM_OPENING_TIME=07:00
GYM_CLOSING_TIME=24:00

6. Clear generated caches after upload. If no terminal is available: back up then remove bootstrap/cache/config.php if it exists, and remove only compiled .php view files inside storage/framework/views. Preserve .gitignore, the directories, and bootstrap/cache/packages.php and services.php. No routes changed in this update, so route-cache deletion is unnecessary.
7. If a terminal is available instead, run php artisan config:clear and php artisan view:clear from the hosted project root.
8. Hard-refresh the browser (Ctrl+F5).

## Check the live site

- Reserve page opens on reservation details and shows calendar colors without clicking dates.
- A yellow date opens its remaining available times; full dates cannot be selected.
- Start time 07:00 is allowed, 06:59 is rejected, and end at midnight works.
- Continue to requestor details; email verification preserves selections and the uploaded letter.
- Internal requestors see equipment dropdowns; external requestors do not.
- Accept and Continue records agreement acceptance and enables submission after confirmation.
- Track a known reference: Pending yellow, Approved green, Cancelled/Rejected red; no timeline or browser history list.
- Owned reservations show the red cancellation section while cancellation is allowed.
- Admin schedule uses the same availability colors.

If an error appears, restore the backed-up matching files and the previous hour settings. Keep database records and uploaded letters intact. Do not add a public cache-clearing or migration script.
