# MCST InfinityFree preparation and manual deployment guide

Prepared on 7 October 2026. Nothing has been deployed. Final domain, database, SMTP, and production environment are not configured.

## Source and changes

The current `http://127.0.0.1:8000` application is:

`C:\Users\THINKPAD\Downloads\MCST Gym Reservation System\mcst_gym_reservation_system`

The separate XAMPP copy is:

`C:\xampp\htdocs\mcst-gym-reservation-system`

They have different features. This package uses the Downloads application because it contains the recent login, requestor, equipment decision, agreement, capacity, and tracking changes shown on port 8000. It does not merge or overwrite the XAMPP application. Confirm this is the intended deployment source before upload.

Only these new preparation files were added to XAMPP; existing application files were not edited:

- `deployment/infinityfree/.gitignore`: excludes generated releases from Git.
- `deployment/infinityfree/production.env.example`: incomplete, secret-free environment template.
- `deployment/infinityfree/htdocs.htaccess`: production-only document-root routing and private-file protection.
- `deployment/infinityfree/private.htaccess`: denies direct HTTP access to private directories.
- `deployment/infinityfree/prepare.ps1`: creates a new isolated release; never runs migrations or connects to MySQL.
- `deployment/infinityfree/audit-package.php`: local-only file-size/asset/layout audit.
- `deployment/infinityfree/README.md`: this report and export/upload instructions.

Generated release files are under `deployment/infinityfree/releases/prepared-*/`. The package preserves source files, with these intentional package-only differences:

- `composer.json`: `optimize-autoloader=false` to keep generated PHP files below the host's 1 MB limit. Locked dependency versions are retained.
- `vendor/`: production dependencies only and regenerated nonoptimized autoload files. No existing local dependency folder is changed.
- Root `.htaccess` plus private-directory `.htaccess` files added.
- `public/build/`: fresh CSS/JS/fonts compiled locally from the current project.
- `.env.example`: production template; no actual `.env`, passwords, or APP_KEY are copied.
- Clean runtime folders; local caches, logs, sessions, database dumps, backup archives, and Vite `hot` file excluded. Uploaded reservation files are copied into private storage.

`public/index.php` and `public/.htaccess` are copied unchanged. Their existing relative paths work with `htdocs/public` retained. The existing local `.env` and APP_KEY remain unchanged. Privately copy that SAME key into the final online `.env` later; do not run `key:generate` or the Composer `setup` script.

The upload folder includes private reservation uploads. Keep the release on your own machine until the secure hosting configuration is complete. Do not share it publicly or commit it to Git.

## Compatibility and problems found

