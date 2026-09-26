#!/usr/bin/env bash
# =============================================================================
# RVN Compare test stand: real WordPress + WooCommerce on real PHP + MariaDB.
#
# The development sandbox is rebuilt between rounds: system packages, caches
# and the sites themselves are wiped; only project files survive. The whole
# stand is described by this script and is restored with two commands.
#
# Usage:
#   bash wp-dev/stand.sh doctor         Preflight check of the sandbox (fast, safe to run first)
#   bash wp-dev/stand.sh install        System packages and tools (once per round, minutes)
#   bash wp-dev/stand.sh up [options]   Boot a fresh site (~20-30 s)
#       --wp=latest        WordPress version ("latest" = newest release)
#       --wc=latest        WooCommerce version
#       --php=8.3          PHP version used by the built-in web server (8.1 | 8.2 | 8.3 | 8.4)
#       --port=8080        Port of the built-in web server
#       --locale=en_US     Site language (e.g. ru_RU)
#       --name=main        Stand label: distinct labels run parallel sites and databases
#   bash wp-dev/stand.sh down [--name=main|--all]
#   bash wp-dev/stand.sh stop           Stop the web server and MariaDB (end of round)
#   bash wp-dev/stand.sh status         What is installed and what is running
#
# Defaults (see docs/DECISIONS.md, "Test environment"):
#   main = latest WP + latest WooCommerce + PHP 8.3, port 8080
#   min  = oldest supported branch (WP 6.6.x, WC 9.0.x, PHP 8.1, ru_RU), port 8081.
#          Exact minor releases are auto-resolved from wordpress.org at first boot.
#   install --php=8.1,8.3 --browsers=chromium. Set DOCTOR_MIN_RAM_MB
#   env var to override the 1.5 GB memory guidance threshold.
#
# If the plugin folder rvn-compare-products-for-woocommerce/ sits next to this
# script, it is symlinked into the site and activated. If wp-dev/seed.php is
# present, the store is populated with test data.
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="rvn-compare-products-for-woocommerce"
PLUGIN_SRC="$ROOT/$PLUGIN_SLUG"
TOOLS="${RVN_TOOLS_DIR:-/tmp/wp-tools}"
ALL_PHP_VERSIONS=(8.1 8.2 8.3 8.4)
PHP_EXT=(cli mysql xml mbstring curl zip intl gd sqlite3 bcmath)

# Timestamped step printer, reset per invocation.
T0=$(date +%s)
step() { printf '[%3ss] %s\n' "$(($(date +%s) - T0))" "$*"; }
die() { printf 'Error: %s\n' "$*" >&2; exit 1; }

# Option parser: --key=value -> OPT_key.
parse_opts() {
  OPT_wp="latest"; OPT_wc="latest"; OPT_php="8.3"; OPT_port="8080"; OPT_locale="en_US"; OPT_name="main"
  for arg in "$@"; do
    case "$arg" in
      --*=*) key="${arg%%=*}"; key="${key#--}"; printf -v "OPT_${key}" '%s' "${arg#*=}" ;;
      *) die "unknown argument: $arg" ;;
    esac
  done
}

