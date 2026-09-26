# RVN Compare — Round Log archive (rounds of versions 0.4.0–0.4.1 and the pre-0.5.0 planning)

Append-only history split out of `docs/LOG.md` on 26.09.2026 (Round 28, workspace
hygiene rule in `AGENTS.md`): `docs/LOG.md` keeps the current milestone (0.5.0)
and the workspace rounds around it; everything below is closed-version history
and is never edited here.

Raw run folders referenced below (`docs/test-reports/0.4.1/runs/…`) were never
carried into this area, and the one local run of Round 25 was pruned by the same
hygiene rule — the full history stays in the transport repo
https://github.com/BlackSauronEx/ForAI-new3 (`docs/test-reports/`).

---

## 2026-09-26 — `ForAI-new` decisions applied + preview list-numbering fixed
- **User answers:** 1) ok (keep «Следующий шаг» + append-only); 2) CSV export → 1.2 as proposed; 3) Playground as an option; 4) ok; 5) ok; 6) wait (0.5.0 on hold); 7) skip WebKit.
- **Synced:** SPEC §12 (client-side CSV export of the visible table in 1.2, respects tab/differences/hidden rows, no server/DB changes); new `wp-dev/playground.md` (optional, informative-only, never evidence) + pointer in `TESTING.md`; DECISIONS (new 5-row `ForAI-new` table); STATUS (1.2 roadmap + next steps + RU summary); LOG; CHECKLIST.
- **Preview bug fixed:** `parseBlocks()` ended a list at the first blank line, so every checklist item became its own `<ol>` starting at 1 (the screenshot). The `ul`/`ol` loops now skip blank lines and continue when the next non-blank line is the same list type. `src/lib/markdown.ts` only; plugin and `wp-dev/` checks untouched.
- **No stands, plugin untouched.** Open: start 0.5.0 (explicit go required); optional WebKit.

## 2026-09-26 — Audit of `ForAI-new` (penultimate workspace): 5 findings
- **Scope:** read the penultimate workspace through the web only (temp dir outside the project, deleted after): tree (97 files), `src/data/project.ts`, `src/data/answer.ts`, `docs/SPEC.md`, `DECISIONS.md`, `STATUS.md`, `TESTING.md`, `HANDOFF.md`, `ARCHITECTURE.md`, `README.md`, `CHANGELOG.md`; every candidate item cross-checked against our docs **and** plugin code.
- **Verified as already present in our workspace:** all 8 iteration-3 files, the fixes of that session (role race `data.isUser`, group enable hiding header **and** rows, boolean meta localized Yes/No, shortcode URL prefix guard `wp_parse_url`, localized page title, `textContent` toast close), frontend output rules, developer hooks table, security rules, the 22/23-item manual checklist, `brand` only when `product_brand` exists, `focus-visible`, `prefers-reduced-motion`, `--rvn-*` CSS variables, safe-area handling. No version regressions found.
- **Finding 1 (restored):** the user rule «каждый ответ заканчивается блоком “Следующий шаг”» and the append-only archive rule existed in both prior workspaces but were lost in our doc rewrites. Restored in `AGENTS.md` (DoD).
- **Finding 2 (open):** the penultimate SPEC listed “экспорт сравнения в PDF/CSV” as out of scope until a decision; our plan has share + browser print/PDF only, no CSV export. Recommended: client-side CSV export of the current table in 1.2.
- **Finding 3 (open):** WordPress Playground was a fixed item of the v0.9 test plan and was never carried over. Recommended: optional dev tool `wp-dev/playground.md`, not part of the plugin version plan.
- **Finding 4 (restored):** “never copy 100+ files just in case” rule from the prior forbidden lists; added as `AGENTS.md` hard rule 13.
- **Finding 5 (documented):** the “no double table output” risk from the penultimate STATUS is handled in code (`append_table()` returns content unchanged when the shortcode is present) but was undocumented; recorded as a verified invariant in `ARCHITECTURE.md`.
- **No stands, plugin untouched.** Open: findings 2–3 plus the prior start-0.5.0 and WebKit questions.

---

