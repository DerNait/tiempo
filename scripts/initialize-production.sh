#!/usr/bin/env bash
set -Eeuo pipefail

BASE_DIR="${BASE_DIR:-/var/www/tiempo}"
SHARED_DIR="${BASE_DIR}/shared"
ENV_FILE="${SHARED_DIR}/.env"
DOMAIN="${DOMAIN:-tiempo.dernait.com}"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run this script as root." >&2
    exit 1
fi

umask 077
mkdir -p \
    "${SHARED_DIR}/storage/app/public" \
    "${SHARED_DIR}/storage/framework/cache/data" \
    "${SHARED_DIR}/storage/framework/sessions" \
    "${SHARED_DIR}/storage/framework/views" \
    "${SHARED_DIR}/storage/logs" \
    "${SHARED_DIR}/bootstrap/cache"

chown -R 1000:1000 "${SHARED_DIR}/storage" "${SHARED_DIR}/bootstrap"

if [[ -f "${ENV_FILE}" ]]; then
    chmod 600 "${ENV_FILE}"
    echo "Production environment already exists; secrets were left unchanged."
    exit 0
fi

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
DB_PASSWORD="$(openssl rand -hex 24)"
DB_ROOT_PASSWORD="$(openssl rand -hex 32)"
TEMPORARY_PASSWORD="$(openssl rand -hex 12)"

printf '%s\n' \
    'APP_NAME="Tiempo"' \
    'APP_ENV=production' \
    "APP_KEY=${APP_KEY}" \
    'APP_DEBUG=false' \
    "APP_URL=https://${DOMAIN}" \
    'APP_USER_TIMEZONE=America/Guatemala' \
    'APP_LOCALE=es' \
    'APP_FALLBACK_LOCALE=es' \
    'LOG_CHANNEL=stack' \
    'LOG_STACK=single' \
    'LOG_LEVEL=warning' \
    'DB_CONNECTION=mysql' \
    'DB_HOST=mysql' \
    'DB_PORT=3306' \
    'DB_DATABASE=tiempo' \
    'DB_USERNAME=tiempo' \
    "DB_PASSWORD=${DB_PASSWORD}" \
    "DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
    'SESSION_DRIVER=database' \
    'SESSION_LIFETIME=40320' \
    'SESSION_ENCRYPT=true' \
    'SESSION_PATH=/' \
    "SESSION_DOMAIN=${DOMAIN}" \
    'SESSION_SECURE_COOKIE=true' \
    'SESSION_HTTP_ONLY=true' \
    'SESSION_SAME_SITE=lax' \
    "SANCTUM_STATEFUL_DOMAINS=${DOMAIN}" \
    'BROADCAST_CONNECTION=log' \
    'FILESYSTEM_DISK=local' \
    'QUEUE_CONNECTION=database' \
    'CACHE_STORE=database' \
    'MAIL_MAILER=log' \
    'MAIL_FROM_ADDRESS=darkcsjuegos1@gmail.com' \
    'MAIL_FROM_NAME="Tiempo"' \
    'PERSONAL_USER_NAME=DerNait' \
    'PERSONAL_USER_EMAIL=darkcsjuegos1@gmail.com' \
    "PERSONAL_USER_PASSWORD=${TEMPORARY_PASSWORD}" \
    > "${ENV_FILE}"

chmod 600 "${ENV_FILE}"
printf 'TEMPORARY_PASSWORD=%s\n' "${TEMPORARY_PASSWORD}"
