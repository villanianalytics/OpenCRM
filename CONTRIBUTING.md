# Contributing to OpenCRM

Thank you for helping improve OpenCRM. Keep contributions focused, secure, and compatible with the project's lightweight self-hosted scope.

## Before starting

1. Search existing issues and pull requests.
2. Open an issue for a large feature, schema redesign, new dependency, or breaking change before implementation.
3. Use a private security report for vulnerabilities; follow [SECURITY.md](SECURITY.md).

## Development workflow

1. Fork the repository and create a descriptive branch from `main`.
2. Follow [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) to configure a local database.
3. Keep secrets and customer data out of commits, tests, screenshots, and fixtures.
4. Make the smallest cohesive change and update documentation when behavior changes.
5. Run the PHP syntax check and relevant smoke tests.
6. Open a pull request describing the motivation, behavior, schema impact, security implications, and validation performed.

## Expectations

- Support PHP 8.2+ and MySQL 8.0+.
- Use prepared SQL statements for request-derived values.
- Enforce permissions and record/tag access server-side; hiding navigation is not authorization.
- Protect authenticated state changes with CSRF validation.
- Validate file type and size and store uploads outside `public/`.
- Escape untrusted HTML output with the existing `e()` helper.
- Add idempotent schema changes to `bin/migrate.php` and the final shape to `database/schema.sql`.
- Preserve existing installations and document any operational step.
- Do not introduce analytics, remote calls, or new dependencies without explaining them.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
