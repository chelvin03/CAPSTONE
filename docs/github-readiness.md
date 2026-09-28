# GitHub readiness review

## Connection and current state

- Existing remote: `https://github.com/chelvin03/CAPSTONE` (`origin`).
- Local branch: `main`, starting at `726dc4b`.
- Fetched remote `main`: `058cc45`, four commits ahead of local HEAD.
- The review included the updated application and pre-existing user changes. The subsequent source publication was explicitly requested by the project owner.
- Remote history was fetched and compared without changing working files.
- Windows Git's Schannel transport failed in this environment. A command-scoped OpenSSL backend using the existing trusted XAMPP CA bundle successfully read the remote. No permanent Git TLS settings or certificate verification were disabled.

Publication scope: commit and push the updated project to the existing GitHub repository so it can be set up on another device. Include source, migrations, dependency lockfiles and documentation; exclude local settings, live data, uploads and temporary artifacts.

## Existing sensitive Git history

The reviewed remote commit `058cc45` tracks `.env` despite ignore rules. It contains a nonempty `APP_KEY`; its value is deliberately not included here. A prior remote commit also added a database SQL dump, subsequently deleted in `058cc45`. Deletion from the current tree does not remove earlier Git objects.

Before publication/release, the repository owner should assess exposure and rotate any actual secrets involved. Application-key rotation needs a plan for encrypted data and active sessions; this review did not rotate keys or alter the running environment. The current tracked `.env` should be removed from the future Git tree without deleting the local environment file. Historical cleanup requires coordination with collaborators and an explicitly approved history-rewrite plan; it has not been attempted.

The local `.gitignore` was strengthened for `.env.*` (except `.env.example`), temporary artifacts, SQL dumps and SQLite database files. Ignore patterns do not untrack files already committed. Local `.tmp` artifacts are tracked by the old base commit; the remote already removes them. Reconcile those remote removals rather than reintroducing the artifacts.

## Stack and structure

Observed runtime: PHP 8.2.12, Laravel 12.64.0, MariaDB 10.4.32 through the `mysql` driver, Node 24.18.0 and npm 11.16.0. Dependency requirements and reproducible versions are in `composer.json`/`composer.lock` and `package.json`/`package-lock.json`.

- Backend: Laravel 12, PHP >=8.2, Breeze authentication scaffolding, Eloquent, migrations, Blade views.
- Frontend: Vite 7, Tailwind CSS 3 configuration, Alpine 3, Axios and Bootstrap Icons. No Bootstrap UI framework. Dashboard typography uses Poppins with system fallbacks.
- Reporting: PhpSpreadsheet and PHPWord; no configured PDF renderer.
- Testing: Pest 3/PHPUnit with in-memory SQLite, fake mail, array cache/sessions and synchronous queues.
- Formatting: Laravel Pint.
- `app/Http/Controllers`: admin, staff, public reservation and authentication actions.
- `app/Services`: BI analytics, staff monitoring, login-code delivery, backups and reports.
- `app/Models`, `database/migrations`, `database/seeders`: schema/domain records and sample setup.
- `resources/views`, `resources/css`, `resources/js`: presentation and frontend source.
- `routes/web.php`, `routes/auth.php`, `routes/console.php`: HTTP/authentication/scheduled tasks.
- `storage/app/private`: private uploads and backup archives; `storage/app/public`: public-disk files; both ignored.
- `public/images/mcst-logo.png`: intentional tracked static asset.
- `vendor`, `node_modules`, `public/build`: generated dependencies/build output, ignored.

The implemented public requestor flow does not require a user login. Internal authenticated operational roles are Admin and Staff. Legacy user/profile fields exist but do not imply a public requestor-login feature.

## Environment and database

The local `.env` remains untouched by GitHub preparation. `.env.example` contains placeholders, the MCST application name and Asia/Manila timezone. Groups of settings include:

- `APP_*`: application identity, environment, secret key, debug flag, URL and timezone.
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, optional `DB_URL`: database access. Example defaults to SQLite; current local installation uses MariaDB.
- `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`: database-backed runtime state in the local application.
- `MAIL_*`: SMTP configuration. Production authentication requires working SMTP delivery. No credentials are included in the example.
- `GYM_LOCAL_TEST_ADMIN_PASSWORD_ONLY`, `GYM_LOCAL_TEST_STAFF_PASSWORD_ONLY`: explicit local-only test-account exceptions, false in the example; they do not apply outside `APP_ENV=local`.
- `GYM_RESERVATION_*`, `GYM_OPENING_TIME`, `GYM_CLOSING_TIME`, `GYM_BACKUP_*`: booking rules, operating hours and backup settings.
- Optional Redis, AWS/filesystem and mail-provider settings are defined in `config/`.

The current application database has 23 applied migrations. Schema inspected without exporting row contents:

| Domain | Tables |
| --- | --- |
| Accounts | users, requestor_profiles, password_reset_tokens, sessions |
| Reservations | reservations, reservation_documents, reservation_status_histories |
| Facilities/equipment | facilities, equipment, reservation_equipment, schedule_blocks |
| Reporting/feedback | analytics_reports, feedback |
| Administration | audit_logs, backup_records, system_settings, email_templates, announcements, notifications |
| Runtime | migrations, cache, cache_locks, jobs, job_batches, failed_jobs |

Foreign keys connect reservations to facilities and optional users, documents/history/equipment to reservations, and administrative activity to users. Important indexes already cover scheduled date/status and facility/time conflicts. Migrations are the source-controlled schema; live database dumps must not be committed.

`DatabaseSeeder` runs `EquipmentSeeder` and creates a sample `test@example.com` user. Equipment seeding uses `firstOrCreate` for chairs, tables, lighting and sports equipment. The main seeder is not an idempotent production-account provisioning procedure; do not rerun it against an existing database just to connect GitHub.

Existing storage includes four `.mcstbak` archives and four PDF/image uploads. Contents were not exported. Git ignores those directories. Database records, approval letters, private backups and the application key must be transferred through a separate controlled deployment/backup process, not GitHub source control.

## Validation completed

- Full Laravel test suite: **124 passed, 885 assertions**.
- Vite production build: passed.
- Blade compilation: passed.
- Staff PHP formatting and read-only MariaDB smoke check: passed.
- Ignore rules confirmed for local environment files, sample dump paths, SQLite files and private backup files; `.env.example` remains trackable.
- Browser visual review remains outstanding because no connected browser is available.

## Publication procedure and remaining history cleanup

1. Publish to the existing `origin/main`, as requested by the project owner.
2. Preserve local `.env` and all working files. The four remote commits only add `.env`, remove `.tmp` artifacts, and ignore `.tmp`; there are no remote application-code changes. Remove the temporary artifacts from the Git index while retaining their local copies, then merge remote history using the reviewed source-only tree. This preserves the remote artifact removals while excluding its environment-file addition.
3. Ensure the resulting Git tree excludes `.env`, SQL dumps, private uploads, backup archives and temporary artifacts. Address exposed key/history separately.
4. Review the exact staged file list and diff, run checks on the reconciled result, then commit/push only the approved source update using a normal fast-forward or review branch. Do not force-push.

GitHub stores source code and migration definitions. It does not automatically host the Laravel application or synchronize its live database.
