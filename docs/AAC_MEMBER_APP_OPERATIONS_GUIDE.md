# AAC Member App: Administrator, PMPro, and Developer Guide

## 1. Purpose

This is the operating guide for the American Alpine Club member app in this repository. It explains how the React application, WordPress, Paid Memberships Pro (PMPro), family accounts, imports, payments, and administrator settings work together.

It is intended for WordPress administrators, membership staff, developers, and release operators. The application is not a replacement for PMPro; it is a branded member experience built on WordPress and PMPro.

## 2. System overview

| Layer | Responsibility |
| --- | --- |
| React/Vite app | Member-facing profile, signup, publications, benefits, linked accounts, billing, and navigation |
| AAC Member Portal plugin | Mounts React, exposes REST, injects settings, integrates PMPro, and implements AAC-specific rules |
| WordPress | Users, authentication, pages, media, options, user metadata, email, and permissions |
| PMPro | Levels, prices, recurring terms, orders, gateways, subscriptions, checkout, billing, and cancellation |

Normal runtime flow:

```text
Browser -> WordPress page with [aac_member_portal]
        -> plugin injects AAC_MEMBER_PORTAL_CONFIG and built assets
        -> React loads a hash route
        -> React calls /wp-json/aac/v1/* with the WordPress session
        -> REST layer combines WordPress, PMPro, portal, and family data
        -> React renders the normalized member experience
```

Checkout flow:

```text
React plan selection -> PMPro checkout for selected level
 -> AAC plugin arranges/styles PMPro fields and adds AAC fields
 -> PMPro validates, charges the gateway, creates the order,
    assigns the membership, and runs normal PMPro hooks
 -> AAC plugin persists supplemental choices and refreshes portal data
```

### Source-of-truth rule

- Change prices, recurrence, trials, gateways, taxes, discount codes, or subscriptions in **PMPro**.
- Change portal copy, images, colors, navigation, benefit descriptions, and publication links in **AAC Portal settings**.
- Change identity, WordPress roles, passwords, or raw user data in **WordPress Users** or the **AAC Member Database**.
- Change group capacity and child-level behavior through **PMPro Group Accounts** and AAC family tools.
- Change code only when the behavior cannot be expressed through those settings.

The amount on a React card is marketing display. PMPro's selected level and checkout calculation are authoritative for the amount charged.

## 3. Repository map

| Path | Purpose |
| --- | --- |
| `src/` | React source |
| `src/pages/` | Route-level experiences |
| `src/components/` | Shared UI |
| `src/lib/` | API client, tiers, settings, access rules, utilities |
| `wordpress/aac-member-portal/` | Installable WordPress plugin source |
| `wordpress/aac-member-portal/includes/` | REST, PMPro, settings, groups, imports, exports, database services |
| `wordpress/aac-member-portal/templates/` | WordPress shells for portal and PMPro pages |
| `wordpress/aac-member-portal/app/` | Generated frontend; do not hand-edit |
| `tools/package-wordpress-plugin.js` | Builds the deployment ZIP |
| `docs/` | Operational and integration references |

Key entry points:

- Routes: `src/main.jsx`
- WordPress bootstrap and PMPro presentation: `wordpress/aac-member-portal/aac-member-portal.php`
- REST API: `includes/class-aac-member-portal-api.php`
- PMPro adapter: `includes/class-aac-member-portal-pmpro.php`
- Admin settings: `includes/class-aac-member-portal-admin.php`
- Defaults/sanitization: `includes/class-aac-member-portal-settings-schema.php`
- Runtime settings: `includes/class-aac-member-portal-runtime-config.php`
- Family accounts: `includes/class-aac-member-portal-group-accounts.php`

## 4. Installation and required pages

### Required plugins

Install and activate PMPro and AAC Member Portal. These PMPro add-ons are required when their feature is used:

- **Donations Add On** for checkout donations
- **Auto-Renewal Checkbox Add On** for member-controlled renewal
- **Proration and Delayed Downgrades Add On** for credits and end-of-term downgrades
- **Group Accounts** for parent and linked family memberships

