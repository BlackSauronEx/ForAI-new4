#!/usr/bin/env bash
# =============================================================================
# RVN Compare plugin verification, on demand and by level.
#
# Levels (see docs/TESTING.md):
#   bash wp-dev/check.sh level0 [--php=8.1,8.3]
#       No site required. PHP syntax across chosen versions, PHP 7.0+ entrypoint
#       compatibility, WordPress Coding Standards + Security, PHPStan level 6,
#       POT/i18n completeness. Run after every code edit.
#   bash wp-dev/check.sh level1 [--browser=chromium]
#       Requires the `main` stand (bash wp-dev/stand.sh up). Unit tests, REST
#       smoke, browser scenarios on the chosen Playwright browser, Plugin Check,
#       uninstall behavior, WP_DEBUG review. Run before handing a build over.
#   bash wp-dev/check.sh level2 [--browser=chromium]
#       Full release matrix: level0 + level1 on `main` plus tests/stand/table/
#       frontend on the `min` stand and the WP Super Cache regression. Run
#       before release tags and compatibility-affecting changes.
#
# Individual checks (all default to the `main` stand):
#   bash wp-dev/check.sh static [--php=8.1,8.3]
#   bash wp-dev/check.sh tests [main|min]
#   bash wp-dev/check.sh stand [main|min]
#   bash wp-dev/check.sh tests/stand/front/table [main|min] [...trailing --browser=chromium]
#   bash wp-dev/check.sh cache
#   bash wp-dev/check.sh pcp
#   bash wp-dev/check.sh runtime   (legacy alias: stand main + stand min + pcp)
#   bash wp-dev/check.sh all       (legacy alias: level0 + level1-equivalent)
#
# Reports live in /tmp/rvn-check/ during a run and are afterwards copied to
# docs/test-reports/<version>/runs/<timestamp>/ so results survive the sandbox
# reset. Exit code 0 means every check passed.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="rvn-compare-products-for-woocommerce"
PLUGIN="$ROOT/$SLUG"
TOOLS="${RVN_TOOLS_DIR:-/tmp/wp-tools}"
BIN="$TOOLS/composer/vendor/bin"
OUT="/tmp/rvn-check"
VERSION="$(sed -n "s/^define( 'RVN_COMPARE_VERSION', '\([^']*\)' );/\1/p" "$PLUGIN/$SLUG.php" 2>/dev/null || echo 'unknown')"
FAILED=0

# Fresh output directory per run: an archive must contain only its own run.
rm -rf "$OUT"
mkdir -p "$OUT/screens"
: >"$OUT/summary.txt"

T0=$(date +%s)
step() { printf '[%3ss] %s\n' "$(($(date +%s) - T0))" "$*"; }

record() {
  local status="$1" name="$2" detail="${3:-}"
  [ "$status" = "FAIL" ] && FAILED=1
  printf '%-4s  %s — %s\n' "$status" "$name" "$detail" | tee -a "$OUT/summary.txt"
}

# Resolve the web port for a stand from its env file, with a sensible default.
stand_port() {
  local stand="$1" env_file="/tmp/wp-stand-$1.env" port=""
  if [ -f "$env_file" ]; then port="$(sed -n 's/^PORT=//p' "$env_file" | head -1)"; fi
  if [ -n "$port" ]; then printf '%s' "$port"; return; fi
  if [ "$stand" = "min" ]; then printf '8081'; else printf '8080'; fi
}

# Parse everything after the mode: a bare stand label (main|min) and --key=value
# options, in any order.
parse_trailing_opts() {
  OPT_php="8.1,8.3"; OPT_browser="chromium"; OPT_stand="main"
  local arg
  for arg in "$@"; do
    case "$arg" in
      main|min) OPT_stand="$arg" ;;
      --php=*) OPT_php="${arg#*=}" ;;
      --browser=*) OPT_browser="${arg#*=}" ;;
      --stand=*) OPT_stand="${arg#*=}" ;;
      *) : ;;
    esac
  done
}

