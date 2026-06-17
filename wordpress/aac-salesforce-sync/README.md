# AAC Salesforce Sync

Separate WordPress plugin for syncing AAC Member Portal / PMPro member data with Salesforce.

## What it includes

- Queue table for retryable outbound sync jobs
- Hook listeners for AAC portal and PMPro events
- Background queue worker using WP-Cron
- Salesforce client-credentials REST upsert client
- Inbound REST endpoints for Salesforce-to-WordPress sync
- Admin page for connection settings and queue monitoring

## Install

1. Copy `wordpress/aac-salesforce-sync` into `wp-content/plugins/`
2. Activate `AAC Salesforce Sync`
3. Open `AAC Portal > Salesforce Sync` in WordPress admin
4. Add:
   - Salesforce token URL
   - Salesforce instance URL
   - client ID / client secret
   - object names and external ID fields
   - inbound shared secret
5. Enable outbound sync

## Outbound hooks

- `aac_member_portal_member_registered`
- `aac_member_portal_profile_updated`
- `profile_update`
- `pmpro_after_checkout`
- `pmpro_after_change_membership_level`

## Inbound REST endpoints

- `POST /wp-json/aac-salesforce-sync/v1/contact`
- `POST /wp-json/aac-salesforce-sync/v1/membership`
- `POST /wp-json/aac-salesforce-sync/v1/enqueue`

Send the shared secret in `X-AAC-SF-Secret`.

Salesforce-to-WordPress inbound writes are limited to member profile and membership access data. Transaction, order, donation, discount, payment gateway, Stripe, and PMPro payment fields are rejected by the profile endpoints. Transactions remain WordPress/PMPro outbound-only.

### Contact payload

```json
{
  "wordpress_user_id": 123,
  "aac_external_key": "aac-wp-user-123",
  "salesforce_contact_id": "003000000000000AAA",
  "first_name": "Alex",
  "last_name": "Member",
  "email": "member@example.org",
  "phone": "555-0100",
  "street": "123 Main St",
  "city": "Denver",
  "state": "CO",
  "postal_code": "80202",
  "country": "US"
}
```

### Membership payload

```json
{
  "wordpress_user_id": 123,
  "aac_external_key": "aac-wp-user-123",
  "salesforce_membership_id": "a0B000000000000AAA",
  "member_id": "AAC12345",
  "membership_level": "Partner",
  "status": "active",
  "renewal_date": "2026-06-04",
  "expiration_date": "2027-06-04",
  "auto_renew": true,
  "rescue_amount": 7500,
  "medical_amount": 5000,
  "mortal_remains_amount": 10000,
  "rescue_reimbursement_process": true,
  "pmpro_level_id": 3
}
```

### Salesforce setup for two-way profile sync

1. Store `wordpress_user_id` or `aac_external_key` on the Salesforce Contact/Membership record. Prefer one of these IDs over email matching.
2. Create a Salesforce Named Credential or middleware connector for the WordPress site.
3. Add `X-AAC-SF-Secret` with the inbound shared secret configured in WordPress.
4. On Contact changes, POST the allowed contact fields to `/wp-json/aac-salesforce-sync/v1/contact`.
5. On Membership changes, POST the allowed membership fields to `/wp-json/aac-salesforce-sync/v1/membership`.
6. Do not include transaction/order/payment fields in Salesforce-to-WordPress payloads.
7. Keep PMPro orders, payments, discounts, donations, gateway transaction IDs, and subscription IDs flowing WordPress-to-Salesforce only.

## Recommended architecture

- Salesforce is source of truth
- Salesforce writes member profile and membership access updates back to WordPress
- WordPress/PMPro mirrors membership state for portal access
- Stripe / PMPro remain payment execution systems
- Use Salesforce CDC or middleware to call the inbound endpoints

## Notes

- This is a foundational scaffold, not a full finished enterprise sync.
- Field mappings can be extended in `class-aac-salesforce-sync-worker.php`.
- For production, add middleware, structured logging, and stronger replay tooling.
