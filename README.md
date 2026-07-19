# OpenCRM

Current stable release: **1.0**

A lightweight, dependency-free CRM for PHP 8.2+ and MySQL 8.0+.

## Disclaimer

This software is provided **"AS IS"**, without warranty of any kind, express or implied, including but not limited to warranties of merchantability, fitness for a particular purpose, and noninfringement. Use of this software is entirely at your own risk. See the [MIT License](LICENSE) for the complete terms.

## Features

- Admin-managed users, roles, password resets, and granular permissions
- Contacts with custom tags and tag-level hidden/read/write overrides
- Opportunity scoring (high, medium, low, keep in touch, not a buyer)
- Contact notes
- Events, slide uploads, and linked attendees
- Dynamic promotional links and downloadable QR codes with click, visitor, referrer, device, and UTM analytics
- Saved reports with filters and graphical charts
- Unified email conversations, templates, signatures, and assignment
- Lightweight event-driven workflows with wait steps and enrollment history
- Gated resource portals with contact engagement tracking
- Multi-calendar booking with round-robin/collective scheduling and write-back
- Products, branded quotes/proposals, acceptance, and Stripe Checkout
- First-touch, last-touch, and linear multi-touch attribution
- Checksummed scheduled backups, verification, health monitoring, and smoke tests
- Searchable in-application Help Center plus user, administrator, and integration manuals in `docs/`
- CSRF protection, secure sessions, password hashing, upload validation, and audit logs

## Install

1. Point the web root at `public/`.
2. Copy `.env.example` to `.env` and set database credentials and `APP_KEY`.
3. Create an empty MySQL database.
4. Set `ADMIN_USERNAME`, `ADMIN_PASSWORD`, and optionally `ADMIN_EMAIL` in `.env`.
5. Run `php bin/migrate.php`, sign in with those administrator credentials, and change the password when prompted.

The migration is idempotent. Uploaded files are stored outside the public web root.
Never commit `.env`, private keys, access tokens, database exports, uploaded files, or production logs.

## Future enhancements

- Optional SMS notifications through Twilio Programmable Messaging, with per-user email/SMS preferences for alerts and individual tag subscriptions, quiet hours, phone verification, delivery logging, and an admin feature switch. Email remains the only enabled notification channel for now.

