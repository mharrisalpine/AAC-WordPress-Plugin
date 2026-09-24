# AAC Salesforce Sync Production Runbook

## Release gate

1. Deploy the exact ZIP that passed staging.
2. Open **AAC Portal > Salesforce Sync > Release Readiness**.
3. Refresh Salesforce metadata from **Authorize** after any Salesforce schema change.
4. Run Production Preflight with the live API test enabled.
5. Do not promote while any critical check fails.
6. Run a small Contact test job, confirm completion, then release the normal queue.

The command-line equivalent is:

```bash
wp aac-salesforce preflight --live
wp aac-salesforce status
wp aac-salesforce run-queue
```

## Incident response

1. Check **Health** for authorization, cron, pending-job age, and dead-letter volume.
2. Check **Jobs** and **Error Log** for the first failure, not only the latest retry.
3. Download the sanitized diagnostics JSON from **Release Readiness** before changing configuration.
4. Correct the Salesforce field, permission, validation rule, OAuth, or WordPress data issue.
5. Run Production Preflight again.
6. Retry dead-letter jobs only after preflight passes.

Bulk recovery preserves payloads and immutable event IDs:

```bash
wp aac-salesforce retry-dead-letters --limit=1000
wp aac-salesforce run-queue
```

## Rollback

1. Keep the previously approved plugin ZIP.
2. Replace the plugin with that ZIP through WordPress plugin upload.
3. Do not truncate the queue. Newer queue columns are backward-compatible and retain recovery evidence.
4. Disable outbound sync if the prior version cannot safely process queued work.
5. Export diagnostics and error CSVs for the incident record.

## Security and evidence

- Never attach raw OAuth tokens, client secrets, or inbound secrets to a ticket.
- The diagnostics export intentionally excludes those values.
- Preserve the diagnostics JSON, error CSV, deployed ZIP checksum, release time, and operator identity for material incidents.
- Rotate OAuth credentials and the inbound shared secret immediately if they may have been exposed.