# Parse "a,b,c" list into an array; empty or "all" -> full defaults.
parse_csv() {
  local input="$1" defaults="$2" out="" item
  if [ -z "$input" ] || [ "$input" = "all" ]; then out="$defaults"; else out="$input"; fi
  for item in ${out//,/ }; do printf '%s\n' "$item"; done | awk '!seen[$0]++'
}

# ---------------------------------------------------------------------------
# doctor: environment preflight. Fast, read-only, and safe as a first command.
# ---------------------------------------------------------------------------
cmd_doctor() {
  local fail=0 warn=0 code
  say()  { printf '%s\n' "$*"; }
  chk()  { say "  $1: $2"; }
  bad()  { fail=1; chk "ERROR" "$*"; }
  nt()   { warn=1; chk "note" "$*"; }

  say "RVN stand doctor — sandbox preflight check"

  chk "OS" "$( (. /etc/os-release 2>/dev/null && echo "$PRETTY_NAME") || uname -sr ) ($(uname -m))"

  if sudo -n true 2>/dev/null; then
    chk "sudo" "OK, passwordless"
  else
    bad "no passwordless sudo — apt packages and MariaDB cannot be installed"
    say "Verdict: IMPOSSIBLE. Only standalone PHP (manual downloads) could work."
    exit 1
  fi

  if command -v apt-get >/dev/null; then
    chk "packages" "apt $(apt-get --version 2>/dev/null | head -1 | awk '{print $2}')"
  else
    bad "apt-get missing — base image is not Debian/Ubuntu"
  fi

  local codename
  codename="$( (. /etc/os-release 2>/dev/null && echo "${VERSION_CODENAME:-}") || true )"
  if [ "$codename" = "bookworm" ]; then
    chk "distro" "bookworm — sury repository works as configured"
  else
    nt "codename '${codename:-unknown}' instead of bookworm — sury needs the codename-adjusted line (see docs/HANDOFF.md)"
  fi

  code="$(curl -sS -m 8 -o /dev/null -w '%{http_code}' https://packages.sury.org/php/apt.gpg 2>/dev/null || echo 000)"
  [ "$code" = "200" ] && chk "network: sury" "OK (200)" || bad "packages.sury.org reachable? got HTTP $code"
  code="$(curl -sS -m 8 -o /dev/null -w '%{http_code}' https://wordpress.org/ 2>/dev/null || echo 000)"
  [ "$code" = "200" ] && chk "network: wordpress.org" "OK (200)" || bad "wordpress.org reachable? got HTTP $code"
  code="$(curl -sS -m 8 -o /dev/null -w '%{http_code}' https://api.wordpress.org/core/stable-check/1.0/ 2>/dev/null || echo 000)"
  [ "$code" = "200" ] && chk "network: WP releases API" "OK (200)" || nt "WP releases API answered $code — branch auto-resolution may fail, pin numbers explicitly"

  local mem_kb mem_mb disk_mb
  mem_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  mem_mb=$(( mem_kb / 1024 ))
  disk_mb="$(df -Pm / 2>/dev/null | awk 'NR==2 {print $4}' || echo 0)"
  chk "RAM" "${mem_mb} MB"
  chk "disk" "${disk_mb} MB free on /"
  local min_ram="${DOCTOR_MIN_RAM_MB:-1536}"
  [ "$mem_mb" -ge "$min_ram" ] || nt "RAM below ${min_ram} MB — install one PHP version at a time on dev level; no parallel heavy steps"
  [ "$disk_mb" -ge 5000 ] || nt "free disk below 5 GB — keep the toolset lean"

  chk "PID 1" "$(ps -p 1 -o comm= 2>/dev/null || echo unknown)"

  say ""
  if [ "$fail" = 0 ]; then
    if [ "$warn" = 0 ]; then
      say "Verdict: FULL stand possible (all install profiles)."
    else
      say "Verdict: possible with notes above. Dev level: bash wp-dev/stand.sh install --php=8.1 --browsers=none"
    fi
    say "Next:"
    say "  level 0 (static):  setsid nohup bash wp-dev/stand.sh install --php=8.1,8.3 --browsers=none </dev/null >/tmp/stand-install.log 2>&1 &"
    say "  level 1 (build):   same + --browsers=chromium, then 'up' detached"
    exit 0
  fi

  say "Verdict: stand IMPOSSIBLE in this sandbox (see ERROR lines above)."
  exit 1
}

# ---------------------------------------------------------------------------
# install: system packages, WP-CLI, Composer toolset, Playwright browsers.
# ---------------------------------------------------------------------------
cmd_install() {
  sudo -n true 2>/dev/null || die "needs passwordless sudo"
  local OPT_php="8.1,8.3" OPT_browsers="chromium"
  for arg in "$@"; do
    case "$arg" in
      --php=*) OPT_php="${arg#*=}" ;;
      --browsers=*) OPT_browsers="${arg#*=}" ;;
      *) die "unknown argument: $arg" ;;
    esac
  done

  mapfile -t php_list < <( { parse_csv "$OPT_php" "${ALL_PHP_VERSIONS[*]}"; } )
  for v in "${php_list[@]}"; do
    case " ${ALL_PHP_VERSIONS[*]} " in *" $v "*) ;; *) die "unsupported PHP version: $v" ;; esac
  done

  local browsers=()
  [ "$OPT_browsers" = "none" ] || { mapfile -t browsers < <( { parse_csv "$OPT_browsers" "chromium"; } ); }
  for b in "${browsers[@]:-}"; do
    case " chromium webkit firefox " in *" $b "*) ;; *) [ -z "$b" ] || die "unknown browser: $b" ;; esac
  done

  local pkgs=(mariadb-server zip unzip subversion)
  for v in "${php_list[@]}"; do for e in "${PHP_EXT[@]}"; do pkgs+=("php$v-$e"); done; done

  if [ ! -f /etc/apt/sources.list.d/sury-php.list ]; then
    sudo -n curl -sSLo /usr/share/keyrings/sury-php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ bookworm main" \
      | sudo -n tee /etc/apt/sources.list.d/sury-php.list >/dev/null
  fi
  sudo -n apt-get update -qq
  sudo -n DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "${pkgs[@]}" >/tmp/stand-apt.log 2>&1 \
    || { tail -20 /tmp/stand-apt.log; die "apt-get install"; }
  # Default `php` points at the minimum supported plugin version.
  sudo -n update-alternatives --set php "/usr/bin/php8.1" >/dev/null 2>&1 \
    || sudo -n update-alternatives --set php "/usr/bin/php${php_list[0]}" >/dev/null
  step "PHP ${php_list[*]} + MariaDB installed"

  if ! command -v wp >/dev/null; then
    sudo -n curl -sSLo /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    sudo -n chmod +x /usr/local/bin/wp
  fi
  if ! command -v composer >/dev/null; then
    sudo -n curl -sSLo /usr/local/bin/composer https://getcomposer.org/download/latest-stable/composer.phar
    sudo -n chmod +x /usr/local/bin/composer
  fi
  step "WP-CLI $(wp --version --allow-root | awk '{print $2}'), Composer"

  export COMPOSER_HOME="$TOOLS/composer"
  composer global config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true >/dev/null
  composer global require --no-interaction -q \
    wp-coding-standards/wpcs:"^3.1" phpcompatibility/phpcompatibility-wp:"*" \
    dealerdirect/phpcodesniffer-composer-installer phpstan/phpstan \
    szepeviktor/phpstan-wordpress php-stubs/woocommerce-stubs >/tmp/stand-composer.log 2>&1 \
    || { tail -20 /tmp/stand-composer.log; die "composer"; }
  step "PHPCS + WPCS + PHPCompatibility, PHPStan + WP/WC stubs"

  if [ "${#browsers[@]}" -gt 0 ]; then
    mkdir -p "$TOOLS/pw"
    (cd "$TOOLS/pw" && [ -f package.json ] || (cd "$TOOLS/pw" && npm init -y >/dev/null))
    (cd "$TOOLS/pw" && npm i -s playwright@1 @axe-core/playwright >/dev/null 2>&1)
    sudo -n env PATH="$PATH" bash -c "cd '$TOOLS/pw' && npx playwright install-deps ${browsers[*]}" >/tmp/stand-pw.log 2>&1 \
      || { tail -20 /tmp/stand-pw.log; die "playwright install-deps"; }
    (cd "$TOOLS/pw" && npx playwright install "${browsers[@]}" >>/tmp/stand-pw.log 2>&1)
    step "Playwright $(cd "$TOOLS/pw" && npx playwright --version | awk '{print $2}'): ${browsers[*]}"
  else
    step "Playwright browsers skipped (--browsers=none)"
  fi

  step "install finished"
}

