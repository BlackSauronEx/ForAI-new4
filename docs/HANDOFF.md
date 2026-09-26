# Handing Off — How to Continue in a New Session

This document is written for the **AI assistant** and the **developer** picking up
the workspace in a new chat.

---

## 0. Project Summary

**RVN Compare Products for WooCommerce** is an audited, lightweight, high-security
WordPress comparison plugin for WooCommerce stores.
- Author / Line: Revolen / RVN Line.
- Target: Clean pass on WordPress.org automated and manual review.
- Workspace: Plugin source (`rvn-compare-products-for-woocommerce/`) + Test & Automation suite (`wp-dev/`) + Project Docs (`docs/`).

---

## 1. Reading Order for a New Agent

Read files in this order. Do not read all PHP files simultaneously:

| Step | File | Purpose |
| --- | --- | --- |
| 1 | `AGENTS.md` | Core constraints, environment separation, DoD |
| 2 | `docs/STATUS.md` | Single source of truth: where the project is right now |
| 3 | `docs/HANDOFF.md` | This file: setup, test levels, session management |
| 4 | `docs/SPEC.md` | Product requirements and data structures |
| 5 | `docs/DECISIONS.md` | Architectural and UX decisions with rationales |
| 6 | `docs/ARCHITECTURE.md` | File map, lifecycle, security conventions |
| 7 | `docs/TESTING.md` | Stand scripts, check levels, test checklists |
| 8 | `CHANGELOG.md` | Release history |

---

## 2. Environment & Session Lifecycle

1. **Sandbox Reset (confirmed):**
   - The OS environment (installed packages, MariaDB daemon, background servers,
     `/tmp`) is rebuilt **between rounds**. Only project files persist; uptime at
     the start of a round is near zero. Plan every round as self-contained.
   - Round preamble for stand work:
     `bash wp-dev/stand.sh doctor`
     `setsid nohup bash wp-dev/stand.sh install </dev/null >/tmp/stand-install.log 2>&1 &`
2. **Round Lifecycle:**
   - Install, boot and checks all happen **within one round**, then shut down:
     `bash wp-dev/check.sh level0|level1|level2`
      `bash wp-dev/stand.sh stop` (web servers + MariaDB; the script proves the clean state itself — never verify with a self-matching `pgrep -f` pattern).
   - Memory between rounds is **files only**: run progress table in
     `docs/test-reports/<version>.md` and round notes in `docs/LOG.md`.
3. **But within one round**, runs are not serialised into one command: level
   executions and individual check modes go in separate tool calls (each command
   has a time limit).
4. **No Background Tasks during Snapshot:**
   - Never conclude an agent response while a heavy background installer is still
     running. Poll the log until the final success line appears before writing
     the final response. (A snapshot taken mid-install once corrupted a sandbox.)

---

## 3. Test Verification Levels

Always execute checks by level, one command per round step:

- **Level 0 (Static):** `bash wp-dev/check.sh level0`
  - PHP syntax on 8.1 and 8.3, PHP 7.0+ entrypoints, WPCS + Security (zero errors/warnings), PHPStan level 6, POT/i18n.
  - Install profile for this: `--php=8.1,8.3 --browsers=none`.
  - Run after every PHP/JS/CSS code modification.
- **Level 1 (Build):** `bash wp-dev/check.sh level1`
  - On stand `main` (latest WP + latest Woo + PHP 8.3): unit tests, REST smoke,
    admin/frontend/table browser scenarios (Chromium by default, `--browser=webkit` optional),
    Plugin Check, uninstall paths, WP_DEBUG.
  - Install profile: `--php=8.1,8.3 --browsers=chromium`.
  - Run before presenting completed milestone builds.
- **Level 2 (Release Matrix):** `bash wp-dev/check.sh level2`
  - Level 0 + Level 1 plus `min` stand (WP 6.6.x, WC 9.0.x, PHP 8.1, ru_RU; branches
    auto-resolve to the newest patch release at boot) and the WP Super Cache regression.
  - Run before versioned tags and compatibility-affecting changes.

Individual modes stay available (`tests|stand|front|table|cache|pcp` with an
optional stand label and `--browser=`). `docs-check.sh` verifies documentation
consistency without any system dependencies; run it at session start and after
documentation edits.

---

## 4. Hard Constraints

| Constraint | Reason |
| --- | --- |
| Never create/commit ZIP files in this workspace | Packaging is handled downstream; keeps sandbox light |
| Never change prefix `rvn_compare` or root namespace `RVN_Compare` | Plugin Check requirement and architectural consistency |
| Never bind project rules to preview app frameworks | Preview app is environment-specific and ephemeral |
| Never read binary files (`.mo`, `.zip`, `.phar`) with text tools | Corrupts tool buffers |
| Project docs in English; user replies in Russian | Minimizes token overhead while providing native user experience |
| Never batch simultaneous edits to the same file; re-read critical edits | A batch of parallel edits to one file once lost a change silently while reporting success |
