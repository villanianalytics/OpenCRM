# OpenCRM Administrator Guide

## Initial configuration

Use Admin settings to set the application name, logo, color scheme, New York or another timezone, mail delivery, logging level, and retention. Create users and roles with the minimum permissions required.

## Security model

- Feature permissions control view and edit access.
- Tag policies can hide a tag and its protected records or provide read/write access.
- Record changes require creator, owner, or administrator status; visible users may add notes.
- API users can be create-only to prevent automation from altering existing records.
- Credentials are encrypted in application settings or supplied through the server environment. Never commit `.env`, tokens, keys, exports, uploads, or logs.

## Role permissions

Admin â†’ Role permissions provides independent View/use and Create/edit controls for Contacts, Companies, Opportunities, Events, Alerts, Partner Sales, Reports, Lead Magnets, Forms, Promotional Links, Sites, Bookings, Communications, Workflows, Resources, and Quotes/Payments. Edit automatically includes view. Navigation, direct URLs, mutations, dashboard cards, and report datasets enforce the same permissions server-side. Users receive changed role permissions at their next sign-in.

Reports require both `reports.view` and view permission for the selected source module. Partner Sales view users see their own performance; Partner Sales edit users can review and manage all partners. Record ownership and tag-level restrictions continue to apply after feature permission checks.

## Configuration catalogs

Maintain tags and tag groups, custom fields and groupings, conditional field rules, pipeline stages, opportunity scores/statuses, snooze periods, and other dropdown lists centrally.

## Operations

Audit reports show logins and record actions. Application logs capture configured request/payload detail and can be purged by age. System health covers database, storage, runtime, and scheduled integration jobs. Lightsail snapshots protect the server, but database/export recovery procedures should still be tested periodically.

OpenCRM creates a daily checksummed database/upload backup, verifies the latest backup daily, retains 30 days, and performs an hourly health check. Results are written to application logs and `storage/logs/cron.log`. See `docs/OPERATIONS.md` for commands and recovery steps.

## Email compliance and payments

Non-transactional email honors contact consent and the suppression list. Configure SES delivery/bounce/complaint notifications to the signed SNS webhook shown on Email compliance. Stripe Checkout uses encrypted credentials and a signed webhook configured under Payment settings.

### Sending mailbox pools

Use Admin â†’ Sending mailboxes to add authenticated SMTP senders for legitimate teams, brands, or workloads. Credentials are encrypted. OpenCRM distributes messages across healthy mailboxes using configured weights while enforcing hourly and daily limits. Failed senders are marked with a warning and retain their error for diagnosis. Consent, unsubscribe, and suppression checks run before mailbox selection.

Run `php bin/process_email_queue.php` every minute from cron to process queued campaign mail. Failed messages retry with exponential backoff up to five attempts. Mailbox usage and health are visible from the administration screen. Configure SPF, DKIM, and DMARC for every sending domain and remain within each provider's acceptable-use policy.

## Operational notifications

Admin â†’ Operational notifications configures the administrator recipient, warning/error threshold, repeat cooldown, and repeated-webhook threshold. Backup failure, failed backup verification, stale backups, low disk, calendar/write-back failures, workflow failures, and repeated rejected SES/Stripe webhooks create deduplicated incidents. OpenCRM sends once when an incident begins, repeats only after the cooldown, and sends a recovery message.

## Legal and privacy content

Admin â†’ Legal & privacy controls the public Privacy Policy, Terms of Use, analytics/cookie notice, marketing consent language, retention statement, company identity/address, and public-form disclosure. Stable pages are published at `/legal/privacy` and `/legal/terms`; public CRM experiences link them automatically. The supplied text is a starting template and must be reviewed by qualified counsel for the organizationâ€™s actual practices and applicable jurisdictions.

## Sites and custom domains

Add a domain from a site's Domains page, publish the displayed `_opencrm` TXT record, point the hostname to the Lightsail instance, and check DNS. A root-only scheduled job provisions Apache and Let's Encrypt after ownership verification. Configure 301/302 path redirects when published URLs change.

Each site has a Site SEO screen and each page has a dedicated SEO editor. Configure titles, descriptions, canonical URLs, social cards, indexing, and Schema.org JSON-LD. OpenCRM flags title/description length, missing H1 headings, missing image alt text, and missing social images. Published indexable pages are included in `/sitemap.xml`; `/robots.txt` advertises that sitemap and excludes private application routes. Submit the sitemap and optional verification token through Google Search Console. Technical SEO improves crawlability but does not guarantee rankings.