# ---------------------------------------------------------------------------
# Level 0: checks that do not need a running site.
# ---------------------------------------------------------------------------
run_static() {
  local files count bad v rc
  mapfile -t php_list < <(printf '%s\n' "${OPT_php:-8.1,8.3}" | tr ',' '\n' | awk 'NF')

  mapfile -t files < <(find "$PLUGIN" -name '*.php' | sort)
  count="${#files[@]}"
  bad=0
  : >"$OUT/lint.txt"
  for v in "${php_list[@]}"; do
    if ! command -v "php$v" >/dev/null; then
      echo "php$v not installed" >>"$OUT/lint.txt"; bad=1; continue
    fi
    for f in "${files[@]}"; do
      "php$v" -l "$f" >>"$OUT/lint.txt" 2>&1 || bad=1
    done
  done
  [ "$bad" = 0 ] && record PASS "PHP syntax (${php_list[*]})" "$count files" \
    || record FAIL "PHP syntax (${php_list[*]})" "see $OUT/lint.txt"

  "$BIN/phpcs" -q --standard=PHPCompatibilityWP --runtime-set testVersion 7.0- \
    "$PLUGIN/$SLUG.php" "$PLUGIN/uninstall.php" "$PLUGIN/rvn-core/bootstrap.php" >"$OUT/phpcompat-entry.txt" 2>&1
  [ $? = 0 ] && record PASS "Entrypoints on PHP 7.0+" "main file, uninstall.php, core bootstrap" \
    || record FAIL "Entrypoints on PHP 7.0+" "see $OUT/phpcompat-entry.txt"

  "$BIN/phpcs" --standard="$ROOT/wp-dev/phpcs.xml.dist" \
    --report-full="$OUT/phpcs.txt" --report-summary="$OUT/phpcs-summary.txt" -q >/dev/null 2>&1
  rc=$?
  [ "$rc" = 0 ] && record PASS "WPCS + Security + PHP 8.1+" "0 errors, 0 warnings" \
    || record FAIL "WPCS + Security" "$(grep -E 'A TOTAL OF' "$OUT/phpcs-summary.txt" 2>/dev/null || echo "see $OUT/phpcs.txt")"

  "$BIN/phpstan" analyse -c "$ROOT/wp-dev/phpstan.neon.dist" --no-progress --memory-limit=1500M >"$OUT/phpstan.txt" 2>&1
  [ $? = 0 ] && record PASS "PHPStan level 6 (WP + Woo stubs)" "no errors" \
    || record FAIL "PHPStan level 6" "$(grep -E 'Found [0-9]+ error' "$OUT/phpstan.txt" || echo "see $OUT/phpstan.txt")"

  bash "$ROOT/wp-dev/i18n.sh" >"$OUT/i18n.txt" 2>&1
  [ $? = 0 ] && record PASS "i18n: POT fresh, ru_RU complete" "$(grep -c '^msgid "' "$PLUGIN/languages/$SLUG.pot" 2>/dev/null || echo '?') entries" \
    || record FAIL "i18n" "see $OUT/i18n.txt"
}

# Debug log hygiene: lines mentioning the plugin's prefix are failures.
check_debug_log() {
  local stand="$1" log="/tmp/wp-stand-$1/wp-content/debug.log" ours other
  ours=0; other=0
  if [ -f "$log" ]; then
    ours="$(grep -ciE 'rvn' "$log" || true)"
    other="$(grep -viE 'rvn' "$log" | grep -cE 'PHP (Warning|Notice|Deprecated|Fatal)|called incorrectly' || true)"
    cp "$log" "$OUT/debug-$stand.log"
  fi
  [ "$ours" = 0 ] && record PASS "[$stand] WP_DEBUG: nothing from the plugin" "other-component entries: $other" \
    || record FAIL "[$stand] WP_DEBUG: plugin entries present" "see $OUT/debug-$stand.log"
}

