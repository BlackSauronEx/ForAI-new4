#!/usr/bin/env python3
"""Кратко пересказывает JSON-результат браузерной проверки для сводки check.sh.

Использование: summary.py результат.json [--failed]
"""

import json
import sys


def main():
    path = sys.argv[1]
    only_failed = "--failed" in sys.argv
    try:
        with open(path, encoding="utf-8") as handle:
            text = handle.read().strip().splitlines()
        data = json.loads(text[-1]) if text else {"checks": []}
    except (OSError, ValueError, IndexError):
        print("нет результата")
        return
    checks = data.get("checks", [])
    passed = sum(1 for item in checks if item.get("ok"))
    if only_failed:
        failed = [f"{item['name']} ({item.get('detail', '')})" for item in checks if not item.get("ok")]
        print("; ".join(failed) or "нет данных")
    else:
        print(f"{passed}/{len(checks)} проверок")


if __name__ == "__main__":
    main()
