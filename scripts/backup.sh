#!/usr/bin/env bash
# Nightly MySQL backup to S3, keeping the last 7 days.
# Cron (as the deploy user):
#   30 2 * * * /var/www/shopmate/scripts/backup.sh >> /var/log/shopmate-backup.log 2>&1
# Needs: the AWS CLI on the server, an IAM role (or keys) allowed to write to the bucket,
# and DB_* and AWS_BUCKET set in /var/www/shopmate/.env.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
# Read DB_* and AWS_BUCKET from .env without running anything else in it.
get_env() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_DATABASE="$(get_env DB_DATABASE)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"
BUCKET="$(get_env AWS_BUCKET)"
KEEP_DAYS=7

STAMP="$(date -u +%Y-%m-%d_%H%M)"
FILE="/tmp/shopmate_${STAMP}.sql.gz"

echo "[$(date -u)] Dumping ${DB_DATABASE}"
# --single-transaction: a consistent snapshot without locking tables while the site runs.
MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
  --single-transaction --quick --routines --no-tablespaces \
  "$DB_DATABASE" | gzip > "$FILE"

echo "[$(date -u)] Uploading to s3://${BUCKET}/backups/"
aws s3 cp "$FILE" "s3://${BUCKET}/backups/$(basename "$FILE")" --only-show-errors
rm -f "$FILE"

echo "[$(date -u)] Removing backups older than ${KEEP_DAYS} days"
CUTOFF="$(date -u -d "-${KEEP_DAYS} days" +%Y-%m-%d)"
aws s3 ls "s3://${BUCKET}/backups/" | while read -r _ _ _ name; do
  day="$(echo "$name" | sed -E 's/^shopmate_([0-9-]{10}).*/\1/')"
  if [[ "$day" < "$CUTOFF" ]]; then
    aws s3 rm "s3://${BUCKET}/backups/${name}" --only-show-errors
    echo "  deleted ${name}"
  fi
done

echo "[$(date -u)] Backup finished"
