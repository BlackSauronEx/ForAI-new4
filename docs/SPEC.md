# RVN Compare — Specification (condensed v0.9 + iteration 3 + 0.5.0 plan)

This is the working specification for chat-driven development. The original
full list of 91+ questions lived in the old `src/lib/questions.ts` (146 KB)
and is **not** copied here: every accepted decision is compressed into
`docs/DECISIONS.md`, and the requirements are below.

If a decision contradicts this file, `docs/DECISIONS.md` wins (it carries the
reason). If both are silent, ask the user — do not guess.

---

## 1. Product

**Name:** RVN Compare Products for WooCommerce
**Slug / folder / text-domain / main file:**
`rvn-compare-products-for-woocommerce`
**Author:** Revolen
**License:** GPL-2.0-or-later
**Requirements:** WordPress 6.6+, PHP 8.1+, WooCommerce 9.0+
**Tested up to:** WordPress 7.1.x, WooCommerce 11.1, PHP 8.4
**Goal:** pass the WordPress.org automated and manual audit with zero remarks.
Security matters more than development speed.

A shopper presses "Compare" on a product card or product page, collects a
short list, opens the comparison page and sees the specifications side by
side. Guests keep the list in the browser; signed-in users keep it in the
profile; on login the lists merge carefully.

---

## 2. Naming (strict)

| What | Value |
| --- | --- |
| Plugin PHP namespace | `RVN_Compare` (+ `Admin`, `Frontend`, `Rest`, `Uninstall`) |
| Line-core PHP namespace | `RVN_Core` |
| Constants | `RVN_COMPARE_*` |
| Options, meta, hooks | `rvn_compare_*` |
| CSS / JS / handles / shortcodes | `rvn-compare-*` (core: `rvn-core-*`) |
| REST namespace | `rvn-compare/v1` |

One prefix across the whole codebase is a Plugin Check requirement (the
prefix is derived from the namespace root). Never go back to `Revolen\…`.

---

## 3. Compatibility and environment

- HPOS, Cart/Checkout blocks, product block editor — declare.
- `Requires Plugins: woocommerce` (WP core 6.5+ blocks wrong activations
  itself; our notice is insurance for WP-CLI / multisite).
- Classic **and** block themes. Auto-buttons via WooCommerce hooks **and**
  `render_block`. Non-standard builders — shortcode only, no per-builder tuning.
- Page cache (WP Super Cache and analogues): HTML without personal data;
  the list and the table hydrate via JS; `GET /session` serves a fresh nonce.
- User devices: Android. iOS/Safari — "unverified".
- Multisite: network activation does **not** iterate sites; install is lazy.

---

## 4. Data

No custom DB tables.

| Key | Where | Purpose | Uninstall |
| --- | --- | --- | --- |
| `rvn_compare_version` | option | data version | yes |
| `rvn_compare_settings` | option | all settings | yes |
| `rvn_compare_page_id` | option | comparison page ID | yes |
| `rvn_compare_page_backup` | option | content backup after reset | yes |
| `{blog_prefix}rvn_compare_list` | user meta | JSON `{"v":1,"ids":[…],"ts":…}` | yes |

The guest list lives **only** in `localStorage` (key includes the site ID).
The server never writes it: it accepts it in the request, normalizes it, and
returns the computed state.

**Unavailable products.** Draft, private, trashed, non-existent products,
variations, and catalog-hidden products are silently excluded — no notice to
the visitor. The single point of the rule is
`Compare_Service::product_available()`; it applies both to the list
(add/merge/state) and to the table data (`Table_Data::build()`), so an
unavailable product appears neither in the counter nor in the columns. Limits
are counted over available products.

`delete_data_on_uninstall` (default `true`). The comparison page is **never**
deleted on uninstall (user content).

Every new key goes into `uninstall.php` immediately.

---

## 5. Limits and categories

- Total limit: **1…50**, default 50.
- Per-tab (category/group) limit: **1…limit_total**, default 12.
- **Iron rule:** a full tab blocks adding; the response carries
  `status: limit_category`, `reason`, and `tab` with the full tab.
- Tabs = direct product categories. `compare_rule` values: `assigned`
  (default) / `top_level` / `all` (no tab split).
- Ignored categories, category groups (a category belongs to one group),
  an "Other" group with an editable name.
- Product and category exclusions for **auto-buttons** (`card` / `single`
  contexts); exclusion wins. Shortcodes ignore exclusions.

---