# ---------------------------------------------------------------------------
# Stand-dependent checks.
# ---------------------------------------------------------------------------
run_runtime_stand() {
  local stand="$1" dir="/tmp/wp-stand-$1" port="$2"
  local WP=(wp --path="$dir" --quiet)
  local url="http://127.0.0.1:$port" out rc

  if [ ! -d "$dir" ]; then
    record FAIL "[$stand] Stand" "not running: bash wp-dev/stand.sh up --name=$stand"
    return
  fi

  : >"$dir/wp-content/debug.log"

  "${WP[@]}" plugin deactivate "$SLUG" >/dev/null 2>&1
  "${WP[@]}" option delete rvn_compare_version >/dev/null 2>&1
  if "${WP[@]}" plugin activate "$SLUG" >/dev/null 2>&1; then
    record PASS "[$stand] Activation" "$("${WP[@]}" core version) · WooCommerce $("${WP[@]}" plugin get woocommerce --field=version) · PHP $(sed -n 's/^PHP_BIN=//p' "/tmp/wp-stand-$stand.env" 2>/dev/null)"
  else
    record FAIL "[$stand] Activation" "wp plugin activate failed"
  fi

  out="$("${WP[@]}" option get rvn_compare_version 2>/dev/null)"
  [ "$out" = "$VERSION" ] && record PASS "[$stand] Data version written at activation" "rvn_compare_version = $out" \
    || record FAIL "[$stand] Data version at activation" "got: '$out'"

  out="$(NODE_PATH="$TOOLS/pw/node_modules" NODE_BROWSER="$OPT_browser" node "$ROOT/wp-dev/e2e/admin-smoke.cjs" "$url" "$stand" "$OUT/screens" menu 2>&1)"
  rc=$?
  echo "$out" >"$OUT/e2e-$stand-menu.json"
  [ "$rc" = 0 ] && record PASS "[$stand] Browser: RVN menu, pages, Settings link ($OPT_browser)" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-menu.json")" \
    || record FAIL "[$stand] Browser: RVN menu" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-menu.json" --failed)"

  "${WP[@]}" plugin deactivate woocommerce >/dev/null 2>&1
  out="$(NODE_PATH="$TOOLS/pw/node_modules" NODE_BROWSER="$OPT_browser" node "$ROOT/wp-dev/e2e/admin-smoke.cjs" "$url" "$stand" "$OUT/screens" requirements 2>&1)"
  rc=$?
  echo "$out" >"$OUT/e2e-$stand-requirements.json"
  "${WP[@]}" plugin activate woocommerce >/dev/null 2>&1
  [ "$rc" = 0 ] && record PASS "[$stand] Without WooCommerce: notice instead of operation" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-requirements.json")" \
    || record FAIL "[$stand] Without WooCommerce" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-requirements.json" --failed)"

  "${WP[@]}" option update rvn_compare_settings '{"delete_data_on_uninstall":false}' --format=json >/dev/null
  "${WP[@]}" plugin uninstall "$SLUG" --deactivate --skip-delete >/dev/null 2>&1
  if "${WP[@]}" option get rvn_compare_version >/dev/null 2>&1; then
    record PASS "[$stand] Uninstall with deletion disabled keeps data" "rvn_compare_version in place"
  else
    record FAIL "[$stand] Uninstall with deletion disabled" "data removed despite opt-out"
  fi

  "${WP[@]}" option delete rvn_compare_settings >/dev/null 2>&1
  "${WP[@]}" plugin uninstall "$SLUG" --skip-delete >/dev/null 2>&1
  if "${WP[@]}" option get rvn_compare_version >/dev/null 2>&1; then
    record FAIL "[$stand] Default uninstall" "rvn_compare_version was not removed"
  else
    record PASS "[$stand] Default uninstall cleans data" "plugin options removed"
  fi

  "${WP[@]}" plugin activate "$SLUG" >/dev/null 2>&1
  check_debug_log "$stand"
}