- The selected project's locked Laravel version is **12.64.0**, requiring **PHP 8.2 or newer within PHP 8.x**, including the PHP 8.4 currently advertised by InfinityFree. Local PHP is 8.2.12. Dependency PHP requirements are compatible in principle; account extension availability still needs verification.
- Important required extensions include `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `ctype`, `filter`, `hash`, `session`, `tokenizer`, `dom`, `gd`, `iconv`, `json`, `libxml`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`, and `pcre`. Use 64-bit PHP. `package-audit.json` lists the full locked-package set.
- Original `vendor/composer/autoload_classmap.php` is 1,095,417 bytes and `autoload_static.php` is 1,194,725 bytes. Both exceed the host's PHP limit. Only the release disables optimized autoloading and omits development dependencies.
- InfinityFree has no SSH/Artisan terminal, npm, persistent queue workers, or cron scheduler. Build dependencies and assets locally. `QUEUE_CONNECTION=sync` avoids workers; `SESSION_DRIVER=file` and `CACHE_STORE=file` use writable local hosting storage, including login-code cache locks. Laravel 12 uses `CACHE_STORE`, not the older `CACHE_DRIVER` setting.
- Hourly pending-reservation auto-cancellation and scheduled daily backups in `routes/console.php` **cannot run automatically on the free plan**. They were not removed or replaced. Use a hosting plan with cron if these existing automations are mandatory; do not expose Artisan/scheduler web endpoints or change business rules to hide this limitation.
- PHP/JS/HTML files must be under 1,000,000 bytes, `.htaccess` under 10,000, and other files under 10,000,000. Inode, CPU, memory, upload, and execution-time limits still apply. Spreadsheet/Word generation and manual encrypted backups need small-to-representative online trials. Backups larger than 10 MB cannot safely remain on this hosting.
- External SMTP with STARTTLS **port 587** is required. An InfinityFree administrator's May 2026 guidance says other SMTP ports are blocked. The template uses Laravel's `MAIL_SCHEME=smtp` for STARTTLS; do not use `smtps` on 587 or disable certificate verification. SMTP must be configured before public email verification and staff login work.
- The selected source's `/` route redirects to login. A separate Public Landing Page is **not present in this copy**. The XAMPP copy differs; it has not been substituted into the release.
- Reservation permits are saved on the private local disk. The selected source loads documents for admin details, but no dedicated document-download route or approval-letter upload/download workflow was found. Online storage preserves those files; it does not create a missing feature. Reconcile which copy contains the intended approval-letter workflow before deciding the final source.
- No public-disk upload workflow was found in the selected controllers. Private permits must NOT be exposed through `/storage`. No symlink is required for them. InfinityFree does not support `storage:link`. If choosing a different source that depends on public-disk URLs, re-audit that source and provide a specific public-only storage mapping rather than exposing private storage.
- Local MySQL-starting middleware is already limited to Windows + local environment. It skips production automatically; no change is necessary.
- Frontend asset URLs use Laravel helpers/Vite. The release contains the logo, your building photo, and built assets. Poppins and other external font/chart resources may still require browser Internet access. No development asset server is uploaded.

### Completed local verification

The prepared release is `releases/prepared-20261007-131210-97c636/htdocs`. It passed Composer production platform checks, Laravel package discovery and boot of 90 application routes, a Vite production build, and the package audit: **7,530 files / 37,324,077 bytes**, with **no file-size or missing-asset issues**. Staged autoload classmap/static files are now **9,149 / 29,008 bytes**. Private reservation documents are included, while production `.env` remains absent.

The staged public front controller and public `.htaccess` match the source byte-for-byte. The preparation verified the source `.env` and Composer settings unchanged, and development dependencies remain installed locally. The current port-8000 login still responds HTTP 200.

Routing/protection was also exercised on the local XAMPP Apache server against this isolated release: clean-path logo/building-image URLs returned 200; `/composer.json`, `/vendor/autoload.php`, `/storage/logs/laravel.log`, and `/.env.example` returned 403; `/public/.env.example` returned 404. These checks validate the prepared rewrite layout locally. InfinityFree's own Apache, PHP extensions, MySQL, SMTP, and resource limits still require account testing. No destructive command, SQL import/export, migration, or database alteration was performed during preparation.

## Module review

These conclusions are code inspection and local package checks, not a claim that the final hosting account has passed testing.

| Module | Preparation result / remaining check |
|---|---|
| Public Landing Page | Separate landing page absent in selected source; `/` currently redirects to login. |
| Reserve a Schedule | Existing `/reserve`, email verification, capacity, agreements, and equipment restrictions retained. Requires MySQL, writable storage, and SMTP. |
| Schedule / Pencil Booking | Existing public availability and admin scheduling/priority/waiting-list logic retained. Test calendar AJAX in a real browser online. Pending timeout automation requires cron unavailable on free hosting. |
| Track Reservation | Existing `/track`, session references, signed equipment access links, and notification records retained. Set canonical HTTPS APP_URL and keep APP_KEY. |
| Admin Login | Current admin password/session flow retained; file sessions must be writable. |
| Staff Login | Existing email-code flow retained; real staff inboxes and SMTP required. Local-only test bypass remains disabled in production. |
| Admin / Staff Dashboard | Existing routes and MySQL queries retained; verify representative data and hosting resource limits. |
| Reservations | Existing CRUD/approval workflow retained; import all related tables and private documents. |
| Facilities / Equipment | Existing CRUD and stock checks retained; approvals/rejections continue recording notifications. |
| Reports | Existing CSV, Excel, Word routes retained. Verify XML/GD/ZIP extensions, temporary-file permissions, and execution/memory limits. No PDF report generator was found in the selected source. |
| Business Intelligence | Existing analytics/filter/export logic retained; not changed for deployment. Verify MySQL import and compiled browser assets. |
| Email Verification / Notifications | Database notifications remain available; email needs configured external SMTP. Test public OTP, staff login OTP, password reset, submission, and equipment decision mail. |