The portal styles and coordinates these add-ons; it must not duplicate their financial calculations.

### Portal page

Create a WordPress page with a Shortcode block containing:

```text
[aac_member_portal]
```

Use a Shortcode block/widget, not Custom HTML. The plugin also supports `[aac_member_login]`, `[aac_member_signup]`, and `[aac_brand_discounts]`.

### PMPro pages

In **Memberships > Settings > Pages**, verify valid assignments for Account, Billing Information, Checkout, Cancel, Confirmation, and Orders/Invoice History. Each page must contain its correct PMPro block or shortcode. The AAC plugin may replace the outer shell or embed the result, but still depends on PMPro's page assignment and form output.

The public signup route is `/signup/`. The join experience can also be reached at the configured portal URL with `#/join`. Do not send members to an obsolete test signup page.

## 5. Frontend routes

| Hash route | Function |
| --- | --- |
| `/`, `/profile` | Member card and profile |
| `/change-password` | Password change |
| `/home` | Portal home |
| `/join` | Plans and PMPro checkout entry |
| `/membership` | Status, transactions, plan management, and billing update |
| `/membership/upgrade` | Standalone upgrade view |
| `/publications` | Publication access/preferences |
| `/discounts` | Discounts and benefits |
| `/rescue` | Rescue benefits |
| `/linked-accounts` | Family/linked accounts |
| `/contact` | Member support |
| `/login` | Sign-in |

`src/App.jsx` supplies the application shell; `src/main.jsx` owns routing. Navigation visibility and order are supplied by WordPress runtime settings.

## 6. Authentication and security

Production uses normal WordPress authentication:

- WordPress owns users and password hashes.
- The browser sends same-origin WordPress cookies.
- Mutating REST requests send `X-WP-Nonce`.
- The API client uses `credentials: include`.
- Public authentication routes are rate-limited.
- Member endpoints require `is_user_logged_in()`.
- Administrator diagnostics require `manage_options`.

Do not publicly cache authenticated REST responses. Exclude portal, checkout, billing, account, and confirmation pages from caching or optimization that breaks cookies, nonces, modules, or iframes.

Normalized profiles are cached for 90 seconds. Relevant user-meta changes, profile updates, PMPro checkout/level changes, family links, and portal-setting updates invalidate the cache.

Signup checks `GET /wp-json/aac/v1/email-availability`; final uniqueness enforcement still occurs on the server during account creation.

## 7. Data model

### WordPress data

Identity lives in `wp_users` and `wp_usermeta`. Portal metadata includes `aac_account_info`, `aac_profile_info`, `aac_benefits_info`, family configuration, connected accounts, publication choices, address, phone, and T-shirt data.

### PMPro data

PMPro is authoritative for current membership, status, dates, subscription/gateway IDs, billing amount/cycle, orders, transactions, and discount-code use.

### Normalized profile

`GET /wp-json/aac/v1/me` combines sources into:

- `account_info`: contact, address, preferences, payment summary
- `profile_info`: member number, tier, status, dates, plan type
- `benefits_info`: rescue-related values
- `membership_actions`: billing, checkout, cancel, upgrade/downgrade URLs/state
- connected accounts and other feature data

Frontend code should consume this contract instead of querying PMPro tables.

## 8. PMPro integration

### Membership catalog

| Level | Public | Default source display |
| --- | --- | ---: |
| Free | No | $0 |
| Supporter | Yes | $45/year |
| Partner | Yes | $100/year |
| Leader | Yes | $250/year |
| Advocate | Yes | $500/year |
| GRF | Staff/manual only | Not public |

`Lifetime` displays as Advocate. `Partner Family` displays as Partner with family plan type. IDs and prices may differ by environment, so confirm the target PMPro configuration before imports or releases.

To change a real charge, edit **Memberships > Settings > Levels**, save the initial payment/recurrence/cycle/expiration, then verify a clean checkout. If the marketing card also needs to change, update frontend tier metadata and rebuild.

### Checkout ownership

PMPro builds and submits the real form. The AAC plugin:

