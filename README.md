# AAC Member Portal App

This repository contains the AAC member experience built as a React app and packaged into a WordPress plugin.

At a high level, the project combines:

- a React/Vite frontend for the public homepage, signup flow, sign-in flow, and member profile
- a WordPress plugin wrapper that mounts the app inside WordPress
- Paid Memberships Pro (PMPro) as the live membership, billing, checkout, and subscription engine
- optional supporting plugins and integrations, including the separate Salesforce sync scaffold in `wordpress/aac-salesforce-sync/`

## What This App Does

The portal is designed to give AAC members a branded, app-like experience while still using WordPress and PMPro as the operational backend.

Current responsibilities include:

- public homepage and signup experience
- member sign-in and authentication against WordPress users
- member profile, account, publications, discounts, rescue, and linked-account screens
- PMPro checkout customization for AAC-specific pricing, discounts, family logic, and preferences
- WordPress admin settings for portal content, navigation, rescue levels, discounts, and other editable content
- a mirrored AAC member database inside the plugin for admin review and reporting

## How It Works

### Frontend

The frontend lives in `src/` and is built with:

- React
- Vite
- Tailwind CSS
- React Router

The React app handles the member-facing UI and calls WordPress REST endpoints for login, profile data, account updates, and related portal actions.

### WordPress plugin

The main plugin lives in `wordpress/aac-member-portal/`.

It is responsible for:

- registering the shortcode and shell output
- injecting runtime config into the frontend
- exposing AAC REST API endpoints
- reading PMPro membership, billing, and transaction data
- customizing PMPro checkout and managed pages
- providing the `AAC Portal` admin screens

### PMPro

PMPro remains the source of operational membership behavior inside WordPress for:

- membership levels
- renewals and expiration
- payment processing
- billing/subscription management
- orders and transaction history

The portal layers AAC-specific UI and business rules on top of PMPro.

## Main Project Areas

### `src/`

Frontend app code.

Important files:

- `src/main.jsx`: app bootstrap and routes
- `src/App.jsx`: main app shell and authenticated layout
- `src/contexts/AppAuthContext.jsx`: session and authentication state
- `src/pages/`: route-level pages
- `src/components/`: reusable UI and member portal components
- `src/lib/memberApi.js`: frontend calls into the WordPress backend
- `src/lib/portalSettings.js`: runtime settings shaping and defaults

### `wordpress/aac-member-portal/`

Installable WordPress plugin for the member portal.

Important files:

- `wordpress/aac-member-portal/aac-member-portal.php`: main plugin bootstrap and checkout logic
- `wordpress/aac-member-portal/includes/class-aac-member-portal-api.php`: REST API and member payload assembly
- `wordpress/aac-member-portal/includes/class-aac-member-portal-admin.php`: WordPress admin settings and content tools
- `wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro.php`: PMPro-specific reads and helpers
- `wordpress/aac-member-portal/includes/class-aac-member-portal-member-database.php`: mirrored member database admin tooling
- `wordpress/aac-member-portal/templates/`: AAC shell templates around PMPro content
- `wordpress/aac-member-portal/app/`: packaged frontend assets copied from the latest Vite build

### `wordpress/aac-salesforce-sync/`

Separate plugin scaffold for WordPress-to-Salesforce sync.

This is intentionally separate from the member portal plugin so CRM sync concerns do not get tightly coupled to the member UI plugin.

### `docs/`

Project documentation and implementation notes.

Helpful starting points:

- `docs/codebase-reference.md`
- `docs/wordpress-hosting.md`
- `docs/wordpress-endpoints.md`
- `docs/change-management.md`
- `docs/family-membership.md`

## Data Flow

Typical member flow:

1. WordPress renders the shortcode or portal shell.
2. The plugin injects runtime config into the page.
3. The React app boots and reads that config.
4. The frontend calls AAC REST endpoints for login and member data.
5. The backend assembles member profile data from WordPress user meta plus PMPro membership and transaction data.
6. The frontend renders the member experience from that normalized payload.

Typical signup/checkout flow:

1. A visitor selects a membership level in the portal UI.
2. The AAC-managed PMPro checkout page loads.
3. The plugin reshapes PMPro checkout, applies AAC pricing rules, and stores extra profile/preference fields.
4. PMPro processes payment and stores the membership/subscription state.
5. The member later signs in to the portal and sees that normalized data in their profile.

## Build and Packaging

Install dependencies if needed:

```bash
npm install
```

Run the app locally:

```bash
npm run dev
```

Build the frontend:

```bash
npm run build
```

Package the WordPress plugin with the latest frontend assets:

```bash
npm run package:wordpress
```

That copies the current frontend build into:

- `wordpress/aac-member-portal/app/`

## WordPress Installation

To use the member portal plugin in WordPress:

1. Install and activate Paid Memberships Pro.
2. Build/package this repo with `npm run package:wordpress`.
3. Zip and upload `wordpress/aac-member-portal/`, or upload the packaged plugin zip if one has already been created.
4. Activate `AAC Member Portal`.
5. Add the portal to a page using the shortcode:

```text
[aac_member_portal]
```

For more detailed WordPress setup guidance, see:

- `wordpress/aac-member-portal/README.md`

## Notes for Future Developers

- The portal has a lot of PMPro-aware UI shaping. When PMPro updates its checkout markup, shell templates may need maintenance.
- The React app should treat the normalized member payload from the backend as the main source of truth.
- The WordPress plugin stores both nested profile/meta blobs and flattened reporting meta.
- Large content/design changes are increasingly meant to be handled through the `AAC Portal` admin settings instead of code changes.
- CRM or external-system sync should stay in a separate plugin or integration layer when possible.

## Related Docs

- [Portal plugin README](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/README.md)
- [Codebase reference](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/docs/codebase-reference.md)
- [Change management](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/docs/change-management.md)
