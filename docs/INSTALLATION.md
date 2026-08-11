# Installation Guide

This guide describes a production-style Ubuntu 24.04, Apache, PHP 8.3, and MySQL deployment. Adapt package names for other platforms.

## 1. Install system packages

```bash
sudo apt update
sudo apt install apache2 mysql-server composer git unzip cron \
  php php-cli php-mysql php-mbstring php-curl php-xml php-zip php-gd php-intl
sudo a2enmod rewrite headers ssl
```

PDF generation is provided by Dompdf through Composer. QR generation and other application assets are included in the source.

## 2. Download the application

```bash
sudo git clone https://github.com/villanianalytics/OpenCRM.git /var/www/opencrm
cd /var/www/opencrm
sudo composer install --no-dev --optimize-autoloader
sudo cp .env.example .env
```

For repeatable production releases, deploy a reviewed tag or commit rather than an unpinned branch.

## 3. Create the database

```sql
CREATE DATABASE opencrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'opencrm'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON opencrm.* TO 'opencrm'@'localhost';
FLUSH PRIVILEGES;
```

Do not expose MySQL publicly. Use a dedicated database account rather than `root`.

## 4. Configure the environment

Edit `/var/www/opencrm/.env`. At minimum, set `APP_URL`, `APP_KEY`, all database values, and the initial administrator values. Generate the application key with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

The key protects encrypted application settings. Store it in a password manager or secrets system; losing it prevents encrypted credentials from being recovered. See [CONFIGURATION.md](CONFIGURATION.md).

## 5. Set permissions

The web server must be able to write uploads, logs, and backups, but should not own the application source.

```bash
sudo chown -R root:www-data /var/www/opencrm
sudo find /var/www/opencrm -type d -exec chmod 0750 {} \;
sudo find /var/www/opencrm -type f -exec chmod 0640 {} \;
sudo chown -R www-data:www-data /var/www/opencrm/storage
sudo find /var/www/opencrm/storage -type d -exec chmod 0750 {} \;
sudo find /var/www/opencrm/storage -type f -exec chmod 0640 {} \;
```

Ensure `storage/uploads`, `storage/logs`, and `storage/backups` exist.

## 6. Configure Apache

Create `/etc/apache2/sites-available/opencrm.conf`:

```apache
<VirtualHost *:80>
    ServerName crm.example.com
    DocumentRoot /var/www/opencrm/public

    <Directory /var/www/opencrm/public>
        Options -Indexes
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>

    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>
    ErrorLog ${APACHE_LOG_DIR}/opencrm-error.log
    CustomLog ${APACHE_LOG_DIR}/opencrm-access.log combined
</VirtualHost>
```

Enable the site and HTTPS:

```bash
sudo a2ensite opencrm
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d crm.example.com
```

Set `SESSION_SECURE=true` only when the application is served through HTTPS; production must use HTTPS.

## 7. Initialize the database

```bash
cd /var/www/opencrm
sudo -u www-data php bin/migrate.php
sudo -u www-data php tests/smoke.php
sudo -u www-data php tests/permissions_smoke.php
```

The migration is idempotent. It creates the administrator only if the supplied username/password are present and no matching account exists. Remove `ADMIN_PASSWORD` from `.env` after the initial account is created, while retaining access through a password manager.

## 8. Schedule workers

Create `/etc/cron.d/opencrm` and adjust the path if needed:

```cron
* * * * * www-data php /var/www/opencrm/bin/process_email_queue.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
* * * * * www-data php /var/www/opencrm/bin/process_workflows.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
*/5 * * * * www-data php /var/www/opencrm/bin/send_booking_reminders.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
*/5 * * * * www-data php /var/www/opencrm/bin/send_scheduled_reports.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
*/5 * * * * www-data php /var/www/opencrm/bin/sync_booking_calendars.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
17 2 * * * www-data php /var/www/opencrm/bin/backup.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
45 2 * * * www-data php /var/www/opencrm/bin/verify_backup.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
12 * * * * www-data php /var/www/opencrm/bin/health_check.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
22 * * * * www-data php /var/www/opencrm/bin/check_calendar_connections.php >> /var/www/opencrm/storage/logs/cron.log 2>&1
```

Custom-domain provisioning requires root privileges and should only be enabled after reviewing `bin/provision_site_domains.php`, DNS validation, Apache, and Certbot behavior. Do not run it as the web-server user.

## 9. Complete administrator setup

Sign in and review:

1. Application name, logo, colors, timezone, and logging
2. Roles, feature permissions, tag policies, API users, and record ownership
3. SMTP and sender identity, operational notification address, and email compliance
4. Legal/privacy text, retention, public forms, and analytics consent
5. Backup status and system health
6. Optional AI, Stripe, calendar, Easy!Appointments, and domain integrations

## Troubleshooting

- **HTTP 500:** inspect Apache's error log and `storage/logs`; confirm PHP extensions and database access.
- **Blank or incomplete page:** run `php -l` on recently changed files and the smoke tests.
- **Uploads fail:** verify PHP `upload_max_filesize`/`post_max_size`, `UPLOAD_MAX_MB`, and storage ownership.
- **Email remains queued:** run `process_email_queue.php` manually as `www-data` and inspect mailbox health.
- **Redirect or cookie loops:** ensure `APP_URL`, HTTPS termination, and `SESSION_SECURE` agree.
- **Migration fails:** back up first, capture the exact SQL/PHP error, and do not repeatedly make manual schema edits.
