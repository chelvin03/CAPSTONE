# Temporary reservation 403 diagnostic

The submission failure is not fixed or attributed to a confirmed cause yet. PDF export was verified online by the user for all five report types.

Download a private backup of the online `htdocs/public/index.php`, then upload the current local `public/index.php` to that exact path. Do not change the root index, `.htaccess`, environment, permissions, CSRF rules, file requirements, or routes.

The temporary block runs before Composer and Laravel, only on GET `/reserve/request` and POST `/reserve`. It writes `storage/logs/reservation-entry.log` with UTC time, method, path (no query string), random diagnostic ID, and the `php_entry` stage. It records no form body, uploads, cookies, IP address, credentials, or participant details. It attempts response headers `X-MCST-PHP-Reached`, `X-MCST-Diagnostic-ID`, and `X-MCST-Trace-Written` before normal framework handling. A logging failure does not intentionally stop the request; the framework itself can still fail if runtime directories are unwritable.

First open `/reserve/request` and confirm a new GET entry. This control establishes that the uploaded entry point is executing and its trace destination works. Only after that control succeeds, submit one dedicated test booking and inspect whether a POST entry is present. Check the admin list for duplicates before another attempt.

- GET control present, POST absent: evidence suggests the rejected POST does not execute this PHP entry point. Share these observations with hosting support; a specific server/security rule is not proven.
- POST present: PHP started; investigate application startup, middleware, exception handling, and routing. The controller marker alone cannot rule out these stages.
- No GET control: verify upload path, cached code, permissions, and whether this is the actual entry point before making conclusions.

Local verification: three diagnostic unit tests plus seven workflow tests passed (10 tests, 68 assertions). Tests cover scoped logging, omission of query/cookie data, and continuing when the log directory is unavailable. Live header/trace behavior still needs verification. No reservation records were changed by the diagnostic tests.

Remove the block between BEGIN/END temporary reservation entry diagnostic comments after diagnosis, as well as the controller's temporary warning marker. Keep the trace private under protected storage. Do not expose it with a public diagnostic route. The normal Composer/bootstrap/request handling below the diagnostic remains unchanged.
