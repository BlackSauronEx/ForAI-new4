# RVN Compare — Round Log

A concise chronological log of work sessions and milestones.

History of closed versions (0.4.0–0.4.1 and the pre-0.5.0 planning rounds)
lives in `docs/archive/LOG-archive.md` — append-only, never edited here.

---

## 2026-09-26 — Round C2: toasts + phone offsets; both logged defects closed
- **Archive trimmed** per the user's first answer: `docs/archive/` now keeps the 3 most recent answers (rounds 20–24 dropped, 5 files, ~28 KB). Content survives in this file and in `docs/archive/LOG-archive.md`; originals remain in the transport repo.
- **Defects closed (both from Round 25):** (1) PHP Warning “Array to string conversion” — real source is `scope_input()` casting `$_POST` values to string, not only `contexts()`. Guarded with `is_scalar()` in `Buttons_Settings::scope_input()`, `General_Settings::scope_input()`, `Settings::sanitize()` and `Buttons_Settings::contexts()`; proven by a runtime probe with `show_contexts[] = ['shop', ['evil'], 'cart']` → `shop|cart`, 0 warnings. (2) React `validateDOMNesting`: the `CheckboxControl` wrapper in `wp-dev/admin-ui/src/index.js` is now a `div` (it rendered `<div>` inside `<p>`).
- **Built:** toast settings on the “Buttons & toasts” tab (desktop/phone position, duration 0–15000 ms, four templates with `{product}` / `{category}` / `{count}` / `{limit}`, empty = built-in translation); phone offsets `scroll_offset_top_mobile` / `scroll_offset_bottom_mobile` (empty = inherits desktop, emitted as CSS variables inside a `max-width: 767px` media query). New keys all live inside `rvn_compare_settings`; `uninstall.php` untouched. `Toasts::templates()` feeds the script; `compare.js` prefers a custom template, then formats the built-in label, so `limitTab` / `limitTotal` now pass structured values instead of a pre-baked string.
- **Defect caught by the new e2e check before shipping:** the toasts block silently did not save — `Buttons_Settings::scope_input()` carries its own key list and the new keys were missing from it. The four fresh admin assertions (“уведомления пережили перезагрузку”, “сброс очищает шаблон”) failed first, keys added, rerun green.
- **Verified:** level 0 **5/5** (`runs/level0-20260926-113344/`: syntax 49 files × 8.1/8.3, entrypoints 7.0+, WPCS + Security 0/0 after 88 phpcbf fixes, PHPStan 6 clean, i18n 308 entries = 22 new, all translated); level 1 on `main` **13/13** (`runs/level1-20260926-113319/`: core 41/41, REST 12/12, frontend 17/17, table 7/7, admin **27/27**, without-Woo 4/4, uninstall keep/clean, WP_DEBUG clean, Plugin Check 0/0). Bundle rebuilt 15.3 KB → 18.3 KB via `wp-dev/admin-ui/build.sh`.
- **Retention:** the three superseded runs of this round were pruned under `AGENTS.md` rule 14; the two green runs stay as evidence.
- **Docs:** `docs/test-reports/0.5.0.md` (Round C2 section), `CHANGELOG.md` (in-development entry), `STATUS.md`, `CHECKLIST.md`, `ANSWER.md` (previous archived). Version still not bumped (0.4.1 headers, 0.5.0-dev tree) — Round F.
- **Round ended clean:** `stand.sh stop` — no web server, no MariaDB, no browsers.
- **Next:** Round D (Table tab: `image_ratio` / `image_fit`, scrollbar, edge shadow, hybrid columns) on the user's go.

---

