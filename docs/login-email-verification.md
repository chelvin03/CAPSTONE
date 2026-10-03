# Admin and staff login

Approved administrators and staff sign in with email and password and go directly to their role dashboard. No login code is required and SMTP availability does not affect login.

Account approval, role restrictions, password throttling, Remember me, session regeneration, and login audit events remain enabled. Password reset email is unchanged.

The old /login/code endpoints are retired. Pending login challenges are cleared when submitting credentials again.

Run authentication checks: `C:\xampp\php\php.exe artisan test --compact tests/Feature/Auth`.
