# RVN Compare — Decision Log

The main source of requirements is `docs/SPEC.md` (condensed v0.9 +
iteration 3 + 0.5.0 plan). This file holds decisions taken after the spec
was fixed, with reasons. The old `src/lib/spec.ts` / `questions.ts` are not
carried into the working area.

If a decision contradicts the spec, the decision wins (the reason is here).
If both are silent, ask the user — do not guess.

---

## User-confirmed (instruction-audit session, 26.09.2026)

1. **ZIP builds cancelled entirely.** The working area does not support ZIP;
   the user downloads the plugin folder and packs it personally.
   `build-zip.sh`, ZIP copies in the root and in `public/downloads/`, SHA-256 —
   removed; mentions removed from README, HANDOFF, TESTING, STATUS.
2. **Platform minimums raised:** WordPress 6.4 → **6.6**, WooCommerce
   8.2 → **9.x**. The "Requires WP 6.6+" core dependency header is enforced
   by the core itself (since 6.5); our requirements notice remains as
   insurance for WP-CLI and multisite. SPEC, readme.txt, ARCHITECTURE updated
   to the new minimums. The `.mo` translation file is planned for removal
   with the nearest release (core 6.5+ uses `.l10n.php`).
3. **Language rule:** agent reasoning stays in English; the final user-facing
   answer is strictly Russian. **All project documents and instructions are
   written in English.** Plugin code keeps Russian PHPDoc and code comments
   (user decision); plugin UI strings are English sources localized via
   ru_RU files. Do not treat English project sections as "draft" only because
   of the language — otherwise the project duplicates documents.
   Scope: living documents. Historical records (`docs/test-reports/*.md`,
   `docs/archive/`) stay as written and are never retranslated.
   `CHANGELOG.md` is a living document: fully translated to English on
   26.09.2026 (user decision); new entries are written in English.
4. **Repository fate:** the GitHub repo is transport only — the user archives
   the folder and drops it into a new session. The model never pushes to git
   itself. Consequence: every significant run lands **inside the working
   area** (`docs/test-reports/`, `docs/LOG.md`, `docs/ANSWER.md`), never in
   `public/reports/`.
5. **Test-site URL stays** in reports and checklists (the site keeps
   running), but stand tooling warns the user when it is unreachable.
6. **Stands between rounds:** the sandbox is rebuilt **between rounds**
   (confirmed by uptime ≈ 30 s at round start; system tools gone, project
   files intact). Every round with stand work is therefore self-contained:
   doctor → minimal install → boot → checks → stop. "Continuing a stand"
   from a previous round is impossible — it no longer exists. Memory between
   rounds is files only (progress table in `docs/test-reports/<version>.md`,
   notes in `docs/LOG.md`). Never finish an answer while a background
   install is still running; shut daemons down at round end.
7. **iOS/Safari:** stays "unverified" by user decision (Android only). A
   separate manual iOS device run is planned (Safari screenshots, comparison
   with Android, report in `docs/test-reports/`). It does not block release.
8. **0.4.1 verification:** the user ran the manual test on the live site
   (5/5 passed, 26.09.2026); the automated `check.sh` re-run in this
   workspace started at level 0 (PASS) and continues at levels 1–2. 0.4.1 is
   treated as verified for the manual part; the automated claim is backed
   level by level.

## Check rules (user, instruction audit)

- Check levels: **0** static — after every change; **1** build — before
  handing the folder over; **2** release and compatibility — before releases
  and compatibility-affecting changes.
- Different levels, stands, and browsers may be scheduled in different
  rounds (e.g. statics in one round, Chromium browser checks in one, WebKit
  in another) — to avoid overloading the sandbox in any single round.
- Stands: main = latest WP + latest WC + PHP 8.3, en_US; min = oldest
  supported branch (WP 6.6.x, WC 9.0.x auto-resolved to the newest patch,
  PHP 8.1, ru_RU). WooCommerce versions are not pinned hard — current
  stable unless there is a conflict. PHP: 8.1 and 8.3 by default; 8.2/8.4 —
  release level only. Browsers: Chromium by default; WebKit/Firefox — only
  with an explicit flag.
