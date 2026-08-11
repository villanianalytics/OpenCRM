# Development Guide

## Local setup

Install PHP 8.2+, Composer, MySQL 8.0+, and the extensions listed in [INSTALLATION.md](INSTALLATION.md). Then:

```bash
composer install
cp .env.example .env
php bin/migrate.php
php -S 127.0.0.1:8080 -t public public/index.php
```

Set `APP_URL=http://127.0.0.1:8080` and `SESSION_SECURE=false` only for local HTTP development. Never reuse production credentials or customer data locally.

## Source conventions

- Keep `declare(strict_types=1)` in PHP entry points and service modules.
- Reuse bootstrap helpers for configuration, escaping, redirects, CSRF, permissions, auditing, encryption, and logging.
- Use PDO prepared statements for values derived from requests.
- Validate authorization again on every mutation, including background/API paths.
- Keep public pages intentionally public and authenticated pages behind `require_login()` or `require_permission()`.
- Treat route ordering as behavior: broad patterns must not shadow specific endpoints.
- Keep migrations idempotent and compatible with existing data.

## Validation

Syntax-check changed PHP files:

```bash
find public src bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run the available suites against a disposable database:

```bash
php tests/smoke.php
php tests/api_smoke.php
php tests/permissions_smoke.php
php tests/expanded_smoke.php
php tests/readiness_smoke.php
```

Some suites create and remove test data. Do not run them against a production database unless you have reviewed the current test implementation and explicitly accept that behavior.

Before opening a pull request, also run:

```bash
composer validate --strict
git diff --check
```

Manually exercise the affected screen at desktop and mobile widths, permission-denied paths, invalid input, expired sessions, and background-worker failure handling.

## Schema changes

`database/schema.sql` represents a clean installation. `bin/migrate.php` upgrades both clean and existing installations and must be safe to rerun. Back up before testing migrations. Prefer additive, nullable changes followed by data migration and constraint tightening when existing rows are involved.

## Logging and fixtures

Use synthetic names, addresses, emails, tokens, and payloads. Do not commit local `.env`, archives, generated documents, uploads, logs, database dumps, or screenshots containing personal data. Log useful identifiers and outcomes without Authorization headers or decrypted secrets.
