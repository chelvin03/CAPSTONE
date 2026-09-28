# Admin and staff login email codes

The existing login page is unchanged. Approved admins and staff now enter their password, receive an email code, and enter it on a separate page before Laravel signs them in. Public requestors still have no accounts.

## 1. Files inspected and changed

All paths below are relative to `C:\xampp\htdocs\mcst-gym-reservation-system`.

| File | Purpose |
| --- | --- |
| `app/Http/Requests/Auth/LoginRequest.php` | Validates the password without signing in; preserves password throttling and failed-login audit events. |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Checks approval and role, sends the code, and stores a temporary pending challenge in the session. |
| `app/Services/LoginCodeService.php` (new) | Generates six digits, hashes them with an application-key pepper, enforces limits, sends mail, and consumes the challenge once. |
| `app/Http/Controllers/Auth/LoginCodeController.php` (new) | Displays the page; handles verification, resend, cancel, and final login. Rechecks status, role, password and email changes. |
| `app/Mail/LoginVerificationCode.php` (new) | Defines the email subject and template. |
| `resources/views/emails/login-verification-code.blade.php` (new) | Email content; the code is only shown in the email. |
| `resources/views/auth/login-code.blade.php` (new) | Separate verification screen using the existing logo and guest layout. |
| `routes/auth.php` | Adds guest-only code, resend and cancel routes with POST/CSRF protection and request throttling. |
| `bootstrap/app.php` | Prevents submitted codes from being flashed into session old input after validation errors. |
| `config/mail.php` | Bounds SMTP socket timeout to 15 seconds. |
| `.env.example` | Corrects the SMTP scheme example. Your actual `.env` is not edited. |
| `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/LoginCodeTest.php` | Tests the two-step flow and security boundaries. |

Also inspected: `routes/web.php`, `RoleMiddleware`, `User`, registration, the guest layout, cache settings, and audit listeners. Protected routes already require `auth`, so no partially authenticated user is allowed through them. Existing role restrictions remain in place. Registration still creates pending staff accounts without logging them in.

## 2. Configure email in your local .env

The approved `staff@mcst.edu.ph` test account with the `staff` role can also use password-only login locally by setting `GYM_LOCAL_TEST_STAFF_PASSWORD_ONLY=true` and running `php artisan config:clear`. This defaults to false, applies only with `APP_ENV=local`, and does not grant administrator access.

For the seeded test account without a real inbox, set `GYM_LOCAL_TEST_ADMIN_PASSWORD_ONLY=true` in your local `.env` and run `php artisan config:clear`. This allows only the approved `admin@mcst.edu.ph` account with the `admin` role to log in using its existing password when `APP_ENV=local`. Other accounts and non-local environments still require email verification. The option defaults to false.

Use SMTP credentials from your email provider. Replace every example value:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD="your-smtp-password-or-app-password"
MAIL_FROM_ADDRESS="your-authorized-sender@example.com"
MAIL_FROM_NAME="MCST Gymnasium"

CACHE_STORE=database
SESSION_DRIVER=database
```

For port 587, `smtp` uses STARTTLS when the server offers it. For a provider requiring implicit TLS on port 465, use `MAIL_SCHEME=smtps` and `MAIL_PORT=465`. `tls` is not a valid `MAIL_SCHEME` in the installed Symfony mailer. Remove an old `MAIL_URL` override if you want these separate SMTP settings to apply. Use the provider's authorized sender address and SMTP/app password as required by that provider.

Login codes explicitly use the `smtp` mailer, even if the application's default mailer is `log`. They are sent synchronously: no queue worker is needed and no plaintext code is stored in queued jobs or Laravel mail logs. The email provider and recipient necessarily receive the code in the email. SMTP failures leave the user signed out and show a retry message.

The existing database cache and cache-lock tables hold expiring hashes and limits. No new migration or user columns are needed. Do not use `CACHE_STORE=array` or `null` for the running application; these do not retain challenges between requests. Do not clear the application cache during normal verification; doing so removes codes and rate limits.

Laravel references: [Mail configuration](https://laravel.com/docs/12.x/mail#configuration), [cache and atomic locks](https://laravel.com/docs/12.x/cache#atomic-locks), [authentication](https://laravel.com/docs/12.x/authentication).

## 3. Start and test in XAMPP

1. Start Apache and MySQL from the XAMPP Control Panel if your existing `.env` uses MySQL. Keep your existing database connection values. Make sure your approved admin and staff accounts have email addresses you can receive mail at.
2. In a PowerShell terminal in the project folder, refresh configuration and build the styles:

   ```powershell
   C:\xampp\php\php.exe artisan config:clear
   npm run build
   C:\xampp\php\php.exe artisan migrate:status
   ```

   Your existing `0001_01_01_000001_create_cache_table` migration must already be applied when using database cache. If it is pending and the cache tables do not exist, apply that migration:

   ```powershell
   C:\xampp\php\php.exe artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php
   ```

   Existing database sessions also require the existing `sessions` table. A working dashboard installation normally already has it. This feature does not require resetting, reseeding, or replacing your database.
3. Open your usual XAMPP login URL. With the default htdocs layout, it is usually `http://localhost/mcst-gym-reservation-system/public/login`. If you have an Apache virtual host pointed at the project's `public` directory, use that host's `/login` URL instead. Keep the same hostname throughout the flow so the session cookie is retained.
4. Sign out of an existing session first, then log in with an **approved admin**. You should see the code page, not the dashboard. Read the actual email and enter its code. You should reach the admin dashboard. Repeat with an **approved staff** account and confirm it reaches the staff dashboard and cannot access admin URLs.
5. While on the code page, manually open an admin or staff protected URL in another tab in the same browser. You should be redirected to login. Return to the code page to complete verification.
6. Enter a wrong six-digit code five times. Verification and resending should be blocked for the remainder of the 10-minute attempt window. Starting a fresh password login must not reset that limit.
7. Click **Resend code** immediately: it should be blocked for 60 seconds after the previous send. After 60 seconds it should send a new code; the previous code must fail. The initial email plus resends are limited to five sends per account per 10-minute window.
8. Leave a code unused for 10 minutes: it must fail. You can resend while the pending password session is younger than 30 minutes. After 30 minutes, sign in with the password again.
9. Test pending, rejected and disabled accounts: the existing status messages must appear, and no email should be sent. If an account is disabled or its email/password changes while a code is pending, verification must fail.
10. Test **Cancel and return to login**, then verify that the cancelled code cannot be reused. After successful verification, log out and check that protected URLs require login again.

The existing **Remember me** option only takes effect after code verification. As with Laravel's normal remembered login, a remembered authenticated session can persist without another prompt until logout/session removal. Sign out or use a fresh private browser session when testing a new login. Sessions established before this change are not retroactively invalidated.

If mail does not arrive, check the recipient's spam folder, SMTP host/port, authorized sender, and provider credentials. On XAMPP, PHP needs OpenSSL and a working certificate authority configuration for TLS. Keep certificate verification enabled. The app shows a generic delivery error rather than exposing SMTP credentials or message contents.

## 4. Automated tests

```powershell
C:\xampp\php\php.exe artisan test --compact tests/Feature/Auth
```

These tests use the separate in-memory SQLite database configured in `phpunit.xml` and fake outgoing mail. They exercise password-only access denial, both role redirects, account statuses, hashed storage, expiry, attempt/resend limits, invalidated codes, session binding, code replay, SMTP failure and cancellation. Fake mail verifies application behavior, not real inbox delivery; use the XAMPP steps above for that final check.
