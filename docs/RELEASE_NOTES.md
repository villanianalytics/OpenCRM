# OpenCRM Release Notes

## Unreleased

- Authenticated list and report pages now re-evaluate current database filters when opened or restored through browser history instead of displaying a stale in-memory result.
- Added confirmed, permission- and ownership-protected opportunity deletion while preserving linked contacts and auditing the deleted opportunity details.
- Added comprehensive installation, configuration, architecture, development, upgrade, API, security, contribution, and community documentation.
- Added synthetic contact import/API examples and GitHub issue/pull-request templates.
- Corrected project requirements for Composer, Dompdf, PHP extensions, workers, HTTPS, permissions, and integration boundaries.
- Preserved the requested page across session expiry and allowed a same-origin stale login form to authenticate without a second trip to the login page.

## 1.0 · 2026-07-19

- Declared the first stable OpenCRM release.
- Consolidated navigation into Contacts, Sales, Marketing, Engage, Reports, Help, and Admin.
- Added encrypted multi-mailbox SMTP pools with weighted distribution, sender health, rate limits, queued delivery, retries, and usage reporting.
- Preserved consent, unsubscribe, suppression, bounce, and complaint controls across pooled campaign delivery.
- Added automatic XML sitemaps and robots directives for published sites.
- Added site and page SEO management with search previews, metadata validation, canonical URLs, social cards, indexing controls, Search Console verification, image/H1 checks, and Schema.org JSON-LD configuration.
- Added secure self-service password recovery through branded email, with expiring single-use tokens, rate limiting, generic account-discovery-safe responses, and audit logging.
- Reduced top-navigation clutter by placing Reports under Automation and using the account name as the settings entry point.
- Added recurring weekly pipeline and month-end new-contact email schedules with multiple recipients and delivery history.

## 2026-07-19

- Restored the dashboard opportunity pipeline chart.
- Added unified communications, templates, signatures, assignment, and opportunity linking.
- Added lightweight event-driven workflows with wait steps and enrollment monitoring.
- Added gated resource portals and engagement tracking.
- Added calendar write-back, video meeting links, retries, team calendars, round-robin and collective availability, and out-of-office controls.
- Added email consent, preference center, suppression management, and signed SES event handling.
- Added products, branded quotes/proposals, PDF export, acceptance, Stripe Checkout, and signed payment events.
- Added first-touch, last-touch, and linear multi-touch marketing attribution.
- Added scheduled checksummed backups, integrity verification, health checks, and expanded regression tests.
- Expanded contextual help, operations guidance, and copy-ready API examples.
- Added deduplicated administrator incident emails with recovery notices and configurable thresholds.
- Added admin-managed Privacy Policy, Terms, cookie notice, marketing consent, retention, company identity, and public legal links.
