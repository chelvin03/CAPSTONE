# MCST Gymnasium Reservation and Utilization System

Web-based reservation management and descriptive utilization analytics for Mandaluyong College of Science and Technology.

- Public requestors submit and track reservations without an account.
- Administrators manage reservations, facilities, equipment, reports and BI analytics.
- Approved staff monitor reservations, schedules, facilities and equipment through read-only pages.

## Project documentation

- [Repository inventory and GitHub readiness](docs/github-readiness.md)
- [Staff dashboard behavior, permissions and verification](docs/staff-dashboard-review.md)
- [BI metrics and data availability](docs/bi-dashboard-review.md)
- [Login email verification and local test accounts](docs/login-email-verification.md)

## Development setup

Requirements: PHP 8.2 or newer with the extensions required by `composer.lock`, Composer, Node/npm compatible with the locked Vite version, and either MariaDB/MySQL or SQLite. The existing XAMPP installation uses MariaDB; tests use isolated in-memory SQLite.

For a **new checkout**, install locked dependencies with `composer install` and `npm ci`. Copy `.env.example` to `.env` only if `.env` does not already exist. Configure the database, application URL/timezone and SMTP credentials, then generate an application key only for that new environment. Run `php artisan migrate` against the intended development database and `npm run build`. Serve Laravel with a web root pointing at `public/`, or use `php artisan serve`.

Existing installations must retain their `.env`, application key, uploaded files and database. Do not use `migrate:fresh` or rerun sample seeders against existing data. The sample seeder does not provision the application's production administrator; account provisioning is a separate administrative step.

Run checks:

```shell
php artisan test --compact
npm run build
php artisan view:cache
```

Do not commit environment secrets, live database dumps, approval-letter uploads or backup archives. Earlier repository history contains an environment file and a database dump; the updated source tree excludes these. Historical cleanup and any necessary secret rotation remain separate tasks.

For another computer, follow the [second-device setup guide](docs/second-device-setup.md). GitHub provides the source code; it does not host this Laravel application or copy its live database automatically.

---

The original Laravel framework information follows.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Local database startup

Open the app normally using `php artisan serve` and http://127.0.0.1:8000.
On Windows with `APP_ENV=local`, requests automatically start XAMPP MySQL
from `C:\xampp` when the configured local MySQL port 3306 is unavailable.
The app waits up to 15 seconds before reading database sessions. Existing
MySQL processes are reused, and the database remains running after requests.
This does not run in testing or production, or for remote database connections.
If startup fails, the page shows a short service-unavailable message; check
the Laravel and XAMPP MySQL logs for details, then refresh.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
