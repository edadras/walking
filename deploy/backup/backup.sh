#!/usr/bin/env bash
# Encrypted nightly backup: MySQL dump + uploaded files, with checksums and retention.
#
#   DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD   database to dump
#   STORAGE_DIR           uploads (backend/storage/app); optional
#   BACKUP_DIR            where archives are written (default /var/backups/gamyar)
#   BACKUP_PASSPHRASE_FILE file holding the encryption passphrase (required; keep a copy off-site)
#   BACKUP_KEEP_DAYS      local retention (default 14)
#   RCLONE_REMOTE         optional off-site copy, e.g. "arvan-s3:gamyar-backups"
#
# APP_KEY and PII_HASH_KEY are NOT in the backup (national codes and Sheba numbers are
# encrypted with APP_KEY): store both keys in the password manager, or the backup is useless.
set -euo pipefail

: "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}" "${BACKUP_PASSPHRASE_FILE:?}"
DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="${DB_PORT:-3306}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/gamyar}"; KEEP="${BACKUP_KEEP_DAYS:-14}"
[ -s "$BACKUP_PASSPHRASE_FILE" ] || { echo "passphrase file is empty or missing" >&2; exit 1; }

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
dir="$BACKUP_DIR/$stamp"
mkdir -p "$dir"
umask 077
enc() { openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass "file:$BACKUP_PASSPHRASE_FILE" -out "$1"; }

# --single-transaction: consistent InnoDB snapshot without locking the site.
MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
  --single-transaction --quick --routines --triggers --hex-blob --no-tablespaces --set-gtid-purged=OFF \
  "$DB_DATABASE" | gzip -6 | enc "$dir/db.sql.gz.enc"

if [ -n "${STORAGE_DIR:-}" ] && [ -d "$STORAGE_DIR" ]; then
  tar -C "$STORAGE_DIR" -czf - . | enc "$dir/storage.tar.gz.enc"
fi

(cd "$dir" && sha256sum ./*.enc > SHA256SUMS)
echo "$stamp" > "$BACKUP_DIR/LATEST"
echo "backup $dir: $(du -sh "$dir" | cut -f1)"

if [ -n "${RCLONE_REMOTE:-}" ]; then
  rclone copy "$dir" "$RCLONE_REMOTE/$stamp" --immutable
fi

find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +"$KEEP" -exec rm -rf {} +
