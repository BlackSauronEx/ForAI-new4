#!/usr/bin/env python3
"""REST-смоук для rvn-compare/v1: запуск по одному стенду.

Использование: rest-smoke.py <url> <путь-к-WordPress>
Ключ (nonce) создаётся для гостя через WP-CLI, поэтому cookie не нужны.
Код выхода 0 — все проверки пройдены.
"""

import json
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

BASE, WP_PATH = sys.argv[1], sys.argv[2]
# Форма ?rest_route= работает при любых постоянных ссылках; /wp-json/ зависит
# от их настроек и на «простых» ссылках уходит в редирект.
API = BASE + "/?rest_route=/rvn-compare/v1"
checks = []


def wp(*args):
    """Выполняет WP-CLI-команду на стенде и возвращает вывод без перевода строки."""
    out = subprocess.run(
        ["wp", f"--path={WP_PATH}", *args],
        capture_output=True,
        text=True,
        check=False,
    )
    return out.stdout.strip()


def call(method, path, payload=None, nonce=None):
    """Выполняет запрос к API и возвращает код, тело-словарь."""
    url = API + path
    if method == "GET" and payload:
        # Параметры идут вторым аргументом запроса: первый уже занят rest_route.
        url += "&" + urllib.parse.urlencode(payload, doseq=True)
    request = urllib.request.Request(url, method=method)
    request.add_header("Content-Type", "application/json")
    if nonce:
        request.add_header("X-WP-Nonce", nonce)
    data = json.dumps(payload).encode() if payload is not None else None
    try:
        with urllib.request.urlopen(request, data=data) as response:
            return response.status, json.loads(response.read().decode())
    except urllib.error.HTTPError as error:
        return error.code, {}


def check(ok, label, detail=""):
    """Записывает результат проверки."""
    checks.append(bool(ok))
    print(("  ok " if ok else "  XX ") + label + ((" — " + detail) if detail else ""))


nonce = wp("eval", 'echo wp_create_nonce("wp_rest");')
def product_ids():
    """Возвращает ID опубликованных товаров, повторяя выборку при пустом ответе.

    Первый запрос к базе сразу после других операций иногда отдаёт пустой
    список: ждём и пробуем снова, чтобы тест не считался проваленным зря.
    """
    for _ in range(3):
        # Из вывода берём только числа: WP-CLI может печатать предупреждения PHP рядом с данными.
        found = [int(x) for x in wp("post", "list", "--post_type=product", "--post_status=publish", "--field=ID").split() if x.isdigit()]

        if len(found) >= 2:
            return found[:2]

        time.sleep(2)

    return []


ids = product_ids()
if len(ids) < 2:
    print("  XX на стенде нужно минимум два опубликованных товара")
    sys.exit(1)
first, second = ids[0], ids[1]

status, body = call("GET", "/list")
check(status == 200 and body.get("status") == "list", "GET /list отвечает", f"HTTP {status}")

status, body = call("GET", "/list", {"ids": f"{first},{first},{second}"})
check(status == 200 and body.get("count") == 2, "список гостя нормализуется (дубли убраны)", f"count={body.get('count')}")

status, body = call("GET", "/table", {"ids": f"{first},{second}"})
tabs = body.get("tabs", []) if isinstance(body, dict) else []
check(status == 200 and body.get("count") == 2 and len(tabs) > 0, "GET /table строит таблицу по списку гостя", f"HTTP {status}, tabs={len(tabs)}")

status, body = call("GET", "/table", {"products": f"{first},{second}"})
static_tabs = body.get("tabs", []) if isinstance(body, dict) else []
check(status == 200 and body.get("count") == 2 and len(static_tabs) > 0, "GET /table принимает фиксированные products", f"HTTP {status}, tabs={len(static_tabs)}")

status, body = call("POST", "/items", {"product_id": first})
check(status == 403, "изменение без ключа (nonce) запрещено", f"HTTP {status}")

status, body = call("POST", "/items", {"product_id": first}, nonce)
check(status == 200 and body.get("status") == "added", "добавление товара", f"status={body.get('status')}")

status, body = call("POST", "/items", {"product_id": first, "ids": [first]}, nonce)
check(status == 200 and body.get("status") == "already", "повторное добавление — already", f"status={body.get('status')}")

status, body = call("POST", "/items", {"product_id": 99999999}, nonce)
check(status == 200 and body.get("status") == "not_found", "несуществующий товар отклонён", f"status={body.get('status')}")

status, body = call("POST", "/items", {"product_id": 0}, nonce)
check(status == 400, "некорректный ID товара отклонён", f"HTTP {status}")

status, body = call("POST", "/items/remove", {"product_id": first, "ids": [first, second]}, nonce)
check(status == 200 and body.get("status") == "removed" and body.get("count") == 1, "удаление товара", f"count={body.get('count')}")

status, body = call("POST", "/clear", {"ids": [first]}, nonce)
check(status == 200 and body.get("status") == "cleared" and body.get("count") == 0, "очистка списка", f"status={body.get('status')}")

status, body = call("POST", "/merge", {"ids": [first]}, nonce)
check(status == 200 and body.get("status") == "not_user", "слияние для гостя отклоняется", f"status={body.get('status')}")

print(f"  проверок: {len(checks)}, провалено: {checks.count(False)}")
sys.exit(0 if all(checks) else 1)