run_plugin_check() {
  local dir="/tmp/wp-stand-main" rc
  if [ ! -d "$dir" ]; then
    record FAIL "Plugin Check" "stand main is not running"
    return
  fi
  wp --path="$dir" plugin check "$SLUG" --format=table \
    --require="$dir/wp-content/plugins/plugin-check/cli.php" >"$OUT/plugin-check.txt" 2>&1
  rc=$?
  if grep -qE '(^|\s)ERROR(\s|$)' "$OUT/plugin-check.txt"; then
    record FAIL "Plugin Check (official catalog scanner)" "errors present, see $OUT/plugin-check.txt"
  elif grep -qE '(^|\s)WARNING(\s|$)' "$OUT/plugin-check.txt"; then
    record WARN "Plugin Check (official catalog scanner)" "no errors, warnings: $(grep -cE '(^|\s)WARNING(\s|$)' "$OUT/plugin-check.txt")"
  elif [ "$rc" = 0 ]; then
    record PASS "Plugin Check (official catalog scanner)" "no errors, no warnings"
  else
    record FAIL "Plugin Check" "exit code $rc, see $OUT/plugin-check.txt"
  fi
}

run_tests_stand() {
  local stand="$1" dir="/tmp/wp-stand-$1" port="$2"

  if [ ! -d "$dir" ]; then
    record FAIL "[$stand] Autotests" "stand not running: bash wp-dev/stand.sh up --name=$stand"
    return
  fi

  wp --path="$dir" eval-file "$ROOT/wp-dev/tests/core-logic.php" >"$OUT/tests-$stand-core.txt" 2>&1
  [ $? = 0 ] && record PASS "[$stand] Core-logic autotests (limits, categories, merge)" \
    "$(grep -E '^Проверок|^Checks' "$OUT/tests-$stand-core.txt" | tail -1)" \
    || record FAIL "[$stand] Core-logic autotests" "$(grep -E '^  XX|^Проверок|^Checks' "$OUT/tests-$stand-core.txt" | head -5 | tr '\n' ' ')"

  python3 "$ROOT/wp-dev/tests/rest-smoke.py" "http://127.0.0.1:$port" "$dir" >"$OUT/tests-$stand-rest.txt" 2>&1
  [ $? = 0 ] && record PASS "[$stand] REST API (nonce, limits, CRUD, merge)" \
    "$(grep -E 'проверок|checks' "$OUT/tests-$stand-rest.txt" | tail -1)" \
    || record FAIL "[$stand] REST API" "$(grep -E 'XX|проверок|checks' "$OUT/tests-$stand-rest.txt" | head -5 | tr '\n' ' ')"
}

run_frontend_stand() {
  local stand="$1" dir="/tmp/wp-stand-$1" port="$2" rc

  if [ ! -d "$dir" ]; then record FAIL "[$stand] Frontend in browser" "stand not running"; return; fi

  NODE_PATH="$TOOLS/pw/node_modules" NODE_BROWSER="$OPT_browser" node "$ROOT/wp-dev/e2e/frontend-smoke.cjs" \
    "http://127.0.0.1:$port" "$stand" "$OUT/screens" >"$OUT/e2e-$stand-frontend.json" 2>&1
  rc=$?
  [ "$rc" = 0 ] && record PASS "[$stand] Frontend: button, counter, toast, re-click ($OPT_browser)" \
    "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-frontend.json")" \
    || record FAIL "[$stand] Frontend in browser" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-frontend.json" --failed)"
}

run_table_stand() {
  local stand="$1" dir="/tmp/wp-stand-$1" port="$2" rc

  if [ ! -d "$dir" ]; then record FAIL "[$stand] Comparison table" "stand not running"; return; fi

  NODE_PATH="$TOOLS/pw/node_modules" NODE_BROWSER="$OPT_browser" node "$ROOT/wp-dev/e2e/table-smoke.cjs" \
    "http://127.0.0.1:$port" "$stand" "$OUT/screens" >"$OUT/e2e-$stand-table.json" 2>&1
  rc=$?
  [ "$rc" = 0 ] && record PASS "[$stand] Comparison table: page, fields, static shortcode ($OPT_browser)" \
    "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-table.json")" \
    || record FAIL "[$stand] Comparison table" "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$OUT/e2e-$stand-table.json" --failed)"
}

