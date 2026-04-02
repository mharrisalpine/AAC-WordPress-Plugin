# AAC Member Portal WordPress Plugin

Use this plugin folder as the installable WordPress package for the AAC member portal.

## Build frontend assets into this plugin

From the project root:

```bash
npm run package:wordpress
```

That copies the latest `dist/` frontend build into:

- `wordpress/aac-member-portal/app/`

## Install

1. Zip this folder
2. Upload it in WordPress under `Plugins > Add New > Upload Plugin`
3. Activate `AAC Member Portal`
4. Create a page with:

```text
[aac_member_portal]
```

## Docs

Full install and Salesforce sync instructions:

- `docs/wordpress-hosting-and-salesforce-sync.md`
