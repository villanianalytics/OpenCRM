# OpenCRM Operations and Recovery

## Automated schedule

The exact cadence can be adjusted for load, but production should schedule every enabled subsystem:

| Suggested schedule | Command | Purpose |
|---|---|---|
| Every minute | `php bin/process_email_queue.php` | Send queued mail with retry/mailbox limits. |
| Every minute | `php bin/process_workflows.php` | Continue due workflow enrollments and wait steps. |
| Every 5 minutes | `php bin/send_booking_reminders.php` | Deliver due booking reminders. |
| Every 5 minutes | `php bin/send_scheduled_reports.php` | Deliver due weekly/month-end report schedules. |
| Every 5 minutes | `php bin/sync_booking_calendars.php` | Retry pending external calendar write-back. |
| Hourly | `php bin/check_calendar_connections.php` | Refresh and record connection health. |
| Hourly at minute 12 | `php bin/health_check.php` | Check database, storage, workers, integrations, and incidents. |
| Daily at 2:17 AM | `php bin/backup.php` | Create checksummed database/upload backup sets. |
| Daily at 2:45 AM | `php bin/verify_backup.php` | Verify the newest backup and required schema content. |

Run commands as the restricted account that owns application storage (commonly `www-data`). Use overlap prevention if a worker could run longer than its interval. `bin/provision_site_domains.php` has elevated web-server and Certificate Authority effects; review and schedule it separately as root only when custom domains are enabled.

Backups live in `storage/backups`, include a compressed MySQL dump and uploads archive, have SHA-256 checksums, and are retained for 30 days. Copy backup sets off the instance; Lightsail snapshots complement rather than replace application backups.

## Routine checks

- Review System health and unresolved operational incidents.
- Confirm email queue depth, mailbox health, provider events, and scheduled-report history.
- Confirm calendar connection health and booking synchronization failures.
- Inspect disk usage, backup age, verification status, and at least one off-host copy.
- Apply operating-system, PHP, web-server, MySQL, and Composer security updates through a tested release process.
- Review administrator accounts, API users, permissions, tag policies, and audit activity.

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

## Incident triage

Record the application timezone, exact time range, affected record label, endpoint, HTTP status, and a request correlation detail when available. Review application logs, audit logs, Apache/PHP logs, worker output, and the relevant provider dashboard. Redact bearer tokens, cookies, authorization headers, SMTP/OAuth/payment secrets, and customer payloads before sharing.

Requests for nonexistent API or exploit paths are normally internet scanners. Keep software patched, expose only required ports, review the supplied hardening examples before enabling them, and consider free controls such as a host firewall, Fail2ban, Apache rate limiting/mod_evasive, and a CDN/WAF. Path blocking never compensates for vulnerable software.
