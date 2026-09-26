# Admin settings UI sources (0.5.0)

React sources for the Compare settings screen. Built bundle lands in the
plugin (`assets/admin/`); these sources never ship to users (option B,
user-confirmed).

## Layout

- `src/index.js` — the app. `createElement` only, no JSX; stable
  `@wordpress/components` only (`TabPanel`, `Notice`, …); no
  `__experimental*` without a separate decision and fallback.
- `package.json` — build tooling (`@wordpress/scripts` + the two component
  packages, all externalized at runtime).
- `build.sh` — builds in `/tmp/wp-admin-ui-build` (override with
  `RVN_ADMIN_BUILD_DIR`), so `node_modules` never enters the working area.

## Build

```bash
bash wp-dev/admin-ui/build.sh
```

## Constraints (from `docs/DECISIONS.md`)

- `@wordpress/*` come from WordPress core via dependency extraction
  (`.asset.php`), never bundled.
- All UI strings arrive from PHP (`window.rvnCompareAdminData.i18n`),
  already translated — no `wp.i18n` calls in the bundle.
- PHP `Settings::sanitize()` is the single final validator; the app never
  writes settings directly (Round A: read-only, no saving at all).
- The app mounts only into `#rvn-compare-admin-root`; without that element
  it does nothing.
