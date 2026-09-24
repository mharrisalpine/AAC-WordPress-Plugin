# Checkout security release gate — 1.0.619

Target: WP Engine, americanalpine.wpenginepowered.com. No Wondrous deployment.

## Implemented

- Retain WordPress identity throughout GET, AJAX and checkout POST; no forced guest.
- Resolve checkout page from its saved option before PMPro initializes page globals.
- Remove server-side invalid-nonce replacement and alternate signed challenge.
- Refresh PMPro and communications tokens before payment handlers receive submission.
- Block submission if the form's session fingerprint differs from the current session.
- Keep form entries in the DOM on refresh/network failure; never persist passwords or payment fields in new browser storage.
- Restore submitted wizard step on server error, preferring a panel with a field error.
- Send successful signup to protected profile with a thank-you message; existing authentication gate shows login if needed.
- Mark checkout DONOTCACHEPAGE and send no-store responses. Host cache configuration still requires verification.

## Local checks

Run `node tools/test-checkout-security.cjs`, PHP lint on main plugin and fullscreen template, and `npm run package:wordpress`.

## Required live acceptance before declaring resolved

- Confirm target hostname and active plugin version; verify checkout-security.js is served.
- Verify WP Engine excludes the configured checkout route and admin-ajax.php from page/CDN cache. Inspect actual response headers; PHP cannot bypass a cache hit served before WordPress starts.
- New guest: complete signup using approved sandbox payment; confirm one order, correct member, profile destination and saved opt-ins.
- Existing member: verify displayed account identity, checkout, one order and profile destination.
- Staff: test new-member creation in a separate private browser, not under the staff login.
- Open form, then log in/out in another tab: submission must stop before payment, with session-change explanation.
- Keep a form open across deployment and refresh expiry: same-session current forms refresh; incompatible older forms must fail safely, never bypass verification.
- Offline refresh: retain entries and show retry message; no payment submission.
- Keyboard submit, submit button and rapid double click; no duplicate orders.
- Validation failure: correct field visible and entries retained where safe; password/card re-entry may be required.
- Verify Chrome and Safari. Confirm no browser errors and no nonce errors in checkout logs.

Do not use production payment details for acceptance without explicit authorization. Never treat a success query parameter as authorization or evidence of an order; profile data remains protected by the existing authenticated API.
