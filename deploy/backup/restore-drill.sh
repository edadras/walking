#!/usr/bin/env bash
# Proves the latest backup can actually be restored: decrypts it, checks the checksums,
# loads it into a throw-away database, runs sanity checks and the ledger reconciliation
# against the restored copy, then drops it. Exit code != 0 means the backup is not usable.
#
#   Same DB_* / BACKUP_DIR / BACKUP_PASSPHRASE_FILE as backup.sh; the DB user needs CREATE/DROP.
#   APP_DIR        backend checkout used to run `artisan ledger:reconcile` (default: ../../backend)
#   DRILL_DATABASE scratch database name (default: <DB_DATABASE>_restore_drill)
#   OPS_ALERT_WEBHOOK optional: result is posted as {"text": ...}
set -euo pipefail

: "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}" "${BACKUP_PASSPHRASE_FILE:?}"
DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="${DB_PORT:-3306}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/gamyar}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/../../backend" && pwd)}"
DRILL_DB="${DRILL_DATABASE:-${DB_DATABASE}_restore_drill}"
export MYSQL_PWD="$DB_PASSWORD"
sql() { mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$@"; }

notify() {
  echo "$1"
  if [ -n "${OPS_ALERT_WEBHOOK:-}" ]; then
    curl -fsS -m 10 -H 'Content-Type: application/json' -d "$(printf '{"text":%s}' "$(printf '%s' "$1" | python3 -c 'import json,sys;print(json.dumps(sys.stdin.read()))')")" "$OPS_ALERT_WEBHOOK" >/dev/null || true
  fi
}
fail() { notify "❌ تمرین بازیابی بکاپ ناموفق: $1"; exit 1; }
trap 'sql -e "DROP DATABASE IF EXISTS \`$DRILL_DB\`" >/dev/null 2>&1 || true' EXIT

stamp="${1:-$(cat "$BACKUP_DIR/LATEST" 2>/dev/null || true)}"
[ -n "$stamp" ] && [ -d "$BACKUP_DIR/$stamp" ] || fail "no backup found in $BACKUP_DIR"
dir="$BACKUP_DIR/$stamp"
(cd "$dir" && sha256sum -c --quiet SHA256SUMS) || fail "checksum mismatch in $stamp"

dec() { openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass "file:$BACKUP_PASSPHRASE_FILE" -in "$1"; }
started=$(date +%s)
sql -e "DROP DATABASE IF EXISTS \`$DRILL_DB\`; CREATE DATABASE \`$DRILL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
dec "$dir/db.sql.gz.enc" | gunzip | sql "$DRILL_DB" || fail "import of $stamp failed"
if [ -f "$dir/storage.tar.gz.enc" ]; then
  dec "$dir/storage.tar.gz.enc" | tar -tzf - > /dev/null || fail "storage archive of $stamp is corrupt"
fi

count() { sql -N -B "$DRILL_DB" -e "SELECT COUNT(*) FROM \`$1\`"; }
users=$(count users); tx=$(count point_transactions); migrations=$(count migrations)
[ "$users" -gt 0 ] && [ "$migrations" -gt 0 ] || fail "restored database looks empty (users=$users migrations=$migrations)"

# The restored ledger must reconcile exactly like production does.
(cd "$APP_DIR" && DB_DATABASE="$DRILL_DB" OPS_ALERT_WEBHOOK= php artisan ledger:reconcile) || fail "ledger reconciliation on the restored copy found issues"

notify "✅ تمرین بازیابی بکاپ $stamp موفق: users=$users transactions=$tx در $(( $(date +%s) - started )) ثانیه"
