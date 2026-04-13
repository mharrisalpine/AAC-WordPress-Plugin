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

## Recommended architecture

- Salesforce is source of truth
- WordPress/PMPro mirrors membership state for portal access
- Stripe / PMPro remain payment execution systems
- Use Salesforce CDC or middleware to call the inbound endpoints

## Notes

- This is a foundational scaffold, not a full finished enterprise sync.
- Field mappings can be extended in `class-aac-salesforce-sync-worker.php`.
- For production, add middleware, structured logging, and stronger replay tooling.
