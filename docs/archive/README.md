# docs/archive — carry-over note

Append-only store of previous `docs/ANSWER.md` versions (never edited).

On 2026-09-26 the working area moved to a new chat. To keep the area light, two
folders were intentionally **not** carried over (user-approved):

- `docs/archive/` rounds 05–19 (old chat answers, not needed for work);
- `docs/test-reports/0.4.1/runs/` (raw run logs of the closed 0.4.1 release; the
  summary lives in `docs/test-reports/0.4.1.md`).

The full history stays in the previous working area:
https://github.com/BlackSauronEx/ForAI-new2 (`docs/archive/`, `docs/test-reports/0.4.1/runs/`).
References to those paths in `docs/LOG.md` and `docs/test-reports/0.4.1.md` point there.

## 2026-09-26 (second move) — `docs/test-reports/` not carried over

On the next move (Round 25) the user asked **not** to transfer the test reports.
So the whole `docs/test-reports/` folder (54 files, 328 KB: version summaries
`0.1.0.md`–`0.5.0.md` plus raw `runs/` logs) stayed behind in the previous area.

- Full report history: https://github.com/BlackSauronEx/ForAI-new3 (`docs/test-reports/`).
- The only thing present here after the move is the fresh run of Round 25
  itself: `docs/test-reports/0.4.1/runs/level1-20260926-095919/` (created by
  `wp-dev/check.sh`, not copied from anywhere).
- Recommended carry-over (asked of the user): `docs/test-reports/0.5.0.md` —
  the progress table of the still-open 0.5.0 — and `TEMPLATE.md`.

## 2026-09-26 (Round 28) — hygiene rule applied: pruned run + LOG history split

User-approved cleanup, recorded as `AGENTS.md` hard rule 14:

- **Deleted** `docs/test-reports/0.4.1/runs/level1-20260926-095919/` — 12 text
  files (60 KB), the raw level-1 run of Round 25 against the closed 0.4.1
  headers. Its findings live in `docs/STATUS.md` ("Open Defects") and in the
  Round 25 entry of `docs/LOG.md`; nothing was lost but raw JSON/log dumps.
- **Added** `docs/archive/LOG-archive.md` — 16 pre-0.5.0 entries moved verbatim
  out of `docs/LOG.md` (0.4.0/0.4.1 verification programme, translation, script
  and audit rounds). Append-only like everything here.
- **Retention from now on:** at most the 3 most recent answers in this folder;
  `docs/test-reports/` keeps only the in-flight version report + `TEMPLATE.md`
  + the latest raw run.

## Answer inventory — the 3-answer cap is applied (Round C2, user-approved)

Applied on the user's word in Round C2: rounds 20–24 (5 files, ~28 KB) were
dropped first, and when this round's own answer was archived the folder hit the
cap again, so round 25 (move + stands proven) went too. What remains is exactly
the cap: rounds 26 (reports carried over), 27 (wp-dev and stands explained) and
28 (hygiene applied). Everything dropped is condensed in `docs/LOG.md` and
`docs/archive/LOG-archive.md`; the originals stay in the transport repo.

## Old inventory (kept for the record)

Eight answers are stored after this round: 20 (Round A done), 21 (workspace
moved), 22 (Round B done), 23 (pre-C notes), 24 (C1 done), 25 (move + stands
proven), 26 (reports carried over), 27 (wp-dev and stands explained; archived
in Round 28 together with the rest of this cleanup).

Rule 14 caps the folder at the 3 most recent answers, which would drop rounds
20–24 (5 files, ~28 KB). That deletion needs the user's word (rule 1 outranks
rule 14), so it is **pending** — see `docs/CHECKLIST.md`. Every one of those
answers is fully summarized in `docs/LOG.md` / `docs/archive/LOG-archive.md`,
and the originals also live in the transport repo.
