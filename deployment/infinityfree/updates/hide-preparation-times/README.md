# Hide setup and cleanup times

Prepared locally; not uploaded. Replace the four packaged views in their matching online htdocs folders after downloading backups:

- resources/views/admin/reservations/_form.blade.php
- resources/views/admin/reservations/edit.blade.php
- resources/views/admin/reservations/show.blade.php
- resources/views/staff/partials/dashboard-events.blade.php

Setup and cleanup inputs are removed from the admin create/edit forms, their values are hidden on reservation details, and the two columns are removed from today's Staff Dashboard schedule. Start/end times stay visible. The Admin details view also retains the earlier Account/Event Type cleanup.

Database columns and stored values are preserved. No SQL, migrations, controllers, CSS, JavaScript or build changes are needed. Staff AR permissions and links are preserved.

15 focused checks passed (110 assertions), including hidden preparation times with stored values preserved, dashboard filtering, AR access and admin booking workflows. No setup/cleanup labels or inputs remain in current application views.