## What is needed from your account later

Provide the final domain/HTTPS APP_URL, domain-specific `htdocs` location, DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD from the InfinityFree control panel. Do not substitute localhost for its database hostname. Also confirm PHP version/required extensions, SSL activation, and your external SMTP host, port 587 support, username/app password, and authorized sender address. FTP host/username and an access method are only needed if you later authorize upload. Do not send account passwords in screenshots, commits, reports, or public files; supply secrets privately when configuring.

## Export the local database safely in XAMPP

1. Start Apache and MySQL in XAMPP. Open `http://localhost/phpmyadmin`.
2. Privately open the selected project's `.env` and identify `DB_DATABASE` (and local DB port if different). Do not post the password or APP_KEY. Export that exact database, not a guessed name.
3. Select the database in phpMyAdmin, then choose **Export → Custom → SQL**.
4. Select **all tables**, including users, reservations, documents, equipment pivots, notifications, audit records, system settings, reports, migration history, and all related tables. Include **structure and data**; keep AUTO_INCREMENT values and UTF-8/utf8mb4 encoding.
5. Choose **Save output to a file**. Use ordinary SQL, or gzip if the remote import size requires compression. Avoid CREATE DATABASE / USE statements so the dump imports into the host-assigned database name.
6. For a new empty hosting database, omit DROP DATABASE, DROP TABLE, and TRUNCATE statements. Do not execute an export file against your local database. Export itself does not delete or modify data.
7. Save the dump outside `public` and the upload package, for example in your private Documents backup folder. It contains personal data and hashed passwords; keep it private. Keep an additional private copy of the original `.env` and uploaded document folders.
8. Record local table counts using phpMyAdmin; these become the import verification baseline. Avoid editing live reservations between export and final upload, or take a fresh export at the agreed cutover.
9. Later create an **empty** MySQL database in InfinityFree's panel, open its phpMyAdmin, select that database, and use **Import** to load the SQL dump. Import the data; do not run migrations, seeds, fresh/reset/wipe, or a restore against the existing local database.
10. If import exceeds the panel limit, use supported compressed SQL or split into valid table-by-table SQL exports preserving relationships. Do not split arbitrary SQL lines. Keep schema/data and IDs intact. Check table counts, user roles, reservation references, notifications, and document paths after import. If foreign-key order is an issue, use phpMyAdmin's export/import foreign-key-check options and verify afterward.

An encrypted `.mcstbak` archive is not a SQL export and cannot be imported by phpMyAdmin. Use the SQL export above.

## Manual InfinityFree steps after you provide the account details

