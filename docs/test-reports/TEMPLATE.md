# Test report <version> — TEMPLATE (do not fill here; copy to `<version>.md`)

**Status:** draft | verified
**Run date(s):**
**Milestone:**
**Result:** all green / issues found (see below)

## Progress

| Level / stand / browser | Status | Round |
| --- | --- | --- |
| 0 static | — | |
| 1 main + Chromium | — | |
| 2 min + Chromium | — | |
| 2 main + WebKit (optional) | — | |

## Run environment

- PHP versions, MariaDB, WP-CLI, Composer, PHPCS/WPCS, PHPStan, Plugin Check, Playwright:
- Stand `main`: WordPress x.y.z, WooCommerce x.y.z, PHP x.y, locale:
- Stand `min`: WordPress x.y.z, WooCommerce x.y.z, PHP x.y, locale:

## Results

| Check | main | min |
| --- | --- | --- |
| PHP syntax 8.1/8.3 | | |
| Entrypoints on PHP 7.0+ | | |
| WPCS (errors/warnings) | | |
| PHPStan level 6 | | |
| i18n (POT + ru_RU) | | |
| Core-logic autotests | | |
| REST smoke | | |
| Frontend in browser | | |
| Compare page & table | | |
| Activation, admin smoke | | |
| Without WooCommerce | | |
| Uninstall (keep / clean) | | |
| WP_DEBUG log | | |
| Plugin Check | | |
| WP Super Cache scenario | | |

## Found and fixed

(Defect → cause → fix → re-verified. One subsection per issue.)

## Left for the user (manual verification)

(What automation cannot cover: real hosting look, block theme, devices.)

## Deviations from the Definition of Done

("None" or an explicit list with reasons.)

## Closure criterion

(State what was required to mark this build verified and whether it is met.)