## 6. REST API `rvn-compare/v1`

| Route | Method | Access | Purpose |
| --- | --- | --- | --- |
| `/session` | GET | public | Fresh nonce, role, limits; `Cache-Control: no-store` |
| `/list` | GET | public | List state; guests pass `ids` |
| `/table` | GET | public | Table data for `ids` (+ optional fixed products) |
| `/items` | POST | nonce `wp_rest` | Add |
| `/items/remove` | POST | nonce | Remove |
| `/clear` | POST | nonce | Clear (optional tab scope) |
| `/merge` | POST | nonce, 20/min | Merge on login |

Uniform response shape: `status`, `ids`, `count`, `tabs`, `limits`;
for rejections — `reason`, `tab`. Business outcomes → HTTP 200 + `status`;
protocol violations (nonce/validation/throttle) → 403/400/429.
The throttle client IP is stored **hashed**.

Test URL form: `?rest_route=/rvn-compare/v1/…` (works under any permalinks).

---

## 7. Buttons, counters, notifications (iteration 2 — done)

- 9 positions on cards and on single; classic hooks + block templates:
  `after_cart` (default), `before_cart`, `above_title`, `below_title`,
  `image_tl`, `image_tr`, `image_bl`, `image_br`, `off` (shortcode only).
- Modes (6): `icon_text` (default), `icon`, `text`, `text_icon`,
  `text_over_icon`, `icon_over_text`; separate desktop/mobile,
  `single_mode_mobile` may be `inherit` (default).
- Button texts: `button_label` / `button_in_label` — an empty value means
  the built-in translation.
- States: "Compare" / "In comparison"; repeated click is configurable in 0.5.0
  (`second_click_action`, user-confirmed 26.09.2026): `remove` (default) or
  `open_page` (opens the comparison page, falling back to `remove` if no page
  URL exists).
- Overlay positions: JS moves the button into the image container
  (`.woocommerce-product-gallery`, `.images`, `figure`) + `position: relative`.
- Counter, counter-button, clear, progress — live, no reload.
- Toasts: added / removed / cleared / limit_total / limit_category;
  countdown, close, **Undo** (direction passed explicitly), pause on hover;
  safe-area + `viewport-fit=cover`. Positions (8): `top_left/center/right`,
  `bottom_left/center/right`, `center`; `toast_position` default
  `bottom_right`, `toast_position_mobile` default `bottom_center`;
  `toast_duration` 0…15000 ms (default 3200).
- General look: `accent_color` (default `#2563eb`) + derived palette
  (`class-colors.php`), `animate_ms` 0…3000 (default 300),
  `scroll_offset_top` / `scroll_offset_bottom` (CSS length, edge offsets).
- Tab sync: `storage` + `BroadcastChannel` + `visibilitychange` + `pageshow`.
- Assets only on pages with comparison elements; data — inline
  `window.rvnCompareData` via `wp_add_inline_script`.

Shortcodes (all accept `class`):

- `[rvn-compare-button]` — `id`, `mode`
- `[rvn-compare-counter]` — `text`, `text_position`, `url`
- `[rvn-compare-counter-button]` — `label`, `mode`, `url`, `link`
- `[rvn-compare-clear]` — `label`, `scope`
- `[rvn-compare-progress]`

> **Status:** all settings in this section are implemented (defaults +
> sanitize + frontend behavior), but the **admin UI for editing them is
> planned for 0.5.0**. Until then, changes go through
> `wp option update rvn_compare_settings '{…}'`.

---

## 8. Comparison page and table (iteration 3 — verified in 0.4.0/0.4.1)

### 8.1 Page

- On activate/update, `Comparison_Page::ensure()` creates a page with slug
  `compare` and content `[rvn-compare-table]`.
- If the slug is taken by a **foreign** page without our shortcode — do not
  touch it; create `compare-products` (or a unique slug).
- If an existing page with our shortcode is found — just remember its ID.
- Option `rvn_compare_page_id`. "Comparison page" label in the page list.
- `noindex_compare_page` (default true) → `wp_robots`.
- `auto_insert_table` (default true): if the admin erased the shortcode,
  `the_content` carefully appends the table (priority 8).
- Admin actions: create / select / reset (with backup) / restore.
- Page title via `get_locale()`: "Сравнение товаров" / "Product comparison".

### 8.2 Table shortcode

`[rvn-compare-table class="" products=""]`

