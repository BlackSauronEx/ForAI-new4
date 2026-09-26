# RVN Compare — working area

Product, test infrastructure, documentation and preview-app workspace for the
**RVN Compare Products for WooCommerce** plugin (WordPress + WooCommerce; GPL-2.0-or-later).

## What is here

| Path | Role | Lives in | Chat portable |
| --- | --- | --- | --- |
| `rvn-compare-products-for-woocommerce/` | The plugin — the deliverable | project | yes |
| `wp-dev/` | Stands, checks, reports (`stand.sh`, `check.sh`, `e2e/`, `tests/`) | project | yes |
| `docs/` | Project docs: `HANDOFF.md`, `STATUS.md`, `SPEC.md`, `DECISIONS.md`, `ARCHITECTURE.md`, `TESTING.md`, `LOG.md`, `ANSWER.md`, `CHECKLIST.md`, `test-reports/`, `archive/` | project | yes |
| `CHANGELOG.md` | Version history (English) | project | yes |
| `AGENTS.md` | Entry point for the AI agent (rules of this area) | project | yes |
| everything else (`src/`, `package.json`, build configs, `node_modules/`, `dist/`) | Preview application and its build environment | environment | **no** |

> **Size and retention (`docs/test-reports/`, `docs/archive/`, `docs/LOG.md`):**
> the area is deliberately short-lived in detail. Historical reports of closed
> versions were **not** carried over into this chat (user decision), and raw run
> folders are pruned as soon as a version closes (`AGENTS.md` rule 14). So
> `docs/test-reports/` holds only `0.5.0.md` (the version in flight) plus
> `TEMPLATE.md`; closed-version history — version summaries `0.1.0.md`–`0.4.1.md`
> and every raw `runs/` log — stays in the transport repo
> https://github.com/BlackSauronEx/ForAI-new3, together with `docs/archive/`
> rounds 05–19. References to pruned paths in `docs/LOG.md`,
> `docs/archive/LOG-archive.md` and `docs/test-reports/0.5.0.md` point there.

> The preview app shows the current agent answer (`docs/ANSWER.md`) as a single
> readable page, so long answers are never cut off. It is a property of the chat
> sandbox, not part of the plugin, and it never travels between chats. If the
> user downloads the folder, the app stays behind; a new chat brings its own.

## Reading order for a new chat

1. `AGENTS.md` — rules of this working area (10 min).
2. `docs/HANDOFF.md` — how to pick the project up, session-reset behavior, stand usage.
3. `docs/STATUS.md` — where the project is and what is next (single source of truth).
4. `docs/SPEC.md` → `docs/DECISIONS.md` → `docs/ARCHITECTURE.md` → `docs/TESTING.md`
   → `CHANGELOG.md` → `docs/test-reports/` — only the sections you need.

## Quick checks

```bash
bash wp-dev/stand.sh doctor    # environment sanity: sudo, packages, network, RAM, disk
bash wp-dev/stand.sh status    # what is installed and running
bash wp-dev/docs-check.sh      # documentation consistency (no dependencies)

bash wp-dev/stand.sh install   # system packages, PHP versions, MariaDB, WP-CLI, Composer,
                               # PHPCS/PHPStan, Playwright browsers. Slow; run detached.
bash wp-dev/stand.sh up        # builds a fresh WP+WC site. A daemon; run detached.
bash wp-dev/stand.sh stop      # all web servers + MariaDB (end of round)

bash wp-dev/check.sh level0    # no site: PHP syntax 8.1/8.3, WPCS, PHPStan, i18n
bash wp-dev/check.sh level1    # main stand: tests, browser, Plugin Check, uninstall
bash wp-dev/check.sh level2    # release matrix: level0/1 + min stand + cache regression
```

Levels: `level0` after every code change; `level1` before handing a build to the
user; `level2` before a release. Levels may be split across rounds. Individual
modes (`tests|stand|front|table|pcp|cache`) stay available; see `docs/TESTING.md`.

## Delivery

The user downloads this folder (as an archive) and gives it to the next chat. The plugin
folder is installed via **Plugins → Add New → Upload Plugin → ZIP → Install → Activate**.
Needs WooCommerce 9.x (the plugin declares `Requires Plugins: woocommerce`).

ZIP artifacts are intentionally disabled in this workspace: the sandbox does not need
them, and builds are produced elsewhere for release.

## Updating docs

Project documents in `docs/` and `CHANGELOG.md` are in English. Agent replies and
checklist items are Russian. Code comments and PHPDoc are Russian (user decision); UI strings live in
`languages/` with a `ru_RU` translation. Every round updates `docs/STATUS.md`,
`docs/LOG.md`, `docs/ANSWER.md` and `docs/CHECKLIST.md` — see `AGENTS.md` § "Every round".
