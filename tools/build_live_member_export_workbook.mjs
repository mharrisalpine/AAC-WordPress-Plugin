import fs from "node:fs/promises";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const SOURCE_CSV = process.argv[2];
const OUTPUT_DIR = process.argv[3];
if (!SOURCE_CSV || !OUTPUT_DIR) {
  throw new Error("Usage: build_live_member_export_workbook.mjs <source.csv> <output-dir>");
}

const php = String.raw`
$f = fopen($argv[1], 'r');
if (!$f) { fwrite(STDERR, "Unable to open CSV\n"); exit(2); }
$header = fgetcsv($f, null, ',', '"', '\\');
$rows = [];
while (($row = fgetcsv($f, null, ',', '"', '\\')) !== false) {
    if (count($row) !== count($header)) { continue; }
    $rows[] = array_combine($header, $row);
}
echo json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
`;

const sourceRows = JSON.parse(execFileSync("php", ["-r", php, SOURCE_CSV], {
  encoding: "utf8",
  maxBuffer: 100 * 1024 * 1024,
}));

const parseJson = (value, fallback) => {
  if (!value) return fallback;
  try { return JSON.parse(value); } catch { return fallback; }
};

const asNumber = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : value;
};

const asBool = (value) => {
  if (value === true || value === 1 || value === "1" || String(value).toLowerCase() === "true" || String(value).toLowerCase() === "yes") return true;
  if (value === false || value === 0 || value === "0" || String(value).toLowerCase() === "false" || String(value).toLowerCase() === "no") return false;
  return value || null;
};

const usersById = new Map(sourceRows.map((row) => [String(row.wp_user_id), row]));

const users = sourceRows.map((row) => ({
  wp_user_id: asNumber(row.wp_user_id),
  user_login: row.user_login,
  user_email: row.user_email,
  display_name: row.display_name,
  roles: row.roles,
  user_registered: row.user_registered,
  aac_member_id: row.aac_member_id,
  mirrored_at: row.mirrored_at,
}));

const profileKeys = [
  "wp_user_id", "aac_member_id", "first_name", "last_name", "display_name", "user_email", "phone", "birthdate",
  "billing_address_1", "billing_address_2", "billing_city", "billing_state", "billing_zip", "billing_country",
  "t_shirt_size", "aaj_preference", "accidents_preference", "acj_preference", "guidebook_preference",
  "membership_discount_type", "student_university", "student_graduation_date", "military_service_component",
  "emergency_contact_first_name", "emergency_contact_last_name", "emergency_contact_phone",
  "emergency_contact_email", "emergency_contact_relationship",
];
const profiles = sourceRows.map((row) => Object.fromEntries(profileKeys.map((key) => [key, key === "wp_user_id" ? asNumber(row[key]) : row[key]])));

const currentMembershipKeys = [
  "wp_user_id", "aac_member_id", "user_email", "current_membership_id", "current_membership_level",
  "current_membership_status", "membership_startdate", "membership_enddate", "membership_modified", "member_since",
  "renewal_date", "expiration_date", "valid_through_date", "auto_renew",
];
const currentMemberships = sourceRows.map((row) => Object.fromEntries(currentMembershipKeys.map((key) => {
  let value = row[key];
  if (["wp_user_id", "current_membership_id"].includes(key)) value = asNumber(value);
  if (key === "auto_renew") value = asBool(value);
  return [key, value];
})));

const addUserContext = (record, sourceRow) => ({
  source_wp_user_id: asNumber(sourceRow.wp_user_id),
  source_aac_member_id: sourceRow.aac_member_id,
  source_user_email: sourceRow.user_email,
  ...record,
});

const membershipHistory = sourceRows.flatMap((row) => {
  const records = parseJson(row.pmpro_membership_history_json, []);
  return Array.isArray(records) ? records.map((record) => addUserContext(record, row)) : [];
});

const subscriptions = sourceRows.flatMap((row) => {
  const records = parseJson(row.pmpro_subscriptions_json, []);
  return Array.isArray(records) ? records.map((record) => addUserContext(record, row)) : [];
});

