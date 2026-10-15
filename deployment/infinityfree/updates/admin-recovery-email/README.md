# Admin recovery email update

Prepared from the XAMPP project. Nothing has been uploaded online.

1. Back up the online database and the existing versions of the files included in this package.
2. In the hosting control panel, open phpMyAdmin and select the website's database. Check whether users already has a recovery_email column. If absent, run add-recovery-email.sql in the SQL tab. Run it only once. Keep the SQL file on your computer; do not upload it to htdocs.
3. Upload the contents of this package's htdocs folder to the matching online htdocs paths, replacing the matching files. Preserve the folder structure. This assumes the htdocs/public layout used by this project's deployment guide. If the live site has newer custom changes in these files, merge the changes instead of replacing those files wholesale.
4. If cached routes or configuration exist, remove only bootstrap/cache/routes-*.php and bootstrap/cache/config.php through File Manager. Remove compiled PHP views inside storage/framework/views, keeping the directory and .gitignore. Keep bootstrap/cache/packages.php and services.php.
5. Keep the existing online .env and APP_KEY. Configure the online mail service privately using MAIL_MAILER=smtp and the provider's MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME. Use the scheme supported by that provider. Confirm APP_URL is the site's HTTPS address. Never upload the local .env or share SMTP credentials in chat.
6. Log in as admin, visit /profile, keep Email unchanged, fill Recovery Email with a real address, enter the current password, and Save.
7. Log out. Request Forgot Password using the existing admin login email. Confirm delivery to the recovery inbox, follow the link, reset the password, then log in with the original email and new password. Staff accounts should receive no reset email.

The database migration file is included for migration-managed deployments. If you applied the SQL manually on this host, do not run that migration there as well without reconciling the migration history. No asset build or vendor upload is required for this update.