- Without `products` — table for the visitor's current list (via JS + REST).
- With `products="1,2,3"` — static table of those IDs (data-static=1).
- The HTML shell carries **no personal data** (cacheable); JS draws columns.

### 8.3 Fields and groups

Base fields (in order):

`price`, `sku`, `availability`, `rating`, `brand` (only if `product_brand`
exists), `weight`, `dimensions`, `short_description`, `description`
(default off).

Default groups: `basic`, `size`, `specifications` (labels from i18n, empty
in DB).

Plus:

- all global WC attributes as `attribute:{taxonomy}`;
- manual meta fields (scalar only, no leading `_`);
- `new_attributes_enabled` (default true) — new attributes appear at once;
- `hide_unassigned_attributes` (default true);
- `groups_enabled` (default true) — category tabs in the table; off =
  flat list without tabs (`groupsEnabled` flag in the table data);
- `hide_empty_rows` (default false);
- `highlight_differences` (default true) + `difference_color` (`#ffcfcc`);
- `show_difference_toggle` (default true) — "Only differences" checkbox;
- `term_tooltips` (default true);
- `smart_normalize` (default true) — value normalization for comparison;
- `attribute_values_layout`: `auto` | `inline` | `lines`;
- columns: desktop 5 / tablet 3 / phone 2 (1…8 allowed per screen);
- hybrid responsive rule (user-confirmed 26.09.2026): the configured window
  breakpoints select the 5/3/2 target, but the table container enforces a
  configurable minimum product-column width. If the target would make columns
  narrower than that minimum (for example, a wide viewport with a sidebar),
  fewer columns are shown. Column width = container width / actual visible
  column count, rounded down;
- breakpoints: tablet 1024 / phone 768 (range 320…2400, phone < tablet
  enforced — sanitize fixes phone to tablet − 1);
- `scrollbar_mode`: `hidden` (default) | `thin` | `system`.

### 8.4 REST `/table`

Returns tabs + columns + rows for the passed `ids`.
The server returns **text**; the client renders via `textContent` (no
`innerHTML` with product or attribute names).

### 8.5 JS `table.js` / CSS `table.css`

- Depends on `compare.js` (core).
- Data: `window.rvnCompareTableData` (restUrl, columns, breakpoints, flags…).
- Category tabs, arrow horizontal scroll, clear tab / clear all, empty state
  with a shop link.
- There is **no left specification column**. Each feature is a two-part row:
  a full-width caption (`rvn-feature-caption`) above a horizontally scrolling
  value strip (`rvn-feature-values`). This is the implemented old-v0.9 layout;
  a second template *with* a left specification column is a future extension.
- Implemented details that must not be rebuilt: collapsible feature groups
  with remembered state; a phone category-tab dropdown; a one-product “add
  another” hint; compact buy controls in product headers plus full-width buy
  controls below; at most three live-region toasts; a merge notification;
  `rvn-compare:*` JS events; `prefers-reduced-motion`; thin/system/hidden
  scrollbar modes.
- Handle: `rvn-compare-table`.

### 8.6 Admin

- `class-table-settings.php` — fields, groups, meta, table flags.
- `class-page-settings.php` — page select/create/reset/restore,
  auto-insert, noindex.
- 0.5.0 settings UI technology (user-confirmed 26.09.2026): a PHP-bootstrapped
  React app on **stable** `@wordpress/components` only. PHP owns capabilities,
  nonce, current settings and the single `Settings::sanitize()` validator;
  `@wordpress/*` packages are consumed from WordPress core (dependency
  extraction, `.asset.php`), never bundled; no `__experimental*` component
  without a separate decision and fallback; a PHP-rendered fallback notice if
  the JS bundle fails to load. The shop frontend stays on lightweight vanilla
  JS. Details and constraints — `docs/DECISIONS.md`.

### 8.7 Product thumbnails (approved for 0.5.0, not in code yet)

Configurable aspect ratio and fit via pure CSS (`aspect-ratio`,
`object-fit`) — no extra image sizes, no media-library bloat:

- `image_ratio`: `1:1` (square, default) / `3:4` (portrait) / `4:3`
  (landscape) / `16:9` (wide) / `auto` (natural ratio).
- `image_fit`: `contain` (whole product visible, default) / `cover`
  (fills the box, crops edges).
- The current fixed 140 px thumbnail height becomes a consequence of
  `1:1` + `contain`, not a magic number.

---

## 9. Security (mandatory, always)

