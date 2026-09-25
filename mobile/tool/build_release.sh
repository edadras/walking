#!/usr/bin/env bash
# Release build for one store, obfuscated, keeping the symbols needed to read
# crash reports (admin → «خطاهای اپ» → «دانلود Stack»).
#
#   tool/build_release.sh bazaar            # appbundle for bazaar
#   tool/build_release.sh play apk          # apk instead of appbundle
#
# Needs the release dart-defines in the environment (see docs/deployment.md):
#   API_BASE_URL CERT_PINS MAP_TILE_URL INTEGRITY_PROJECT_NUMBER FCM_API_KEY FCM_APP_ID FCM_SENDER_ID FCM_PROJECT_ID
# and PUSHE_TOKEN for bazaar/myket.
set -euo pipefail

store="${1:?store: play | bazaar | myket}"
kind="${2:-appbundle}"
cd "$(dirname "$0")/.."

version="$(grep -E '^version:' pubspec.yaml | awk '{print $2}')"
symbols="build/symbols/${version}/${store}"
mkdir -p "$symbols"

defines=()
for name in API_BASE_URL CERT_PINS MAP_TILE_URL INTEGRITY_PROJECT_NUMBER FCM_API_KEY FCM_APP_ID FCM_SENDER_ID FCM_PROJECT_ID; do
  if [[ -n "${!name:-}" ]]; then defines+=("--dart-define=${name}=${!name}"); fi
done

flutter build "$kind" --release --flavor "$store" --obfuscate --split-debug-info="$symbols" "${defines[@]}"

echo
echo "Symbols: $symbols  — archive them with the release (without them obfuscated stack traces can't be read)."
