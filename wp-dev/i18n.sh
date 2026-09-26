#!/usr/bin/env bash
# =============================================================================
# Переводы плагина: POT из исходников, русский PO из словаря, затем MO и PHP.
#
#   bash wp-dev/i18n.sh
#
# Словарь — wp-dev/i18n/ru_RU.json («английская строка» → «перевод»).
# Если в исходниках появилась строка без перевода или make-pot нашёл ошибку
# оформления строки (например, нет комментария для переводчиков), скрипт
# завершается с ошибкой.
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="rvn-compare-products-for-woocommerce"
PLUGIN="$ROOT/$SLUG"
LANG_DIR="$PLUGIN/languages"
POT="$LANG_DIR/$SLUG.pot"

command -v wp >/dev/null || { echo "Ошибка: нужен WP-CLI (bash wp-dev/stand.sh install)" >&2; exit 1; }
mkdir -p "$LANG_DIR"

wp i18n make-pot "$PLUGIN" "$POT" \
  --slug="$SLUG" --domain="$SLUG" \
  --package-name="RVN Compare Products for WooCommerce" \
  --exclude="languages" >/tmp/rvn-i18n-pot.log 2>&1 || { cat /tmp/rvn-i18n-pot.log; exit 1; }

if grep -q "Warning:" /tmp/rvn-i18n-pot.log; then
  cat /tmp/rvn-i18n-pot.log
  echo "Ошибка: make-pot нашёл проблемы в переводимых строках" >&2
  exit 1
fi

python3 "$ROOT/wp-dev/i18n/build-po.py" "$POT" "$ROOT/wp-dev/i18n/ru_RU.json" "$LANG_DIR/$SLUG-ru_RU.po"

rm -f "$LANG_DIR"/*.mo "$LANG_DIR"/*.l10n.php
wp i18n make-mo "$LANG_DIR" >/dev/null
wp i18n make-php "$LANG_DIR" >/dev/null

echo "Строк в шаблоне: $(grep -c '^msgid "' "$POT") (включая заголовок); файлы:"
ls -1 "$LANG_DIR"
