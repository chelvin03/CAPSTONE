# Public reservation email verification

## Diagnosis and inspected architecture

This project runs Laravel 12.64.0 with PHP 8.2.12; Composer requires PHP ^8.2 and Laravel ^12.0. The public workflow remains Contact Info -> Email Verification -> Event Information. Requestors are not users and are never authenticated. Reservations retain nullable user_id, contact_person, contact_email, contact_number and reservation_type; the existing public-requestor migration already supports this. No database changes or migrations were needed.

Inspected routes/web.php, routes/auth.php, PublicReservationController, the authentication controller/request, Reservation and User models, public reservation views/layout, PreventBackHistory and bootstrap middleware, existing Mail classes, migrations, tests, config/mail.php, config/logging.php, .env.example, and the configured Laravel log. There are no application notification classes for this workflow. Existing public OTP delivery uses PublicRegistrationCode synchronously; no queue worker is required.

The exact old message, "Could not send your verification code. Please try again in one minute.", came from the catch(Throwable) around Mail::mailer('smtp')->to(...)->send(...) in PublicReservationController::sendVerificationCode. That catch discarded the exception. A connection/authentication-only probe (no email sent) reached Gmail and failed with SMTP 535 authentication/credentials rejection. Config cache was absent. This is not the first-request cooldown, a missing Mailable, a queue problem or a recipient routing problem. The configured sender credentials need correcting; code cannot supply a valid Google App Password.

The handler now logs exception class, numeric code, source location and a safe diagnostic category to the configured Laravel log (normally storage/logs/laravel.log). Raw SMTP messages, debug transcripts, passwords and OTPs are excluded because transport exceptions can include sensitive contents. The public response contains only a generic failure message and retry interval.

## Files changed in this update

- app/Http/Controllers/PublicReservationController.php: validated requestor details, OTP lifecycle, delivery diagnostics, trusted Step 3 and submission checks, session cleanup.
- app/Mail/PublicRegistrationCode.php: professional reservation subject; reused existing Mailable.
- resources/views/emails/public-registration-code.blade.php: branded email and expiry instructions.
- resources/views/public/reservations/create.blade.php: existing layout retained; session-backed fields, guarded event section, resend feedback, edit invalidation, server-authorized navigation.
- routes/web.php: added POST /reserve/edit-details (reservation.edit_details).
- .env.example: Gmail placeholders and Laravel 12 MAIL_SCHEME guidance.
- tests/Feature/PublicVerificationTest.php and tests/Feature/PublicReservationPermitTest.php: focused verification and reservation coverage.

Created: this document. No new package, model, migration, authenticated role or duplicate OTP Mailable. config/mail.php was reviewed and already supports the required environment variables; local .env was not overwritten.

## Routes and behavior

- GET /reserve (reservation.create): existing form, restores contact details. GET /reserve?step=3 redirects to /reserve unless session verification is valid. Event fields are not rendered for an unverified browser.
- POST /reserve/send-code (reservation.send_code): validates all Step 1 fields, stores public_reservation_details, sends/replaces a code. Existing throttle:5,1 retained.
- POST /reserve/verify-code (reservation.verify_code): verifies email, challenge, expiry and hash; returns the guarded Step 3 URL. Existing throttle:30,1 retained.
- POST /reserve/edit-details (reservation.edit_details): new CSRF-protected endpoint invalidates proof and challenge while preserving contact details.
- POST /reserve (reservation.store): existing final submission; requires matching, unexpired, verified server proof. Browser email_verified fields are ignored.

OTP: random_int(100000, 999999), stored using Laravel Hash::make. Session public_email_challenge contains email, hash, expires_at, verified=false; raw codes are only used for sending. Codes expire after 10 minutes. Verification consumes the challenge and creates public_email_verified with verified=true, matching email and a 30-minute submission window.

Resends: 60-second cooldown and at most five delivery attempts per email per ten minutes, plus the route IP throttle. A fresh resend replaces the old code and expiry; cooldown rejection preserves an existing challenge. Delivery failure leaves no usable challenge. Initial sending is allowed when limits are unused.

Guessing: five failed code comparisons per email/IP in ten minutes; the fifth failure invalidates the challenge. After the lock window, a new code must be requested. Resends do not reset the failed-attempt limiter. Separate messages distinguish incorrect, expired and missing-session codes. Malformed codes are rejected before comparison.

Edit Details clears verification server-side. A changed email on send also clears prior proof before checking send limits. The final submitted email must match the trusted proof. Successful reservation storage clears public_email_challenge, public_email_verified and public_reservation_details; the reservation/reference-number page remains unchanged.

## Local mail configuration

Set these values manually in local .env; do not commit the real credentials:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@gmail.com
MAIL_PASSWORD="your-google-app-password"
MAIL_FROM_ADDRESS="your-sender@gmail.com"
MAIL_FROM_NAME="MCST Gymnasium Reservation System"
```

Use a Google App Password for the sender account, not its ordinary sign-in password. Google instructions: https://support.google.com/accounts/answer/185833 . Availability depends on the Google account's security/admin settings. MAIL_SCHEME=smtp on port 587 supports STARTTLS through the installed Symfony transport; do not substitute MAIL_SCHEME=tls. This Laravel configuration does not read MAIL_ENCRYPTION. Leave MAIL_URL unset unless intentionally overriding these settings. The recipient is the requestor's entered email; it need not equal MAIL_USERNAME.

After editing .env:

```powershell
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan view:clear
```

Restart the running artisan server after credential changes. A full optimize:clear is optional during maintenance; it also clears cached rate limits, so do not repeatedly run it during OTP testing. No migrations, reseeding or queue worker are needed. Keep persistent session/cache stores such as the project's existing database drivers.

## Validation and browser checks

Automated run: 77 tests passed (490 assertions), including admin/staff authentication and existing modules. Frontend build passed. Focused JS checks exercise complete/autofilled details and missing/invalid fields. Mail tests use fakes; real inbox delivery remains blocked by Gmail's 535 rejection until sender credentials are corrected.

1. Update local SMTP credentials, clear config and restart the server from this workspace. Open /reserve in a fresh browser session.
2. Enter all contact fields and a real recipient email, then Continue to Verification. Confirm Step 2, delivery notice and actual inbox/spam delivery.
3. Enter a wrong six-digit code: expect a clear error. Enter the correct code: expect Step 3 with contact details preserved.
4. Before verifying, open /reserve?step=3: expect redirect and no Event Information fields. A forged email_verified=1 cannot submit a reservation.
5. Wait 60 seconds and resend. The old code must fail; the newest code must work. Immediate resends are blocked. Five sends per ten minutes reach the overall limit.
6. Leave a code unused for ten minutes: expect the expiry message. Enter five wrong codes: expect challenge invalidation and a ten-minute lock before requesting a new code.
7. Verify, click Edit Contact Details, and change email. Previous proof is revoked; verify the new email before Step 3 can be opened again. Refreshing preserves saved Step 1 details.
8. Submit valid event details and a permit. Confirm normal reference-number/confirmation behavior, no requestor account, and that /reserve?step=3 is denied afterward.
9. Confirm admin and staff still log in directly with email/password.

Run tests with `C:\xampp\php\php.exe artisan test --compact` and assets with `npm run build`.