- Boot and checks live in self-contained rounds; never finish an answer
  while a background install runs; shut everything down at round end, in
  peace.
- Check reports go to `docs/test-reports/` in the repo, never to `public/`.

---

## User-confirmed (original project)

1. Name — **RVN Compare Products for WooCommerce**; folder, slug, and
   text-domain — `rvn-compare-products-for-woocommerce`; main file —
   `rvn-compare-products-for-woocommerce.php`.
2. Code prefix — `rvn_compare` (PHP), `rvn-compare` (CSS/JS/shortcodes).
   `rvn-comrape` is a typo.
3. The user verifies builds on a local site and on hosting; every build
   ships with exact instructions.
4. The iteration order is accepted; development started 23.09.2026.
5. The preview home page = current answer + numbered checklist, refreshed
   every round.
6. Priority — passing the WordPress.org automated and manual catalog audit
   with zero remarks; security beats development speed.

## Iteration 0 decisions (developer)

| Decision | Reason |
| --- | --- |
| `RVN_Compare` and `RVN_Core` namespaces instead of `Revolen\RVCompare` and `Revolen\RVN\Core` | Plugin Check derives the plugin prefix from the namespace root; with `Revolen` it demanded a `revolen` prefix on all hooks and produced 8 warnings. Options were tested against the scanner: `RVN_Compare` + `RVN_Core` produce no conflicting prefix, and one `rvn_compare` prefix across the code reads clean in manual review |
| Line pages register via `RVN_Core\Core::add_product()`; the core has no hooks | Core hooks are shared by the whole line and cannot carry one plugin's prefix; a direct API call removes the prefix question and is easier to maintain |
| `load_plugin_textdomain()` with `phpcs:ignore` and a justification | The spec requires a built-in ru_RU translation. WordPress.org language packs still take priority. Before catalog submission we decide whether to keep the built-in files after the translate.wordpress.org publication |
| The "R" icon is a text glyph in CSS, not SVG | No base64 (WPCS rule against obfuscation), the color follows the admin color scheme automatically, no repaint scripts |
| The top-level `rvn` item gets the first line page handler; the submenu duplicate is removed | Clicking "RVN" opens the first page, the direct `page=rvn` address is not empty, stable `#toplevel_page_rvn` id for styles |
| Menu position — midway between "Marketing" and the next item; fallback 58.9 | Resilient to position changes in future WooCommerce versions |
| The `product_block_editor` declaration stays | On WooCommerce 9.x and 11.1 it produces no log entries; the plugin adds no fields to the product editor |
| Network activation does not iterate sites | Slow on large networks; install runs on each site at first admin entry |
| `uninstall.php` code inside a function, network sites in batches of 100 | No globals; bounded memory on large networks |
| PHPStan: `treatPhpDocTypesAsCertain: false` | Hook data arrives from third-party code; defensive type checks are mandatory |
| Built-in translations include `.l10n.php` | Fast translation loading on WordPress 6.5+; older cores fall back to `.mo` |

## Check split: user-confirmed

- User devices: Android only. iPhone/iPad and real Safari stay "unverified"
  in reports; WebKit emulation is the only available iOS data source.
  Manual iOS checks are scheduled separately, not inside the full release
  cycle.
- Paid themes and builders are not pinned (Avada is one of the future
  sites, not the only one). We guarantee baseline compatibility; the
  universal path for non-standard cards is the `[rvn-compare-button]`
  shortcode. No per-builder tuning.
- The user's local site is Local WP (WP-CLI available as "Site Shell");
  hosting has no WP-CLI. Environment diagnostics therefore live on an
  **"Tools → RVN Diagnostics"** admin page (administrators only): it works
  in Local and on hosting alike. (A WP-CLI variant was once discussed as a
  possible bonus for Local, but it was never built — no `WP_CLI` command
  exists in the plugin.)
- No Docker: the user confirmed that automated checks stay entirely in the
  development environment.
- The user's test site: a plain free theme, 50 products, 40+ attributes.
  Two plugins are recommended there: **WP Super Cache** (page cache: the
  personal comparison page must behave correctly under full cache) and
  **Query Monitor** (query counts and slow queries in reports). Nothing else
  needs adding.

