#!/bin/bash
# Daily database backup: mysqldump -> gzip -> upload to Dropbox (App Folder).
# Run via cron from the app root, e.g.:
#   0 3 * * * cd /home/khairul87/public_html && bash deploy/backup-database.sh >> storage/logs/backup.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."   # app root (this script lives in deploy/)

ENV_FILE=".env"
[ -f "$ENV_FILE" ] || { echo "$(date '+%F %T') FATAL: .env not found"; exit 1; }

env_get() { grep -m1 "^$1=" "$ENV_FILE" | cut -d= -f2- ; }

DB_DATABASE=$(env_get DB_DATABASE)
DB_USERNAME=$(env_get DB_USERNAME)
DB_PASSWORD=$(env_get DB_PASSWORD)
DROPBOX_TOKEN=$(env_get DROPBOX_ACCESS_TOKEN)

BACKUP_DIR="$HOME/db_backups"
LOCAL_RETENTION_DAYS=7     # local copies kept this long (disk is finite)
DROPBOX_RETENTION_DAYS=30  # Dropbox copies kept this long (durable off-server copy)

mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="zennacraft_${TIMESTAMP}.sql.gz"
FILEPATH="$BACKUP_DIR/$FILENAME"

echo "$(date '+%F %T') Starting backup: $FILENAME"

if [ -z "$DB_PASSWORD" ]; then
    mysqldump -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$FILEPATH"
else
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$FILEPATH"
fi

SIZE=$(stat -c%s "$FILEPATH" 2>/dev/null || stat -f%z "$FILEPATH")
if [ "$SIZE" -lt 1000 ]; then
    echo "$(date '+%F %T') FATAL: dump suspiciously small ($SIZE bytes) — not uploading, not deleting local copy for inspection."
    exit 1
fi
echo "$(date '+%F %T') Dump OK ($SIZE bytes)"

if [ -z "$DROPBOX_TOKEN" ]; then
    echo "$(date '+%F %T') WARN: DROPBOX_ACCESS_TOKEN not set in .env — skipping upload, local backup kept."
else
    HTTP_CODE=$(curl -s -o /tmp/dbx_upload_response.json -w '%{http_code}' \
        -X POST https://content.dropboxapi.com/2/files/upload \
        --header "Authorization: Bearer $DROPBOX_TOKEN" \
        --header "Dropbox-API-Arg: {\"path\": \"/$FILENAME\", \"mode\": \"add\", \"autorename\": true, \"mute\": true}" \
        --header "Content-Type: application/octet-stream" \
        --data-binary @"$FILEPATH")

    if [ "$HTTP_CODE" = "200" ]; then
        echo "$(date '+%F %T') Uploaded to Dropbox: /$FILENAME"
    else
        echo "$(date '+%F %T') ERROR: Dropbox upload failed (HTTP $HTTP_CODE): $(cat /tmp/dbx_upload_response.json)"
    fi
    rm -f /tmp/dbx_upload_response.json

    # Prune old copies on Dropbox past the retention window.
    CUTOFF=$(date -d "-${DROPBOX_RETENTION_DAYS} days" +%Y%m%d 2>/dev/null || date -v-${DROPBOX_RETENTION_DAYS}d +%Y%m%d)
    LIST=$(curl -s -X POST https://api.dropboxapi.com/2/files/list_folder \
        --header "Authorization: Bearer $DROPBOX_TOKEN" \
        --header "Content-Type: application/json" \
        --data '{"path": "", "limit": 200}')
    echo "$LIST" | grep -oE '"name": *"zennacraft_[0-9]{8}_[0-9]{6}\.sql\.gz"' | grep -oE 'zennacraft_[0-9]{8}_[0-9]{6}\.sql\.gz' | while read -r NAME; do
        FDATE=$(echo "$NAME" | grep -oE '[0-9]{8}' | head -1)
        if [ "$FDATE" -lt "$CUTOFF" ]; then
            curl -s -X POST https://api.dropboxapi.com/2/files/delete_v2 \
                --header "Authorization: Bearer $DROPBOX_TOKEN" \
                --header "Content-Type: application/json" \
                --data "{\"path\": \"/$NAME\"}" > /dev/null
            echo "$(date '+%F %T') Pruned old Dropbox backup: $NAME"
        fi
    done
fi

# Prune old local copies.
find "$BACKUP_DIR" -name 'zennacraft_*.sql.gz' -mtime "+${LOCAL_RETENTION_DAYS}" -print -delete | while read -r OLD; do
    echo "$(date '+%F %T') Pruned old local backup: $OLD"
done

echo "$(date '+%F %T') Backup complete."
