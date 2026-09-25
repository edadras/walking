#!/usr/bin/env bash
# Turns an obfuscated stack trace (downloaded from admin → «خطاهای اپ») into
# readable frames using the symbols archived by tool/build_release.sh.
#
#   tool/symbolize.sh crash.txt 1.2.0 bazaar [arm64|arm|x64]
set -euo pipefail

trace="${1:?stack trace file}"
version="${2:?app version}"
store="${3:?store}"
arch="${4:-arm64}"
cd "$(dirname "$0")/.."

symbols="build/symbols/${version}/${store}/app.android-${arch}.symbols"
[[ -f "$symbols" ]] || { echo "No symbols at $symbols" >&2; exit 1; }
flutter symbolize -i "$trace" -d "$symbols"
