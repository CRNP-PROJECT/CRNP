#!/usr/bin/env bash
cd "$(dirname "$0")"
if ! command -v php &>/dev/null; then
  for p in "../../php/php.exe" /c/xampp/php/php.exe C:/xampp/php/php.exe; do
    [ -f "$p" ] && export PATH="$PATH:$(dirname "$p")" && break
  done
fi
php -S localhost:8000
