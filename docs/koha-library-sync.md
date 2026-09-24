# Koha Library Sync

Location: **AAC App → Member Database → Koha Library Sync**, beside Daily Member Export.

## Setup before enabling

1. Obtain the installed Koha version, HTTPS REST API base URL ending in `/api/v1`, home library code and patron category code from library staff. The public documentation URL is not your library's API endpoint.
2. In Koha, enable `RESTOAuth2ClientCredentials` and create API credentials for a dedicated service account. Grant patron list/edit permissions and read access to libraries and patron categories; do not grant patron deletion. Confirm the required endpoints exist on the installed Koha version.
3. Enter the client ID and client secret on the new settings page, along with the library/category codes. Select eligible PMPro levels, including adult/dependent levels if they receive library access. Save while disabled.
4. Use **Test connection (read only)**. It verifies OAuth, library/category codes and patron read access; it cannot prove write permissions without a test patron.
5. Test against a Koha sandbox with an AAC test membership before using production. Verify creation, renewal/reactivation and expiration, and confirm the library's circulation rules treat expired cards as intended. Staff overrides and local Koha rules can affect borrowing restrictions.
6. Choose whether to include existing active members (off by default). Choose whether to adopt matching existing Koha patrons (off by default). Then enable automatic sync and save. **Run sync now** processes a batch and schedules continuation if needed.
7. Configure server-driven WordPress cron if an exact two-hour cadence is important. WP-Cron alone requires site traffic. Monitor the last completed scan, next scheduled event and recent activity.

## Behavior

- An active, selected PMPro membership that has started and has not passed its end date qualifies. Any qualifying membership keeps a person active, including children with their own active PMPro rows. Billing subscription status does not determine eligibility. Turning off auto-renew does not end an otherwise active prepaid membership.
- The first enable records the maximum PMPro membership row ID. With backfill off, enrollment includes later active membership rows and inactive-to-active transitions observed by the scanner. Existing active rows are not mass-imported. Direct database changes that do not create a new row and occur entirely between scans are not event-history tracking.
- New patrons receive `cardnumber` from `aac_member_id`, deterministic `userid` of `aac-member-NUMBER`, first/last name, email, configured library/category, and `expiry_date`. No fallback to WordPress ID. Missing names/member numbers or duplicate AAC numbers are logged for correction.
- No passwords, payment details, addresses, emergency contacts, SMS numbers or marketing consents are copied. Koha password setup/welcome procedures should be configured by library staff.
- Multiple active levels use the latest qualifying end date. No-end-date memberships receive a rolling seven-day expiry, refreshed daily while active. This avoids leaving indefinite library access if synchronization stops; a prolonged outage can therefore expire such patrons and needs monitoring.
- Inactive managed patrons receive yesterday's `expiry_date`. Patron records, loans, fines and history are never deleted. Reactivation extends expiry on the same Koha patron. Other restrictions are not cleared.
- Existing matches require card number, email and surname verification. A matching integration login allows recovery of an interrupted creation. Other matching existing patrons require the explicit adoption option. Collisions are logged, never bypassed with a duplicate-override header.
- Existing patron updates send only expiry plus Koha's three required update fields (surname/library/category), preserving their remote values. Existing profiles and login credentials are not overwritten. A changed remote card number or missing/redacted fields requires manual review.

## Operational safeguards

- Disabled by default; administrator capability and nonce required for settings/test/run actions.
- Secret encrypted using AES-256-GCM and the WordPress auth salt; blank input preserves it. Rotating salts requires re-entry. No bearer token is persisted. A server compromise with both database and salts can decrypt credentials, so use a least-privilege service account.
- Public HTTPS endpoint required; SSRF-safe WordPress HTTP requests, certificate validation, no redirects carrying credentials, bounded response size/timeouts. Private-network endpoints require a separately reviewed deployment adaptation, not disabling these safeguards.
- MySQL advisory lock prevents concurrent workers/settings updates. Resumable keyset scans process at most 250 candidates per batch, with an 18-second soft budget between members and continuation after a minute. One slow member's in-flight requests can exceed the soft budget.
- Table `{prefix}aac_koha_members` retains managed patron mappings, observed eligibility, successful state, pending creation intent and errors. It survives deletion of a WordPress user so their managed patron can be expired. Do not clear this ledger to troubleshoot errors.
- A durable creation intent precedes POST. An uncertain POST is looked up by card number on retry, including if membership became inactive meanwhile. Successful signatures suppress redundant writes.
- Individual validation failures retry on subsequent complete scans. Authentication/rate-limit/server failures pause a scan at its checkpoint until the next scheduled cycle. Logs contain the latest 100 events, identifiers and safe messages, not credentials or raw remote responses. Unresolved per-member errors remain in the ledger.
- Missing PMPro or database errors halt the scan instead of treating everyone as inactive. Cron stops when disabled. A changed WordPress home URL blocks cloned sites from syncing. The Koha endpoint is locked after first activation; migrations require a deliberate developer-led mapping review.
- Removing an eligible level will expire already managed patrons with no other selected active membership. Disabling sync does not expire everyone; it simply stops requests.

## Verification

`php tools/test-koha-sync.php` runs offline contract tests against the production class with a mock API. It covers encrypted secrets, URL validation, dates/eligibility, creation, expiration, reactivation, duplicate prevention, verified adoption, and timeout recovery. It does not establish installed-Koha compatibility, WordPress cron behavior on the actual host, or production write permission.

Official references: [Koha API documentation](https://api.koha-community.org/), [patron endpoints](https://github.com/Koha-Community/Koha/blob/main/api/v1/swagger/paths/patrons.yaml), [patron schema](https://github.com/Koha-Community/Koha/blob/main/api/v1/swagger/definitions/patron.yaml), [OAuth endpoint](https://github.com/Koha-Community/Koha/blob/main/api/v1/swagger/paths/oauth.yaml). The implementation was checked against the current official schemas; confirm against the library's version before enabling.