## Check split (instruction audit, user-configured)

Automated checks stay entirely in the development environment — Docker on
the user's side would duplicate them. Only things unavailable in the sandbox
move to the user: real devices, paid themes and builders, the actual
hosting, and subjective look-and-feel. Additionally — user-environment
diagnostics via the "Tools → RVN Diagnostics" admin page in the plugin
(`includes/admin/class-diagnostics-page.php`) — it checks the real site, not
the container. (A WP-CLI variant was once floated but never built: there is
no `wp-dev/diagnostics.php` and no `WP_CLI` command in the plugin.)

## Iteration 1 decisions (comparison core)

| Decision | Reason |
| --- | --- |
| The guest list lives only in the browser; the server never writes it | Pages stay cacheable; personal data never lands in the shared cache |
| The user list is JSON in user_meta with `wp_slash` on write | User requirement (JSON format) and spec v0.9; the schema version number allows future migration |
| REST business outcomes (already, limit_*, not_found) return code 200 with a `status` field | Easier for the client to handle one state shape; 4xx stay for protocol violations (nonce, validation, throttle) |
| The rate-limit client address is stored hashed | Privacy: the IP is stored nowhere |
| REST tests use the `?rest_route=` form | Works under any permalinks; `/wp-json/` on "plain" links redirects, which is awkward for POST |
| Negative IDs are filtered by int cast, not `absint` | An autotest caught `absint(-1)` turning garbage into valid product ID 1 |
| Data removal and multisite already cover `rvn_compare_list` and `rvn_compare_settings` | Keys matched the `uninstall.php` lists from iteration 0 |
| The user takes the plugin folder, not a ZIP | Their file browser does not serve binary archives; ZIP is cancelled entirely (see "User-confirmed") |
| `Requires Plugins: woocommerce` behavior accepted by the user | WordPress 6.5+ core itself blocks WooCommerce deactivation under an active extension and extension activation without WooCommerce; our notice remains as insurance for WP-CLI and multisite |

## Iteration 2 decisions (buttons, counters, notifications)

| Decision | Reason |
| --- | --- |
| Asset enqueue on `wp_enqueue_scripts` with a need flag | Block rendering outputs buttons before the script queue forms; enqueueing "in place" lost both data and files. The flag keeps the savings: files load only on pages with comparison elements |
| Script data travels via `wp_add_inline_script`, not `wp_localize_script` | The explicit inline is visible in markup and independent of queue order; simplified a defect hunt |
| The over-image button is moved out of the product link by script | Clicking a button inside the product link opened the card; a theme may not close the link before the button, so positioning and moving belong to the script |
| The phone button mode is set via attribute and applies on `(hover: none)` | Breakpoints and modes are configurable; touch devices are detected by input capabilities |
| Panel and toast offsets come from settings plus system safe areas | Sticky headers, theme bottom bars, and the iPhone strip are honored without theme edits |
| `viewport-fit=cover` is appended by script | WordPress outputs no viewport meta of its own, and without it the browser does not report notch sizes |
| The `.rvn-is-empty` class hides a zero counter | An empty counter in the menu looks like a styling bug |

## Iteration 2a decisions (user-report fixes)

| Decision | Reason |
| --- | --- |
| One handler per single-product position, position checked inside | The hook fires for all positions: no conditional priority logic, and new positions are easy to add |
| Over-image positions are moved into the image container by script | In classic themes the post-gallery slot sits in the product column; column-relative positioning gave wrong bottom corners |
| Button-mode rules share the state rules' specificity and sit after them | Otherwise the "icon only" mode failed in the active state: stray text appeared |
| Page-cache flush on activation | Pages cached before activation were served without buttons (user remark about the phone) |
| "Undo" in the added-notification calls remove | The undo direction is now a parameter: it used to always call add, which returned "already" |
| Hover-pause of the countdown ships without a separate setting | Small scope; the setting arrives with the design tabs |

## Iteration 3 decisions (comparison page and table) — verified

> These decisions were **derived from code** that an earlier chat wrote but
> did not document or test. They were confirmed during the 0.4.0 check run;
> do not break them without reason.

