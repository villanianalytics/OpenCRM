# OpenCRM

OpenCRM is a self-hosted, lightweight CRM built with PHP and MySQL. It combines contact and opportunity management with focused marketing, booking, reporting, and communication tools without the complexity of a large CRM platform.

> **Release status:** OpenCRM 1.0 is suitable for self-hosted evaluation and controlled production use. Review the security, email, privacy, and backup settings before storing real customer data.

## Highlights

- Contacts, companies, ownership, notes, relationships, custom fields, tags, tag groups, saved views, imports, and duplicate suggestions
- Configurable opportunity pipeline, Kanban board, opportunity notes, reporting, partner performance, quotes, and Stripe Checkout
- Events, attendee imports, presenters, recurring reminders, alerts, and scheduled reports
- Public forms, promotional links, QR codes, attribution, lead magnets, resource portals, and a visual site/landing-page builder
- Booking pages with individual, round-robin, and collective calendars plus Google, Microsoft, CalDAV, and optional Easy!Appointments integration
- Email conversations, templates, SMTP mailbox pools, consent/suppression controls, and event-driven workflows
- Granular feature permissions, record ownership rules, tag-level access, API create-only mode, audit trails, operational health checks, and verified backups
- Dependency-aware smart caching that invalidates only affected contact, opportunity, dashboard, and report results

## Requirements

- Linux or another PHP-capable host
- PHP 8.2 or newer with PDO MySQL, mbstring, cURL, fileinfo, DOM/XML, and ZIP extensions
- MySQL 8.0 or compatible MariaDB release
- Composer 2
- Apache 2.4 with `mod_rewrite` (the supplied deployment examples assume Apache), or an equivalent Nginx configuration
- HTTPS for production
- Cron for queued email, reminders, workflows, reports, calendar synchronization, health checks, and backups

## Quick start

```bash
git clone https://github.com/villanianalytics/OpenCRM.git
cd OpenCRM
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env`, create an empty database, and provide a unique administrator password of at least 12 characters. Generate `APP_KEY` with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then run:

```bash
php bin/migrate.php
php tests/smoke.php
```

Configure the web server document root as the repository's `public/` directory. Do **not** expose the repository root. Visit the configured `APP_URL` and sign in with `ADMIN_USERNAME` and `ADMIN_PASSWORD`; the initial administrator is prompted to change the password.

See the complete [installation guide](docs/INSTALLATION.md), [configuration reference](docs/CONFIGURATION.md), and [operations guide](docs/OPERATIONS.md) before production use.

## Documentation

| Document | Purpose |
|---|---|
| [Installation](docs/INSTALLATION.md) | Server packages, database, Apache, permissions, cron, and validation |
| [Configuration](docs/CONFIGURATION.md) | Environment variables and administrator-managed integrations |
| [User guide](docs/USER_GUIDE.md) | Everyday contacts, sales, marketing, booking, and reporting workflows |
| [Administrator guide](docs/ADMIN_GUIDE.md) | Users, permissions, security, email, legal, and operational settings |
| [API reference](docs/API_REFERENCE.md) | Bearer authentication, contact upsert fields, responses, and examples |
| [Integration guide](docs/INTEGRATIONS_GUIDE.md) | GoHighLevel, calendars, SMTP, SES, Stripe, Easy!Appointments, and AI |
| [Operations and recovery](docs/OPERATIONS.md) | Workers, monitoring, backups, verification, and restoration |
| [Architecture](docs/ARCHITECTURE.md) | Request lifecycle, source layout, data, security boundaries, and extension points |
| [Development](docs/DEVELOPMENT.md) | Local setup, coding conventions, tests, and pull-request checks |
| [Upgrading](docs/UPGRADING.md) | Safe upgrade, migration, rollback, and validation procedure |
| [Contributing](CONTRIBUTING.md) | How to propose and submit changes |
| [Security policy](SECURITY.md) | Supported release and private vulnerability reporting |

## API example

Create an API user in **Admin → Settings → API users**, copy its token once, and submit a contact:

```bash
curl -X POST "https://crm.example.com/api/v1/contacts" \
  -H "Authorization: Bearer crm_REPLACE_WITH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ada",
    "last_name": "Lovelace",
    "email": "ada@example.com",
    "company": "Analytical Engines Inc",
    "tag": "Website Lead",
    "tag1": "Newsletter",
    "custom_Customer_Type": "Prospect",
    "notes": "Requested a product demonstration."
  }'
```

Webhook systems such as GoHighLevel should serialize their body key/value mappings as JSON and send `Content-Type: application/json`. See the [API reference](docs/API_REFERENCE.md).

## Security and data protection

- Never commit `.env`, API tokens, SMTP credentials, OAuth secrets, database dumps, uploads, private keys, logs, or release archives containing production data.
- Use HTTPS, a unique 64-character hexadecimal `APP_KEY`, a dedicated least-privilege database user, and strong administrator credentials.
- Keep PHP, Composer dependencies, the operating system, and the web server patched.
- Configure cron-driven backups and copy verified backup sets off the application server.
- Review public forms, tracking, email consent, retention, and legal text for the laws applicable to your organization.
- Report vulnerabilities according to [SECURITY.md](SECURITY.md), not through a public issue.

## Project scope

OpenCRM intentionally favors understandable, self-hosted workflows over enterprise-scale automation. It is not a substitute for legal advice, a deliverability service, a payment processor, or a managed backup platform. Third-party features require separate accounts and remain subject to their providers' terms.

## License and disclaimer

OpenCRM is licensed under the [MIT License](LICENSE).

This software is provided **“AS IS”**, without warranty of any kind, express or implied, including warranties of merchantability, fitness for a particular purpose, and noninfringement. Use is at your own risk.