- Every PHP file: `defined( 'ABSPATH' ) || exit;` (`uninstall.php` —
  `WP_UNINSTALL_PLUGIN`).
- Output escaping: `esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`.
- Capabilities: `manage_woocommerce` for settings.
- Nonce on every mutating action (REST + admin_post).
- Input sanitization; IDs — `(int)` / own normalization, **not** `absint`
  for values that may be negative (absint(-1) → 1).
- No direct SQL without `$wpdb->prepare`; no custom tables.
- No `eval`, `create_function`, base64 obfuscation, remote code.
- JS: user strings only via `textContent` / `el()`.

---

## 10. i18n

- Text domain = slug.
- Built-in ru_RU (`.po` / `.mo` / `.l10n.php`) — user requirement.
  (The `.mo` file is planned for removal with the nearest release: on
  WP 6.5+ the core uses `.l10n.php`.)
- String source for builds: `wp-dev/i18n/ru_RU.json` + `wp-dev/i18n.sh`.
- User-facing strings in PHP/JS — via `__()` / `_e()` / `wp.i18n` (if any).
- Before release: every new table string must be in the dictionary.

---

## 11. RVN Core (line)

- A copy of `rvn-core/` in every line plugin; the **newest** copy loads.
- "RVN" menu with an "R" icon (text glyph in CSS) right after Marketing.
- API: `RVN_Core\Core::add_product( string $slug, array $args )` on
  `admin_menu` with priority < 90. Signature is stable.
- Subpages: "Compare", "Support" (stub; becomes the Support hub in 0.6.0).

---

## 12. Out of scope until a separate decision

### Deferred but recorded (user decisions of 25.09.2026 and 26.09.2026)

Backlog below is **scheduled by version** (user-confirmed 26.09.2026).
Stages use the current numbering (see §13).

**0.6.0 — remaining original v1.0 (user-confirmed):**
- **Category-group builder UI** (audit B13): category tree with search, locked
  occupied categories, “select with subcategories”, group order via
  drag-and-drop plus keyboard-focusable Move up/Move down buttons.
- **Ready “RVN: Compare” nav-menu item** (B3) with a live counter for
  “Appearance → Menus”.
- **One-time shopper notice after an admin lowers a limit** (B5).
- **Object-cache layer for immutable product data** (B10), invalidated on
  product/term/settings change; prices are always computed live.
- **Basic RTL** (B11); **logout behavior test** (guest list must not linger in
  the browser).
- **Release housekeeping carried here if 0.5.0 slips:** header minimums
  WP 6.6 / WC 9.0, `.mo` removal, `wpml-config.xml`.

**1.1 — design and convenience (user-confirmed):**
- **Shortcode builder in Help** (C1): pick a shortcode, fill arguments, copy
  the ready string with examples and common mistakes.
- **Admin “Add product” search** (C2, admin half): find products and build
  static shortcodes/lists. The visitor-facing “add on the comparison page”
  stays separate (1.2/1.3, only after a mockup).
- **Category-tab styling** (C3), progressive disclosure: color first; icon /
  emoji / thumbnail under “Advanced”.
- **Term-description lists** (C4): global toggle → per-attribute → per-value
  exceptions for attribute-value tooltips.
- **Menu-insert** (counter button into a chosen site menu with position),
  **settings export/import**, **ACF field import**, **custom “Buy” text or
  shortcodes**, **style editor with element preview** (the admin live
  mini-preview: button/toast re-render as settings change; distinct from the
  dropped C6 site preview).
- **Tooltip hints with long values** (bottom sheet on phone/tablet, placement
  by free space on desktop).

**1.2 — panels, publishing, print (user-confirmed):**
- **Floating panels**: table header (top/bottom/both) and the shop comparison
  panel (thumbnails + “N products → go”); styles and per-panel on/off.
- **Manual sticky-header offset only** (C8, user-confirmed): keep the admin-set
  `scroll_offset_top` as the guaranteed mechanism; no automatic sticky-header
  detection.
- **Share** (link + socials), **print/PDF** via browser print, **product
  slider**, **pinned group names**.
- **Comparison export to CSV** (user-confirmed 26.09.2026, `ForAI-new` finding 2):
  client-side export of the currently visible table from the already loaded
  `GET /table` data; respects active tab, “Only differences” and hidden rows
  (exports what the visitor sees); no server changes, no files in DB; filename
  includes site slug and date. PDF stays covered by browser print — no separate
  PDF generator.