- renders the checkout nonce
- normalizes required profile/billing fields
- validates student-specific fields
- presents military, student, family, and publication choices
- coordinates donation and auto-renewal add-on fields
- arranges promo, summary, and payment sections
- records confirmation line items
- synchronizes successful checkout profile data
- updates family dates and invalidates caches

The plugin must not compute a final gateway charge independently. For price errors, inspect the PMPro level, discount mapping, donation, family adjustment, PMPro order, and gateway transaction.

### Signup sequence

The UI presents Account Information, Member Information, Publication Preferences when eligible, then Discounts/Donation/Promo/Auto-renewal/Summary/Payment. All PMPro-required inputs must remain inside the submitted PMPro form. Visually hiding a field does not make it optional.

### Publications

Digital is the default. Selecting a card requests print; an unselected card represents digital. Supporter skips publication selection. A change must preserve both frontend values and the server-side PMPro/user-meta mapping.

### Discounts and donations

Student and military choices reveal supporting fields. PMPro promotion codes remain configured in PMPro and must be valid for the selected level. Donations come from the PMPro Donations Add On and must appear once in both line items and total.

### Auto-renewal, upgrades, downgrades

The renewal control is supplied by the PMPro add-on and styled by AAC. PMPro/gateway own the subscription. Upgrades route through checkout; PMPro's add-on owns proration. Supported downgrades can be scheduled for the renewal boundary.

### Billing updates

**Update Billing Information** on `#/membership` embeds PMPro's real billing form using `aac_embed=1`, which suppresses duplicate public navigation. The embedded page reports its height to the parent.

If the form is wrong, verify PMPro's Billing page assignment, its PMPro block or `[pmpro_billing]`, the member's compatible gateway subscription, the `aac_embed=1` query, and the iframe DOM/network response.

### Transactions

The register reads PMPro orders. Checkout captures membership, discount, donation, promo, and total information for line-item display. Reconcile a discrepancy against the PMPro order and gateway transaction ID, not only the browser UI.

## 9. Family and linked accounts

Family plans use PMPro Group Accounts plus AAC parent/child metadata and invites.

1. Activate Group Accounts.
2. Configure Partner as a group parent level.
3. Verify **Partner Adult** and **Partner Dependent** child levels.
4. Confirm group capacity and price application.
5. Test an adult and dependent invitation.

The integration defaults to a maximum of 20 seats when provisioning settings. Always verify current pricing/configuration in PHP and PMPro before production checkout.

Parent flow: acquire family plan, create/reconcile group, open `#/linked-accounts`, create a child or redeem an invite, assign the child level, and invalidate both profiles. Public endpoints now support invite validation, redemption, creation, and scheduled removal; older notes saying redemption is future-only are obsolete.

Before manually changing a linked user, inspect both PMPro Group Accounts and AAC linked metadata. Editing one side can orphan a seat.

## 10. AAC Portal administrator settings

Open **WordPress Admin > AAC Portal > Member Portal Settings**.

| Tab | Controls |
| --- | --- |
| Global | Shared labels, content, and section titles |
| Home | Hero, CTAs, intro, involvement/publication cards, partner logos |
| Experience | Join/login/profile copy, design, navigation, and layout |
| Discounts | Discount cards and benefits gallery; not PMPro promo codes |
| Level Benefits | Visible benefit lines and rescue-related display values |
| Publications | Images, descriptions, links, and digital/locked messaging |
| Linked Accounts | Family copy and staff invite tools |
| Error Log | Portal-captured administrator diagnostics |

Experience consolidates older Join, Login, Profile, Design, Navigation, and Layout settings. It includes backgrounds, overlays, colors, buttons, nav/sidebar visibility and order, and home-section visibility/order.

Other AAC tools include Member Database, Export Fields, Daily Member Export, Import Manager, and Redpoint API. Some are reached through the Member Database tool navigation.

## 11. Change recipes

### Copy, images, colors, or navigation

Edit the appropriate AAC Portal setting and save. No build is required for database-backed settings. Verify the exact public route in a private window. Preserve valid hash routes when reordering navigation.

