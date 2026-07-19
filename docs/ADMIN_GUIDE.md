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

Admin → Role permissions provides independent View/use and Create/edit controls for Contacts, Companies, Opportunities, Events, Alerts, Partner Sales, Reports, Lead Magnets, Forms, Promotional Links, Sites, Bookings, Communications, Workflows, Resources, and Quotes/Payments. Edit automatically includes view. Navigation, direct URLs, mutations, dashboard cards, and report datasets enforce the same permissions server-side. Users receive changed role permissions at their next sign-in.

Reports require both `reports.view` and view permission for the selected source module. Partner Sales view users see their own performance; Partner Sales edit users can review and manage all partners. Record ownership and tag-level restrictions continue to apply after feature permission checks.

## Configuration catalogs

Maintain tags and tag groups, custom fields and groupings, conditional field rules, pipeline stages, opportunity scores/statuses, snooze periods, and other dropdown lists centrally.

## Operations

Audit reports show logins and record actions. Application logs capture configured request/payload detail and can be purged by age. System health covers database, storage, runtime, and scheduled integration jobs. Lightsail snapshots protect the server, but database/export recovery procedures should still be tested periodically.

OpenCRM creates a daily checksummed database/upload backup, verifies the latest backup daily, retains 30 days, and performs an hourly health check. Results are written to application logs and `storage/logs/cron.log`. See `docs/OPERATIONS.md` for commands and recovery steps.

## Email compliance and payments

Non-transactional email honors contact consent and the suppression list. Configure SES delivery/bounce/complaint notifications to the signed SNS webhook shown on Email compliance. Stripe Checkout uses encrypted credentials and a signed webhook configured under Payment settings.

## Sites and custom domains

Add a domain from a site's Domains page, publish the displayed `_opencrm` TXT record, point the hostname to the Lightsail instance, and check DNS. A root-only scheduled job provisions Apache and Let's Encrypt after ownership verification. Configure 301/302 path redirects when published URLs change.

