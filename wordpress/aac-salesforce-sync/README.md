# AAC Salesforce Sync

Separate WordPress plugin for syncing AAC Member Portal / PMPro member data with Salesforce.

## What it includes

- Queue table for retryable outbound sync jobs
- Hook listeners for AAC portal and PMPro events
- Background queue worker using WP-Cron
- Salesforce client-credentials REST upsert client
- Inbound REST endpoints for Salesforce-to-WordPress sync
- Admin page for connection settings and queue monitoring
- Field-level Salesforce failure log with CSV export
- Optional daily error report email with CSV attachment
- Optional WordPress-initiated pull sync for Salesforce Contact and Membership Term updates
- PMPro transaction-line sync that separates membership dues from donations for accounting and tax reporting
- Versioned event envelopes, field ownership rules, lifecycle states, and reconciliation reporting

## Phase 2 data contract

Every queued event has an immutable UUID `event_id`, schema version, operation, source timestamp, and source. Contact uses the WordPress user ID as its external key; membership uses `aac-wp-user-{user_id}` when no historical term key exists; PMPro transaction lines use `{order_id}-membership` or `{order_id}-donation`. Configure each corresponding Salesforce external ID field as **Unique** and **External ID**.

Field Maps define authority per field: **WordPress**, **Salesforce**, or **Both**. Outbound processing skips Salesforce-owned fields, inbound processing skips WordPress-owned fields, and empty values are omitted unless **Clear Empty** is explicitly enabled. Inbound payloads may include `event_id`, `source_modified_at`, and `schema_version`; duplicates are ignored and older events are rejected.

Membership cancellation, refunded/cancelled orders, WordPress deletion, and user reassignment/merge are represented as lifecycle operations. If a mapped Salesforce object contains the fields, the plugin also sends `Integration_Event_ID__c`, `Integration_Schema_Version__c`, `Source_Modified_At__c`, `Integration_Source__c`, and `Lifecycle_Status__c`. Deletion and merge jobs require `Lifecycle_Status__c` so records are tombstoned rather than physically deleted.

The **Reconciliation** admin tab performs a read-only comparison of the latest 100 WordPress users with configured Salesforce Contact and Membership external IDs and can export the findings as CSV.

## Phase 3 operations

The **Health** admin tab is the production operations console. It reports authorization and worker scheduling, queue depth, oldest-pending age, dead-letter volume, 24-hour completed/failed counts, success rate, last completion, and the next worker run.

Operations can configure a pending-job SLA, dead-letter threshold, alert recipient, and completed-job retention. The hourly monitor sends an email when health first becomes critical and repeats no more than every six hours while the condition remains. It also prunes completed queue jobs and error rows according to their configured retention windows.

The plugin keeps a bounded 500-entry operational audit trail for job completions, failures, health alerts, and administrator settings changes. It records event/job IDs, object keys, retry state, and duration without storing OAuth credentials or inbound secrets. The same health summary appears in **Tools > Site Health** and in WordPress debug information.

## Phase 4 release and recovery

The **Release Readiness** tab provides a production preflight covering HTTPS, OAuth, worker scheduling, inbound-secret strength, metadata freshness, configured Salesforce objects, and external ID fields. A live Salesforce API test can be included. Preflight is advisory until all critical checks pass; warning checks identify hardening work such as marking external IDs unique.

The same tab exports a sanitized JSON support bundle and can reset up to 1,000 dead-letter jobs after the underlying problem is fixed. Recovery retains payloads, attempts, event IDs, and audit evidence.

WP-CLI commands are available for unattended operations:

- `wp aac-salesforce status`
- `wp aac-salesforce preflight --live`
- `wp aac-salesforce run-queue`
- `wp aac-salesforce retry-dead-letters --limit=1000`

Repository CI lints the plugin across PHP 7.4, 8.1, and 8.3, rejects merge markers, and verifies version consistency. See `PRODUCTION-RUNBOOK.md` for release gates, incident response, recovery, rollback, and evidence-handling procedures.

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

## Error Log and Daily CSV Report

Open `AAC Portal > Salesforce Sync > Error Log` to download failed sync rows as a CSV.

When a Salesforce job fails, the plugin records one row per field/value that was being sent. The CSV includes:

- `id` as the unique error-log row identifier
- failure date
- queue job ID and job type
- WordPress object ID and external key
- Salesforce object and field
- value being passed
- attempt number
- Salesforce/transport error message

The same tab can enable a daily email report. When enabled, WordPress cron sends the last 24 hours of failed field rows as a CSV attachment to the configured recipient. If no failed field rows exist for the last 24 hours, no email is sent.

## Optional Salesforce Pull Sync

Open `AAC Portal > Salesforce Sync > Salesforce Pull` to let WordPress poll Salesforce for changed records.

This is optional and disabled by default. The primary sync remains WordPress-to-Salesforce through the outbound queue. When enabled, WordPress uses the existing Salesforce OAuth connection and SOQL queries based on `SystemModstamp` to pull:

- Contact updates into WordPress user/profile fields
- Membership Term updates into WordPress membership access fields

The pull feature uses the existing Field Maps where possible, then sends the normalized payload through the same inbound WordPress update methods used by the REST endpoints. Transactions, Stripe IDs, PMPro orders, donations, and payment data remain WordPress/PMPro-to-Salesforce only.

The Salesforce Pull tab includes:

- Enable/disable switch
- Contact and Membership Term toggles
- schedule interval
- batch size
- initial lookback window
- manual `Run Pull Now`
- pull timestamp reset

Failed pull rows are written to the Error Log tab.

## PMPro transaction lines

PMPro stores checkout as a single order, but the sync exports accounting lines from that order:

- `Membership Dues`
- `Donation`, when a positive donation exists

Both rows carry the same PMPro/Stripe order and payment IDs, including `PMPro_Order_ID__c`, `Payment_Transaction_ID__c`, and `Subscription_Transaction_ID__c`. The Salesforce upsert key is line-specific and defaults to `PMPro_Order_Line_Key__c`, for example `12345-membership` and `12345-donation`, so the donation row does not overwrite the membership-dues row.

Discount codes and discount amounts are attached to the membership-dues line only. Donation rows do not inherit membership discounts.

Recommended transaction fields in Salesforce:

- `PMPro_Order_Line_Key__c` as a unique External ID
- `PMPro_Order_ID__c`
- `Product_Type__c`
- `Product_Name__c`
- `Amount__c`
- `Gross_Membership_Amount__c`
- `Discount_Amount__c`
- `Net_Membership_Amount__c`
- `Discount_Code__c`
- `Discount_Type__c`

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

Send the shared secret in the `X-AAC-SF-Secret` HTTP header. Query-string secrets are not accepted.

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
3. Add the `X-AAC-SF-Secret` HTTP header with the inbound shared secret configured in WordPress. Do not send this secret in the URL or request body.
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
- For production, keep monitoring WP-Cron/mail delivery and archive downloaded error CSVs before retention cleanup removes old rows.