# ---------------------------------------------------------------------------
# up: boot a clean site with chosen WP / WooCommerce / PHP versions.
# ---------------------------------------------------------------------------

# Newest patch release inside a WordPress branch (e.g. 6.6 -> 6.6.9), taken from
# the official list of all releases (api.wordpress.org stable-check).
latest_in_branch() {
  local branch="$1" out
  out="$(curl -sS -m 20 https://api.wordpress.org/core/stable-check/1.0/ 2>/dev/null | python3 -c '
import json, sys
branch = sys.argv[1]
try:
    data = json.load(sys.stdin)
except Exception:
    sys.exit(0)
vers = [v for v in data if (v == branch or v.startswith(branch + ".")) and all(p.isdigit() for p in v.split("."))]
print(max(vers, key=lambda s: [int(x) for x in s.split(".")]) if vers else "")
' "$branch")"
  [ -n "$out" ] || die "could not resolve a WordPress version for branch $branch (api.wordpress.org)"
  printf '%s' "$out"
}

# Newest patch release inside a WooCommerce major.minor branch (e.g. 9.0 -> 9.0.4),
# taken from the plugin directory API. The whole major.minor prefix is matched,
# never the major alone. -g: the URL contains literal brackets.
latest_wc_in_branch() {
  local branch="$1" out
  out="$(curl -gsS -m 30 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce&request[fields][versions]=1' 2>/dev/null | python3 -c '
import json, sys
branch = sys.argv[1]
try:
    data = json.load(sys.stdin)
except Exception:
    sys.exit(0)
vers = [v for v in (data.get("versions") or {}) if (v == branch or v.startswith(branch + ".")) and all(p.isdigit() for p in v.split("."))]
print(max(vers, key=lambda s: [int(x) for x in s.split(".")]) if vers else "")
' "$branch")"
  [ -n "$out" ] || die "could not resolve a WooCommerce version for branch $branch (api.wordpress.org)"
  printf '%s' "$out"
}

