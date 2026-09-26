# AGENTS.md — Agent Entry Point for This Working Area

> `docs/` holds project documents; this file holds agent policy — see
> `docs/DECISIONS.md` item 3 (language rule).
> Project docs are written in English; user-facing replies are always Russian.

## The working area

| Zone | Contents | Portable between chats |
| --- | --- | --- |
| **Project** | `rvn-compare-products-for-woocommerce/` (the plugin), `wp-dev/` (stands and checks), `docs/`, `CHANGELOG.md` | YES — travel together |
| **Agent work product** | `docs/ANSWER.md`, `docs/CHECKLIST.md` (this chat's current answer and checklist for the preview app) | YES |
| **Environment** | `src/`, `package.json`, `vite.config.ts` or `next.config.ts`, `tsconfig.json`, `node_modules/`, `dist/`, and anything else not listed above | NO — belongs to the sandbox only, must NOT be copied, moved or described in project rules |

The sandbox snapshot can break between sessions (see `docs/HANDOFF.md` §2).
The user downloads this folder to move the work to a fresh chat. **If a progress file is
not inside this area, it is lost.**

## Hard rules

1. **Never rename or delete files belonging to the plugin, `wp-dev/`, `docs/`
   or `CHANGELOG.md`.** Make changes by editing files in place or creating new ones.
2. **Nothing depends on the preview app or on PostgreSQL/Next.js specifics.** The preview
   app is a thin wrapper that renders `docs/ANSWER.md` only (user decision 26.09.26:
   `docs/CHECKLIST.md` stays a project file and is not published as a page). Do not
   reintroduce framework-specific rules into `docs/`.
3. **Never run `wp-dev/stand.sh up` (or any long-running server) in the foreground tool
   call.** It is a daemon: the call will hang and the session dies. Only
   `setsid nohup bash wp-dev/stand.sh up </dev/null >/tmp/stand-main.log 2>&1 &`
4. `bash wp-dev/stand.sh install` takes minutes and hits the package network. Only
   detached; never conclude the answer while it still runs — poll the log until the
   `install finished` line (a snapshot taken mid-run once corrupted a sandbox).
5. **Never read binary files (.zip, .png, .phar) with text tools.**
6. **Never change the prefix** `rvn_compare` (PHP) / `rvn-compare` (CSS/JS) — Plugin Check
   derives the plugin prefix from the root namespace `RVN_Compare`.
7. **Do not make `phpcbf` a separate manual step.** WPCS clean is the end of every edit
   session, and edits that reintroduce violations are not allowed. `phpcs` must report
   zero errors and zero warnings.
8. **PHPStan runs at level 6.** `phpcbf` fixes style; `phpstan` catches real type issues.
   Treat `phpcs` and `phpstan` failures the same: both block the change.
9. **Never say "done" without a passing run of the checks appropriate to the change level.**
   State which level was run and paste the summary line. This includes `wp-dev/`
   tooling: `bash -n` / `node --check` prove syntax only; a tooling change counts as
   verified only after a real run that exercises it (several tooling bugs once passed
   syntax checks and failed at the first real run).
10. **i18n:** strings in code stay in English and must be translated in `languages/`.
    Every new user-facing string requires a corresponding `msgid` in the POT file.
11. **ZIP** is disabled in this workspace: never build, store or leave one behind.
    (Mentioning ZIP in install instructions is fine; ZIP artifacts are not.)
    Release artifact tooling lives outside this area (see `docs/DECISIONS.md`).
12. **Uninstall data:** any new persistent key goes into `uninstall.php` and the docs
    table in the same round.
13. **Never bulk-copy or re-download the whole working area "just in case"** — read and
    copy only what the current round needs (step and token limits).
14. **Workspace hygiene — the portable zone stays small.** It must not grow with the
    number of rounds. Retention caps:
    - `docs/test-reports/` keeps **only** the report of the version in flight
      (`<version>.md`), `TEMPLATE.md`, and the raw run artifacts of the **latest**
      round. When a version is closed, its `runs/` folder is deleted — findings
      belong in the version report, `docs/STATUS.md` and `docs/LOG.md`, never only
      in a raw log. Long-term history stays in the GitHub transport repo.
    - `docs/archive/` keeps at most the **3 most recent** answers; older ones are
      dropped at the move (append-only applies to what is kept — never edited).
    - `docs/LOG.md` holds the current milestone's rounds; when it passes ~35 KB,
      older closed-version entries move verbatim to `docs/archive/LOG-archive.md`.
    - Never store screenshots, ZIPs, `node_modules`, blueprints or other binaries
      in the area; run artifacts are text only (`screens-list.txt` lists, not copies).
    - Raw runs are archived under `RVN_COMPARE_VERSION`, which keeps the released
      number until the housekeeping round bumps it — that is expected, not a bug.
    - Deleting or moving anything under `docs/` (including pruned runs) requires
      the **user's explicit approval** — rule 1 stays above this one.
    - Sanity anchor: the portable zone is ~100 files / ~1.3 MB. If it grows past
      ~150 files or ~2 MB, run the cleanup before writing code in that round.

## Language rules (short form; details in `docs/DECISIONS.md`)

- Reasoning language: English.
- Final answer and checklist items: Russian.
- Project docs (`docs/*.md`, `CHANGELOG.md`, `readme.txt`): English.
- Code: English identifiers, **Russian PHPDoc** (user decision — code comments stay in Russian), Russian UI strings via `languages/` files.

## First session move

1. Read `docs/HANDOFF.md` and `docs/STATUS.md` (status first; `HANDOFF.md` points to the
   rest in reading order). Do not read all 44 PHP files at once — read per task.
2. Run `bash wp-dev/docs-check.sh` (dependency-free): version coherence, framework
   bindings, ZIP absence, required files, namespace health. Fix what is red first.
3. Only if the round involves a stand: `bash wp-dev/stand.sh doctor`, then the install
   profile matching the planned level (`docs/HANDOFF.md` §3). A red doctor blocks
   everything; report it instead of promising work.
4. Tell the user where the project is, what is verified vs. claimed, and the smallest
   useful next step. Do not begin implementation without a go-ahead.

## Every round — definition of done

- If the round changes released code: version bumped in the three places (plugin header,
  `RVN_COMPARE_VERSION`, `readme.txt`); otherwise left untouched.
- `CHANGELOG.md` entry written; `docs/LOG.md` round entry written (one line: date, what
  changed, why, open questions, next step).
- `docs/ANSWER.md` replaced (previous version moved to `docs/archive/`), `docs/CHECKLIST.md`
  refreshed if there are user questions. `docs/archive/` is append-only: archived files
  are never edited.
- **The chat answer ends with a «Следующий шаг» block** (user rule): one clear next
  action, numbered when the user must choose. Also required here because the preview
  page shows the same text.
- All checks for the current level green; report appended to `docs/test-reports/` if a
  stand was involved.
- **Stand down at round end:** `bash wp-dev/stand.sh stop` (web servers + MariaDB; the
  script proves the clean state itself). Never verify with a pattern like
  `pgrep -f 'php -S'` — such a pattern matches the command line that runs it and a
  clean state can never be shown. A daemon left running is a bug.
- The user must be able to re-open the area and pick up exactly where this round ended.

## The next round

Read `docs/STATUS.md` first. Its "Next step" section is the plan; `docs/LOG.md` is the
round-by-round history; `docs/test-reports/` holds verification evidence.
