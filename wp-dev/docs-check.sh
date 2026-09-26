#!/usr/bin/env bash
# =============================================================================
# Documentation consistency check (no PHP, no MariaDB, no network).
#
#   bash wp-dev/docs-check.sh
#
# Verifies that facts that must agree across documents actually do, and that
# project docs carry no environment-specific framework bindings. Run at the
# start of a session and at the end of documentation rounds.
# Exit code 0 means everything matches.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="rvn-compare-products-for-woocommerce"
FAILED=0

record() {
  local status="$1" detail="$2" name="${3:-}"
  [ "$status" = "FAIL" ] && FAILED=1
  printf '%-4s  %s — %s\n' "$status" "$name" "$detail"
}

# 1. Version coherence: plugin header, RVN_COMPARE_VERSION, readme.txt Stable tag.
header=""
[ -f "$ROOT/$SLUG/$SLUG.php" ] && header="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/$SLUG/$SLUG.php" | head -1 | tr -d '[:space:]')"
constant="$(sed -n "s/^define( 'RVN_COMPARE_VERSION', '\([^']*\)' );/\1/p" "$ROOT/$SLUG/$SLUG.php" 2>/dev/null | head -1)"
stable="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$ROOT/$SLUG/readme.txt" 2>/dev/null | head -1 | tr -d '[:space:]')"

if [ -n "$header" ] && [ "$header" = "$constant" ] && [ "$constant" = "$stable" ]; then
  record PASS "version consistent in 3 places" "$header"
elif [ -z "$header" ]; then
  record FAIL "plugin files missing" "$SLUG/$SLUG.php"
else
  record FAIL "version mismatch" "header=$header constant=$constant stable=$stable"
fi

# 2. STATUS.md mentions the same version somewhere (coarse, non-fatal warning).
if grep -qF "$header" "$ROOT/docs/STATUS.md" 2>/dev/null; then
  record PASS "STATUS.md mentions plugin version" "$header"
else
  record WARN "STATUS.md has no mention of version $header" "check manual sync"
fi

# 3. A changelog entry exists for the plugin version.
if grep -qF "$header" "$ROOT/CHANGELOG.md" 2>/dev/null; then
  record PASS "CHANGELOG.md has an entry for the version" "$header"
else
  record FAIL "CHANGELOG.md is missing an entry" "$header"
fi

# 4. No framework/DB bindings in project docs (portable rules only).
# Only rule-bearing files are scanned; answers, checklists, logs, archives and
# test reports may legitimately mention frameworks as history. Prohibition
# mentions (the rule itself) are not violations.
bind_patterns='Next\.js|next\.config|drizzle|PostgreSQL|vite\.config'
rule_files=("$ROOT/AGENTS.md" "$ROOT/README.md" "$ROOT/docs/HANDOFF.md" "$ROOT/docs/STATUS.md" "$ROOT/docs/SPEC.md" "$ROOT/docs/TESTING.md" "$ROOT/docs/DECISIONS.md")
hits="$(grep -rEn "$bind_patterns" "${rule_files[@]}" 2>/dev/null \
  | grep -viE 'nothing depends|without dependence|property of the sandbox|must not|never copy|cannot|stays behind|do not|запрещ|нельзя|не перенос|не копир|уберите|убрано|удалён|удалить' \
  | head -10)"
if [ -z "$hits" ]; then
  record PASS "no environment framework bindings in rule docs" "AGENTS.md, README.md, docs rules"
else
  record FAIL "framework binding leaked into rule docs" "$(printf '%s' "$hits" | head -3 | tr '\n' ' ')"
fi

# 5. ZIP artifacts are not present in the workspace (decision: disabled).
if find "$ROOT" -maxdepth 2 -name '*.zip' -o -maxdepth 2 -name '*.zip.sha256' | grep -q .; then
  record FAIL "ZIP artifacts found in workspace" "$(find "$ROOT" -maxdepth 2 -name '*.zip' | head -3 | tr '\n' ' ')"
else
  record PASS "no ZIP artifacts in workspace" "maxdepth 2"
fi

# 6. Agent-facing files exist where the DoD expects them.
for required in "AGENTS.md" "README.md" "docs/STATUS.md" "docs/HANDOFF.md" "docs/LOG.md" "docs/ANSWER.md" "docs/CHECKLIST.md"; do
  if [ -f "$ROOT/$required" ]; then
    record PASS "file present" "$required"
  else
    record FAIL "file missing" "$required"
  fi
done

# 7. wp-dev tooling intact and plugin prefix unchanged where it must live.
if [ -f "$ROOT/wp-dev/stand.sh" ] && [ -f "$ROOT/wp-dev/check.sh" ]; then
  record PASS "stand/check scripts present" "wp-dev/"
else
  record FAIL "stand/check scripts missing" "wp-dev/"
fi

if [ -n "$header" ]; then
  # The forbidden thing is the old PHP namespace and a misspelled prefix, not
  # the author name. Scan PHP sources only, excluding language files.
  bad_prefix="$(grep -rlE '\b(namespace +Revolen|Revolen\\\\|rvn-comrape)\b' \
    --include='*.php' --exclude-dir=languages "$ROOT/$SLUG" 2>/dev/null | head -3 | tr '\n' ' ')"
  if [ -z "$bad_prefix" ]; then
    record PASS "namespace / prefix consistent" "RVN_Compare / rvn_compare"
  else
    record FAIL "forbidden namespace remnant found" "$bad_prefix"
  fi
fi

echo
[ "$FAILED" = 0 ] && echo "RESULT: docs-check passed" || echo "RESULT: docs-check failed"
exit "$FAILED"
