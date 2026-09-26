# RVN Compare — Code Map

Current for **0.5.0 Round A** (scaffolding, verified level 0 + level 1).
Updated on every iteration. Verified against the actual
`rvn-compare-products-for-woocommerce/` tree (57 files, 47 PHP files).

## Plugin structure

```
rvn-compare-products-for-woocommerce/
├── rvn-compare-products-for-woocommerce.php   Header, constants, PHP check, i18n, Woo decls, boot
├── uninstall.php                              Data removal (checkbox + multisite); keeps the page
├── readme.txt                                 WordPress.org catalog (EN)
├── includes/
│   ├── class-autoloader.php                   RVN_Compare\* → includes/…/class-*.php
│   ├── class-plugin.php                       boot(): Requirements → Installer → REST → Nonce_Recovery → Frontend → Admin
│   ├── class-requirements.php                 WP + Woo check, admin notice with links
│   ├── class-installer.php                    version option, lazy install, Comparison_Page::ensure(), cache flush
│   ├── class-settings.php                     all settings + sanitize (limits, buttons, toasts, table)
│   ├── class-storage.php                      user list JSON in user_meta
│   ├── class-category-model.php               tabs: direct cats, groups, Other, rules
│   ├── class-limits.php                       50/12 iron rule + reason
│   ├── class-compare-service.php              single point for add/remove/clear/merge
│   ├── class-visibility.php                   auto-button exclusions
│   ├── class-nonce-recovery.php               GET /session, fresh nonce for cached pages
│   ├── class-table-fields.php                 field registry: core + attributes + meta, groups
│   ├── class-table-data.php                   build(): tabs/columns/rows for REST /table
│   ├── rest/
│   │   └── class-rest-controller.php          /session /list /table /items /items/remove /clear /merge
│   ├── frontend/
│   │   ├── class-frontend.php                 site boot + safe-area viewport
│   │   ├── class-assets.php                   compare.css/js + table.css/js, inline data
│   │   ├── class-colors.php                   derived colors from accent
│   │   ├── class-button-renderer.php          button/counter/clear/progress markup
│   │   ├── class-buttons.php                  auto-insert: loop + single (hooks + render_block)
│   │   ├── class-shortcodes.php               6 shortcodes (table + 5 from 0.3)
│   │   ├── class-comparison-page.php          ensure/select/reset/restore, noindex, append_table
│   │   ├── class-table-view.php               cacheable table HTML shell
│   │   ├── class-toasts.php                   notification texts
│   │   └── index.php
│   └── admin/
│       ├── class-admin.php                    RVN menu, "Settings" link
│       ├── class-settings-page.php            "Compare" page (General/Page/Fields/System tabs)
│       ├── class-settings-app.php             0.5.0 React bootstrap: enqueue, root, fallback, data
│       ├── class-general-settings.php         0.5.0 General tab: JSON save/reset, product search
│       ├── class-buttons-settings.php         0.5.0 Buttons tab (C1): JSON save/reset, bootstrap
│       ├── class-page-settings.php            comparison-page tab
│       ├── class-table-settings.php           table fields/groups tab
│       └── class-diagnostics-page.php         Tools → RVN Diagnostics
├── assets/
│   ├── js/compare.js                          list, buttons, counters, toasts, tab sync
│   ├── js/table.js                            table: tabs, rows, diff, scroll (textContent only)
│   ├── css/compare.css                        buttons, toasts, safe-area
│   ├── css/table.css                          table, sticky col, arrows, empty
│   └── admin/settings.js + settings.asset.php 0.5.0 settings bundle (built, React from core)
├── rvn-core/                                  shared line core (newest copy wins)
│   ├── bootstrap.php                          core registration
│   ├── class-core.php                         menu builder, add_product() API
│   └── assets/menu.css                        "R" icon styles
├── languages/                                 pot + ru_RU po/mo/l10n.php
└── index.php in every folder                  anti directory listing
```

## Naming

| What | Value |
| --- | --- |
| Folder, slug, text-domain | `rvn-compare-products-for-woocommerce` |
| Plugin namespace | `RVN_Compare` (subspaces: `RVN_Compare\Admin`, `RVN_Compare\Uninstall`) |
| Shared core namespace | `RVN_Core` |
| Constants | `RVN_COMPARE_*` |
| Hooks, options, metadata | `rvn_compare_*` |
| CSS classes, handles, shortcodes | `rvn-compare-*` (core — `rvn-core-*`) |

Namespaces match the `rvn_compare` prefix: Plugin Check derives the plugin
prefix from the namespace root, so one prefix across the code yields a clean
scan (details — `docs/DECISIONS.md`).

## Boot sequence

