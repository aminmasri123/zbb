#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_DIR="${1:-/var/www/matrix}"
readonly UNIT_FILE='/etc/systemd/system/zbb-laravel-queue.service'

if [[ "${EUID}" -ne 0 ]]; then
    echo 'Dieses Installationsskript muss mit sudo ausgeführt werden.' >&2
    exit 1
fi

for command in readlink php systemctl install mktemp; do
    command -v "${command}" >/dev/null 2>&1 || {
        echo "Erforderlicher Befehl fehlt: ${command}" >&2
        exit 1
    }
done

resolved_app_dir="$(readlink -f -- "${APP_DIR}")"
if [[ -z "${resolved_app_dir}" || ! -f "${resolved_app_dir}/artisan" || ! -d "${resolved_app_dir}/storage" ]]; then
    echo "Ungültiges Laravel-Verzeichnis: ${APP_DIR}" >&2
    exit 1
fi

php_bin="$(command -v php)"
unit_tmp="$(mktemp)"
trap 'rm -f -- "${unit_tmp}"' EXIT

if [[ -f "${UNIT_FILE}" ]]; then
    backup_file="${UNIT_FILE}.$(date -u +%Y%m%dT%H%M%SZ).bak"
    install -m 0644 -- "${UNIT_FILE}" "${backup_file}"
    echo "Vorhandene Unit gesichert: ${backup_file}"
fi

printf '%s\n' \
    '[Unit]' \
    'Description=ZBB Laravel Queue Worker' \
    'After=network-online.target' \
    'Wants=network-online.target' \
    '' \
    '[Service]' \
    'Type=simple' \
    'User=www-data' \
    'Group=www-data' \
    "WorkingDirectory=${resolved_app_dir}" \
    "ExecStart=${php_bin} ${resolved_app_dir}/artisan queue:work database --queue=default --sleep=1 --tries=1 --timeout=1200 --memory=256" \
    'Restart=always' \
    'RestartSec=3' \
    'KillSignal=SIGTERM' \
    'TimeoutStopSec=1230' \
    'UMask=0027' \
    'NoNewPrivileges=true' \
    'PrivateTmp=true' \
    'ProtectSystem=full' \
    'ProtectHome=true' \
    "ReadWritePaths=${resolved_app_dir}/storage ${resolved_app_dir}/bootstrap/cache" \
    'StandardOutput=journal' \
    'StandardError=journal' \
    '' \
    '[Install]' \
    'WantedBy=multi-user.target' > "${unit_tmp}"

cd -- "${resolved_app_dir}"
sudo -u www-data "${php_bin}" artisan migrate --force
sudo -u www-data "${php_bin}" artisan config:cache

install -m 0644 -- "${unit_tmp}" "${UNIT_FILE}"
systemctl daemon-reload
systemctl enable --now zbb-laravel-queue.service
systemctl restart zbb-laravel-queue.service

systemctl is-active --quiet zbb-laravel-queue.service
echo 'ZBB-Queue-Worker ist aktiv.'
systemctl status zbb-laravel-queue.service --no-pager -l