run_cache_regression() {
  local main="main" dir="/tmp/wp-stand-main" result="$OUT/e2e-main-cache.json" rc port
  port="$(stand_port main)"

  if [ ! -d "$dir" ]; then record FAIL "Cache regression" "stand main not running"; return; fi

  NODE_PATH="$TOOLS/pw/node_modules" NODE_BROWSER="$OPT_browser" node "$ROOT/wp-dev/e2e/cache-regression.cjs" \
    "http://127.0.0.1:$port" "$dir" >"$result" 2>&1
  rc=$?

  if [ "$rc" = 0 ]; then
    record PASS "Cache HIT, updates and stale nonce (guest/account, $OPT_browser)" \
      "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$result")"
  else
    record FAIL "Cache HIT, updates and stale nonce ($OPT_browser)" \
      "$(python3 "$ROOT/wp-dev/e2e/summary.py" "$result" --failed)"
  fi
}

# ---------------------------------------------------------------------------
# Level profiles.
# ---------------------------------------------------------------------------
run_level0() {
  run_static
}

# Order mirrors the proven original sequence: the runtime block deactivates and
# uninstalls the plugin, so it runs after the browser scenarios, and Plugin
# Check runs last.
run_level1() {
  run_tests_stand main "$(stand_port main)"
  run_frontend_stand main "$(stand_port main)"
  run_table_stand main "$(stand_port main)"
  run_runtime_stand main "$(stand_port main)"
  run_plugin_check
}

run_level2() {
  run_static
  run_tests_stand main "$(stand_port main)"
  run_tests_stand min "$(stand_port min)"
  run_frontend_stand main "$(stand_port main)"
  run_frontend_stand min "$(stand_port min)"
  run_table_stand main "$(stand_port main)"
  run_table_stand min "$(stand_port min)"
  run_cache_regression
  run_runtime_stand main "$(stand_port main)"
  run_runtime_stand min "$(stand_port min)"
  run_plugin_check
}

MODE="${1:-all}"; shift || true
parse_trailing_opts "$@"

case "$MODE" in
  static) run_static ;;
  level0) run_level0 ;;
  level1) run_level1 ;;
  level2) run_level2 ;;
  runtime) run_runtime_stand main 8080; run_runtime_stand min 8081; run_plugin_check ;; # legacy alias
  stand) run_runtime_stand "$OPT_stand" "$(stand_port "$OPT_stand")" ;;
  tests) run_tests_stand "$OPT_stand" "$(stand_port "$OPT_stand")" ;;
  front) run_frontend_stand "$OPT_stand" "$(stand_port "$OPT_stand")" ;;
  table) run_table_stand "$OPT_stand" "$(stand_port "$OPT_stand")" ;;
  cache) run_cache_regression ;;
  pcp) run_plugin_check ;;
  all) run_level0; run_level1 ;; # level0 + level1-equivalent on main
  *) sed -n '2,32p' "$0"; exit 1 ;;
esac

# Copy the run artifacts into the repo so they survive the sandbox reset.
# Text only: screenshots and other binaries are listed, never copied — the
# working area travels between chats as text and must stay light.
archive_run() {
  if [ -d "$OUT" ]; then
    local stamp dest
    stamp="$(date +%Y%m%d-%H%M%S)"
    dest="$ROOT/docs/test-reports/$VERSION/runs/$MODE-$stamp"
    mkdir -p "$dest"
    tar -C "$OUT" --exclude='*.png' --exclude='*.jpg' --exclude='*.jpeg' --exclude='*.webm' --exclude='*.zip' -cf - . 2>/dev/null \
      | tar -C "$dest" -xf - 2>/dev/null || true
    rmdir "$dest/screens" 2>/dev/null || true
    if [ -n "$(ls -A "$OUT/screens" 2>/dev/null)" ]; then
      (cd "$OUT/screens" && ls -l) >"$dest/screens-list.txt" 2>/dev/null || true
    fi
    printf '# Check run %s (%s)\n\nText artifacts copied from %s. Screenshots, if any, are listed in screens-list.txt and never copied.\n' "$MODE" "$stamp" "$OUT" >"$dest/README.md"
    step "run archived to docs/test-reports/$VERSION/runs/$MODE-$stamp"
  fi
}
archive_run

echo
[ "$FAILED" = 0 ] && echo "RESULT: all checks passed" || echo "RESULT: failures present"
exit "$FAILED"
