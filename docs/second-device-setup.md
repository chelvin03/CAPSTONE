# Running MCST Gymnasium on another device

GitHub stores the application source, migrations and dependency versions. Each development computer needs its own PHP/database environment. For browser-only access from a phone or another computer, the application must instead be running on an accessible server; GitHub Pages cannot execute Laravel/PHP.

## Clone and install

Install Git, Composer, PHP 8.2+ with the extensions required by Composer, compatible Node/npm, and MariaDB/MySQL (XAMPP is suitable for local development).

```shell
git clone https://github.com/chelvin03/CAPSTONE.git
cd CAPSTONE
composer install
npm ci
```

On a fresh checkout, copy `.env.example` to `.env`. Never replace an existing environment file during an update. Configure `APP_URL`, `APP_TIMEZONE`, database connection and SMTP settings locally. For a new MariaDB installation, create an empty development database and set:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mcst_gym_reservation_system
DB_USERNAME=your_database_user
DB_PASSWORD="your_database_password"
```

Use real local database credentials. Do not upload `.env`.

## Choose the data source

- **New development database:** run `php artisan key:generate`, then `php artisan migrate`. The default seeder adds sample equipment and a test user; it does not create the existing administrator/staff accounts. Have the project administrator provision accounts using Laravel password hashing and the correct approved role. Avoid rerunning the sample seeder against existing records.
- **Copy of the existing system:** transfer a database export and required private uploaded files through a trusted private channel, outside GitHub. Import into a separate database and configure its credentials. Preserve the appropriate application key if encrypted data requires it; coordinate replacement of any previously exposed key. Run `php artisan migrate:status` and apply pending migrations only after backing up the imported copy. Do not use `migrate:fresh`.

The repository intentionally excludes live accounts/password hashes, reservations, audit rows, uploaded approval letters, backups, sessions and local credentials. Cloning alone will not recreate these records.

## Build and run

```shell
npm run build
php artisan config:clear
php artisan serve
```

Open the URL printed by Artisan. For Apache, point the web root at the project's `public` directory. If public-disk assets are needed, use `php artisan storage:link`; keep private reservation documents private.

For the existing local test accounts only, these options can skip email codes with `APP_ENV=local`, while retaining password/approval/role checks:

```dotenv
GYM_LOCAL_TEST_ADMIN_PASSWORD_ONLY=true
GYM_LOCAL_TEST_STAFF_PASSWORD_ONLY=true
```

They apply only to `admin@mcst.edu.ph` with the admin role and `staff@mcst.edu.ph` with the staff role. They do not create accounts, reset passwords or work in production. Other accounts require working SMTP email delivery.

Verify the installation with `php artisan test --compact`. Tests use an isolated in-memory SQLite database. For remote live access, configure an appropriate PHP host, database, HTTPS, `APP_DEBUG=false`, private storage, SMTP and backups separately; a Git push is not a deployment.