# MariaDB without assuming init system (systemd service or mysqld_safe fallback).
start_db() {
  if sudo -n mariadb -e 'SELECT 1' >/dev/null 2>&1; then return; fi
  if [ "$(ps -p 1 -o comm= 2>/dev/null)" = "systemd" ]; then
    sudo -n service mariadb start >/dev/null 2>&1 \
      || sudo -n service mysql start >/dev/null 2>&1 || true
  fi
  if ! sudo -n mariadb -e 'SELECT 1' >/dev/null 2>&1; then
    sudo -n mkdir -p /run/mysqld && sudo -n chown mysql:mysql /run/mysqld
    (sudo -n mysqld_safe --user=mysql >/tmp/stand-mysqld.log 2>&1 &)
  fi
  for _ in $(seq 1 30); do sudo -n mariadb -e 'SELECT 1' >/dev/null 2>&1 && return; sleep 1; done
  die "MariaDB did not start, see /tmp/stand-mysqld.log"
}

stop_server() {
  local pidfile="/tmp/wp-stand-$1.pid"
  if [ -f "$pidfile" ]; then kill "$(cat "$pidfile")" 2>/dev/null || true; rm -f "$pidfile"; fi
}

cmd_up() {
  parse_opts "$@"
  command -v wp >/dev/null || die "run first: bash wp-dev/stand.sh install"
  command -v "php$OPT_php" >/dev/null || die "PHP $OPT_php is not installed"

  start_db
  if ! sudo -n mariadb -e 'SHOW TABLES IN mysql' >/dev/null 2>&1; then
    die "MariaDB storage looks corrupted (system tables unreadable) — sandbox reset required"
  fi

  # Stand label presets: name/resolution applied only to unset defaults.
  if [ "$OPT_name" = "min" ]; then
    [ "$OPT_wc" = "latest" ] && OPT_wc="9.0"
    [ "$OPT_php" = "8.3" ] && OPT_php="8.1"
    [ "$OPT_port" = "8080" ] && OPT_port="8081"
    [ "$OPT_locale" = "en_US" ] && OPT_locale="ru_RU"
    [ "$OPT_wp" = "latest" ] && OPT_wp="6.6"
  fi
  # "latest" and exact x.y.z pass through; a bare branch (6.6, 9.0) resolves to
  # its newest patch release.
  case "$OPT_wp" in latest|*.*.*) : ;; *) OPT_wp="$(latest_in_branch "$OPT_wp")" ;; esac
  case "$OPT_wc" in latest|*.*.*) : ;; *) OPT_wc="$(latest_wc_in_branch "$OPT_wc")" ;; esac

  local dir="/tmp/wp-stand-$OPT_name" db="wp_stand_${OPT_name//-/_}" url="http://127.0.0.1:$OPT_port"
  local WP=(wp --path="$dir" --quiet)

  sudo -n mariadb -e "DROP DATABASE IF EXISTS \`$db\`; CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'wp'@'127.0.0.1' IDENTIFIED BY 'wp'; GRANT ALL ON \`$db\`.* TO 'wp'@'127.0.0.1'; FLUSH PRIVILEGES;"
  step "MariaDB: database $db recreated"

  stop_server "$OPT_name"
  rm -rf "$dir" && mkdir -p "$dir"
  local wpver=(); [ "$OPT_wp" != "latest" ] && wpver=(--version="$OPT_wp")
  "${WP[@]}" core download "${wpver[@]}" >/dev/null
  "${WP[@]}" config create --dbname="$db" --dbuser=wp --dbpass=wp --dbhost=127.0.0.1 --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
