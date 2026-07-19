# OpenCRM Operations and Recovery

## Automated schedule

- Hourly at minute 12: `php bin/health_check.php`
- Daily at 2:17 AM server time: `php bin/backup.php`
- Daily at 2:45 AM: `php bin/verify_backup.php`

Backups live in `storage/backups`, include a compressed MySQL dump and uploads archive, have SHA-256 checksums, and are retained for 30 days. Copy backup sets off the instance; Lightsail snapshots complement rather than replace application backups.

## Manual checks

```bash
sudo -u www-data php /var/www/opencrm/tests/smoke.php
sudo -u www-data php /var/www/opencrm/tests/api_smoke.php
sudo -u www-data php /var/www/opencrm/tests/expanded_smoke.php
sudo -u www-data php /var/www/opencrm/bin/health_check.php
sudo -u www-data php /var/www/opencrm/bin/verify_backup.php
```

## Restore drill

1. Put the application in maintenance mode or prevent writes.
2. Copy the selected database `.sql.gz`, uploads `.tar.gz`, and manifest to an isolated recovery host.
3. Run `php bin/verify_backup.php /path/to/manifest.json` before restoring.
4. Create a clean MySQL database and import with `gzip -dc database.sql.gz | mysql recovery_database`.
5. Extract uploads into an empty recovery storage directory.
6. Point a non-production OpenCRM instance at the recovery database/storage and run all three smoke tests.
7. Record the backup timestamp, restore duration, test results, and operator. Only then schedule a production recovery if needed.

Do not overwrite production during a drill. Keep `.env`, application keys, SMTP/API credentials, and OAuth tokens protected throughout recovery.

