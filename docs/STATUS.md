# RVN Compare — Project Status

> **Read this file first** in every session. It is the single source of truth for
> current project state, verified milestones, and the next steps.

> **Для пользователя (кратко):** раунд B 0.5.0 готов и проверен (уровни 0 и 1
> зелёные). Оба ваших ответа зафиксированы: настройка «Где показывать» будет во
> вкладке «Кнопки и уведомления», настройка повторного клика (`remove` /
> `open_page`) включена в план. Собраны уточнения перед стартом раунда C.

---

## Current State

| Attribute | Status |
| --- | --- |
| **Current Stage** | **0.5.0 Round C2 done** (26.09.2026): toasts + phone offsets implemented and both Round-25 defects closed. Level 0 **5/5** and level 1 on `main` **13/13** green (admin browser 27/27, WP_DEBUG clean, Plugin Check 0/0). Next: Round D — Table tab (`image_ratio` / `image_fit`, scrollbar, edge shadow, hybrid columns) on the user's go. |
| **Workspace Size** | Portable zone **119 files / ~1.3 MB**: plugin 59, `wp-dev/` 22, `docs/` 35 (incl. 2 green run folders kept as evidence), root 3. `docs/test-reports/` = `0.5.0.md` + `TEMPLATE.md` + the two green Round-C2 runs; superseded runs of the same round pruned under rule 14. `docs/archive/` capped at 3 answers (rounds 20–24 dropped on the user's word). `node_modules/` (675 MB) and `.next/` (6.6 MB) are environment-only and never travel. |
| **Open Defects (0.5.0-dev)** | **None.** Both Round-25 defects are fixed and verified at level 0 + level 1: the “Array to string conversion” warning (now guarded with `is_scalar()` in both `scope_input()` methods, `Settings::sanitize()` and `Buttons_Settings::contexts()`) and the React `validateDOMNesting` `<p>`/`<div>` wrapper. A third defect — the toasts block silently dropping its keys on save — was caught by the new e2e assertions and fixed in the same round. |
| **Verified Builds** | `0.1.0` → `0.2.0` → `0.3.0` → `0.3.1` → `0.4.0` → **`0.4.1`** (verified range: WP 6.6.9 – 7.1.2, WC 9.0.4 – 11.1.2, PHP 8.1/8.3). |
| **Platform Baseline** | Decided: **WordPress 6.6+**, **WooCommerce 9.0+**, **PHP 8.1+**. The plugin headers still declare the old minimums (`Requires at least: 6.4`) — the bump ships with 0.5.0, together with the `.mo` removal. |
| **Admin UI Technology** | Decided: PHP-bootstrapped React on **stable** `@wordpress/components`; PHP owns capabilities/nonce/sanitize; core-provided packages via `.asset.php`; shop frontend stays vanilla JS. Details — `docs/DECISIONS.md`. |
| **Delivery Model** | Clean plugin folder `rvn-compare-products-for-woocommerce/`. ZIP generation is disabled in this working area. |
| **Test Environment** | `wp-dev/check.sh` (levels 0/1/2) + `wp-dev/stand.sh` + `wp-dev/docs-check.sh`, all exercised by real runs. |
| **Workspace** | This chat is the primary working area: plugin (58 files, headers 0.4.1, code 0.5.0-dev) + `wp-dev/` (incl. `admin-ui/`) + `docs/` + `CHANGELOG.md`. Preview app is environment, not portable. |

---

## 0.4.1 Verification Summary

Manual 5/5 on the live Storefront 4.6.2 host; automated level 0 (5/5), level 1
`main` (12/12), level 2 `min` (11/11, Plugin Check runs on `main` only), cache
regression (24/24); Plugin Check 0/0. Zero plugin code changes. Details —
`docs/test-reports/0.4.1.md`.

---

## User Host Environment

- Web server: OpenResty, PHP 8.3.33, 256 MB memory limit.
- Theme: **Storefront 4.6.2** (classic PHP template).
- WooCommerce: HPOS enabled, Cart/Checkout blocks supported.
- Caching: **WP Super Cache** active (verified by the 24-point regression).
- Active admin plugins: Query Monitor, Plugin Check, WP Super Cache.
- Test site: `http://h607951395.nichost.ru/` (report unreachability to the user; never file it as a plugin defect).
- Target devices: Android (iOS/Safari "unverified", separate manual device run planned).

---

## Roadmap (user-approved 26.09.2026; single source alongside SPEC §12–13)

### Milestone 0.5.0 (in progress — Rounds A, B and C1 done)
1. **Settings UI on constrained React** (stable `@wordpress/components`;
   PHP sanitization stays authoritative): General tab (limits, rules,
   exclusions with server product search, accent color, offsets, per-tab reset —
   done in Round B), Buttons & toasts tab (C1 done: positions, modes,
   texts, second-click action, icon set/emoji/none, where-to-show with
   WooCommerce contexts + URL masks; C2: toast positions/duration/templates,
   phone offsets), Table tab (`image_ratio` / `image_fit`, scrollbar, edge shadow, hybrid
   columns).
2. **Contextual help:** `add_help_tab()`, inline descriptions, per-shortcode
   help panels, FAQ data model for the Support screen.
3. **Housekeeping:** headers to WP 6.6 / WC 9.0; `.mo` removal;
   `wpml-config.xml`; 44 px tap targets; version 0.5.0 in three places.

### Milestone 0.6.0 (remaining original v1.0)
Group builder UI; ready nav-menu item; limit-lowered shopper notice;
object cache; basic RTL; logout test.

### Milestone 0.7.0 (RVN hub)
`RVN → Support` screen with common line tab + per-plugin tabs via
`Core::add_support_tab()`; full guide + FAQ in the “Compare” tab.

### Milestone 1.0.0 — core complete (NOT catalog submission)
Stabilization, docs, public hook/filter API. A celebration milestone; the
catalog comes only after 1.3+ and hardening.

### Milestone 1.1 (design and convenience)
Style editor with admin live mini-preview; category-tab styling (C3);
menu insertion; export/import; ACF import; shortcode builder (C1);
admin “Add product” search (C2 admin); term-description lists (C4).

### Milestone 1.2 (panels, publishing, print)
Floating panels; manual sticky-header offsets only (C8);
share + print/PDF; **comparison export to CSV** (client-side, from visible
`GET /table` data); product slider; pinned group names; stored-list rewrite
on limit drop.

### Milestone 1.3+ (extensions)
Gutenberg block (C9 classic widget only after it, low priority); concrete
variation comparison; “Recently compared” + “Similar” as two opt-out modules
(C10); second left-column table template; developer SVG filter (C5);
statistics only as a separate opt-in module (C11).

### Final hardening, then catalog
Theme matrix (Storefront, Twenty Twenty-Five, Astra, Kadence, Blocksy, Hello
Elementor — never all on one stand), WebKit/iOS, RTL, performance budgets,
accessibility, docs. Catalog submission only after this phase.

---

## Next Immediate Step

Round C2 is done: toasts (positions, duration, four templates with
`{product}` / `{category}` / `{count}` / `{limit}`), phone scroll offsets, and
both defects reported in Round 25 are closed and verified — level 0 5/5,
level 1 on `main` 13/13, Plugin Check 0/0. Report: `docs/test-reports/0.5.0.md`.

Open items, awaiting the user's go-ahead:

1. **Round D (Table tab)?** On the user's go: `image_ratio` (`1:1`, `3:4`,
   `4:3`, `16:9`, `auto`) and `image_fit` (`contain`, `cover`) for comparison
   images, scrollbar mode, edge shadow, hybrid columns — then level 0 and
   level 1 on `main`.
2. **Manual smoke is postponed** to the end of 0.5.0 by user decision: one big
   manual run (the 23-item regression table) after the last round. The new
   notifications can be tried earlier from “Buttons & toasts” → “Notifications”
   if wanted.
