# Member import rollback

Rollback is available for staged **Current members / WordPress users** imports performed after this feature is installed. It covers manual sync and the automatic worker. It does not retroactively cover older imports or the separate subscription, order, history, and family-link upload handlers.

## Use

1. Keep a full database backup before migration. Upload and validate the member CSV.
2. Run the member import. Before each live row, the importer saves a private database checkpoint. It finalizes that checkpoint after member and family-link processing, including partial failures that return normally.
3. To undo, pause processing and wait for the batch to stop. Review the latest checkpoint's batch ID and timestamp in **Member Import Manager → Rollback latest member import**.
4. Confirm the checkbox and click **Roll back latest batch**. This reverses the latest CSV batch's journal entries, one per background request, newest first. WordPress cron must run. Refresh to view progress and the completion/failure notice.
5. Restored staging rows are marked `rolled_back` and are not automatically reimported. Upload a corrected CSV to import them again.

## Protection and limits

- Restores existing users and their local metadata, membership records, PMPro order/subscription records and related metadata, group rows, and AAC member database rows captured for the affected accounts. Removes newly created accounts only when the captured state still matches and they own no WordPress posts or comments.
- Checks the current records against the after-import snapshot. Any newer change stops rollback without overwriting it. Resolve conflicts before restarting. Parent and family state may cause a conflict if it changed afterward.
- Uses database transactions and requires supported single-primary-key InnoDB tables. An interrupted checkpoint without a complete after-image requires manual review; it is not automatically undone or skipped.
- Direct local restoration does not call WordPress user-deletion or PMPro cancellation hooks, send emails, or make Stripe requests. It cannot undo charges, external subscriptions, emails already sent, Salesforce/other remote changes, or arbitrary third-party tables and settings. WordPress user ID and member-number counters may retain gaps.
- Rollback and import workers share a lock. A stale lock after a server kill needs administrator investigation before clearing; never clear it while a worker might still be running.
- Journal records contain sensitive original user and metadata values, including password hashes. They stay in a private database table, are not downloadable from the UI, and must be included in the site's database access and backup protections.

## Validation

`php tests/import-rollback.test.php` exercises restoration, new-account removal, conflict detection, authored-content protection, transaction recovery, incomplete checkpoints, completion state, and worker locking using an in-memory transactional database double. This does not replace a staging-site acceptance test against the installed PMPro/Group Accounts schemas and persistent cache.
