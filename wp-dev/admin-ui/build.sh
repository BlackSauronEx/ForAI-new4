#!/usr/bin/env bash
# =============================================================================
# Сборка интерфейса настроек 0.5.0 (React на компонентах WordPress).
#
#   bash wp-dev/admin-ui/build.sh
#
# Исходники живут в wp-dev/admin-ui/src (вариант Б: в поставку не входят).
# Сборка идёт во временной папке /tmp/wp-admin-ui-build: node_modules никогда
# не попадает в рабочую область. Готовые файлы кладутся в плагин:
#   assets/admin/settings.js        — собранный бандл (React/ядро извне, не в bundle)
#   assets/admin/settings.asset.php — зависимости и версия (генерирует wp-scripts)
#
# Пакеты @wordpress/* берутся из ядра WordPress (wp-element, wp-components),
# поэтому бандл весит килобайты. Перед публикацией в каталог исходники
# доступны здесь же, в wp-dev/admin-ui/src.
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="$ROOT/wp-dev/admin-ui"
WORK="${RVN_ADMIN_BUILD_DIR:-/tmp/wp-admin-ui-build}"
OUT="$ROOT/rvn-compare-products-for-woocommerce/assets/admin"

command -v node >/dev/null || { echo 'Нужно: node' >&2; exit 1; }
command -v npm >/dev/null || { echo 'Нужно: npm' >&2; exit 1; }

rm -rf "$WORK"
mkdir -p "$WORK/src"
cp "$SRC/package.json" "$WORK/package.json"
cp "$SRC/src/index.js" "$WORK/src/index.js"

cd "$WORK"
npm install --no-audit --no-fund
echo "wp-scripts: $(node -p "require('./node_modules/@wordpress/scripts/package.json').version")"
npx wp-scripts build

mkdir -p "$OUT"
cp "$WORK/build/index.js" "$OUT/settings.js"

# В сгенерированный asset.php добавляем защиту от прямого вызова и шапку.
python3 - "$WORK/build/index.asset.php" "$OUT/settings.asset.php" <<'PY'
import re
import sys

src = open( sys.argv[1], encoding='utf-8' ).read().strip()
body = re.sub( r'^<\?php\s*', '', src, count=1 )
out = (
    "<?php\n"
    "/**\n"
    " * Зависимости и версия бандла настроек (сгенерировано wp-scripts).\n"
    " *\n"
    " * Не править вручную: собирается командой bash wp-dev/admin-ui/build.sh\n"
    " * из wp-dev/admin-ui/src/index.js.\n"
    " *\n"
    " * @package RVN_Compare\n"
    " */\n\n"
    "defined( 'ABSPATH' ) || exit;\n\n" + body + "\n"
)
open( sys.argv[2], 'w', encoding='utf-8' ).write( out )
PY

echo 'Готово:'
ls -l "$OUT/settings.js" "$OUT/settings.asset.php"
du -sh "$OUT/settings.js"