1. **Main file** (PHP 7.0+ syntax): constants → translation registration on
   `init` → WooCommerce declarations on `before_woocommerce_init` → if
   PHP < 8.1, notice and exit.
2. `rvn-core/bootstrap.php` (core copy registration) and the autoloader load.
3. `plugins_loaded`, priority 1 — core: the newest copy connects, `Core::init()`.
4. `plugins_loaded`, priority 10 — `Plugin::boot()`: WordPress and
   WooCommerce checks. Failed — notice only. Passed — `Installer::init()`,
   `Admin::init()` in console, then the `rvn_compare_loaded` action.
5. `admin_menu`, priority 10 — `Admin::register_product()` →
   `Core::add_product()`.
6. `admin_menu`, priority 90 — the core builds the menu: position midway
   between "Marketing" and the next item (fallback 58.9), subitems,
   "Support", removal of the "RVN" duplicate.

## Data

| Key | Where | Purpose | Removed |
| --- | --- | --- | --- |
| `rvn_compare_version` | site option (autoload) | data version for future upgrades | yes |
| `rvn_compare_settings` | site option | limits, categories, exclusions, buttons, toasts, **table fields/groups**, columns, noindex/auto_insert, `delete_data_on_uninstall` | yes |
| `rvn_compare_page_id` | site option | published comparison page ID | yes |
| `rvn_compare_page_backup` | option | content backup after reset | yes |
| `{site_prefix}rvn_compare_list` | user metadata | JSON `{"v":1,"ids":[…],"ts":…}` — comparison list | yes |

No custom tables. Every new data key must be added to the `uninstall.php`
lists.

The guest list lives only in the browser `localStorage` (key includes the
site ID): the server never writes it — it accepts it in the request for
validation and returns the computed state. Pages stay cacheable, and
personal data never lands in the shared cache.

## REST API: `rvn-compare/v1`

| Route | Method | Access | Essence |
| --- | --- | --- | --- |
| `/session` | GET | public | Fresh security key, visitor role and limits; private response (`no-store`) — for cached pages |
| `/list` | GET | public | List state; guests pass their list in the `ids` parameter |
| `/table` | GET | public | Table payload (tabs/columns/rows) for the passed `ids`; static when `products` is present |
| `/items` | POST | nonce `wp_rest` | Add a product (`product_id`, `ids` for guests) |
| `/items/remove` | POST | nonce | Remove a product |
| `/clear` | POST | nonce | Clear the list |
| `/merge` | POST | nonce, 20/min | Merge the guest list into the user list on login |

Response — a uniform state: `status` (added, already, not_found,
limit_total, limit_category, removed, cleared, merged, not_user, list),
`ids`, `count`, `tabs`, `limits`; rejections add `reason` and `tab` with
the full tab. Business outcomes return code 200 with `status`; 403 — bad
nonce, 400 — bad ID, 429 — merge rate exceeded. The client address for rate
limiting is stored hashed only.

## Shortcodes

| Shortcode | Arguments | Purpose |
| --- | --- | --- |
| `[rvn-compare-table]` | `class`, `products` | Comparison table; without `products` — visitor list (JS+REST); with `products` — static |
| `[rvn-compare-button]` | `id`, `mode`, `class` | Compare button for a product; no args — inside the product card |
| `[rvn-compare-counter]` | `text`, `text_position`, `url`, `class` | Number of products in the list |
| `[rvn-compare-counter-button]` | `label`, `mode`, `url`, `link`, `class` | "Compare" button with counter |
| `[rvn-compare-clear]` | `label`, `scope`, `class` | "Clear all" button |
| `[rvn-compare-progress]` | `class` | "3 of 12" for the current tab |

## Frontend output rules

- Assets attach on `wp_enqueue_scripts` (registration priority 5, need-check
  10, data 99) — before this hook the script queue is not ready yet, while
  block rendering may request assets earlier.
- Files load only on pages with comparison elements: shop, categories and
  tags, product, cart, search, home, or a page with our shortcode.
- Script data travels via an inline script before it (`window.rvnCompareData`).
- An over-image button is automatically moved out of the product link into
  the image container (`.woocommerce-product-gallery`, `.images`, `figure`),
  which receives `position: relative` — so all four corners are exact in both
  classic and block templates.
- Product page (classic template): `above_title` / `below_title` positions —
  the `woocommerce_single_product_summary` hook with priorities 5 and 15;
  `before_cart` / `after_cart` — `woocommerce_before/after_add_to_cart_button`;
  over-image positions — `woocommerce_before_single_product_summary` with
  priority 21 (after the gallery).
- Toasts: the countdown pauses on hover (`is-paused` +
  `animation-play-state: paused`) and resumes with the remaining time.