## 2026-09-26 — React approved, group C decided, version plan approved and synced
- **User decisions:** constrained React on stable `@wordpress/components` approved for 0.5.0 admin UI; C6 clarified (admin live mini-preview kept in the style editor, C6 site-token preview DROPPED as overkill); C7 clarified (theme template overrides, not table layouts) and DROPPED; C8 manual offsets only; C1–C4 → 1.1, C2-visitor → 1.2/1.3 with mockup, C5 filter-only 1.3+, C9 low-priority 1.3+, C10 two opt-out modules 1.3+, C11 separate opt-in module after 1.3+; full version plan approved (0.5→0.6→0.7→1.0 core-complete→1.1→1.2→1.3+→hardening→catalog).
- **Synced everywhere:** SPEC §8.6 (React architecture), §12 (staged C backlog, dropped C6-site/C7/auto-offset), §13 (full iterations table); DECISIONS (React row decided + 12-row group-C table); STATUS (roadmap + next step); LOG; CHECKLIST. No stands, plugin untouched.
- **Next:** start 0.5.0 only on explicit go; optional WebKit informative.

---

## 2026-09-26 — Old-spec decisions: SPEC corrected; React/C discussion prepared
- **User decisions applied:** correct and document table layout + implemented details (A1/A2); hybrid responsive columns (window target + container minimum width); concrete variations restored to 1.3+; all B items retained except the multi-theme matrix deferred to final hardening; catalog submission explicitly moved after 1.3+ (1.0 = core complete, not catalog); admin UI technology left open for a dedicated comparison.
- **SPEC updated:** removed the false "sticky first column" line; described the actual caption-row/value-strip layout and overlooked implemented behavior; added hybrid column rule; added B1–B14 confirmed backlog; moved variation comparison out of "out of scope"; catalog timing corrected.
- **DECISIONS updated:** seven decisions with rationale, including the meaning of "drag-and-drop plus keyboard arrows" (pointer drag plus focusable Move up/Move down buttons, not holding an item while pressing arrow keys).
- **React research:** official WordPress docs confirm `@wordpress/components` is the native reusable UI package; on WordPress it should be consumed from core (`wp-components`, `wp-element`) instead of bundled, with `@wordpress/scripts` dependency extraction and generated `.asset.php`. Stable components are viable; experimental APIs, version drift, build/source delivery and JS failure need explicit constraints. Full discussion and group-C review in ANSWER.
- **No stands, plugin untouched.** Open: React architecture choice, group C decisions, final version-plan approval.

---

## 2026-09-26 — Old-spec comparison (ForAI-old) + CHANGELOG translated
- **CHANGELOG.md translated to English in full** (user decision): all 6 versions (0.1.0–0.4.1), zero Cyrillic lines; facts unchanged; two historical inconsistencies annotated (0.4.0 screenshots not carried over; the old "table screenshots missing" line contradicting the report); 0.4.1 status line now points at the re-verification. `DECISIONS.md` item 3 updated (CHANGELOG is a living English document). `cn.ts` kept (user decision).
- **Old workspace studied via web only** (temp dir outside the project, deleted afterwards): spec v0.9 (107 items / 14 sections + ROADMAP v1.0–v1.3+), 93 questions of TZ v2 (their answers are consolidated in v0.9), old DECISIONS/STATUS. Every item compared against the **actual plugin code** (grep for feature markers), not only against docs.
- **Findings (30):** A — current docs wrong vs code (2): `SPEC.md` §8.5 says "sticky first column" and §12 lists the "no-left-column layout" as not started, while `table.js` already implements exactly the old v1.0 layout (caption row + value strip, no `sticky` in CSS); several implemented features undocumented (group collapse with memory, phone tab dropdown, one-product hint, compact + bottom buy buttons, 3-toast stack, aria-live, merge notice, `rvn-compare:*` JS events, reduced motion). B — old v1.0 items absent from code and plan (15): where-to-show contexts/URL masks, second-click action, ready "RVN: Compare" nav-menu item, icon set/emoji/none, limit-lowered shopper notice, toast placeholders, desktop/phone offsets, edge shadow, 44 px tap target, object cache, RTL, admin per old spec (5 tabs, server search, drag & drop, reset all), category-group builder UI, per-shortcode help panels, theme matrix. C — deferred roadmap items missing from SPEC §12 (11): shortcode builder, "Add product" search, category-tab styling, term-description lists, custom SVG, site preview, template overrides, sticky-header auto offset, classic widget, similar/recently compared, admin statistics. D — conscious divergences to confirm (2): breakpoints by window (0.4.1) vs container (old spec) → hybrid proposed; variations "out of scope" vs old v1.3.
- **Full version plan proposed (not written into SPEC/STATUS, per user: study only):** 0.5.0 settings UI (+ B1/B2/B4/B6/B7/B8/B9/B12/B14, image ratio/fit, help, housekeeping, wpml-config.xml, SPEC fixes) → 0.6.0 remaining v1.0 (group builder, nav-menu item, limit notice, object cache, RTL, logout test, theme matrix) → 0.7.0 Support hub (renumbered from 0.6.0) → 1.0.0 catalog submission → 1.1 design → 1.2 panels/publishing → 1.3+ extensions.
- **No stands, plugin untouched.** Open: user checklist items 1–6.