| Decision | Reason |
| --- | --- |
| The page is created with the `[rvn-compare-table]` shortcode, ID in `rvn_compare_page_id` | Shortcode = one render point for the auto-page and manual inserts alike |
| `ensure()` does not touch a foreign page with the `compare` slug | User content is sacred; WordPress picks `compare-products` itself |
| `auto_insert_table` via `the_content` prio 8 | The admin may have erased the shortcode while the page is still "the comparison page" |
| `rvn_compare_page_backup` on reset | Manual content edits can be restored after an accidental reset |
| `noindex_compare_page` default on | The page is personal (visitor-dependent content); nothing to index |
| Table: ID-less HTML shell + `GET /table` | Page cache serves one shell to everyone; personalization only in JS |
| Fields: core + auto attributes + explicit meta | Store attributes appear by themselves; meta — only what the admin added explicitly (scalar, no `_`) |
| `description` default off, `short_description` on | Full descriptions are often long and noisy; short is enough |
| `brand` only when `product_brand` exists | On brand-less WC the registry must not carry a dead field |
| `new_attributes_enabled` default on | A new store attribute lands in the table without manual enabling |
| `hide_unassigned_attributes` default on | Do not show an attribute no tab product carries |
| Diff: server `smart_normalize` + client toggle | Normalization ("1 kg" vs "1kg") on the server; the UI toggle needs no new request |
| Columns 5/3/2, breakpoints 1024/768, phone < tablet enforced | Predictable grid; sanitize prevents breakpoint mix-ups |
| `scrollbar_mode: hidden` default | Horizontal arrow scrolling, no scrollbar noise |
| Table assets depend on `compare.js` | The table reuses the core list/state/sync; no logic duplication |
| Admin split: Page_Settings + Table_Settings | Page and fields are different tasks with different capability points in the UI |

## Decisions from the 0.4.0 check run

| Decision | Reason |
| --- | --- |
| **The static `[rvn-compare-table products="…"]` shortcode ignores category tabs**: all listed IDs render as one "All products" group (`Table_Data::build()` takes a third `$single_tab` parameter; REST `GET /table` sets it when the request carries `products`) | The `check.sh table main` check caught the defect: products from different categories split across tabs, and an admin listing two IDs saw one column at a time. A static shortcode means "show these products side by side"; tabs belong to the visitor's dynamic list with per-category limits |
| **The `admin-smoke.cjs` test navigates to the System status tab** instead of expecting the status table on the first tab | In iteration 3 the "Compare" page split into tabs (Page / Comparison fields / System status). The test predated the tabs and had never run — a stale test expectation, not a plugin defect. Coverage grew: `stand main/min` — 12/12 |
| **`Table_View::render()` takes `$is_static`, not `$static`** | WPCS warns: a reserved word in a parameter name may become a syntax error in future PHP |
| **Product availability is a single point: `Compare_Service::product_available()`** | Code review: the rule applies both to the list and to the table. Never duplicate the check — tests cover this |

## Old-repo audit decisions (0.4.0)

Checked whether anything from the old v0.9 spec (`ForAI-old`,
`src/lib/spec.ts`, 91+ items) was lost in the move to the new repo. Four
divergences found.

| Question | Decision | Reason |
| --- | --- | --- |
| **A. Unavailable products** (draft, private, trash) are silently filtered | **Implemented** — not a defect. Autotests added: private product, trash, filtering from guest state and table columns | Code review: `Compare_Service::product_available()` (post_type + `publish` + `is_visible()`) applies in `add`/`merge`/`sanitize_list` and in `Table_Data::build()`. The initial "not implemented" verdict was based on `Storage::normalize_ids()` — that is only ID normalization; the availability rule lives above, in the service |
| **B. Trimming the stored list when the limit drops** | Future feature (see `docs/SPEC.md` §12) | User: leave for later. Trimming at **read** already works (`sanitize_list` → `normalize_ids( ..., limit_total )`); only rewriting the stored list is missing |
| **C. Floating panels, Gutenberg block/widget, menu insert** | Added to `docs/SPEC.md` §12 as deferred (v1.1) | User: "yes, add to spec". Previously recorded nowhere — loss risk |
| **D. Multilingual sites (WPML / Polylang)** | Baseline compatibility by construction; full integration deferred | Complexity: translating product IDs and per-language lists is a separate feature, not a small fix. Already honored: standard APIs only, no direct SQL, page title via `get_locale()` (site language, not profile language). Recorded in SPEC §3/§12 |