PHP
  "${WP[@]}" core install --url="$url" --title="RVN Stand" --admin_user=admin --admin_password=admin \
    --admin_email=admin@example.com --skip-email
  # Pretty permalinks: /wp-json/ and product links behave like in a store.
  "${WP[@]}" rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true
  step "WordPress $("${WP[@]}" core version) installed (WP_DEBUG on, log: $dir/wp-content/debug.log)"

  local wcver=(); [ "$OPT_wc" != "latest" ] && wcver=(--version="$OPT_wc")
  "${WP[@]}" plugin install woocommerce "${wcver[@]}" --activate >/dev/null 2>&1 || die "WooCommerce $OPT_wc"
  "${WP[@]}" option update woocommerce_coming_soon no >/dev/null 2>&1 || true
  # WooCommerce onboarding wizard would otherwise hijack admin navigations.
  "${WP[@]}" transient delete _wc_activation_redirect >/dev/null 2>&1 || true
  "${WP[@]}" option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1 || true
  "${WP[@]}" option update woocommerce_task_list_hidden yes >/dev/null 2>&1 || true
  "${WP[@]}" option update woocommerce_show_marketplace_suggestions no >/dev/null 2>&1 || true
  step "WooCommerce $("${WP[@]}" plugin get woocommerce --field=version) activated"

  if [ "$OPT_locale" != "en_US" ]; then
    "${WP[@]}" language core install "$OPT_locale" --activate >/dev/null 2>&1 || true
    "${WP[@]}" language plugin install woocommerce "$OPT_locale" >/dev/null 2>&1 || true
    step "site language: $OPT_locale"
  fi

  if [ -d "$PLUGIN_SRC" ]; then
    ln -s "$PLUGIN_SRC" "$dir/wp-content/plugins/$PLUGIN_SLUG"
    "${WP[@]}" plugin activate "$PLUGIN_SLUG" && step "plugin $PLUGIN_SLUG symlinked and activated"
  else
    step "folder $PLUGIN_SLUG/ not present yet — stand boots without the plugin"
  fi

  if [ -f "$ROOT/wp-dev/seed.php" ]; then
    "${WP[@]}" eval-file "$ROOT/wp-dev/seed.php" && step "test data seeded"
  fi

  if "${WP[@]}" plugin install plugin-check --activate >/dev/null 2>&1; then
    step "Plugin Check $("${WP[@]}" plugin get plugin-check --field=version 2>/dev/null || echo '?') installed"
  else
    step "Plugin Check could not be installed"
  fi

  : >"$dir/wp-content/debug.log"
  printf 'PHP_BIN=%s\nPORT=%s\nWP=%s\nWC=%s\n' "$OPT_php" "$OPT_port" "$OPT_wp" "$OPT_wc" >"/tmp/wp-stand-$OPT_name.env"

  nohup "php$OPT_php" -S "127.0.0.1:$OPT_port" -t "$dir" </dev/null >"/tmp/wp-stand-$OPT_name.log" 2>&1 &
  echo $! >"/tmp/wp-stand-$OPT_name.pid"
  sleep 1
  local code; code=$(curl -s -o /dev/null -w '%{http_code}' "$url/")
  [ "$code" = "200" ] || die "site answered HTTP $code, see /tmp/wp-stand-$OPT_name.log"
  step "ready: $url (PHP $OPT_php, admin / admin)"
}