### Price or billing cycle

Edit the PMPro level, then update matching marketing text/card source if needed. Test the PMPro summary and a test-mode order.

### Promotion code

Configure it in PMPro. Verify eligible levels, dates, initial/recurring amounts, and limits.

### New profile field

A complete change may require PMPro registration, validation/sanitization, storage mapping, `/me`, `/profile`, React UI, imports/exports, and cache invalidation. Adding only a visible input produces a field that may not persist.

### Benefits/access

Copy is configurable, but feature access also lives in `src/lib/membershipAccess.js`, `src/lib/membershipBenefits.js`, and server normalization. Protect data on the server as well as in the UI.

## 12. Member Database, imports, and exports

The Member Database combines WordPress identity, portal fields, PMPro memberships/orders, and family relationships. Avoid direct custom-table edits outside a tested migration.

For CSV imports, follow `docs/aac-pmpro-member-import-instructions.md`:

1. Back up the database.
2. Confirm level IDs/names and gateway account.
3. Start with a small validation file.
4. Import users/membership data through the supported tool.
5. Reconcile level, dates, gateway, subscription, order, and amount.
6. Link group parents/children only after users exist.
7. Run the member-database/PMPro backfill when appropriate.
8. Verify login, portal, billing, transactions, and renewal.

Never invent production gateway subscription IDs. An imported active membership does not guarantee a collectible recurring subscription.

The Daily Member Export can send a member-auth CSV over SFTP. It configures host, port, remote path, identifier, credentials, time, and timezone. Its cron hook is `aac_member_portal_daily_member_export`. WordPress cron is traffic-driven unless the host invokes it externally. Never commit SFTP credentials.

## 13. REST API

Base: `/wp-json/aac/v1`

### Public

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/login` | Start session |
| POST | `/register` | Create account |
| GET | `/email-availability` | Check email |
| GET | `/universities` | Search schools |
| GET | `/invite-code` | Validate family invite |
| POST | `/redeem-invite` | Redeem/link invite |

### Authenticated

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/logout` | End session |
| GET | `/me` | Normalized profile |
| PATCH | `/profile` | Save allowed fields |
| POST | `/change-password` | Change password |
| GET | `/transactions` | PMPro orders |
| POST | `/membership/downgrade` | Schedule downgrade |
| POST | `/linked-accounts/create` | Create/link family account |
| POST | `/linked-accounts/remove` | Schedule removal |
| POST | `/contact` | Member support message |

`GET /debug/last-fatal` is administrator-only.

## 14. External integrations

The plugin has separately authenticated `aac-redpoint/v1` and `aac-ripcord/v2` APIs. Configure them on the Redpoint API admin page and never log tokens.

Salesforce sync belongs to the separate `aac-salesforce-sync` plugin. Do not add direct CRM calls to React. Useful extension points include `aac_member_portal_profile_updated`, `aac_member_portal_family_account_linked`, `aac_member_portal_profile`, and standard PMPro hooks.

## 15. Development, build, and package

```bash
npm ci
npm run dev
npm run build
npm run preview
npm run package:wordpress
```

Vite runs on port 3000. Local React rendering cannot prove WordPress cookies, PMPro pages, gateways, or Group Accounts.

WordPress injects `window.AAC_MEMBER_PORTAL_CONFIG` with REST base/nonce, mount and user context, content/design, navigation/layout, and action URLs. Keep production credentials out of frontend bundles.

Before release:

1. Update the plugin header and `AAC_MEMBER_PORTAL_VERSION` in `aac-member-portal.php`.
2. Run JavaScript and changed-PHP checks.
3. Run `npm run package:wordpress`.
4. Inspect `wordpress/aac-member-portal.zip`.

Packaging rebuilds `app/`, creates stable `portal-app.js/css`, excludes nested ZIPs, `.DS_Store`, and `._*`, and creates a ZIP rooted at `aac-member-portal/`. Never hand-edit generated assets.

## 16. Deployment and rollback

This checkout's Git remote is `mharrisalpine/AAC-WordPress-Plugin`. Git push and WordPress deployment are separate.

