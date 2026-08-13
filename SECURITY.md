# Security Policy

## Supported versions

Security fixes are applied to the current `main` branch and the latest published release. Older snapshots may not receive patches.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use GitHub's **Security → Report a vulnerability** private reporting feature for this repository. If private reporting is unavailable, contact `dvillani@rapidanalyticsinc.com` with the subject “OpenCRM security report.”

Include the affected version or commit, prerequisites, reproduction steps, impact, and any suggested remediation. Remove real credentials and customer data. You should receive an acknowledgement within five business days; investigation and disclosure timing depend on severity and reproducibility.

## Deployment responsibility

OpenCRM is self-hosted. Operators are responsible for HTTPS, operating-system and dependency updates, firewall and database exposure, credentials, mail reputation, privacy compliance, backup retention, and incident response. Review [docs/INSTALLATION.md](docs/INSTALLATION.md) and [docs/OPERATIONS.md](docs/OPERATIONS.md).

Never publish `.env`, `APP_KEY`, API tokens, OAuth/SMTP/payment credentials, private keys, database exports, uploads, logs, or unredacted diagnostic payloads.