## 2026-09-26 — Round 28: workspace hygiene applied (raw run pruned, LOG split, rule recorded)
- **User decisions:** approve both cleanup items (delete the closed-version raw run, split `LOG.md`), fix the hygiene rule in `AGENTS.md`, and **do not start Round C2 in this round** — the first two items are closed here, C2 moves to the next round.
- **Removed:** `docs/test-reports/0.4.1/runs/level1-20260926-095919/` (12 text files, 60 KB) — the Round 25 level-1 run made against the closed 0.4.1 headers. What it proved is recorded in `STATUS.md` ("Open Defects") and in the Round 25 entry here; `docs/test-reports/` now holds exactly the in-flight report `0.5.0.md` + `TEMPLATE.md`.
- **Split:** 16 pre-0.5.0 entries (0.4.0/0.4.1 verification programme, translation, script and audit rounds) moved **verbatim** to a new append-only file `docs/archive/LOG-archive.md`; `docs/LOG.md` 37.7 KB → 18.6 KB and 10 entries (9 kept from the 0.5.0 milestone + this round's entry); 16 moved entries are in the archive. No project file was renamed or deleted — only this one folder of pruned artifacts, which the user explicitly approved.
- **Rule added:** `AGENTS.md` hard rule 14 — retention caps for the portable zone (current-version report + latest raw run only; answers archive capped at 3; raw runs named after `RVN_COMPARE_VERSION`, which stays at the released number until the Round F bump).
- **Measured after cleanup:** portable zone **105 files / ~1.3 MB** — plugin 59 (684 KB), `wp-dev/` 22 (264 KB), `docs/` 21 (268 KB: 9 living docs, 10 archive, 2 reports), root 3 (29 KB). Only binary remains `languages/*.mo` (leaves with Round F). `docs-check.sh`: 14 PASS / 0 FAIL.
- **No plugin or `wp-dev/` code changed; version untouched (headers 0.4.1, tree 0.5.0-dev); no stands booted, nothing left running.** Next: Round C2 on the user's go (two defect fixes → toasts + phone offsets → level 0, then level 1 on `main`).

---

## 2026-09-26 — Round 27: wp-dev and stands explained; workspace audit (no deletions, options offered)
- **Explained (user question):** the purpose and full inventory of `wp-dev/` (22 files, 264 KB — stand/check/docs-check scripts, seed, unit + REST + 4 browser suites, i18n pipeline, admin-UI sources, PHPCS/PHPStan configs, Playground note) and the stand map: level 0 (no stand), `main` (latest WP/WC + PHP 8.3, :8080, en_US — level 1 + cache regression), `min` (WP 6.6.x + WC 9.0.x + PHP 8.1, :8081, ru_RU — level 2), ad-hoc `--name=` stands (theme matrix, isolated reproductions), WordPress Playground (informative-only fallback), plus the user's live store as the manual-verification target. Written into `docs/ANSWER.md`.
- **Workspace audited (user concern about chat breakage):** portable zone = **113 files / ~1.3 MB** (plugin 59 / 684 KB, `wp-dev/` 22 / 264 KB, `docs/` 29 / 292 KB, root 3 / 29 KB). `node_modules/` (675 MB) and `.next/` (6.6 MB) are environment-only and never travel. Verified: no ZIPs, no screenshots (listed in `screens-list.txt` only), no blueprint JSON; the single binary is `languages/*.mo` (removal already planned for Round F). Conclusion: the earlier chats' failure is far more likely chat length than file count — the area is small.
- **Reduction options offered (nothing deleted — hard rule 1 requires user approval):** (1) delete `docs/test-reports/0.4.1/runs/level1-20260926-095919/` (12 files, 17 KB, raw run of a closed version; both defect findings already recorded in STATUS/LOG); (2) split `LOG.md` (37.7 KB) — keep 0.5.0 rounds in place, move 0.1.0–0.4.1 history to `docs/archive/LOG-archive.md`; (3) condense `DECISIONS.md` (30.9 KB → ~10 KB of binding decisions); (4) `.mo` removal stays in Round F; (5) keep the built `assets/admin/settings.js` and the generated `.po` (removal would force a ~200 MB npm build every level-1 run / hurt translators). Also proposed an optional hygiene rule for `AGENTS.md` (one raw run per open version; archive capped at 3 answers).
- **Docs:** `docs/ANSWER.md` replaced (previous archived to `docs/archive/2026-09-26-round-26-reports-carried-over.md`); `STATUS.md` and `CHECKLIST.md` updated. `docs-check.sh` 14 PASS / 0 FAIL.
- **No plugin code changed; version untouched (headers 0.4.1, tree 0.5.0-dev). No stands were booted in this round; nothing left running.** Open: user's choice on the reduction options and the go-ahead for Round C2.

---

## 2026-09-26 — Round 26: requested test-reports files transferred; ready for Round C2
- **Transferred** from https://github.com/BlackSauronEx/ForAI-new3 per user confirmation: `docs/test-reports/0.5.0.md` (progress table of the open 0.5.0 milestone: rounds A/B/C1, run environments, defect lessons) and `docs/test-reports/TEMPLATE.md` (template for future reports). Verified intact.
- **Standby:** Round C2 postponed per explicit user instruction ("Следующий шаг пока не начинай").
- **Docs updated:** `docs/ANSWER.md` replaced (previous archived to `docs/archive/2026-09-26-round-25-workspace-moved-stands-proven.md`), `docs/STATUS.md`, `docs/CHECKLIST.md`.
- **No plugin code changed; version untouched (headers 0.4.1, tree 0.5.0-dev).** Open: user go-ahead for Round C2.

---

## 2026-09-26 — Round 25: workspace moved to a new chat; stands proven live; level 1 run (2 defects found)
- **Copied** from https://github.com/BlackSauronEx/ForAI-new3: `rvn-compare-products-for-woocommerce/` (59 files, byte-identical), `wp-dev/` (22 files, byte-identical), `docs/*.md` + `docs/archive/` (14 files), `CHANGELOG.md`, `README.md`, `AGENTS.md`. **Not** copied per the user's instruction: the whole `docs/test-reports/` folder (54 files, 328 KB). Preview-app wrapper (`src/`) belongs to the sandbox, not the project.
- **Environment proven, not assumed:** `stand.sh doctor` → “FULL stand possible”; `stand.sh install --php=8.1,8.3 --browsers=chromium` → PHP 8.1.34 + 8.3.35, MariaDB, WP-CLI 2.12.0, Composer 2.10.3, PHPCS 3.13.6 + WPCS + PHPCompatibility, PHPStan + WP/WC stubs, Playwright 1.63.0 + Chromium (56 s); `stand.sh up --name=main` → WP 7.1.2 + WC 11.1.2, plugin symlinked and active, seed data, Plugin Check 2.1.0, ready in 19 s.
- **Verified:** `docs-check.sh` 14 PASS / 0 FAIL (the round notes first said 15 — the script ships 14 checks); `check.sh level1` on `main` — 12 of 14 green (core 41/41, REST 12/12, frontend 17/17, table 7/7, activation + data version, without-WooCommerce 4/4, uninstall keep/clean, Plugin Check 0/0). Artifacts were archived to `docs/test-reports/0.4.1/runs/level1-20260926-095919/` and pruned in Round 28 under the new hygiene rule (a closed version's raw run carries nothing beyond these recorded numbers). Stand stopped at round end.
- **Defects found (pre-existing in the 0.5.0-dev tree; level 1 was deferred to C2 by plan):** (1) `includes/admin/class-buttons-settings.php:365` — “Array to string conversion” when a non-scalar element reaches `sanitize_key( (string) $context )` (5 debug.log entries per visit); (2) React `validateDOMNesting`: a `CheckboxControl` renders a `<div>` inside our `<p>` wrapper in `wp-dev/admin-ui/src/index.js` → 2 console errors on the RVN admin page, failing the “no plugin JS errors” check. Not fixed in this round (round scope was the move); both are first items of Round C2.
- **Docs:** `docs/ANSWER.md` (Round C1) archived to `docs/archive/2026-09-26-round-24-c1-buttons-done.md`; carry-over note appended to `docs/archive/README.md`; `README.md` notes where the full report history lives; `STATUS.md` and `CHECKLIST.md` updated.
- **No plugin code changed; version untouched (headers 0.4.1, tree 0.5.0-dev).** Open: whether to carry over `docs/test-reports/0.5.0.md` (+ `TEMPLATE.md`); go-ahead for Round C2.

---

## 2026-09-26 — 0.5.0 Round C1: the Buttons & toasts tab works (buttons half)
- **Built:** `includes/admin/class-buttons-settings.php` (SCOPE of 14 keys, save/reset JSON handlers, bootstrap with translated position/mode/context/mask options, `handler_url()` like General); `Settings` +7 keys (`second_click_action`, `button_icon`, `button_icon_emoji`, `show_contexts`, `show_url_mode`, `show_url_masks` + constants) and helpers `mask_list()` / `emoji()`; `Visibility::context_allowed()` / `url_allowed()` (mask matched once per request); `Buttons::card_context()` mapping (product page → `related`, cart → `cart`, unknown loops → `shop`); `Button_Renderer` icon modes (builtin / custom emoji / none with `rvn-icon-none` CSS fallback to text in icon-only mode); `compare.js` second-click (`open_page` → `data.comparePage`, fallback to remove); new PHP nav-tab “Buttons & toasts” (`tab=buttons`); React app now paints the active panel (`activeTab`) — the Round-A `TabPanel` scaffold removed, `ButtonsPanel` with positions, modes (6 + inherit), texts, second-click, icon/emoji, 6 WooCommerce context checkboxes (cart off by default) and URL-mode + masks textarea; e2e `admin-smoke.cjs` extended (5 nav tabs, buttons-panel mount, second-click save → reload → reset).
- **Decisions (user-confirmed in chat):** tabs are classic PHP nav-tabs with own URLs, React paints only the active panel; the full old-spec B1 variant (contexts + URL masks with `*`); split C into C1 (buttons) + C2 (toasts + phone offsets, level 1 there). `open_page` without a comparison page and an empty custom emoji both fall back safely; “show only on listed” with an empty mask falls back to “hide on listed” so buttons cannot disappear everywhere.
- **Verified:** level 0 green — syntax 49 files × PHP 8.1/8.3, entrypoints 7.0+, WPCS + Security 0/0, PHPStan level 6 clean, i18n 286 entries (60 new strings translated). Bundle: `assets/admin/settings.js` 15.3 KB. Level 1 — in Round C2.
- **Defects found in the round:** (1) the `mask_list`/`emoji` helpers insertion orphaned `int_list`’s docblock (WPCS missing-doc + PHPStan missingType) — reattached; (2) `strtok` empty-path check flagged by PHPStan — truthy check used; (3) `strip_tags` → `wp_strip_all_tags` per WPCS.
- **Next:** Round C2 (toasts positions/duration/templates with `{product}`/`{category}`/`{count}`/`{limit}`, phone offsets) + level 1 on `main`.

---

## 2026-09-26 — Round B follow-up decisions recorded & pre-Round-C notes prepared (no plugin changes)
- **User decisions:** (1) confirmed keeping “where to show the button” in the “Buttons & toasts” tab (Round C), not duplicating in General; (2) confirmed adding the `second_click_action` setting (per old spec v0.9 answer 55: `remove` default / `open_page` with safe fallback to `remove` if no comparison page URL exists); (3) do not start the next round yet — record answers and present any pre-Round-C notes.
- **Synced:** `docs/DECISIONS.md` (two new rows replacing the deferred note), `docs/SPEC.md` (§7 updated for `second_click_action`), `docs/STATUS.md`, `docs/CHECKLIST.md`, `docs/ANSWER.md` (previous archived to `docs/archive/2026-09-26-round-22-round-b-done.md`).
- **Pre-Round-C notes surfaced:** (1) `second_click_action` options (`remove` / `open_page` from old spec answer 55, no `none` needed; placed in the Buttons & toasts tab next to button state labels); (2) `where-to-show` scope from old spec answer 57 (WooCommerce contexts: shop, categories, search, home, related, upsells, cart off by default + page/URL masks with `*`); (3) splitting Round C into C1 (Buttons: positions, modes, texts, second-click, icon set/emoji/none, where-to-show) and C2 (Toasts: positions, duration, templates with `{product}`, `{category}`, `{count}`, `{limit}` + phone offsets) because both admin UI and frontend PHP/JS change; (4) `uninstall.php` invariant — all new keys stay inside the single `rvn_compare_settings` option array.
- **No stands, plugin untouched.** Open: user confirmation of pre-Round-C notes and explicit go.

---

## 2026-09-26 — 0.5.0 Round B: the General tab saves real settings
- **Built:** `includes/admin/class-general-settings.php` (scope keys, `admin-post.php` save/reset handlers answering JSON, authenticated `admin-ajax.php` product search by name/SKU, bootstrap data for the UI, export of the General-tab keys only); `wp-dev/admin-ui/src/index.js` rewritten as a working General tab (limits, comparison rule, ignored categories, other-label, product/category exclusions with per-context switches, accent color, offsets, Save and Reset); `Settings_App::bootstrap_data()` now carries settings, categories, rules, handler URLs and 40 translated strings; e2e `admin-smoke.cjs` extended with a save → reload → reset cycle.
- **Decisions:** save/reset go through `admin-post.php` + JSON, not new public REST routes; nonce and capability checks are written inside each handler (WPCS only recognises them in the same scope) and `$_POST` is read there, helpers receive plain arrays; “Reset this tab” touches only `General_Settings::SCOPE`. Two roadmap items deferred with explicit questions: where-to-show (belongs to the Buttons tab) and the second-click action (no settings key exists).
- **Verified:** level 0 green — syntax 48 files × PHP 8.1/8.3, entrypoints 7.0+, WPCS + Security 0 errors / 0 warnings, PHPStan level 6 clean, i18n 225 entries (42 new strings translated, 3 obsolete removed). Bundle rebuilt: `assets/admin/settings.js` 9.2 KB. Level 1 on `main` green 12/12 in 57 s (admin browser 18/18 including save → reload → search → exclusions → reset, Plugin Check 0/0). Report: `docs/test-reports/0.5.0.md`; stand stopped at round end.
- **Defects found and fixed:** (1) every save returned 403 — `wp_nonce_url()` / `add_query_arg()` HTML-escape `&`, so in the inline JSON the browser posted the nonce as `amp;_wpnonce`; fixed by building the handler URL manually (`handler_url()`), confirmed live (HTTP 200 + sanitized settings). (2) Playwright could not find the product search — `data-testid` on a `TextControl` does not reliably reach the `<input>`; all controls are now wrapped in `<div data-testid="…">` and locators use `[data-testid="…"] input|select`.
- **Housekeeping:** manual smoke postponed to one big run at the end of 0.5.0 (user decision); the preview page no longer publishes `docs/CHECKLIST.md`, only `docs/ANSWER.md` (`AGENTS.md` rule 2 updated).
- **Next:** Round C (Buttons & toasts tab) on the user's go; answers to the two deferred questions.

---

## 2026-09-26 — Workspace moved to a new chat (no plugin changes)
- **Why:** the previous chat stopped responding after Round A was finished. Cause unconfirmed; the workspace itself was light (187 files, 2.2 MB; tools, sites and `node_modules` live in `/tmp`, never in the area), so the likely cause is chat length, not `wp-dev/`.
- **Done:** plugin, `wp-dev/`, docs, `CHANGELOG.md`, `AGENTS.md`, `README.md` copied from https://github.com/BlackSauronEx/ForAI-new2 (plugin and `wp-dev/` byte-identical via `diff -rq`). Not carried over (user-approved): `docs/archive/` rounds 05–19 and `docs/test-reports/0.4.1/runs/` — see `docs/archive/README.md`. Preview app rebuilt for the new sandbox (environment only).
- **Verified:** `stand.sh doctor` — FULL stand possible (Debian 12, passwordless sudo, sury/wordpress.org reachable, 3.9 GB RAM, 18.9 GB disk); `docs-check.sh` passed. No install, no stands launched.
- **Next:** unchanged — Round B (General tab) on the user's go; Round A manual smoke (3 items).

---

## 2026-09-26 — 0.5.0 Round A: settings-app scaffolding built and verified
- **Built:** `wp-dev/admin-ui/` (package.json, `src/index.js` with `createElement` only / `TabPanel` + `Notice` / no JSX / no `wp.i18n`, `build.sh` building in `/tmp/wp-admin-ui-build`, README); plugin `includes/admin/class-settings-app.php` (enqueue gated to Compare+General, nonce + settings + translated strings via inline data, PHP fallback + `<noscript>`); new default “General” tab; `assets/admin/` bundle (1.4 KB, React externalized to `wp-element`/`wp-components`) + guarded `settings.asset.php`.
- **Verified:** level 0 green (47 files, WPCS 0/0, PHPStan 6 clean, i18n 186 entries); level 1 green 12/12 on `main` (WP 7.1.2 / WC 11.1.2 / PHP 8.3, admin 13/13 incl. the new React-mount check, Plugin Check 0/0). e2e updated (4 tabs + mount assertion). Report: `docs/test-reports/0.5.0.md`.
- **Fixed in round:** (1) WPCS vs generated `settings.asset.php` → `*/assets/admin/*` excluded in `phpcs.xml.dist` (sources reviewed in `wp-dev/admin-ui/src/`); (2) flaky frontend check (click race, empty details, follow-ups green) → green on isolated rerun (17/17) and full level-1 rerun; red intermediate artifacts deleted.
- **Decisions held:** no version bump until Round F (headers 0.4.1; CHANGELOG has an “in development” section); no new settings keys; sanitizer untouched; shop frontend untouched; `node_modules` never entered the workspace (build in `/tmp`).
- **Next:** Round B (General tab) only on explicit go; Round A manual smoke (3 items) in `docs/CHECKLIST.md`; full 23-item checklist at Round G.

## 2026-09-26 — 0.5.0 round-splitting proposal (planning only, no code)
- **User question:** does 0.5.0 include stand testing, and would it be better to implement everything in one round and test in the next? Concern: not overloading the assistant or the workspace.
- **Answer given:** yes, 0.5.0 needs the stand, but only ~40% of the work does. Level 0 costs ≈1 min total (42 s install + 7 s run, no stand, no browsers) so it belongs in *every* code round; level 1 costs ≈3.5 min (81–94 s install + 28–33 s boot + 64 s run) so it belongs at slice boundaries; level 2 once at the end.
- **Argued against the "write everything, test later" option** with project evidence: debugging distance across a brand-new build chain; the 0.4.0 "declared verified, wasn't" precedent; the level-1 round where 6 tooling bugs were caught by review and 1 more only by the first real run; the 75-step limit risking a half-written workspace against the round DoD.
- **Workspace-size analysis:** code cannot overload the area (plugin 548 KB → ~700–800 KB; admin PHP 925 → ~2000–2500 lines; bundle ~30–80 KB — all text). The real risk is `node_modules` for the React build; proposed installing build tooling into a temp dir outside the area, same pattern as Composer/Playwright (`/tmp/wp-tools`).
- **Proposed split:** A scaffolding (level 0 + build + level 1, highest risk first) → B General tab → C Buttons/toasts tab → D Table tab → E Help → F housekeeping → G acceptance (level 1) → H matrix (level 2); six-round variant offered by merging B+C and D+E.
- **New decision surfaced before any code:** where React sources live — inside the plugin (`admin-src/`, literal old-spec wording) vs `wp-dev/admin-ui/` with only the built bundle shipping (recommended, matches our project/environment split).
- **No stands, no code changes.** Open: checklist items 1–4.
