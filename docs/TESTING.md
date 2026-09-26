# RVN Compare — Testing & Quality Assurance

This document outlines automated and manual verification workflows.

---

## 1. Automated Verification Levels

Execute checks in structured levels according to the scope of your changes:

### Level 0: Static Analysis (Run on Every Edit)
```bash
bash wp-dev/check.sh level0            # or individual: bash wp-dev/check.sh static [--php=8.1,8.3]
```
- **PHP Syntax:** Verified across PHP 8.1 and PHP 8.3 by default (`--php=` for others).
- **Entrypoint Compatibility:** Verifies root entry files on PHP 7.0+ for safe execution before PHP version checks.
- **Standards & Security:** `phpcs` running WordPress-Core, WordPress-Docs, WordPress-Extra, and WordPress-Security rulesets. Zero errors and zero warnings required.
- **Static Types:** `phpstan` running at Level 6 with WordPress and WooCommerce stubs. Zero errors required.
- **Localization:** `wp i18n make-pot` verifies POT synchronization; tests verify complete `ru_RU` string coverage.

### Level 1: Build & Stand Evaluation (Run Before Milestone Handoff)
```bash
bash wp-dev/check.sh level1            # requires the main stand to be up
bash wp-dev/check.sh level1 --browser=webkit   # optional: another engine in a later round
```
- **Unit & Logic Tests:** `wp-dev/tests/core-logic.php` unit evaluations.
- **REST Smoke Suite:** `wp-dev/tests/rest-smoke.py` endpoint evaluations.
- **Browser Automation:** Playwright on the pinned engine (Chromium default): admin menus, settings tabs, frontend button flow, comparison table.
- **WordPress.org Plugin Check (PCP):** Official Plugin Check scanner reports zero errors and zero warnings.
- Individual modes: `tests|stand|front|table|pcp` with an optional `min` stand label.

### Level 2: Matrix & Full Release Validation
```bash
bash wp-dev/check.sh level2            # level0 + level1 + min stand + cache regression
```
- Executes the full Level 1 set on both `main` (latest WP / latest WC / PHP 8.3) and
  `min` (WP 6.6.x / WC 9.0.x / PHP 8.1, ru_RU) test sites.
- Executes the WP Super Cache integration scenario (`bash wp-dev/check.sh cache`).
- Split across rounds when a session timeout is plausible: `level0` in one round,
  `level1` in the next, `min` stand checks after that, `cache` last.

---

## 2. Managing Test Stands

The sandbox resets between rounds; every round with stand work is self-contained:
doctor → install → boot → checks → stop.

```bash
# 1. Environment preflight (fast, run first)
bash wp-dev/stand.sh doctor

# 2. Install tools — profile matches the planned level (run detached):
setsid nohup bash wp-dev/stand.sh install --php=8.1,8.3 --browsers=none </dev/null >/tmp/stand-install.log 2>&1 &   # level 0
setsid nohup bash wp-dev/stand.sh install --php=8.1,8.3 --browsers=chromium </dev/null >/tmp/stand-install.log 2>&1 &  # level 1/2
# Watch until the "install finished" line:
tail -f /tmp/stand-install.log

# 3. Boot stands (run detached; 'main' defaults: latest WP+WC, PHP 8.3, en_US, :8080)
setsid nohup bash wp-dev/stand.sh up </dev/null >/tmp/stand-main.log 2>&1 &
setsid nohup bash wp-dev/stand.sh up --name=min </dev/null >/tmp/stand-min.log 2>&1 &
# 'min' defaults: WP 6.6.x, WC 9.0.x (newest patch of the branch, auto-resolved),
# PHP 8.1, ru_RU, port 8081. Pin exact numbers with --wp=/--wc= when needed.

# 4. Status & end of round
bash wp-dev/stand.sh status
bash wp-dev/stand.sh down --name=main        # single web server
bash wp-dev/stand.sh down --name=all         # all web servers
bash wp-dev/stand.sh stop                    # servers + MariaDB; proves the clean state itself

# 5. Documentation consistency (no dependencies)
bash wp-dev/docs-check.sh
```

Every `check.sh` run copies its artifacts to `docs/test-reports/<version>/runs/`
so evidence survives the sandbox reset. Only text is copied; screenshots are
listed in `screens-list.txt` and never copied — the working area stays text-only.

> Fallback: if `stand.sh doctor` says a real stand is impossible in the current
> sandbox, use WordPress Playground as an **optional, informative-only** smoke
> tool — see `wp-dev/playground.md`. It never replaces levels 0–2 and never
> counts as verification evidence.

---

## 3. Seed data and manual verification

Every `stand.sh up` run seeds a demo store via `wp-dev/seed.php`: 12
products of all types, 5 categories (including "Sale" and the default
category), 4 global attributes with value descriptions, and Plugin Check.
Login: `admin` / `admin`.

The full manual regression checklist (activation, page, table, cache,
security — 23 items, ~15–20 min, Storefront + one block theme) lives in
`docs/CHECKLIST.md` (Russian, user-facing). Automation never replaces it:
structure, data, cache, and security are covered by `check.sh`; the human
eye covers layout, theme quirks, and subjective feel.

When verifying on the user's live test store
(`http://h607951395.nichost.ru/`), confirm the site is reachable before
reporting anomalies; unreachable site is reported to the user, never filed
as a plugin defect.

On a problem, collect: WP/WC/PHP versions, theme, cache plugins,
`wp-content/debug.log` excerpt, screenshot, URL, reproduction steps.
