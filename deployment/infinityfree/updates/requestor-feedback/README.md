# Public and admin feedback

Prepared locally. No online changes have been made.

1. Back up the live database and matching online files.
2. In phpMyAdmin, select the website database and run allow-public-feedback.sql. It changes the existing feedback.user_id to nullable, so public requestors do not need accounts. Existing feedback is preserved. The feedback table is already used by dashboard analytics. Keep the SQL file on your computer.
3. Upload the new model, mail class, public/admin controllers, routes/feedback.php, and the four new Blade views to matching htdocs paths. Create missing directories. Preserve your online .env, APP_KEY and mail settings.
4. Edit online routes/web.php and add this line at the bottom, after the existing auth.php require, only once:

```php
require __DIR__.'/feedback.php';
```

The complete web.php is intentionally not included, to preserve the live reservation routes. If bootstrap/cache/routes-*.php exists, back it up and remove that generated route cache file so the new routes can load. Keep packages.php and services.php.

5. Verify /feedback opens. Then upload resources/views/public/partials/header.blade.php and resources/views/components/sidebar.blade.php, replacing backed-up files. These add Feedback to public navigation and the admin sidebar while preserving the current blue/logo design. Staff has no feedback access.
6. Test with an approved or completed reservation whose session has ended. Enter its reference and original booking email on /feedback, open the emailed link (valid for one hour), submit a 1–5 rating and optional comments, then verify Admin > Feedback. Only one response per reservation is allowed. Cancelled/rejected/pencil/upcoming reservations are ineligible. Comments are visible only to admin.

Use either the SQL update or the included migration on the live database; don't apply both without reconciling migration history. Local migration has been applied and five focused feature tests passed (31 assertions). No npm build or vendor upload is needed.