Conclusion for future sessions: the product-availability rule changes
**only** in `Compare_Service::product_available()` — the single point;
never duplicate the check elsewhere.

## Instruction-audit decisions (26.09.2026)

| Question | Decision |
| --- | --- |
| Preview-app framework | A PROPERTY OF THE ENVIRONMENT, not part of the project. Never copied between chats, never mentioned in project rules. Preview content lives in `docs/ANSWER.md` / `docs/CHECKLIST.md`; the environment app is a thin wrapper. If the wrapper cannot render a file, a 5–10 minute shim is fine — but that is not a project rule |
| Documents and repo | Content in English (see the language rule above). The repo is transport for downloads, not a source of truth |
| Stands across rounds | Self-contained rounds (doctor → minimal install → boot → checks → stop); check levels split across rounds; `setsid nohup` for install/up; shutdown at round end |
| iOS/Safari | Marked "unverified"; a separate manual device run with screenshots is planned; does not block release |
| Reports | Only in `docs/test-reports/`, never in `public/` |
| ZIP | Cancelled entirely; all mentions removed from README, HANDOFF, TESTING, STATUS |
| Minimum versions | WP 6.6+, WooCommerce 9.x (see "User-confirmed") |
| Git | The repo is download transport; the model never pushes; runs and reports live inside the working area only |
| Test-site URL | Kept; stand tooling warns on unreachability |

## Old-spec reconciliation decisions (26.09.2026)

