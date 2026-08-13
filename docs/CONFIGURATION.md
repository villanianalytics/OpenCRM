# Configuration Reference

OpenCRM uses server environment variables or a root-level `.env` file for bootstrap settings. Operational integrations and branding are then managed by administrators in the application. `.env` values are parsed as unquoted `KEY=value` pairs; avoid inline comments and unnecessary quotes.

## Environment variables

| Variable | Required | Default | Description |
|---|---:|---|---|
| `APP_ENV` | No | `production` | Environment label used by the application. |
| `APP_URL` | Yes | `http://localhost` | Canonical external URL without a trailing slash. Use HTTPS in production. |
| `APP_KEY` | Yes | empty | 64-character random hexadecimal key used for encrypted settings and keyed hashes. Never rotate without a credential migration plan. |
| `DB_HOST` | Yes | `127.0.0.1` | MySQL host. |
| `DB_PORT` | No | `3306` | MySQL port. |
| `DB_NAME` | Yes | `opencrm` | Database name. |
| `DB_USER` | Yes | `opencrm` | Dedicated database user. |
| `DB_PASS` | Yes | empty | Database password. |
| `ADMIN_USERNAME` | Initial setup | empty | Initial administrator username. |
| `ADMIN_PASSWORD` | Initial setup | empty | Initial administrator password; minimum 12 characters. Remove after initialization. |
| `ADMIN_EMAIL` | Recommended | empty | Initial administrator email and recovery/notification fallback. |
| `SESSION_SECURE` | Production | `true` | Sends session cookies only over HTTPS. |
| `UPLOAD_MAX_MB` | No | `12` | Application upload limit in megabytes; PHP/web-server limits must be at least as large. |
| `APP_TIMEZONE` | No | `America/New_York` | Default PHP/application timezone; users may select their own display timezone. |

Use `.env.example` as the copyable template. Do not commit the populated `.env` file.

## Application-managed settings

Administrators reach settings by selecting their account name and opening the settings sections. Depending on release and permissions, settings include:

- Application name, logo, primary/secondary colors, timezone, logging level, and retention
- Users, roles, permissions, API users, tag policies, option lists, tag groups, and custom fields
- Mail transport, sending mailboxes, email compliance, operational notifications, and scheduled reports
- AI provider key and knowledge-base content
- Google/Microsoft OAuth, Easy!Appointments, Stripe, legal/privacy content, domains, and SEO

Sensitive settings are encrypted using `APP_KEY` before database storage. Encryption protects database-only disclosure but does not replace server access control. Application administrators can still replace or use configured integrations.

## Mail

For SMTP, collect host, port, encryption mode, username, password, From address, and From name from the provider. Verify the sending identity and configure SPF, DKIM, and DMARC. Amazon SES sandbox accounts can send only to verified identities until production access is approved.

Run `bin/process_email_queue.php` every minute. Test delivery from the administration screen before enabling alerts, scheduled reports, workflows, queued messages, or password recovery. OpenCRM 1.0 does not document a general bulk campaign composer as a supported feature.

## OpenAI

Create a dedicated API key with the least access and budget appropriate for lead-magnet generation. Enter it in AI Setup; never embed it in browser JavaScript or source control. Uploaded knowledge-base content may be included in model requests, so do not upload regulated or secret material without reviewing the provider's data terms.

## Calendars and booking

- Google and Microsoft require OAuth application credentials and a callback URL matching the value shown by OpenCRM.
- CalDAV should use an app-specific password when the provider supports one.
- Easy!Appointments remains a separate GPL application connected through its API; follow its licensing and deployment documentation.
- Calendar connection checks and booking synchronization require cron workers.

## Stripe

Use separate test and live credentials. Configure the signed `checkout.session.completed` webhook exactly as shown in Payment settings. Never accept unsigned webhook payloads or place a secret key in public page code.

## Logging and privacy

Request logging can include payload details useful for webhook diagnosis. Payloads may contain personal data. Choose the minimum useful log level, restrict access, configure retention, and never record Authorization headers or plaintext secrets. Ensure legal text, tracking consent, email consent, retention, and deletion practices match your actual deployment.