const transactions = sourceRows.flatMap((row) => {
  const records = parseJson(row.pmpro_orders_json, []);
  return Array.isArray(records) ? records.map((record) => addUserContext(record, row)) : [];
});

const connectionMap = new Map();
const putConnection = (connection) => {
  const key = [connection.parent_wp_user_id, connection.child_wp_user_id, connection.invite_code, connection.slot_id].join("|");
  const existing = connectionMap.get(key);
  connectionMap.set(key, existing ? {...existing, ...Object.fromEntries(Object.entries(connection).filter(([,v]) => v !== null && v !== ""))} : connection);
};

for (const row of sourceRows) {
  const connected = parseJson(row.connected_accounts_json, []);
  if (Array.isArray(connected)) {
    for (const child of connected) {
      const childRow = usersById.get(String(child.child_user_id || ""));
      putConnection({
        relationship_source: "parent connected_accounts",
        parent_wp_user_id: asNumber(row.wp_user_id),
        parent_aac_member_id: row.aac_member_id,
        parent_email: row.user_email,
        parent_name: row.display_name,
        child_wp_user_id: asNumber(child.child_user_id),
        child_aac_member_id: childRow?.aac_member_id || "",
        child_email: child.child_email || childRow?.user_email || "",
        child_name: child.child_name || childRow?.display_name || "",
        slot_id: child.id || row.linked_account_slot_id || "",
        invite_code: child.invite_code || "",
        relationship_type: child.type || "",
        relationship_label: child.label || "",
        status: child.status || "",
        annual_price: asNumber(child.price),
        scheduled_removal_date: child.scheduled_removal_date || "",
      });
    }
  }
  const parent = parseJson(row.linked_parent_account_json, null);
  if (parent && typeof parent === "object" && !Array.isArray(parent) && parent.parent_user_id) {
    const parentRow = usersById.get(String(parent.parent_user_id));
    putConnection({
      relationship_source: "child linked_parent_account",
      parent_wp_user_id: asNumber(parent.parent_user_id),
      parent_aac_member_id: parentRow?.aac_member_id || row.parent_member_id || "",
      parent_email: parent.parent_email || parentRow?.user_email || row.parent_email || "",
      parent_name: parent.parent_name || parentRow?.display_name || "",
      child_wp_user_id: asNumber(row.wp_user_id),
      child_aac_member_id: row.aac_member_id,
      child_email: row.user_email,
      child_name: row.display_name,
      slot_id: row.linked_account_slot_id || "",
      invite_code: parent.invite_code || "",
      relationship_type: parent.type || "",
      relationship_label: parent.label || "",
      status: parent.status || "",
      annual_price: null,
      scheduled_removal_date: parent.scheduled_removal_date || "",
    });
  } else if (row.parent_user_id) {
    const parentRow = usersById.get(String(row.parent_user_id));
    putConnection({
      relationship_source: "flat family fields",
      parent_wp_user_id: asNumber(row.parent_user_id),
      parent_aac_member_id: row.parent_member_id || parentRow?.aac_member_id || "",
      parent_email: row.parent_email || parentRow?.user_email || "",
      parent_name: parentRow?.display_name || "",
      child_wp_user_id: asNumber(row.wp_user_id),
      child_aac_member_id: row.aac_member_id,
      child_email: row.user_email,
      child_name: row.display_name,
      slot_id: row.linked_account_slot_id || "",
      invite_code: "",
      relationship_type: row.account_role || "",
      relationship_label: "",
      status: "",
      annual_price: null,
      scheduled_removal_date: "",
    });
  }
}
const accountConnections = [...connectionMap.values()];

const groupAccounts = sourceRows.flatMap((row) => {
  const records = parseJson(row.pmpro_group_accounts_json, []);
  if (!Array.isArray(records)) return [];
  return records.map((record) => ({
    source_wp_user_id: asNumber(row.wp_user_id),
    source_aac_member_id: row.aac_member_id,
    source_user_email: row.user_email,
    source_table: record.table || "",
    record_id: asNumber(record.row?.id),
    group_id: asNumber(record.row?.group_id || (record.table?.includes("_groups") ? record.row?.id : null)),
    parent_wp_user_id: asNumber(record.row?.group_parent_user_id),
    parent_level_id: asNumber(record.row?.group_parent_level_id),
    checkout_code: record.row?.group_checkout_code || "",
    total_seats: asNumber(record.row?.group_total_seats),
    child_wp_user_id: asNumber(record.row?.group_child_user_id),
    child_level_id: asNumber(record.row?.group_child_level_id),
    child_status: record.row?.group_child_status || "",
    status_updated: record.row?.status_updated || "",
  }));
});