| Question | Decision | Reason |
| --- | --- | --- |
| Table layout documentation | Correct SPEC to match the implemented old-v0.9 design: no left specification column; full-width caption row above the scrolling value strip. Document already implemented details instead of rebuilding them | Code inspection (`table.js` / `table.css`) proved the old design is already in 0.4.1; the current "sticky first column" sentence was false |
| Responsive column calculation | Hybrid: window breakpoints choose a target (5/3/2), but a minimum product-column width computed against the actual table container may reduce the visible count | Keeps the 0.4.1 Storefront fix (`window.innerWidth`) while preventing five unusably narrow columns in a wide viewport with a sidebar |
| Concrete variation comparison | Restore to the 1.3+ extension backlog (not "out of scope in principle") | It was a fixed item in spec v0.9; current behavior remains parent-product comparison until then |
| Missing v1.0 items (audit group B) | Keep all B1–B14 in the backlog; postpone B15 (multi-theme matrix) to the final hardening phase | User accepted all other items and explicitly asked not to contaminate routine stands with many themes |
| Drag-and-drop accessibility wording | Every reorderable admin list gets pointer drag-and-drop **and** explicit "Move up" / "Move down" controls (keyboard-focusable buttons); it does not mean that a user must hold items and press arrow keys | Drag-only controls exclude keyboard and screen-reader users; explicit buttons are predictable and testable |
| WordPress.org submission | Not tied to version 1.0. Submit only after the 1.3+ phases and a final full verification; 1.0 means the main product is complete and subsequent versions improve it | User wants the catalog entry only when the plugin is maximally mature and tested |
| Admin UI technology | **Decided (user-confirmed 26.09.2026): constrained React on stable `@wordpress/components`.** PHP registers the page, checks `manage_woocommerce`, creates the nonce and prints settings into a root container; React renders the settings UI only; `@wordpress/*` packages come from WordPress core via dependency extraction (`.asset.php`), never bundled; `__experimental*` components forbidden without a separate decision and fallback; PHP `Settings::sanitize()` is the single final validator; a PHP fallback notice covers JS failure; admin CSS is scoped to `.rvn-compare-admin`; sources + build command live in the working area | Official WordPress UI toolkit gives a native, beautiful admin experience for a highly interactive settings screen (group builder, live preview, search, drag-and-drop); constraints neutralize version drift, build and review risks. The shop frontend stays on vanilla JS |
| Settings save path (0.5.0 Round B) | The React tab saves through **`admin-post.php` handlers that answer JSON** (`rvn_compare_save_general`, `rvn_compare_reset_general`) plus an authenticated **`admin-ajax.php` product search** (`rvn_compare_search_products`). No new public REST routes; the shop REST API stays visitor-only. Capability `manage_woocommerce` + a per-action nonce are checked in one place; the response carries the sanitized settings back and the UI re-renders exactly what the server stored | Same mechanism the classic tabs already use, so one mental model and one validator (`Settings::sanitize()`); a public REST surface for admin writes would add nonce/permission handling for no gain |
| Reset scope | “Reset this tab” restores only the keys the tab edits (`General_Settings::SCOPE`) and asks for confirmation; there is no global reset button in 0.5.0 | A per-tab reset cannot destroy work done in other tabs; a global reset belongs with the Support screen (0.7.0) |
| Where-to-show settings placement (user-confirmed 26.09.2026) | **Kept in the “Buttons & toasts” tab (Round C), not duplicated in General.** All button placement, visibility contexts and mode settings live in one tab | Avoids two sources of truth between General and Buttons & toasts |
| Second-click action setting (user-confirmed 26.09.2026) | **Confirmed required for 0.5.0.** Per old spec v0.9 (answer 55), repeated click on an active compare button defaults to removing the product (`remove`) and can be switched to opening the comparison page (`open_page`), with a safe fallback to `remove` if no comparison page URL exists. Stored inside `rvn_compare_settings` (already covered by `uninstall.php`) | Matches the original v0.9 specification and user confirmation |
| Settings-page tab architecture (confirmed in chat, 26.09.2026) | Tabs are **classic PHP nav-tabs, each with its own URL** (`admin.php?page=rvn-compare&tab=general|buttons|page|table|system`); PHP dispatches the tab in `Settings_Page::render()`. The React app is enqueued only on React tabs (`general`, `buttons`) and paints **exactly the active panel** — the Round-A `TabPanel` scaffold inside React is replaced by a switch on `activeTab`. Page / Comparison fields / System status stay pure PHP forms that work without JS | One navigation (the nav-tab bar), deep-linkable tabs, back-button support, no JS dependency for the already-working classic tabs, and the bundle is not loaded on tabs that do not need it |
| Round C1 design points (user-confirmed full B1 variant, 26.09.2026) | (1) Card contexts are detected in `Buttons::card_context()`: product page → `related`, cart → `cart`, shop → `shop`, taxonomy → `catalog`, search → `search`, front page → `home`, unknown loops → `shop` (so product collections on custom pages do not silently lose buttons); `cart` is off by default per old spec answer 57. (2) URL masks (`*` wildcard, regex-escaped) apply to card and single automatic buttons alike, matched **once per request** and cached; “show only on listed” with an empty mask falls back to “hide on listed” so buttons can never disappear everywhere by accident. (3) Shortcodes with an explicit ID bypass context and mask filters, exactly like exclusions. (4) An empty custom emoji falls back to the built-in icon set so a button never renders without a mark. | Keeps the old-spec semantics, predictable fallbacks, and no per-product regex cost in loops |

## Deferred-roadmap group C decisions (user-confirmed 26.09.2026)

