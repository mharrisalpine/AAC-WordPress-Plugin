<?php
if (!defined('ABSPATH')) { exit; }

/** Local-only cleanup: never invokes a payment gateway or membership cancellation. */
final class AAC_Member_Portal_Order_Cleanup {
 const PAGE = 'aac-order-cleanup';
 const BACKUP = 'aac_deleted_user_order_backup';
 public function __construct() {
  add_action('admin_menu', [$this, 'menu']);
  add_action('admin_post_aac_order_cleanup', [$this, 'handle']);
 }
 public function menu() {
  add_submenu_page('aac-member-portal-settings', 'Order Cleanup', 'Order Cleanup', 'manage_options', self::PAGE, [$this, 'render']);
 }
 private function tables() {
  global $wpdb;
  return [$wpdb->prefix . 'pmpro_membership_orders', $wpdb->prefix . 'pmpro_membership_ordermeta', $wpdb->prefix . 'aac_member_db_transactions'];
 }
 private function rows($sql) {
  global $wpdb;
  $rows = $wpdb->get_results($sql, ARRAY_A);
  if ($wpdb->last_error) { throw new RuntimeException('Could not read order records.'); }
  return $rows;
 }
 private function collect($lock = false) {
  global $wpdb;
  [$subs, $meta, $mirror] = $this->tables();
  $suffix = $lock ? ' FOR UPDATE' : '';
  // PMPro displays [deleted] only for positive user IDs with no WP user.
  $records = $this->rows("SELECT o.* FROM `$subs` o WHERE o.user_id > 0 AND NOT EXISTS (SELECT 1 FROM `$wpdb->users` u WHERE u.ID = o.user_id) ORDER BY o.id" . $suffix);
  $ids = array_map('intval', array_column($records, 'id'));
  $in = implode(',', $ids ?: [0]);
  return [$subs => $records, $meta => $this->rows("SELECT * FROM `$meta` WHERE pmpro_membership_order_id IN ($in) ORDER BY meta_id" . $suffix), $mirror => $this->rows("SELECT * FROM `$mirror` WHERE source_record_id IN ($in) ORDER BY id" . $suffix)];
 }
 public function render() {
  if (!current_user_can('manage_options')) { return; }
  echo '<div class="wrap"><h1>Deleted User Order Cleanup</h1>';
  echo '<p>Removes PMPro orders whose positive WordPress user ID no longer exists, plus order metadata and matching Member Database transaction copies. Orders with existing users or no assigned user are excluded. This does not refund payments or contact Stripe.</p>';
  if (isset($_GET['done'])) { echo '<div class="notice notice-success"><p>Operation completed successfully.</p></div>'; }
  try {
   $sets = $this->collect(); $tables = $this->tables();
   echo '<p><strong>' . count($sets[$tables[0]]) . ' PMPro orders; ' . count($sets[$tables[2]]) . ' Member Database copies.</strong></p>';
   echo '<table class="widefat striped"><thead><tr><th>PMPro ID</th><th>Order Code</th><th>WordPress user ID</th></tr></thead><tbody>';
   foreach ($sets[$tables[0]] as $r) { echo '<tr><td>' . (int)$r['id'] . '</td><td>' . esc_html($r['code'] ?? '') . '</td><td>' . (int)$r['user_id'] . '</td></tr>'; }
   echo '</tbody></table>';
   $backup = get_option(self::BACKUP);
   echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="aac_order_cleanup">';
   wp_nonce_field('aac_order_cleanup');
   if ($backup) {
    echo '<p>A restore snapshot is available from ' . esc_html($backup['created']) . '. Restore it before another cleanup. Restoration stops if any record ID has been reused.</p>';
    echo '<button class="button" name="operation" value="restore">Restore removed orders</button>';
   } elseif (array_sum(array_map('count', $sets))) {
    echo '<input type="hidden" name="preview" value="' . esc_attr(hash('sha256', wp_json_encode($sets))) . '"><p>The cleanup saves a private restore snapshot in this database before removing these records.</p>';
    echo '<button class="button button-primary" name="operation" value="remove">Remove orders for deleted users</button>';
   }
   echo '</form>';
  } catch (Throwable $e) { echo '<p>' . esc_html($e->getMessage()) . '</p>'; }
  echo '</div>';
 }
 public function handle() {
  if (!current_user_can('manage_options')) { wp_die('Administrator access required.'); }
  check_admin_referer('aac_order_cleanup');
  global $wpdb;
  $locked = false;
  try {
   if (is_multisite()) { throw new RuntimeException('This cleanup supports single-site WordPress only.'); }
   if (AAC_Member_Portal_Import_Background::active() || AAC_Member_Portal_Import_Rollback::active()) { throw new RuntimeException('Wait for the import or rollback to finish first.'); }
   if (!add_option(AAC_Member_Portal_Import_Background::LOCK, time(), '', false)) { throw new RuntimeException('An import worker is still active. Try again after it finishes.'); }
   $locked = true;
   if ((int)$wpdb->get_var("SELECT GET_LOCK('aac_deleted_user_order_cleanup', 0)") !== 1) { throw new RuntimeException('Another cleanup is running.'); }
   foreach (array_merge($this->tables(), [$wpdb->options]) as $table) {
    $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
    if (strtolower($status['Engine'] ?? '') !== 'innodb') { throw new RuntimeException('Cleanup requires transactional tables: ' . $table); }
   }
   if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Could not begin cleanup transaction.'); }
   $backup = get_option(self::BACKUP);
   if (($_POST['operation'] ?? '') === 'restore') {
    if (!$backup || array_keys($backup['rows']) !== $this->tables()) { throw new RuntimeException('No valid restore snapshot.'); }
    foreach ($backup['rows'] as $table => $rows) {
     foreach ($rows as $row) {
      // Insert preserves original IDs. A collision aborts the entire restoration.
      if ($wpdb->insert($table, $row) === false) { throw new RuntimeException('Restore stopped: a record conflicts with the snapshot. No restoration committed.'); }
     }
    }
    if (!delete_option(self::BACKUP)) { throw new RuntimeException('Could not finalize restoration.'); }
   } elseif (($_POST['operation'] ?? '') === 'remove') {
    if ($backup) { throw new RuntimeException('A restore snapshot already exists.'); }
    $sets = $this->collect(true);
    if (!hash_equals(hash('sha256', wp_json_encode($sets)), (string)($_POST['preview'] ?? ''))) { throw new RuntimeException('Orders changed since preview. Reload the cleanup page.'); }
    if (!add_option(self::BACKUP, ['created'=>current_time('mysql'), 'rows'=>$sets], '', false)) { throw new RuntimeException('Could not save restore snapshot.'); }
    foreach (array_reverse($sets, true) as $table => $rows) {
     $pk = $table === $this->tables()[1] ? 'meta_id' : 'id';
     foreach ($rows as $row) {
      if (!isset($row[$pk]) || $wpdb->delete($table, [$pk=>$row[$pk]]) !== 1) { throw new RuntimeException('Cleanup stopped: a record could not be removed. No deletions committed.'); }
     }
    }
   } else { throw new RuntimeException('Unknown operation.'); }
   if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('Commit failed.'); }
  } catch (Throwable $e) {
   $wpdb->query('ROLLBACK');
   wp_cache_delete(self::BACKUP, 'options'); wp_cache_delete('notoptions', 'options');
   if ($locked) { delete_option(AAC_Member_Portal_Import_Background::LOCK); }
   $wpdb->query("SELECT RELEASE_LOCK('aac_deleted_user_order_cleanup')");
   wp_die(esc_html($e->getMessage()));
  }
  wp_cache_delete(self::BACKUP, 'options'); wp_cache_delete('notoptions', 'options');
  delete_option(AAC_Member_Portal_Import_Background::LOCK);
  $wpdb->query("SELECT RELEASE_LOCK('aac_deleted_user_order_cleanup')");
  wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&done=1')); exit;
 }
}