const excludedMetaPatterns = [/^_application_passwords$/i, /password[_-]?hash/i, /session[_-]?token/i, /^session_tokens$/i, /auth[_-]?token/i, /nonce/i];
const userMeta = [];
const excludedMetaKeys = new Set();
for (const row of sourceRows) {
  const meta = parseJson(row.all_user_meta_json, {});
  if (!meta || typeof meta !== "object" || Array.isArray(meta)) continue;
  for (const [metaKey, rawValue] of Object.entries(meta)) {
    if (excludedMetaPatterns.some((pattern) => pattern.test(metaKey))) {
      excludedMetaKeys.add(metaKey);
      continue;
    }
    const value = typeof rawValue === "string" ? rawValue : JSON.stringify(rawValue);
    userMeta.push({
      wp_user_id: asNumber(row.wp_user_id),
      aac_member_id: row.aac_member_id,
      user_email: row.user_email,
      meta_key: metaKey,
      meta_value: value.length > 32000 ? `${value.slice(0, 31900)}…` : value,
    });
  }
}

const jsonFields = new Set([
  "connected_accounts_json", "linked_parent_account_json", "family_membership_json", "pmpro_membership_history_json",
  "pmpro_subscriptions_json", "pmpro_orders_json", "pmpro_group_accounts_json", "all_user_meta_json", "portal_profile_json",
]);
const rawExportHeaders = Object.keys(sourceRows[0] || {}).filter((key) => !jsonFields.has(key));
const rawExport = sourceRows.map((row) => Object.fromEntries(rawExportHeaders.map((key) => [key, row[key]])));

const fieldGroups = {
  "WordPress Account": ["wp_user_id","user_login","user_email","user_registered","display_name","roles"],
  "AAC Profile": ["aac_member_id","first_name","last_name","phone","birthdate","billing_address_1","billing_address_2","billing_city","billing_state","billing_zip","billing_country","t_shirt_size","aaj_preference","accidents_preference","acj_preference","guidebook_preference","membership_discount_type","student_university","student_graduation_date","military_service_component","emergency_contact_first_name","emergency_contact_last_name","emergency_contact_phone","emergency_contact_email","emergency_contact_relationship"],
  "Current Membership": ["current_membership_id","current_membership_level","current_membership_status","membership_startdate","membership_enddate","membership_modified","member_since","renewal_date","expiration_date","valid_through_date","auto_renew"],
  "Subscription / Stripe": ["current_subscription_id","current_subscription_gateway","current_subscription_environment","current_subscription_transaction_id","current_subscription_status","current_subscription_startdate","current_subscription_enddate","current_subscription_next_payment_date","current_subscription_billing_amount","current_subscription_cycle_number","current_subscription_cycle_period","stripe_customer_id"],
  "Latest Order / Payment": ["latest_order_id","latest_order_code","latest_order_membership_id","latest_order_status","latest_order_gateway","latest_order_environment","latest_order_payment_transaction_id","latest_order_subscription_transaction_id","latest_order_subtotal","latest_order_tax","latest_order_total","latest_order_timestamp","latest_order_discount_code"],
  "Family / Group Accounts": ["account_role","parent_user_id","parent_member_id","parent_email","linked_account_slot_id","family_membership_mode","family_additional_adult","family_dependent_count","group_account_id","group_account_code","group_account_seat_count","aac_group_account_group_id","aac_group_account_checkout_code","aac_group_account_child_level_id","aac_group_account_synced_at","pmpro_group_account_id","pmpro_group_account_checkout_code","pmpro_group_account_total_seats","pmpro_group_account_active_members"],
  "Raw JSON / Audit": [...jsonFields, "mirrored_at"],
};
const groupByField = new Map(Object.entries(fieldGroups).flatMap(([group, keys]) => keys.map((key) => [key, group])));
const label = (field) => field.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
const joinNotes = {
  wp_user_id: "Primary WordPress user key; joins every member-centered sheet.",
  aac_member_id: "AAC business identifier; use as a secondary cross-system key.",
  parent_user_id: "Foreign key to Users.wp_user_id for the paying/parent account.",
  current_membership_id: "Foreign key to the PMPro membership level ID.",
  latest_order_id: "PMPro order identifier; detailed rows are in Transactions.",
  current_subscription_id: "PMPro subscription record identifier; detailed rows are in Subscriptions.",
};
const sensitiveGroups = new Set(["WordPress Account", "AAC Profile", "Subscription / Stripe", "Latest Order / Payment", "Family / Group Accounts"]);
const fieldDictionary = Object.keys(sourceRows[0] || {}).map((field) => ({
  source_section: groupByField.get(field) || "Other",
  export_field: field,
  display_name: label(field),
  primary_workbook_tab: groupByField.get(field) === "AAC Profile" ? "Member Profiles"
    : groupByField.get(field) === "WordPress Account" ? "Users"
    : groupByField.get(field) === "Current Membership" ? "Current Memberships"
    : groupByField.get(field) === "Subscription / Stripe" ? "Subscriptions"
    : groupByField.get(field) === "Latest Order / Payment" ? "Transactions"
    : groupByField.get(field) === "Family / Group Accounts" ? "Account Connections / Group Accounts"
    : "Raw Export / normalized child tables",
  source_of_truth: groupByField.get(field)?.includes("Membership") || groupByField.get(field)?.includes("Order") || groupByField.get(field)?.includes("Subscription") ? "PMPro / gateway mirrored by AAC" : "WordPress / AAC Member App mirror",
  join_or_relationship_note: joinNotes[field] || "",
  contains_personal_data: sensitiveGroups.has(groupByField.get(field)) ? "Yes" : "Review",
}));