Safe deployment:

1. Commit only intended changes.
2. Build from the intended commit.
3. Record checksum, version, commit, host, and time.
4. Retain the last known-good ZIP/database backup.
5. Upload the ZIP under **Plugins > Add New > Upload Plugin**.
6. Choose **Replace current with uploaded**.
7. Verify active version and clear relevant caches.
8. Confirm served `portal-app.js?ver=<version>`.
9. Complete live acceptance on the exact host.

A build or Git push is not proof of deployment. Roll back using the exact known-good ZIP, then retest. Code rollback does not reverse orders, subscriptions, imports, or membership changes.

## 17. Live acceptance checklist

- site header/footer and portal positioning are correct
- navigation is not clipped
- login/logout and existing-email rejection work
- every card selects the correct PMPro level
- Supporter skips publications
- print/digital choices persist
- student/military/family fields appear conditionally
- linked-account creation/redemption works
- promo, donation, and auto-renewal reach PMPro
- displayed total equals PMPro order and gateway amount
- checkout creates the correct user, membership, order, and subscription
- confirmation/redirect works
- billing shows and submits the real payment form
- transactions, upgrades, downgrades, cancellation, and mobile layout work

Use gateway test mode. Never put real card data, passwords, tokens, or credentials in chat, Git, screenshots, or fixtures.

## 18. Troubleshooting

### Blank portal

Verify shortcode block, installed `app/` assets, JS/CSS network errors, CSP, `wp_footer()`, optimization exclusions, active version, and served asset version.

### Old or wrong design

Verify hostname/route, asset `?ver=`, plugin version, host/CDN/browser cache, duplicate old bundles, and settings in the target database.

### Nonce failure

Use a fresh checkout, confirm the nonce is inside the submitted form, exclude checkout from caching, keep iframe and parent same-origin, and inspect the POST before weakening security.

### Required-fields error

Inspect PMPro required arrays and hidden fields; confirm moved controls remain in the form with original names; check server-side student/profile validation; find duplicates/disabled fields; consult the Error Log and PMPro logs.

### Wrong total

Confirm checkout level ID, PMPro initial/recurring amount, discount mapping, family adjustment, donation, PMPro order, and gateway transaction. Never patch a financial error with display-only JavaScript.

### Wrong billing page or duplicate navigation

Verify Billing page assignment/content, `aac_embed=1`, managed template recognition, gateway subscription compatibility, and iframe response.

### Endless iframe height

Inspect visible child height and `aac-pmpro-checkout-height` messages. Look for an observer responding to the height it set and hidden steps retaining fixed/min heights.

### Family “signal is aborted”

Inspect REST/PHP logs; confirm Group Accounts classes, group/capacity, child mappings, email uniqueness, invite state, and any partially created user before retrying.

### Missing transactions

Confirm PMPro orders belong to the current user, their statuses are queried, imported ownership/gateway IDs are correct, and the profile cache is flushed after repair.

## 19. Security and maintenance

- Use least privilege, nonces, capability checks, sanitization, and output escaping.
- Never trust browser-supplied level, price, discount, or capacity.
- Keep gateway/SFTP/API/CRM secrets out of Git and frontend assets.
- Avoid logging passwords, full card data, tokens, or unnecessary personal data.
- Back up before imports, migrations, or bulk changes.
- Keep PHP source and generated assets in one versioned release.
- Update this guide and field dictionaries when routes, tabs, add-ons, or data ownership change.
- Retain a known-good ZIP and distinguish build, push, installation, and live acceptance.

## 20. Related documents

- `README.md` — repository introduction
- `wordpress/aac-member-portal/README.md` — plugin installation
- `docs/aac-pmpro-member-import-instructions.md` — import workflow
- `docs/change-management.md` — release practices
- `docs/codebase-reference.md` — code map
- `docs/wordpress-hosting.md` — hosting setup
- `docs/wordpress-endpoints.md` — API notes
- `docs/family-membership.md` — family metadata reference

Some focused documents describe earlier implementations. When they conflict, verify the current source files named in this guide before changing the live site.