| Item | Decision | Reason |
| --- | --- | --- |
| C1 shortcode builder in Help | **Return to 1.1** | Beginners need argument pickers, defaults, copyable examples and common mistakes next to each shortcode |
| C2 “Add product” search | **Split: admin search in 1.1; visitor add-on-page in 1.2/1.3 only after a mockup** | Admin static-list building is safe; the visitor flow changes shop UX (categories/limits) and needs design first |
| C3 category-tab styling | **Return to 1.1, progressive disclosure** (color by default; icon/emoji/thumbnail under “Advanced”) | Useful for vivid stores, but easy to make garish; uploads add media data |
| C4 term-description lists | **Return to 1.1** (global toggle → per-attribute → per-value exceptions) | Stores with dirty internal term descriptions need control without a complex screen for everyone |
| C5 custom SVG | **Developer filter only, 1.3+; no SVG upload in the admin UI** | SVG is an active format and a hard XSS surface; the catalog watches it closely; built-in icons + emoji suffice for admins |
| C6 preview | **Admin live mini-preview kept (style editor, 1.1); site preview with a temporary token DROPPED.** The user clarified they meant the in-admin demo (button/toast re-render as settings change, hover shows the hover background), not a token opening the real site. The site-token mechanism was the old neural note's over-engineering for this need | Admin preview covers the real need with zero token/TTL/cache-leak machinery; the token approach is rejected, not postponed |
| C7 template overrides | **DROPPED.** Clarified: this is the WooCommerce-style “theme copies a plugin PHP template” mechanism, NOT the table-layout choice (caption-row vs left-column) and NOT the planned second left-column template. Public hooks/filters + stable CSS are the supported extension path | Stale theme copies break security fixes and support; our JS-driven table gains little from PHP template copies. Revisit only on a real theme-developer request |
| C8 sticky-header offset | **Manual `scroll_offset_top` only; no automatic detection** | No universal way to detect foreign sticky headers; the manual value is the guaranteed fallback and must never be overwritten by automation |
| C9 classic widget wrapper | **Low-priority 1.3+, only after the Gutenberg block; droppable without a real request** | WP 6.6+ is block-oriented; two UIs for one function need justification |
| C10 “Similar” / “Recently compared” | **Two independent disabled-by-default modules in 1.3+**; “Recently compared” (localStorage, capped) first | Different features (local history vs recommendation algorithm) with different privacy/scope costs; “Similar” may fight theme/WC recommendations |
| C11 comparison statistics | **Separate opt-in module/add-on after 1.3+, never in core by default** | Custom tables, cron, GDPR, DB growth and performance contradict the “no custom tables” core; needs retention and deletion design |
| Version plan | **Approved:** 0.5 settings (React) → 0.6 remaining v1.0 → 0.7 Support hub → 1.0 core complete (no catalog) → 1.1 design → 1.2 panels/publishing → 1.3+ extensions → final hardening → catalog | Catalog only when maximally mature; 1.0 celebrates completion, not submission |

## `ForAI-new` audit decisions (user-confirmed 26.09.2026)

| Finding | Decision | Reason |
| --- | --- | --- |
| 1. «Следующий шаг» block + append-only archive | **Kept** (user: ok). Restored in `AGENTS.md` DoD | Was a user rule in both prior workspaces; lost in rewrites; chat answers and the preview page must end with one clear next action |
| 2. Comparison export to CSV | **Add to 1.2 as client-side CSV export of the visible table** (data from `GET /table`; honors tab / differences / hidden rows; no server or DB changes). PDF stays via browser print | Old spec listed “экспорт в PDF/CSV” as out of scope until a decision; our plan had share + print only. CSV is cheap, safe, useful; a separate PDF generator is not needed |
| 3. WordPress Playground | **Add as an optional dev tool** (`wp-dev/playground.md`), outside the plugin version plan | Fixed v0.9 test-plan item that never carried over; useful fallback when `doctor` says a real stand is impossible; never counts as verification evidence |
| 4. No bulk-copy rule | **Kept** (user: ok). Added as `AGENTS.md` hard rule 13 | Prior workspaces forbade copying 100+ files “just in case” (step/token limits); we only had the “don't read all files at once” half |
| 5. No-double-table-output invariant | **Kept** (user: ok). Recorded in `ARCHITECTURE.md` as a verified invariant | `append_table()` already guards it in code; future edits must not break it |

## Recommendations, not separately approved

WordPress-native personal-data export/erasure, script size budgets
(15–20 KB buttons and counter, up to 40 KB table), WCAG 2.1 AA
accessibility. Treated as engineering norms, never presented as user
requirements.

## Before catalog submission

- Replace `Contributors: revolen` in `readme.txt` with the real
  WordPress.org login.
- Cross-check Plugin Name in the main file and `readme.txt`, folder, main
  file, and text-domain.
- No third-party store names in code, UI, or docs.
- Plugin Check on the assembled ZIP: 0 errors. (The catalog ZIP is a
  release artifact built outside this working area.)