const tables = [
  ["Users", "WordPress account identity", "wp_user_id", "aac_member_id; user_email", users.length, "One row per exported WordPress user."],
  ["Member Profiles", "AAC profile, contact, address, preferences, and eligibility", "wp_user_id", "aac_member_id", profiles.length, "One row per user; contains PII."],
  ["Current Memberships", "Current membership status and term", "wp_user_id", "current_membership_id", currentMemberships.length, "Current snapshot; historical terms are separate."],
  ["Membership History", "PMPro membership term history", "id", "user_id → Users.wp_user_id; membership_id → PMPro level", membershipHistory.length, "One row per PMPro history record."],
  ["Subscriptions", "Recurring subscription records", "id", "user_id → Users.wp_user_id; membership_level_id → PMPro level", subscriptions.length, "Gateway identifiers are references, not card credentials."],
  ["Transactions", "PMPro orders and payment results", "id", "user_id → Users.wp_user_id; membership_id → PMPro level", transactions.length, "Order total is authoritative for the recorded PMPro order."],
  ["Account Connections", "AAC parent/child and linked-account relationships", "parent_wp_user_id + child_wp_user_id", "Both IDs → Users.wp_user_id", accountConnections.length, "Combines parent-side and child-side relationship snapshots."],
  ["Group Accounts", "Native PMPro Group Accounts rows", "source_table + record_id", "group_id; parent/child user IDs → Users.wp_user_id", groupAccounts.length, "Contains group header and group member records."],
  ["User Meta", "Non-secret WordPress user metadata", "wp_user_id + meta_key", "wp_user_id → Users.wp_user_id", userMeta.length, "Authentication/session/password values intentionally excluded."],
  ["Raw Export", "Flat live Member Database export without raw JSON blobs", "wp_user_id", "See Field Dictionary", rawExport.length, "Use normalized tabs for analysis."],
];

const workbook = Workbook.create();
workbook.comments.setSelf({ displayName: "Marshall Harris" });
const sheetStats = new Map();

const COLORS = {
  black: "#111111", gold: "#FFC72C", red: "#C32026", white: "#FFFFFF",
  lightGold: "#FFF4CC", lightGray: "#F3F4F6", midGray: "#D1D5DB", darkGray: "#4B5563",
};

