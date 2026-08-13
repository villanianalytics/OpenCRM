# Upgrading OpenCRM

OpenCRM database upgrades are applied by `bin/migrate.php`. Treat every upgrade as an application-and-schema release.

## Before upgrading

1. Read release notes and review the code diff, dependencies, migration, worker changes, and web-server requirements.
2. Record the current commit/tag and PHP/MySQL versions.
3. Create a database dump and uploads archive, copy them off-host, and verify checksums.
4. Test the upgrade against a restored copy when the installation is business-critical.
5. Put the application in maintenance mode or otherwise prevent writes during the final database/application switch.

## Upgrade procedure

```bash
cd /var/www/opencrm
git fetch --tags origin
git checkout REPLACE_WITH_REVIEWED_TAG_OR_COMMIT
composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/migrate.php
sudo -u www-data php tests/smoke.php
sudo -u www-data php tests/permissions_smoke.php
sudo systemctl reload apache2
```

Confirm the dashboard, login, contacts, pipeline, public forms/links, mail queue, scheduled workers, system health, and any enabled external integrations. Review logs immediately after release.

## Rollback

Application code can usually be returned to the previously recorded commit, but an older release may not understand a newer schema. Do not assume migrations are reversible. If compatibility is uncertain:

1. Stop writes and workers.
2. Restore the pre-upgrade database and uploads as one consistent set.
3. Restore the previous application commit and Composer lock file.
4. Reapply the protected `.env`/`APP_KEY` without replacing it from an untrusted archive.
5. Run smoke tests before reopening access.

Document the incident and retain failed-release logs after redacting secrets and personal information.
