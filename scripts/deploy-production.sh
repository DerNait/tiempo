#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_TARGET="${SSH_TARGET:-root@167.233.42.11}"
SSH_PORT="${SSH_PORT:-17}"
BASE_DIR="${BASE_DIR:-/var/www/tiempo}"
RELEASE_ID="${1:?Usage: SSH_TARGET=root@host $0 RELEASE_ID}"
IMAGE_NAME="tiempo-app:${RELEASE_ID}"
ARTIFACT="${ROOT_DIR}/artifacts/tiempo-app-${RELEASE_ID}.tar.gz"
REMOTE_RELEASE="${BASE_DIR}/releases/${RELEASE_ID}"
SSH=(ssh -p "${SSH_PORT}" "${SSH_TARGET}")
RSYNC_SSH="ssh -p ${SSH_PORT}"

test -s "${ARTIFACT}"
test -s "${ARTIFACT}.sha256"

"${SSH[@]}" "install -d -m 755 '${BASE_DIR}/releases' '${BASE_DIR}/images' && install -d -m 700 '${BASE_DIR}/shared' && mkdir -p '${REMOTE_RELEASE}' '${BASE_DIR}/shared/storage/app/public'"

rsync -az --delete -e "${RSYNC_SSH}" \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.build/' \
    --exclude='artifacts/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='public/hot' \
    --exclude='public/storage' \
    --exclude='storage/app/public/' \
    --exclude='storage/logs/' \
    --exclude='storage/framework/' \
    --exclude='.phpunit.result.cache' \
    "${ROOT_DIR}/" "${SSH_TARGET}:${REMOTE_RELEASE}/"

rsync -az -e "${RSYNC_SSH}" \
    "${ARTIFACT}" "${ARTIFACT}.sha256" \
    "${SSH_TARGET}:${BASE_DIR}/images/"

"${SSH[@]}" bash -s -- "${BASE_DIR}" "${RELEASE_ID}" "${IMAGE_NAME}" <<'REMOTE'
set -Eeuo pipefail
BASE_DIR="$1"
RELEASE_ID="$2"
IMAGE_NAME="$3"
RELEASE_DIR="${BASE_DIR}/releases/${RELEASE_ID}"
ARTIFACT="${BASE_DIR}/images/tiempo-app-${RELEASE_ID}.tar.gz"

BASE_DIR="${BASE_DIR}" bash "${RELEASE_DIR}/scripts/initialize-production.sh"
printf 'APP_IMAGE=%s\n' "${IMAGE_NAME}" > "${BASE_DIR}/shared/deploy.env"
chmod 600 "${BASE_DIR}/shared/deploy.env"

cd "${BASE_DIR}/images"
sha256sum -c "$(basename "${ARTIFACT}").sha256"
gzip -dc "${ARTIFACT}" | docker load

docker compose \
    --env-file "${BASE_DIR}/shared/.env" \
    --env-file "${BASE_DIR}/shared/deploy.env" \
    -f "${RELEASE_DIR}/docker-compose.production.yml" config --quiet

ln -sfn "${RELEASE_DIR}" "${BASE_DIR}/current"
rm -f "${BASE_DIR}/current/public/storage"
mkdir -p "${BASE_DIR}/current/public/storage"

compose=(docker compose --env-file "${BASE_DIR}/shared/.env" --env-file "${BASE_DIR}/shared/deploy.env" -f "${BASE_DIR}/current/docker-compose.production.yml")
# Bind mounts that pass through the `current` symlink are resolved when the
# container is created, so release services are recreated to pick up the new
# code and public assets.
"${compose[@]}" up -d --remove-orphans --force-recreate app worker nginx
"${compose[@]}" exec -T app php artisan migrate --force </dev/null

# DatabaseSeeder only creates the personal account and tops up its default
# categories, so it is safe to run on every deploy.
"${compose[@]}" exec -T app php artisan db:seed --force </dev/null

"${compose[@]}" exec -T app php artisan optimize </dev/null
"${compose[@]}" ps

printf '%s\n' '23 3 * * * root /var/www/tiempo/current/scripts/backup-production.sh >> /var/log/tiempo-backup.log 2>&1' \
    > /etc/cron.d/tiempo-backup
chmod 644 /etc/cron.d/tiempo-backup
install -d -m 700 /var/backups/tiempo
REMOTE

echo "Release ${RELEASE_ID} deployed. Run configure-host-nginx.sh once if HTTPS is not configured."