const excelCol = (index) => {
  let n = index + 1;
  let result = "";
  while (n > 0) { n -= 1; result = String.fromCharCode(65 + (n % 26)) + result; n = Math.floor(n / 26); }
  return result;
};

const styleTitle = (sheet, title, subtitle, columnCount) => {
  const last = excelCol(Math.min(12, Math.max(1, columnCount)) - 1);
  sheet.showGridLines = false;
  sheet.getRange(`A1:${last}1`).merge();
  sheet.getRange("A1").values = [[title]];
  sheet.getRange("A1").format = { fill: COLORS.black, font: { bold: true, color: COLORS.white, size: 16 }, rowHeight: 30, verticalAlignment: "center" };
  sheet.getRange(`A2:${last}2`).merge();
  sheet.getRange("A2").values = [[subtitle]];
  sheet.getRange("A2").format = { fill: COLORS.lightGold, font: { color: COLORS.black, italic: true }, wrapText: true, rowHeight: 28, verticalAlignment: "center" };
};

const humanizeHeaders = (headers) => headers.map((header) => label(header));

const applyWidths = (sheet, headers, rowCount) => {
  headers.forEach((header, index) => {
    const col = excelCol(index);
    const descriptive = /address|notes|meta_value|relationship|source|description|email|name|street|join_or|primary_workbook|source_of_truth/i.test(header);
    const longId = /transaction|subscription|checkout_code|customer|invite_code/i.test(header);
    const width = descriptive ? 24 : longId ? 22 : /date|timestamp|modified|registered|updated/i.test(header) ? 19 : /_id$|^id$|amount|subtotal|total|tax|price|seats|count|number|limit/i.test(header) ? 14 : 18;
    sheet.getRange(`${col}4:${col}${Math.max(5, rowCount + 4)}`).format.columnWidth = width;
  });
};

const addDataSheet = (name, subtitle, records, preferredHeaders = null, tableName = null) => {
  const sheet = workbook.worksheets.add(name);
  const headers = preferredHeaders || (records[0] ? Object.keys(records[0]) : ["message"]);
  styleTitle(sheet, name, subtitle, headers.length);
  const values = records.length ? records.map((record) => headers.map((header) => record[header] ?? null)) : [["No records exported"]];
  const finalHeaders = records.length ? headers : ["message"];
  const endCol = excelCol(finalHeaders.length - 1);
  sheet.getRange(`A4:${endCol}4`).values = [humanizeHeaders(finalHeaders)];
  sheet.getRange(`A4:${endCol}${4 + values.length}`).getRangeByIndexes(1, 0, values.length, finalHeaders.length).values = values;
  const table = sheet.tables.add(`A4:${endCol}${4 + values.length}`, true, tableName || name.replace(/[^A-Za-z0-9]/g, "") + "Table");
  table.style = "TableStyleMedium2";
  table.showFilterButton = true;
  sheet.getRange(`A4:${endCol}4`).format = { fill: COLORS.gold, font: { bold: true, color: COLORS.black }, wrapText: true, rowHeight: 34, verticalAlignment: "center" };
  sheet.getRange(`A5:${endCol}${4 + values.length}`).format = { font: { color: COLORS.black, size: 9 }, verticalAlignment: "top" };
  applyWidths(sheet, finalHeaders, values.length);
  sheet.getUsedRange().format.wrapText = true;
  sheet.freezePanes.freezeRows(4);
  sheetStats.set(name, { headers: finalHeaders, dataRows: values.length });
  return {sheet, headers: finalHeaders, rows: values.length};
};

