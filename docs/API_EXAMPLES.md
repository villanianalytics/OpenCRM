# OpenCRM API Examples

## Authentication

Create an API user under **Admin → API users**, copy the token once, and send it as a bearer token. Use `create_only` for integrations that must never change existing contacts.

```http
Authorization: Bearer crm_REPLACE_WITH_TOKEN
Content-Type: application/json
```

## Create or update a contact

```bash
curl -X POST "https://rapidanalyticssoftware.com/api/v1/contacts" \
  -H "Authorization: Bearer crm_REPLACE_WITH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ada",
    "last_name": "Lovelace",
    "email": "ada@example.com",
    "phone": "+1 212 555 0100",
    "company": "Analytical Engines Inc",
    "tag": "Website Lead",
    "tag1": "AI Interest",
    "tag2": "Newsletter",
    "custom_Customer_Type": "Prospect",
    "notes": "Submitted the AI Week landing page."
  }'
```

GoHighLevel may send the same names as flat key/value form fields instead of JSON. Keep `custom_Customer_Type` at the top level. Tags may continue as `tag3`, `tag4`, and so on.

## SES events

Configure Amazon SES to publish delivery, bounce, and complaint events to SNS, then subscribe the HTTPS endpoint displayed under **Admin → Email compliance**. OpenCRM validates the SNS certificate/signature and rejects unsigned requests.

## Stripe events

Create a Stripe webhook for `checkout.session.completed` using the endpoint displayed under **Admin → Payment settings**. Store the resulting `whsec_...` signing secret in that screen. Unsigned or stale webhook requests return HTTP 403.

## Safety

Never put tokens in URLs, client-side JavaScript, screenshots, source control, or public logs. Rotate a token immediately if it is exposed.

