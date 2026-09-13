# Contact API Reference

OpenCRM 1.0 exposes one supported general API endpoint:

```http
POST /api/v1/contacts
```

It creates a contact or, for an `upsert` API user, updates the contact matched by email. API users configured as `create_only` cannot modify ordinary contact fields, notes, or custom fields on an existing record, but requested tags may still be applied so inbound opt-ins are not lost.

## Authentication

Create an API user under **Admin → Settings → API users** and copy the token when displayed. It cannot be retrieved later.

```http
Authorization: Bearer crm_REPLACE_WITH_TOKEN
```

Do not use `Bearer` as the header name. `Authorization` is the header name; `Bearer`, a space, and the token form its value.

Tokens are equivalent to passwords. Use a separate API user for each integration, select create-only access when possible, transmit only over HTTPS, and rotate a token that is exposed.

## Request format

The endpoint requires `Content-Type: application/json` and a valid JSON object. Webhook builders may present the body as individual key/value rows, but they must serialize those mappings to JSON. A form-encoded payload returns HTTP `415`.

Nested custom-field JSON is not required. Send custom values as top-level JSON keys.

## Fields

| Field | Description |
|---|---|
| `first_name` | Contact first name. |
| `last_name` | Contact last name. |
| `email` | Email and primary deterministic match field. Use a valid address. |
| `phone` | Optional telephone number. |
| `company` | Optional company name. Existing company normalization may be suggested in the UI. |
| `job_title` | Optional job title. |
| `website` | Optional website URL. |
| `linkedin_url` | Optional LinkedIn profile URL. |
| `external_id` | Optional caller-controlled identifier used for traceability where supported. |
| `tag` | First tag name to create/apply. |
| `tag1`, `tag2`, … | Additional tag names. Numbered tag fields may continue sequentially. |
| `tags` | JSON clients may also supply a supported tag collection; flat `tag` fields are most portable. |
| `notes` | Optional note appended to the contact. |
| `custom_FIELD_NAME` | Custom-field value, using the configured field name/key after `custom_`, for example `custom_Customer_Type`. |

Use the exact configured custom-field identifier. List fields must receive a configured value. Conditional custom fields remain subject to the application's rules.

## JSON example

```bash
curl -X POST "https://crm.example.com/api/v1/contacts" \
  -H "Authorization: Bearer crm_REPLACE_WITH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Ada",
    "last_name": "Lovelace",
    "email": "ada@example.com",
    "phone": "+1 212 555 0100",
    "company": "Analytical Engines Inc",
    "linkedin_url": "https://www.linkedin.com/in/example",
    "tag": "Website Lead",
    "tag1": "AI Interest",
    "tag2": "Newsletter",
    "custom_Customer_Type": "Prospect",
    "notes": "Submitted the AI Week landing page."
  }'
```

## GoHighLevel-style body mapping

Configure the webhook as `POST`, choose JSON as its body/content type, add an `Authorization` header whose value is `Bearer crm_REPLACE_WITH_TOKEN`, and map these JSON keys:

```text
first_name={{contact.first_name}}
last_name={{contact.last_name}}
email={{contact.email}}
phone={{contact.phone}}
tag=GoHighLevel
tag1=Webinar Opt In
custom_Customer_Type=Prospect
notes=Submitted from the webinar workflow
```

## Responses

Successful requests return JSON with an HTTP 2xx status and the contact outcome/identifier. Treat the response body as authoritative and log the status without recording the bearer token.

Typical failures include:

| Status | Meaning | Action |
|---:|---|---|
| `400` | Invalid or missing contact data | Correct field names, email format, custom-list value, or request body. |
| `401` | Missing, malformed, revoked, or unknown token | Confirm the `Authorization: Bearer …` header and rotate/re-enable the API user if needed. |
| `403` | Authenticated but operation is prohibited | Review create-only behavior or API-user status. |
| `404` | Unsupported `/api/v1/...` endpoint | OpenCRM 1.0 supports only `POST /api/v1/contacts`. |
| `405` | Wrong HTTP method | Send `POST`. |
| `413` | Request exceeds server/application limits | Reduce the body and inspect proxy/web-server limits. |
| `422` | Semantically invalid input, where returned | Correct the reported field. |
| `429` | Rate limit or server protection | Retry with backoff; do not disable protection to accommodate a request storm. |
| `500` | Unexpected server failure | Use the request time and source address to find the redacted application log entry. |

Exact response fields may gain additive metadata in compatible releases. Clients should not fail when unknown JSON fields appear.

## Idempotency and duplicate handling

Use a stable, normalized email whenever available. An upsert user can update the matching contact; a create-only user preserves existing fields/notes/custom values while still applying requested tags. Contacts without a stable match can create duplicates, so webhook retries should occur only after checking the HTTP response.

## Logging and testing

Inbound API attempts, including rejected and unknown `/api/v1` paths, are recorded according to the configured logging level. Payload logging can contain personal data and must use short retention and restricted access. Authorization values must remain redacted.

Test with a synthetic contact and a dedicated API user. Verify the contact, tags, note, custom fields, audit/application log entry, and create-only behavior before enabling a live automation.