// Overview and relationship guide.
const overview = workbook.worksheets.add("Overview");
styleTitle(overview, "AAC WP Engine Member Data Export", "Live export from the AAC Member Database mirror. Contains personal information; store and share securely.", 6);
overview.getRange("A4:B4").values = [["Dataset", "Row Count"]];
overview.getRange("A5:A14").values = tables.map((row) => [row[0]]);
overview.getRange("D4:E4").values = [["Export Detail", "Value"]];
overview.getRange("D5:E10").values = [
  ["Source site", "https://americanalpine.wpenginepowered.com/"],
  ["Source page", "AAC Portal → Member Database Export"],
  ["Export date", "2026-09-01"],
  ["Selected source fields", Object.keys(sourceRows[0] || {}).length],
  ["Excluded security meta keys", [...excludedMetaKeys].sort().join(", ") || "None found"],
  ["Relationship rule", "Join on wp_user_id. Parent and child IDs both reference Users.wp_user_id."],
];
overview.getRange("A4:B14").format.borders = { preset: "outside", style: "thin", color: COLORS.midGray };
overview.getRange("D4:E10").format.borders = { preset: "outside", style: "thin", color: COLORS.midGray };
overview.getRange("A4:B4").format = { fill: COLORS.gold, font: { bold: true, color: COLORS.black } };
overview.getRange("D4:E4").format = { fill: COLORS.gold, font: { bold: true, color: COLORS.black } };
overview.getRange("A16:F16").merge();
overview.getRange("A16").values = [["How to read the relationships"]];
overview.getRange("A16").format = { fill: COLORS.black, font: { bold: true, color: COLORS.white, size: 12 } };
overview.getRange("A17:F20").merge(true);
overview.getRange("A17:F20").values = [
  ["1. Users.wp_user_id is the central identity key."],
  ["2. Membership History, Subscriptions, and Transactions use user_id to point back to Users."],
  ["3. Account Connections uses parent_wp_user_id and child_wp_user_id to show family relationships."],
  ["4. Group Accounts preserves the native PMPro group header/member records and their group_id relationship."],
];
overview.getRange("A17:F20").format = { fill: COLORS.lightGray, wrapText: true, rowHeight: 24 };
overview.getRange("A1:F20").format.font.name = "Aptos";
overview.getRange("A:A").format.columnWidth = 24;
overview.getRange("B:B").format.columnWidth = 14;
overview.getRange("C:C").format.columnWidth = 3;
overview.getRange("D:D").format.columnWidth = 24;
overview.getRange("E:E").format.columnWidth = 54;
overview.getRange("F:F").format.columnWidth = 12;
overview.freezePanes.freezeRows(4);

const tableMapRecords = tables.map(([worksheet, purpose, primaryKey, foreignKeys, rowCount, notes]) => ({worksheet, purpose, primary_key: primaryKey, foreign_keys_and_joins: foreignKeys, row_count: rowCount, notes}));
addDataSheet("Table Map", "One row per workbook table, including its purpose and relationship keys.", tableMapRecords, null, "TableMapTable");
addDataSheet("Field Dictionary", "All 96 live export fields categorized by ownership and destination worksheet.", fieldDictionary, null, "FieldDictionaryTable");
addDataSheet("Users", "WordPress account identity and AAC member identifiers.", users, null, "UsersTable");
addDataSheet("Member Profiles", "AAC member contact, billing, preference, discount, and emergency-contact fields.", profiles, profileKeys, "MemberProfilesTable");
addDataSheet("Current Memberships", "Current membership snapshot. Join wp_user_id to Users; level IDs are PMPro membership levels.", currentMemberships, currentMembershipKeys, "CurrentMembershipsTable");
addDataSheet("Membership History", "All PMPro membership history records returned by the live export.", membershipHistory, null, "MembershipHistoryTable");
addDataSheet("Subscriptions", "All PMPro subscription records returned by the live export.", subscriptions, null, "SubscriptionsTable");
addDataSheet("Transactions", "All PMPro order/payment records returned by the live export; card data is limited to masked account metadata.", transactions, null, "TransactionsTable");
addDataSheet("Account Connections", "Unified AAC parent/child linked-account relationships from both parent and child snapshots.", accountConnections, null, "AccountConnectionsTable");
addDataSheet("Group Accounts", "Native PMPro Group Accounts header and group-member records.", groupAccounts, null, "GroupAccountsTable");
addDataSheet("User Meta", "Non-secret WordPress user metadata. Password, session-token, auth-token, and nonce-like values are excluded.", userMeta, null, "UserMetaTable");
addDataSheet("Raw Export", "Flat live export fields excluding nested raw JSON blobs; use normalized tabs for relational analysis.", rawExport, rawExportHeaders, "RawExportTable");