1. Confirm the intended source copy, create domain/hosting account and empty MySQL database, and enable SSL. Confirm required PHP extensions. Record the actual domain-specific upload root.
2. Complete a PRIVATE copy of `production.env.example`, rename it `.env`, and fill the final APP_URL/database/SMTP values. Copy the EXISTING APP_KEY from the chosen local `.env` without displaying it. Preserve extra existing `GYM_*` settings; do not invent or reset operating hours, notice periods, capacity, or other rules. Keep APP_DEBUG=false and HTTPS secure cookies.
3. Do not cache configuration locally for this release: cached local connection settings and Windows paths must not be uploaded. Final `.env` is read directly on hosting. Do not run `composer setup` or regenerate the key.
4. Export/import SQL as above. Keep uploaded files separate from SQL. No migration is necessary after importing the complete current database.
5. Using FTP with hidden files enabled, upload the **contents** of the prepared `htdocs` folder into the host's actual `htdocs`. Keep the nested `public` directory. Upload root `.htaccess` FIRST, then private-directory deny files, then application files, then the final `.env`. Do not upload credentials before protection is in place. Do not put files above the account's allowed htdocs.
6. Ensure `storage/app/private`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache` exist and are writable by PHP. Start with directories 755 and files 644; use account-supported writable settings if required, rather than blindly using 777. Preserve existing online uploads during any later update.
7. Upload all copied private reservation documents under `storage/app/private` using their original relative paths. If the selected source has additional approval letters, include their existing private paths after the source discrepancy is resolved. Do not copy backups, local logs, caches, or active local sessions.
8. Visit `/login`, `/reserve`, `/track`, calendar/availability, dashboards, reservations, facilities/equipment, report exports, and BI in a real browser. Verify CSS, JS, MCST logo, building image, remembered sessions, form CSRF, and signed links.
9. Confirm `/.env`, `/public/.env`, `/composer.json`, `/vendor/autoload.php`, `/storage/logs/laravel.log`, and a known private document URL return **403/404**, never file contents. Stop before entering real data if they do not. Verify Laravel deep routes resolve without `/public` in user-facing URLs. Apache behavior must be verified on the actual host.
10. Test public OTP delivery, staff OTP, password reset, submission mail and equipment approval/rejection notification. Test attendee 2000/2001 and both required agreements, without changing these rules. Check document storage and download workflows that actually exist in the selected source. Trial representative report export sizes.
11. Keep a copy of the SQL export, source files, uploads, and APP_KEY offline. Plan manual backups or use a host with cron for the existing automations. Do not claim full deployment readiness until the missing source modules and hosting checks are resolved.

### What to upload / omit

Upload only prepared `htdocs/` contents: `app`, `bootstrap`, `config`, `database` (migrations/factories/seeders, no local databases), `public` including `build` and images, `resources`, `routes`, `storage` with private documents and clean runtime directories, production `vendor`, `artisan`, `composer.json`, `composer.lock`, all included `.htaccess` files, and the final PRIVATE `.env`. `.env.example` is optional.

Do NOT upload the whole XAMPP or Downloads directory, `node_modules`, `.git`, tests, preparation scripts/audit helpers, SQL dumps, local `.env`, backup archives, `.restore-backups`, logs, development sessions/cache, Vite `public/hot`, or the release's outside-htdocs reports. No web installer, migration runner, phpinfo page, or credential-dumping diagnostic has been added.

### Rebuild an isolated package locally

From the XAMPP project, use PowerShell:

```powershell
./deployment/infinityfree/prepare.ps1 -SourcePath 'C:\Users\THINKPAD\Downloads\MCST Gym Reservation System\mcst_gym_reservation_system'
```

Each run creates a new release; it never overwrites the source or an older release. Read its `PREPARATION.txt` and `package-audit.json`. Source `.env`, Composer settings, front controller, and public routing are checked unchanged. The command performs no database operations, upload, or final connection configuration.

## Verified hosting references

- [InfinityFree features: PHP 8.4, MySQL/MariaDB, SSL and .htaccess](https://www.infinityfree.com/)
- [Official Laravel hosting guide: htdocs/public layout, storage limitations, workers and cron](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578)
- [Composer autoload file-size fix](https://forum.infinityfree.com/t/hosting/114556)
- [File-size restrictions](https://forum.infinityfree.com/t/upload-a-website-impossible/98906)
- [May 2026 SMTP guidance: port 587](https://forum.infinityfree.com/t/smtp-error-110-connection-timed-out-on-cafeitoco-shop/118791)
