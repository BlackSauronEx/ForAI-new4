# WordPress Playground (optional dev tool)

> Status: **optional** (user-confirmed 26.09.2026, `ForAI-new` finding 3).
> Not part of the plugin version plan and never a substitute for levels 0–2.
> Use it only when a full `stand.sh` environment is impossible (`doctor` says
> NO) or for a quick demo without sudo/apt/MariaDB.

WordPress Playground runs WordPress in the browser (PHP compiled to WASM).
No server, no database daemon, no system packages.

## What it is good for

- Smoke check: the plugin activates, no fatals, admin pages render.
- Visual demo: `[rvn-compare-table]` and button shortcodes on a demo page.
- Reproducing a reporter's setup quickly (core version switch in the UI).

## What it is NOT good for

- MariaDB behavior, HPOS on MySQL, multisite, object cache.
- WP Super Cache regression (needs a real filesystem + headers flow).
- Performance, `WP_DEBUG_LOG` discipline, Plugin Check audits.
- Anything that gates a release — that stays on levels 0–2.

## How to use (manual, 5 minutes)

1. Open https://playground.wordpress.net/ (or the "Gutenberg → Playground"
   link inside `wordpress.org` docs).
2. In the Playground UI: **Add plugin → Upload** the `rvn-compare-products-for-woocommerce/`
   folder as a ZIP (zip it locally first), **Add plugin → WooCommerce** from
   the directory, activate both.
3. Create a page with `[rvn-compare-table]` and open it; add a Compare button
   shortcode to a post. Confirm: no PHP errors in the Playground console,
   table shell renders, counter appears.
4. For a repeatable setup, save a Blueprint JSON (Playground → Export) next to
   this file as `playground-blueprint.json` (not committed by default — ask the
   user before adding binaries/large JSON).

## Suggested Blueprint (pseudo-JSON, fill versions when needed)

```json
{
  "landingPage": "/compare/",
  "phpExtensionBundles": ["kitchen-sink"],
  "features": { "networking": true },
  "steps": [
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org", "slug": "woocommerce" } },
    { "step": "installPlugin", "pluginData": { "resource": "upload", "path": "/tmp/rvn-compare-products-for-woocommerce.zip" } },
    { "step": "activatePlugin", "pluginPath": "woocommerce/woocommerce.php" },
    { "step": "activatePlugin", "pluginPath": "rvn-compare-products-for-woocommerce/rvn-compare-products-for-woocommerce.php" },
    { "step": "runPHP", "code": "update_option('rvn_compare_settings', array('limit_total' => 50));" }
  ]
}
```

## Rules

- Playground results are **informative only**: they never turn a red level
  green and never appear in `docs/test-reports/<version>.md` as evidence.
  Mention them in `docs/LOG.md` as "playground smoke: pass/fail", nothing more.
- Never install all matrix themes here either — one theme at a time, same as
  on stands.
- If `doctor` passes, prefer the real stand. Playground is the fallback, not
  the default.
