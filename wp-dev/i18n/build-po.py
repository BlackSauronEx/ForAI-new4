#!/usr/bin/env python3
"""Собирает PO-файл перевода из POT-шаблона и словаря JSON.

Использование: build-po.py шаблон.pot словарь.json результат.po

Словарь: {"английская строка": "перевод"}. Для строк с контекстом ключ —
"контекст\\u0004строка". Для множественного числа значение — список из трёх форм.
Скрипт завершается с кодом 1, если для какой-то строки нет перевода.
"""

import json
import re
import sys

PLURAL_FORMS = (
    "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : "
    "n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"
)


def unescape(value):
    """Раскрывает экранирование строки PO."""
    return (
        value.replace('\\"', '"')
        .replace("\\n", "\n")
        .replace("\\t", "\t")
        .replace("\\\\", "\\")
    )


def escape(value):
    """Экранирует строку для записи в PO."""
    return (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
    )


def parse_pot(path):
    """Разбирает POT-файл в список записей."""
    entries, current, field = [], {}, None
    with open(path, encoding="utf-8") as handle:
        for raw in handle:
            line = raw.rstrip("\n")
            if not line.strip():
                if current:
                    entries.append(current)
                current, field = {}, None
                continue
            if line.startswith("#"):
                current.setdefault("comments", []).append(line)
                continue
            match = re.match(r'^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?) "(.*)"$', line)
            if match:
                field = match.group(1)
                current[field] = unescape(match.group(2))
                continue
            match = re.match(r'^"(.*)"$', line)
            if match and field:
                current[field] += unescape(match.group(1))
    if current:
        entries.append(current)
    return entries


def main():
    pot_path, dict_path, po_path = sys.argv[1:4]
    entries = parse_pot(pot_path)
    with open(dict_path, encoding="utf-8") as handle:
        translations = json.load(handle)

    header = next((e for e in entries if e.get("msgid") == "" and "msgctxt" not in e), {})
    project = re.search(r"Project-Id-Version: ([^\n]*)", header.get("msgstr", ""))
    project = project.group(1) if project else "RVN Compare Products for WooCommerce"

    lines = [
        "# Russian translation for RVN Compare Products for WooCommerce.",
        "# This file is distributed under the same license as the plugin.",
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: {escape(project)}\\n"',
        '"Language: ru_RU\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        f'"Plural-Forms: {PLURAL_FORMS}\\n"',
        '"X-Domain: rvn-compare-products-for-woocommerce\\n"',
        "",
    ]

    missing, used = [], set()
    for entry in entries:
        msgid = entry.get("msgid")
        if msgid is None or (msgid == "" and "msgctxt" not in entry):
            continue
        key = (entry["msgctxt"] + "\u0004" + msgid) if "msgctxt" in entry else msgid
        value = translations.get(key)
        used.add(key)
        for comment in entry.get("comments", []):
            if comment.startswith(("#.", "#:", "#,")):
                lines.append(comment)
        if "msgctxt" in entry:
            lines.append(f'msgctxt "{escape(entry["msgctxt"])}"')
        lines.append(f'msgid "{escape(msgid)}"')
        if "msgid_plural" in entry:
            lines.append(f'msgid_plural "{escape(entry["msgid_plural"])}"')
            forms = value if isinstance(value, list) and len(value) == 3 else ["", "", ""]
            if not all(forms):
                missing.append(msgid)
            for index, form in enumerate(forms):
                lines.append(f'msgstr[{index}] "{escape(form)}"')
        else:
            if not isinstance(value, str) or value == "":
                missing.append(msgid)
                value = ""
            lines.append(f'msgstr "{escape(value)}"')
        lines.append("")

    with open(po_path, "w", encoding="utf-8") as handle:
        handle.write("\n".join(lines))

    unused = sorted(set(translations) - used)
    if unused:
        print("Строки словаря, которых больше нет в коде (можно удалить):")
        for item in unused:
            print("  -", item)
    if missing:
        print("Нет перевода для строк:")
        for item in missing:
            print("  -", item)
        sys.exit(1)
    print(f"PO собран: {po_path} ({len(used)} строк переведено)")


if __name__ == "__main__":
    main()
