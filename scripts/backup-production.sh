#!/usr/bin/env bash
set -Eeuo pipefail

BASE_DIR="${BASE_DIR:-/var/www/tiempo}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/tiempo}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
COMPOSE_FILE="${BASE_DIR}/current/docker-compose.production.yml"
KEEP_DAYS="${KEEP_DAYS:-14}"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run this script as root." >&2
    exit 1
fi

umask 077
install -d -m 700 "${BACKUP_DIR}"

docker compose \
    --env-file "${BASE_DIR}/shared/.env" \
    --env-file "${BASE_DIR}/shared/deploy.env" \
    -f "${COMPOSE_FILE}" \
    exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --lock-tables=false --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip -9 > "${BACKUP_DIR}/mysql-${STAMP}.sql.gz"

# A dump that cannot be decompressed is not a backup.
gzip -t "${BACKUP_DIR}/mysql-${STAMP}.sql.gz"
find "${BACKUP_DIR}" -type f -name 'mysql-*.sql.gz' -mtime "+${KEEP_DAYS}" -delete

printf 'Created %s\n' "${BACKUP_DIR}/mysql-${STAMP}.sql.gz"
