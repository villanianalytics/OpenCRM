# OpenCRM Integrations Guide

## Contact API and GoHighLevel

Create an API user under Admin settings and copy its token once. Prefer create-only access for inbound lead synchronization. Send standard contact fields as flat key/value pairs. Tags may be supplied as `tag`, `tag1`, `tag2`, and continuing numbered keys. Custom fields use `custom_Field_Name`, for example `custom_Customer_Type`. Contact notes may be included in the creation request.

Never place an API token in a public URL, browser script, repository, screenshot, or support ticket.

## Calendar connections

Administrators first configure Google and/or Microsoft OAuth client credentials under Calendar integrations. Each user then connects their own account under User settings. CalDAV requires the calendar URL, username, and preferably an app-specific password. External busy periods are removed from offered booking slots, and connection health is checked on a schedule.

## Easy!Appointments

OpenCRM keeps Easy!Appointments isolated as a separate GPL application. The Booking engine page stores the encrypted API token and maps OpenCRM calendars and meeting types to Easy!Appointments providers and services.

## Email and AI

Mail settings support local transport or authenticated SMTP such as Amazon SES. Verify the sender identity and use the provider's SMTP credentials. OpenAI credentials are encrypted and configured under AI setup; the knowledge base supplies approved business context to the lead-magnet generator.

## Diagnostics

Use Application logs, Audit report, Calendar connection health, Booking engine test, Mail test, and System health. Record the timestamp and endpoint when investigating webhooks. Redact authorization headers and secrets before sharing diagnostics.