# ---------------------------------------------------------------------------
cmd_down() {
  local target=""
  for arg in "$@"; do
    case "$arg" in --name=*) target="${arg#*=}" ;; *) die "unknown argument: $arg (use --name=main or --name=all)" ;; esac
  done
  if [ "$target" = "all" ]; then
    for pidfile in /tmp/wp-stand-*.pid; do
      [ -e "$pidfile" ] || continue
      local name="${pidfile#/tmp/wp-stand-}"; name="${name%.pid}"
      stop_server "$name"
      step "web server for stand $name stopped"
    done
    return
  fi
  target="${target:-main}"
  stop_server "$target"
  step "web server for stand $target stopped"
}

cmd_stop() {
  for pidfile in /tmp/wp-stand-*.pid; do
    [ -e "$pidfile" ] || continue
    local name="${pidfile#/tmp/wp-stand-}"; name="${name%.pid}"
    stop_server "$name"
  done
  local srv=""
  srv="$(ps -p 1 -o comm= 2>/dev/null || echo unknown)"
  if [ "$srv" = "systemd" ]; then
    sudo -n service mariadb stop >/dev/null 2>&1 || sudo -n service mysql stop >/dev/null 2>&1 || true
  else
    sudo -n mysqladmin shutdown >/dev/null 2>&1 \
      || sudo -n pkill -TERM mysqld >/dev/null 2>&1 || true
  fi
  sleep 1
  # Exact-name match (-x) is deliberate: a pattern like 'php -S' also matches
  # the very command line that runs this check, which would make a clean state
  # impossible to prove.
  local leftovers=""
  leftovers="$(pgrep -x mysqld; pgrep -x mariadbd; pgrep -x php8.1; pgrep -x php8.2; pgrep -x php8.3; pgrep -x php8.4; true)"
  if [ -n "$leftovers" ]; then
    printf '%s\n' "warning: processes remain running:" >&2
    printf '%s\n' "$leftovers" >&2
    step "stopped, but PIDs remain above — review and kill manually"
  else
    step "all web servers stopped, MariaDB stopped, no leftover processes"
  fi
}

cmd_status() {
  for v in "${ALL_PHP_VERSIONS[@]}"; do printf 'php%s: %s\n' "$v" "$(command -v "php$v" >/dev/null && "php$v" -r 'echo PHP_VERSION;' || echo 'not installed')"; done
  printf 'MariaDB: %s\n' "$(sudo -n mariadb -N -e 'SELECT VERSION()' 2>/dev/null || echo 'not running')"
  printf 'WP-CLI: %s\n' "$(command -v wp >/dev/null && wp --version --allow-root || echo 'missing')"
  printf 'PHPCS: %s\n' "$([ -x "$TOOLS/composer/vendor/bin/phpcs" ] && "$TOOLS/composer/vendor/bin/phpcs" --version | head -1 || echo 'missing')"
  printf 'POT entries: %s\n' "$(grep -c '^msgid "' "$ROOT/$PLUGIN_SLUG/languages/$PLUGIN_SLUG.pot" 2>/dev/null || echo 0)"
  for pidfile in /tmp/wp-stand-*.pid; do
    [ -e "$pidfile" ] || continue
    local name="${pidfile#/tmp/wp-stand-}"; name="${name%.pid}"
    printf 'stand %s: %s\n' "$name" "$(kill -0 "$(cat "$pidfile")" 2>/dev/null && echo 'running' || echo 'stopped')"
  done
}

case "${1:-}" in
  doctor) shift; cmd_doctor "$@" ;;
  install) shift; cmd_install "$@" ;;
  up) shift; cmd_up "$@" ;;
  down) shift; cmd_down "$@" ;;
  stop) shift; cmd_stop "$@" ;;
  status) shift; cmd_status "$@" ;;
  *) sed -n '2,47p' "$0"; exit 1 ;;
esac
