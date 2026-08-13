# Architecture

OpenCRM is a server-rendered PHP application designed to remain understandable without a large framework.

## Request lifecycle

1. The web server sends application traffic to `public/index.php`.
2. `src/bootstrap.php` loads configuration, starts the secure session, establishes shared helpers, and opens the PDO connection when required.
3. The front controller loads route modules from `src/`.
4. Route modules enforce authentication, feature permissions, record ownership, and tag visibility before querying or mutating data.
5. HTML is rendered through the shared layout helpers; public tracking and selected integrations return JSON or webhook status responses.

The only supported public CRM API endpoint in 1.0 is `POST /api/v1/contacts`. Public forms, booking pages, promotional redirects, published resources/sites, and signed provider webhooks have separate purpose-built routes.

## Repository layout

| Path | Responsibility |
|---|---|
| `public/` | Web document root, front controller, browser assets, and safe public entry points |
| `src/` | Bootstrap, helpers, route modules, integrations, notifications, and business behavior |
| `database/schema.sql` | Complete schema for a new installation |
| `bin/migrate.php` | Idempotent creation and upgrades for existing installations |
| `bin/` | Queue workers, reminders, workflows, calendar sync, reporting, health, backup, and domain tasks |
| `storage/` | Private uploads, logs, and backups; not a web root and not committed |
| `tests/` | Executable smoke/readiness/permission/API regression scripts |
| `deploy/` | Optional server hardening examples; review before use |
| `docs/` | Operator, user, integration, API, and development documentation |

## Data model areas

The schema is organized around contacts/companies/tags/custom fields, opportunities/quotes/payments, events/alerts, forms/sites/resources/attribution, conversations/email delivery, workflows, bookings/calendars, users/roles/audit logs, and application configuration. Foreign keys are used for primary relationships while selected configurable structures use JSON.

## Security boundaries

- Authentication is session-based; passwords use PHP's current `PASSWORD_DEFAULT` hash.
- State-changing browser requests require CSRF validation.
- Login accepts an expired form token only when browser origin metadata proves the submission is same-origin.
- Permissions are checked server-side by module and action. Record creator/owner and tag policies further restrict access.
- API tokens are bearer credentials stored as hashes/prefixes and may be limited to contact creation.
- Integration secrets stored in the database are encrypted with `APP_KEY`.
- Upload routes validate size and permitted MIME types and store generated filenames outside `public/`.
- SES and Stripe webhooks validate provider signatures.
- Audit logs answer who changed application records; application logs support operational diagnosis.

## Smart cache

Expensive list, dashboard, and report queries use revision-tagged cached results. Each entry records dependency keys such as `contacts`, `opportunities`, `reports:contacts`, and individual entity identifiers. Mutations through the application or contact API increment the affected database-backed revisions in the same transaction as the change. A cached result is reused only while its revision snapshot still matches; unrelated entries remain valid. APCu is used when available, with `storage/cache` as a private filesystem fallback. Time-dependent queries also retain a short TTL.

Direct database edits bypass application invalidation. After an approved direct SQL change, clear the smart cache from the administrator screen or increment the applicable revision keys.

## Extending OpenCRM

For a new module:

1. Add the final schema to `database/schema.sql` and an idempotent upgrade in `bin/migrate.php`.
2. Define distinct `.view` and `.edit` permissions and enforce them on navigation, pages, mutations, dashboard widgets, reports, and APIs.
3. Add route code under `src/` and load it from the front controller in an unambiguous order.
4. Use prepared statements, output escaping, CSRF validation, ownership/tag checks, audit events, and validated uploads as applicable.
5. Add smoke coverage and update user, administrator, API, and operations documentation.

Avoid putting secrets or environment-specific behavior in source code. Keep third-party integrations optional and fail with actionable diagnostics.