---

## 2026-09-26 — Workspace re-verification (no stands): min count corrected, docs polished
- **Scope:** full inventory (every file outside `node_modules/`), `docs-check.sh` green, version coherence in 3 places, `bash -n` on all shell scripts, `node --check` on all e2e scripts, `py_compile` on Python helpers, plugin headers vs docs, run summaries vs the 0.4.1 report, stale-reference sweep (`build-zip`, `public/`, old minimums, `diagnostics.php`, TODOs, dates), Cyrillic scan of the English docs, binary scan, archive/runs weight check. No stands launched, plugin untouched.
- **Factual error fixed:** the `min` total was reported as 12/12 in the report, STATUS and LOG, but the four run summaries give 2+1+1+7 = 11 (Plugin Check runs on `main` only, by design). Corrected to **11/11** in `docs/test-reports/0.4.1.md`, `docs/STATUS.md` and the Round A LOG entry. Sub-check counts (41/12/17/7/12/4) were and remain correct and identical on both stands.
- **Stale references fixed:** `docs/DECISIONS.md` pointed at a non-existent `wp-dev/diagnostics.php` and claimed a WP-CLI diagnostics variant "ships as a bonus" — neither exists (no `WP_CLI` code in the plugin); both passages now state the admin page is the only diagnostics. Language-rule scope clarified (living docs vs historical records); `CHANGELOG.md` language left as an open user question.
- **Doc defects fixed:** `AGENTS.md` rewritten (English header, correct HANDOFF anchor, "never overwrite" contradiction removed, install rule aligned with self-contained rounds, "commits" → "edits", ZIP rule reworded, version-bump DoD made conditional); `HANDOFF.md` lost its last banned `pgrep -f` line; `README.md` no longer lists `AGENTS.md` inside `docs/`.
- **Junk removed:** `__pycache__/` in three `wp-dev/` folders (created by this round's own `py_compile` check) deleted and added to `.gitignore`.
- **Confirmed present and correct:** plugin 53 files / 44 PHP, version 0.4.1 × 3, headers as decided (old minimums until 0.5.0); `wp-dev/` 17 files with all level-1/level-2 fix markers in place; 9 living docs + 7 archived answers + 7 text-only run archives + 6 historical reports + template; single binary is the `.mo` file (removal planned with 0.5.0); no ZIPs, no screenshots, no TODOs, no temp files in the workspace.
- **Left for the user:** `CHANGELOG.md` language (checklist item 1, default: translate fully); `src/utils/cn.ts` + `clsx`/`tailwind-merge` (item 2, default: leave); next step (item 3, default: wait). 0.5.0 and WebKit stay on hold.

---

## 2026-09-26 — Round B: WP Super Cache regression — 24/24 green; level 2 complete
- **Sandbox reset again at round start** (uptime 20 s) — self-contained round: doctor → install (`--php=8.1,8.3 --browsers=chromium`, 81 s) → `up` (WP 7.1.2 / WC 11.1.2 / PHP 8.3, 28 s) → `check.sh cache` → stop.
- **Result: 24/24 PASS in 33 s.** WP Super Cache is installed and configured by the scenario itself (served-header proof, cache_enabled + super_cache_enabled). Coverage: real cache HIT before activation, cache-file removal on activation and on update, button + current CSS/JS on the first post-activation request, guest nonce recovery via a single un-cached AJAX call with `private/no-store`, stale-nonce rejection then acceptance, read-only nonce endpoint, cross-site request rejection, retired old `/session` route gone, guest list persistence, account REST/auth behavior, merge failure keeping the list and success clearing it, zero JS errors in the browser.
- **Archived:** `docs/test-reports/0.4.1/runs/cache-20260926-025300/` (16 KB, text only).
- **Round ended clean:** `stand.sh stop` — no MariaDB, no web server, no browsers.
- **Level 2 is complete**; the whole 0.4.1 verification programme is closed with zero plugin code changes. Report, STATUS and the artefacts record the final picture.
- **Next:** 0.5.0 — awaiting the user's explicit go (it also carries the WP 6.6/WC 9.0 header bump and `.mo` removal). Optional WebKit pass remains available but does not gate anything.

---

## 2026-09-26 — Round A: level 2 `min` stand — 11/11 green, new platform baseline confirmed
- **Sandbox reset confirmed again** at round start (uptime 22 s, all tools gone) — self-contained round as planned: doctor → install (`--php=8.1,8.3 --browsers=chromium`, 94 s) → `up --name=min` → checks → stop.
- **Branch auto-resolution worked as designed:** `stand.sh up --name=min` with no explicit `--wp=`/`--wc=` booted **WordPress 6.6.9** and **WooCommerce 9.0.4** (PHP 8.1, ru_RU) — the newest patches of the new platform-baseline branches, resolved live from api.wordpress.org.
- **Result: 11/11 PASS on `min`** (tests 2 + frontend 1 + table 1 + stand 7; Plugin Check runs on `main` only) — core-logic 41/41, REST 12/12, frontend 17/17, table 7/7, activation + data version, admin 12/12, without-WooCommerce 4/4, uninstall keep/clean, WP_DEBUG clean. Identical counts to the `main` run (41/12/17/7/12/4) — the new minimums behave the same as the tested-up-to versions. Plugin code untouched. No new tooling defects found. (Correction: round-A notes first wrote 12/12; recount of the four run summaries gives 2+1+1+7 = 11.)
- **Archived:** `docs/test-reports/0.4.1/runs/tests-20260926-023809/`, `front-20260926-023816/`, `table-20260926-023835/`, `stand-20260926-023856/` (text only, 84 KB total).
- **Round ended clean:** `stand.sh stop` — no MariaDB, no browsers; independently re-verified with `pgrep -x`.
- **STATUS.md rewritten in one write** (per the new no-parallel-edits rule) to record the `min` result and the updated "tested down to" range.
- **Next:** round B (WP Super Cache regression on `main`, closes level 2 in full) — **on hold**, per user instruction ("ждём"); 0.5.0 also on hold.

---

## 2026-09-26 — Level 1 on 0.4.1: 12/12 green; six tooling defects fixed first
- **Result:** level 1 on `main` (WP 7.1.2, WC 11.1.2, PHP 8.3, Chromium headless shell 153, Playwright 1.63.0, Plugin Check 2.1.0) — **12/12 PASS in 64 s**: core 41/41, REST 12/12, frontend 17/17, table 7/7, activation + data version, admin 12/12, without-WooCommerce 4/4, uninstall keep/clean, WP_DEBUG clean, Plugin Check 0 errors / 0 warnings. Plugin code untouched. Archive `docs/test-reports/0.4.1/runs/level1-20260926-022300/` (44 KB, text only; 6 screenshots listed, not copied).
- **Pre-flight review before the run** (all of these passed `bash -n` / `node --check`): `ENGLINES` typo in all four e2e scripts (every browser check would have thrown); `stand.sh up` routed `--wp=latest` into the branch resolver (main could not boot); WC branch resolver matched the major only (9.0 → 9.9.x); archive copied PNG screenshots into the working area; `--browser=` dropped by the option parser; level order diverged from the proven original; output dir not cleaned between runs. All fixed; resolvers now use api.wordpress.org release lists (WP 6.6 → 6.6.9, WC 9.0 → 9.0.4).
- **First start** aborted on `local stand="$1" dir=".../$stand"` under `set -u` (bash expands the whole `local` line first) — fixed in six functions; restart green.
- **Lost edit found:** the STATUS "Test Environment" row edited in the level-0 round never landed (two parallel edits to one file; the tool reported success). STATUS rewritten in one write; HANDOFF now forbids batched edits to one file; AGENTS rule 9 now says tooling changes count as verified only after a real run. (An AGENTS "lost" alarm was a false positive of a line-based grep on wrapped text.)
- **Dates corrected:** level-0 run and translation round are 26.09.2026 (archive stamp), not 27.09.
- **Round ended clean:** `stand.sh stop` — no servers, no MariaDB; 0 browser processes.
- **Next:** user choice — level 2 (min stand WP 6.6.9 / WC 9.0.4 / PHP 8.1 ru_RU, then cache regression; split into two rounds by default) or hold; 0.5.0 stays on hold until the user's go.

---

## 2026-09-26 — Translation round: full English SPEC/DECISIONS/ARCHITECTURE
- **SPEC.md** rewritten in English from the 20 KB original with every detail carried over (§1–13: naming, data, limits, REST, buttons/toasts, page/table fields/groups, security, i18n, core, scope, iterations). Updates applied: WP 6.6+ / WC 9.0+ minimums, iteration 3 marked verified, §8.7 added (`image_ratio` / `image_fit` approved for 0.5.0), `.mo` removal noted, iterations table extended (0.4.1 ✅, 0.5.0/0.6.0 planned).
- **ARCHITECTURE.md** rewritten in English from the 17 KB original: full file tree (verified against the actual 53-file tree), boot sequence, data, REST (with the previously missing `/table` route added), shortcodes, frontend rules, page/table notes (incl. `window.innerWidth` breakpoints), hooks, security rules.
- **DECISIONS.md** translated to English from the 34 KB Russian file: all sections preserved, duplicates removed (ZIP ×2, stands ×2, iOS ×2, reports ×2, git/repo ×2), outdated item fixed (stands live between rounds → sandbox resets between rounds), typos fixed ("1km", "споки"), "not separately approved" recommendations restored from the original.
- **TESTING.md**: fixed a stale `pgrep -a -f 'mysqld|php -S'` line (the self-matching pattern banned in AGENTS.md), added seed-data description and a pointer to the manual checklist.
- **CHECKLIST.md**: round questions on top + the full 23-item Russian regression checklist (activation/page/table/cache/security) moved from the original TESTING.md.
- Temp originals (`tmp-orig/`) removed after the merge; `docs-check.sh` green.
- **Next:** level 1 on 0.4.1 (user default); 0.5.0 only after level 1 is green.

---

## 2026-09-26 — Level 0 on 0.4.1: green; two script bugs found and fixed
- **doctor:** verdict FULL stand possible (Debian 12 bookworm, sudo, apt, network, 1991 MB RAM, 19.9 GB disk, systemd).
- **install (level 0 profile):** `--php=8.1,8.3 --browsers=none` finished in **42 s** (PHP + MariaDB, WP-CLI 2.12.0, Composer, PHPCS/WPCS/PHPStan stubs). No browsers, no disk bloat in the workspace.
- **level 0 result on 0.4.1: all PASS** — syntax 44 files × PHP 8.1/8.3, entrypoints 7.0+, WPCS 0/0, PHPStan level 6 no errors, i18n 178 entries. Artifacts archived to `docs/test-reports/0.4.1/runs/level0-20260926-014213/` (44 KB, text only).
- **Two tooling bugs fixed in-round** (both false alarms, plugin untouched): PHP version list parsing in `check.sh`; self-matching `pgrep` pattern in `stand.sh stop` (replaced with exact-name matching).
- **Round ended clean:** `stand.sh stop` proved no leftover processes.
- **Decision recorded:** 0.4.1 needs no code fix for static level; the old claim of a full cycle is now backed for level 0.
- **Next:** level 1 (needs `--browsers=chromium` + boot of the `main` stand) in its own round; translation round after; 0.5.0 only after 0.4.1 is re-verified.

---

## 2026-09-26 — Script round: stand.sh/check.sh rebuilt, docs-check added
- **stand.sh v2:** `doctor` preflight (OS, sudo, apt, codename, network to sury/wordpress.org, RAM, disk, PID 1) with a readable verdict; install profiles `--php=` (default 8.1,8.3) and `--browsers=` (default chromium, `none` allowed); `min` stand defaults auto-resolve newest patch releases of WP 6.6.x / WC 9.0.x via api.wordpress.org + plugins.svn; memory-aware MariaDB start (systemd service or mysqld_safe); `down --name=all`; new `stop` command (web servers + MariaDB + empty pgrep proof); storage sanity check before DB ops.
- **check.sh v2:** `level0|level1|level2` profiles (handoff §3), `--php=` for static lint versions, `--browser=` passed through to Playwright scripts via NODE_BROWSER, stand ports read from env files, PHPStan memory limit 1500M, every run archives artifacts to `docs/test-reports/<version>/runs/<mode-ts>`.
- **e2e scripts:** `NODE_BROWSER` support added to admin-smoke, frontend-smoke, table-smoke, cache-regression (default stays chromium).
- **wp-dev/docs-check.sh:** dependency-free consistency sweep (version in 3 places, STATUS/CHANGELOG sync, framework-binding scan of rule docs, ZIP absence, required files, namespace hygiene). First run: 14 PASS (two initial false positives fixed — prohibition mentions and the author name).
- **Docs synced:** AGENTS.md, README.md, HANDOFF.md, TESTING.md now describe doctor/levels/stop exactly as the scripts behave.
- **Not touched:** stands (no install this round, by plan), plugin code, level runs.
- **Next:** translation round (SPEC/DECISIONS/ARCHITECTURE full English detail) OR re-verification of 0.4.1 with level0/level1 (install + boot detached), whichever the user schedules first; 0.5.0 starts only after 0.4.1 is re-verified.

---

## 2026-09-26 — Workspace completed in this chat; session/round terminology clarified
- **Copied** from the GitHub transport repo: `rvn-compare-products-for-woocommerce/` (53 files, v0.4.1 in all 3 places), `wp-dev/` (176K), `CHANGELOG.md`, `docs/test-reports/` (0.1.0–0.4.1). **Not** copied: `src/`, `public/`, Next.js configs, ZIPs (environment / disabled artifacts).
- **Removed** `wp-dev/build-zip.sh` per the ZIP decision; no code depended on it (only historical doc mentions remain).
- **Fixed** language rules: Russian PHPDoc/code comments confirmed as the user decision in `AGENTS.md` and `README.md` (both incorrectly said "English").
- **Added** `.gitignore` (`node_modules/`, `dist/`, `.next/`) and `docs/archive/` (previous `ANSWER.md` moved there per the round DoD).
- **Clarified** session vs round vs sandbox reset for the user (evidence: uptime ~30s at round start, system tools gone, project files persist).
- **Open:** `stand.sh doctor` + `--php/--browsers` flags + `check.sh` levels + `docs-check.sh` (script work, next round); translation round for SPEC/TESTING/ARCHITECTURE still pending (current files are condensed rewrites; DECISIONS.md is the full original in Russian).

---

## 2026-09-26 — Workspace Audit, Documentation Standardization & 0.5.0 Planning
- **Author / Agent:** AI Assistant & User.
- **Milestone Status:** 0.4.1 verified and accepted by user (5/5 manual verification tests passed on Storefront 4.6.2).
- **Decisions Finalized:**
  - Standardized project documentation language to English (`PROJECT_DOCS_EN`).
  - Elevated minimum platform requirements to WordPress 6.6+ and WooCommerce 9.0+.
  - Fully removed ZIP build references and workspace artifacts; packaging handled downstream.
  - Approved `image_ratio` (`1:1`, `3:4`, `4:3`, `16:9`, `auto`) and `image_fit` (`contain`, `cover`) for Milestone 0.5.0.
  - Approved modular Support Hub architecture via `Core::add_support_tab()` for Milestone 0.6.0.
  - Created portable workspace documentation: `AGENTS.md`, `README.md`, `STATUS.md`, `HANDOFF.md`, `SPEC.md`, `DECISIONS.md`, `ARCHITECTURE.md`, `TESTING.md`, `LOG.md`.
- **Next Step:** Begin Milestone 0.5.0 (Admin Settings UI + Contextual Help + Image Controls).

---

## 2026-09-25 — Milestone 0.4.1 Bugfix Release & Verification
- **Summary:** Fixed 5 Storefront issues identified in live user manual testing:
  1. Russian quotes in static shortcodes (`products="«214,217»"`).
  2. Thumbnail layout overflow and title overlap.
  3. Storefront button style conflicts on comparison slider arrows.
  4. Tab-switch loading flicker on `visibilitychange`.
  5. 5-column wide-screen desktop breakpoint calculation (`window.innerWidth`).
- **Result:** Manual acceptance test passed 5/5. 0.4.1 closed.

---

## 2026-09-25 — Milestone 0.4.0 (Comparison Page & Table)
- **Summary:** Implemented automatic comparison page, `[rvn-compare-table]` shortcode, REST `/table` endpoint, field groups, difference highlighting, and admin tab layout.
- **Verification:** Full Level 1 & Level 2 suite passed; 0 errors in Plugin Check.