- On activation the plugin flushes page caches (WP Super Cache, W3 Total
  Cache, WP Rocket, LiteSpeed) so pages cached earlier are not served
  without buttons.

## Comparison page and table (0.4.0/0.4.1)

- `Installer` on activate/update calls `Comparison_Page::ensure()` and
  `flush_page_cache()`.
- `Comparison_Page::ensure()` never rewrites foreign content: slug `compare`
  taken without a shortcode → create `compare-products`.
- `the_content` (prio 8) + `auto_insert_table`: appends the table when the
  shortcode was removed but the page is still the selected one.
- **Invariant (verified): no double table output.** `append_table()` returns the
  content unchanged whenever the comparison page content already contains
  `[rvn-compare-table]`, so the auto-insert and the shortcode can never render
  the table twice. Keep this guard when touching the page code.
- `Table_View::render()` serves the cacheable shell (`data-rvn-table`);
  `table.js` draws columns after `GET /table`.
- `Table_Fields::registry()`: core fields + WC attribute taxonomies +
  explicit meta (scalar, no leading `_`).
- `Table_Data::build()` — server-side row assembly; values normalized for
  diff (`smart_normalize`).
- Assets: `Assets::enqueue_table()` on the comparison page or when the
  `rvn-compare-table` shortcode is present; data → `window.rvnCompareTableData`.
- JS rule: **no** names/descriptions via `innerHTML` — `textContent` / `el()`
  only.
- Unavailable products (draft, private, trash, non-existent, variation,
  catalog-hidden) are filtered at **one** point —
  `Compare_Service::product_available()`; both the list and
  `Table_Data::build()` use it. Duplicating the check in other classes is
  forbidden (see `docs/DECISIONS.md`).
- Breakpoints evaluate `window.innerWidth` (live viewport width, F12 panel
  and zoom included); column widths derive from the table container
  (`clientWidth`). Thumbnail box: fixed 140 px height until the 0.5.0
  `image_ratio` / `image_fit` settings land.
- Admin: `Page_Settings` (create/select/reset/restore) and `Table_Settings`
  (fields/groups/meta/flags).

## Admin settings app (0.5.0, Round A scaffold)

- `Settings_App` (admin): enqueues the bundle on the Compare page, General tab
  only; prints `window.rvnCompareAdminData` (version, nonce, settings subset,
  translated strings) via inline script; renders `#rvn-compare-admin-root`
  with a PHP fallback notice + `<noscript>`.
- Sources: `wp-dev/admin-ui/src/index.js` (`createElement` only, stable
  `@wordpress/components`, no `wp.i18n`); built by
  `bash wp-dev/admin-ui/build.sh` in `/tmp` into `assets/admin/settings.js` +
  `settings.asset.php` (React from core, ~1.4 KB bundle).
- Rules: PHP `Settings::sanitize()` stays the single validator; the app mounts
  only into its root element; generated `assets/admin/*` is excluded from
  PHPCS filename rules. Round A is read-only — no saving yet.

## Developer hooks

| Hook | Type | Since | Purpose |
| --- | --- | --- | --- |
| `rvn_compare_loaded` | action | 0.1.0 | All plugin components booted |
| `rvn_compare_script_data` | filter | 0.3.0 | Data passed to the site script |
| `rvn_compare_button_html` | filter | 0.3.0 | Compare button markup |
| `rvn_compare_page_url` | filter | 0.3.0 | Comparison page URL when not created yet |
| `rvn_compare_shortcode_notice` | action | 0.3.0 | Shortcode called without a required argument (only under `WP_DEBUG`) |

Shared-core API (for line plugins): `RVN_Core\Core::add_product( string
$slug, array $args )` on `admin_menu` with priority below 90. The signature
does not change between core versions.

## Security rules (mandatory for all code)

- Every PHP file starts with `defined( 'ABSPATH' ) || exit;` (`uninstall.php`
  — `WP_UNINSTALL_PLUGIN`).
- All output is escaped at output time: `esc_html()`, `esc_attr()`,
  `esc_url()`, `wp_kses_post()` for markup.
- Every action and page checks capabilities (`manage_woocommerce` for
  settings), even when WordPress already checked them at menu entry.
- Mutating requests carry a nonce only (`wp_verify_nonce`,
  `check_ajax_referer`, REST `permission_callback`).
- Inputs are sanitized on write (`sanitize_*`, `absint`, allowlists);
  outputs are escaped on display.
- No direct DB queries; when unavoidable — `$wpdb->prepare()` only, with
  result caching.
- No external requests, CDNs, tracking, `eval`, base64, or obfuscation.
- Entrypoints (main file, `uninstall.php`, `rvn-core/bootstrap.php`) —
  no PHP 7.1+ syntax.