// Populate the overview formulas only after every referenced worksheet exists.
overview.getRange("B5:B14").formulas = [
  [`=COUNTA('Users'!$A$5:$A$${4 + users.length})`],
  [`=COUNTA('Member Profiles'!$A$5:$A$${4 + profiles.length})`],
  [`=COUNTA('Current Memberships'!$A$5:$A$${4 + currentMemberships.length})`],
  [`=COUNTA('Membership History'!$A$5:$A$${4 + membershipHistory.length})`],
  [`=COUNTA('Subscriptions'!$A$5:$A$${4 + subscriptions.length})`],
  [`=COUNTA('Transactions'!$A$5:$A$${4 + transactions.length})`],
  [`=COUNTA('Account Connections'!$A$5:$A$${4 + accountConnections.length})`],
  [`=COUNTA('Group Accounts'!$A$5:$A$${4 + groupAccounts.length})`],
  [`=COUNTA('User Meta'!$A$5:$A$${4 + userMeta.length})`],
  [`=COUNTA('Raw Export'!$A$5:$A$${4 + rawExport.length})`],
];

// Semantic number formats.
for (const sheetName of ["Membership History", "Subscriptions", "Transactions"]) {
  const sheet = workbook.worksheets.getItem(sheetName);
  const stats = sheetStats.get(sheetName);
  const headers = stats.headers;
  const lastDataRow = 4 + stats.dataRows;
  headers.forEach((header, index) => {
    const col = excelCol(index);
    if (/amount|payment|subtotal|tax|total|price/i.test(String(header))) sheet.getRange(`${col}5:${col}${lastDataRow}`).format.numberFormat = "$#,##0.00";
    if (/date|timestamp|modified/i.test(String(header))) sheet.getRange(`${col}5:${col}${lastDataRow}`).format.numberFormat = "yyyy-mm-dd hh:mm";
  });
}

await fs.mkdir(OUTPUT_DIR, { recursive: true });
const outputPath = path.join(OUTPUT_DIR, "AAC_WPEngine_Member_Data_Export_2026-09-01.xlsx");
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);

const checks = {};
for (const sheetName of ["Overview", "Table Map", "Field Dictionary", "Users", "Member Profiles", "Current Memberships", "Membership History", "Subscriptions", "Transactions", "Account Connections", "Group Accounts", "User Meta", "Raw Export"]) {
  const sheet = workbook.worksheets.getItem(sheetName);
  const used = sheet.getUsedRange();
  const stats = sheetStats.get(sheetName);
  const previewRange = sheetName === "Overview" ? "A1:F20" : `A1:${excelCol(Math.min(stats.headers.length, 12) - 1)}${Math.min(4 + stats.dataRows, 25)}`;
  const preview = await workbook.render({ sheetName, range: previewRange, scale: 1, format: "png" });
  const previewPath = path.join(OUTPUT_DIR, `preview-${sheetName.replace(/[^A-Za-z0-9]+/g, "-").toLowerCase()}.png`);
  await fs.writeFile(previewPath, new Uint8Array(await preview.arrayBuffer()));
  checks[sheetName] = { usedRange: used.address, previewRange, previewPath };
}

const inspect = await workbook.inspect({kind:"table", range:"Overview!A1:F20", include:"values,formulas", tableMaxRows:20, tableMaxCols:6, maxChars:6000});
const errors = await workbook.inspect({kind:"match", searchTerm:"#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A", options:{useRegex:true,maxResults:300}, summary:"final formula error scan", maxChars:4000});
console.log(JSON.stringify({
  outputPath,
  counts: {users:users.length, profiles:profiles.length, currentMemberships:currentMemberships.length, membershipHistory:membershipHistory.length, subscriptions:subscriptions.length, transactions:transactions.length, accountConnections:accountConnections.length, groupAccounts:groupAccounts.length, userMeta:userMeta.length},
  excludedMetaKeys:[...excludedMetaKeys].sort(),
  checks,
  overviewInspect: inspect.ndjson,
  formulaErrors: errors.ndjson,
}, null, 2));