- **Rewriting the stored list when the limit drops** (today the limit applies
  at read time only; raising the limit back restores products).
- Visitor “Add product” on the comparison page — only with an approved mockup.

**1.3+ — extensions (user-confirmed):**
- **Gutenberg block “RVN: Compare”** (counter-button + mini-list); the classic
  widget wrapper (C9) is low priority, only after the block, and may be
  dropped without a real request.
- **Concrete variation comparison** (separate variation cards). Until then, a
  variation resolves to its parent and the buy action says “Choose an option”.
- **“Recently compared” and “Similar products”** (C10) as two independent,
  disabled-by-default modules; “Recently compared” (localStorage, capped list)
  first.
- **Second table template with a left specification column** (the current
  caption-row layout stays default).
- **Developer SVG filter** (C5): code-supplied, pre-sanitized SVG icons via a
  filter — no SVG upload in the admin UI.
- **Full WPML/Polylang integration** only if a real multilingual site needs it
  (translated product IDs, per-language lists).

**Separate opt-in module after 1.3+ (user-confirmed):**
- **Comparison statistics** (C11): top products, pairs, charts; daily table;
  explicit opt-in, retention and data deletion. Never in core by default —
  core keeps the “no custom tables” architecture.

**Final hardening, then catalog (user-confirmed):**
- Full run over Storefront, Twenty Twenty-Five, Astra, Kadence, Blocksy,
  Hello Elementor — never all themes on one stand; WebKit/iOS pass; RTL;
  performance budgets; accessibility; docs. Catalog submission only after
  this phase (see “Out of scope in principle”).

**Dropped by explicit decision (do not re-add without a new user decision):**
- **C6 site preview with unsaved settings** (temporary token opening the real
  site for the admin). The user confirmed the admin live mini-preview in the
  style editor covers the need; the token mechanism is rejected as overkill.
- **C7 theme template overrides** (theme-copied PHP templates à la
  WooCommerce). Public hooks/filters and stable CSS classes are the supported
  extension path instead.
- **Automatic sticky-header detection** (C8 auto half): manual
  `scroll_offset_*` only.

**Kept as before:**
- **Multilingual sites (WPML / Polylang) — basic compatibility only.**
  Standard APIs only, no direct SQL, page title via `get_locale()`.
- **Trimming the stored list when the admin lowers the limit** — scheduled in
  1.2 (see above).

### Out of scope in principle

- Per-builder support for Avada / Elementor / etc.
- WCAG 2.1 AA as a formal audit (we code to the norm, we do not claim it).
- wordpress.org submission (a separate "Before catalog submission" list),
  deliberately only after the 1.3+ feature phases and final hardening. Version
  1.0 means “the main product is complete”, not “submit to the catalog now”.

---

## 13. Iterations

| # | Version | Content | Status |
| --- | --- | --- | --- |
| 0 | 0.1.0 | Skeleton, RVN menu, requirements, i18n, uninstall | ✅ |
| 1 | 0.2.0 | Storage, limits, categories, REST, diagnostics | ✅ |
| 2 | 0.3.0 | Buttons, counters, toasts, shortcodes, tab sync | ✅ |
| 2a | 0.3.1 | Storefront fixes, undo, overlay, cache flush | ✅ |
| 3 | 0.4.0 | Comparison page + table + fields + `/table` | ✅ |
| 3a | 0.4.1 | Storefront fixes: shortcode quotes, thumbnails, arrows, tab flicker, columns | ✅ |
| 4 | 0.5.0 | React settings UI (stable `@wordpress/components`) + help + image ratio/fit + housekeeping | planned, ON HOLD |
| 5 | 0.6.0 | Remaining original v1.0: group builder, nav-menu item, limit notice, object cache, RTL | planned |
| 6 | 0.7.0 | `RVN → Support` hub screen + per-plugin tabs | planned |
| 7 | 1.0.0 | Core complete (stabilization, docs, API) — NOT catalog submission | planned |
| 8 | 1.1 | Design: style editor + admin live preview, tab styling, menu insert, export/import, ACF, shortcode builder, C1–C4 | planned |
| 9 | 1.2 | Panels, share, print/PDF, manual offsets, stored-list rewrite | planned |
| 10 | 1.3+ | Gutenberg block, variations, recently/similar, 2nd table template, SVG filter | planned |
| — | Hardening | Theme matrix, WebKit/iOS, RTL, perf, a11y, docs | planned |
| — | Catalog | WordPress.org submission only after hardening | planned |
