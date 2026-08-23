#!/usr/bin/env bash
set -Eeuo pipefail

readonly VERSION='0.2.1'
readonly RELEASE_ARCHIVE="/home/aminmasri/zbb-ai-agent-${VERSION}.tar.gz"
readonly STAGED_UNIT='/home/aminmasri/zbb-ai-agent.service'
readonly APP_ROOT='/opt/zbb-ai-agent'
readonly RELEASE_DIR="${APP_ROOT}/releases/${VERSION}"
readonly CURRENT_LINK="${APP_ROOT}/current"
readonly CONFIG_DIR='/etc/zbb-ai-agent'
readonly ENV_FILE="${CONFIG_DIR}/agent.env"
readonly UNIT_FILE='/etc/systemd/system/zbb-ai-agent.service'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'
readonly SERVICE_USER='zbb-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Dieses Skript muss mit sudo ausgeführt werden.' >&2
    exit 1
fi

for command in systemctl systemd-analyze sha256sum install tar python3 curl openssl \
    useradd find ln readlink ss awk date grep sed; do
    command -v "${command}" >/dev/null || {
        echo "Pflichtprogramm fehlt: ${command}" >&2
        exit 1
    }
done

[[ -f "${RELEASE_ARCHIVE}" ]] || {
    echo "Release-Archiv fehlt: ${RELEASE_ARCHIVE}" >&2
    exit 1
}
[[ -f "${STAGED_UNIT}" ]] || {
    echo "Systemd-Unit fehlt: ${STAGED_UNIT}" >&2
    exit 1
}
[[ ! -e "${RELEASE_DIR}" ]] || {
    echo "Release-Ziel existiert bereits: ${RELEASE_DIR}" >&2
    exit 1
}

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${BACKUP_ROOT}/${timestamp}-pre-deploy"
install -d -m 0700 -o root -g root "${backup_dir}"

old_release=''
if [[ -L "${CURRENT_LINK}" ]]; then
    old_release="$(readlink -f "${CURRENT_LINK}")"
    printf '%s\n' "${old_release}" > "${backup_dir}/previous-release.txt"
fi
if [[ -f "${UNIT_FILE}" ]]; then
    install -m 0600 -o root -g root "${UNIT_FILE}" "${backup_dir}/zbb-ai-agent.service"
fi
if [[ -f "${ENV_FILE}" ]]; then
    install -m 0600 -o root -g root "${ENV_FILE}" "${backup_dir}/agent.env"
fi
sha256sum "${RELEASE_ARCHIVE}" "${STAGED_UNIT}" > "${backup_dir}/staged-sha256sums.txt"
chmod 0600 "${backup_dir}/staged-sha256sums.txt"

created_user=0
if ! id "${SERVICE_USER}" >/dev/null 2>&1; then
    useradd --system --home-dir /var/lib/zbb-ai-agent --shell /usr/sbin/nologin \
        --user-group "${SERVICE_USER}"
    created_user=1
fi

rollback() {
    echo 'Deployment fehlgeschlagen – führe Rollback aus.' >&2
    systemctl stop zbb-ai-agent.service 2>/dev/null || true

    if [[ -n "${old_release}" && -d "${old_release}" ]]; then
        ln -sfn "${old_release}" "${CURRENT_LINK}"
    else
        rm -f -- "${CURRENT_LINK}"
    fi

    if [[ -f "${backup_dir}/zbb-ai-agent.service" ]]; then
        install -m 0644 -o root -g root \
            "${backup_dir}/zbb-ai-agent.service" "${UNIT_FILE}"
    else
        rm -f -- "${UNIT_FILE}"
    fi

    if [[ -f "${backup_dir}/agent.env" ]]; then
        install -m 0600 -o root -g root "${backup_dir}/agent.env" "${ENV_FILE}"
    fi

    systemctl daemon-reload
    if [[ -n "${old_release}" ]]; then
        systemctl start zbb-ai-agent.service 2>/dev/null || true
    else
        systemctl disable zbb-ai-agent.service 2>/dev/null || true
    fi

    if [[ ${created_user} -eq 1 ]]; then
        echo 'Der neu angelegte, gesperrte Service-User bleibt für die Diagnose erhalten.' >&2
    fi
    echo "Rollback-Sicherung: ${backup_dir}" >&2
}
trap rollback ERR

install -d -m 0755 -o root -g root "${APP_ROOT}" "${APP_ROOT}/releases"
install -d -m 0750 -o root -g "${SERVICE_USER}" "${CONFIG_DIR}"
install -d -m 0750 -o "${SERVICE_USER}" -g "${SERVICE_USER}" /var/lib/zbb-ai-agent
install -d -m 0755 -o root -g root "${RELEASE_DIR}"
tar -xzf "${RELEASE_ARCHIVE}" --strip-components=1 -C "${RELEASE_DIR}"
chown -R root:root "${RELEASE_DIR}"
find "${RELEASE_DIR}" -type d -exec chmod 0755 {} +
find "${RELEASE_DIR}" -type f -exec chmod 0644 {} +

python3 -m venv "${RELEASE_DIR}/.venv"
"${RELEASE_DIR}/.venv/bin/python" -m pip install --disable-pip-version-check \
    --no-cache-dir "${RELEASE_DIR}"
chown -R root:root "${RELEASE_DIR}/.venv"

if [[ ! -f "${ENV_FILE}" ]]; then
    secret="$(openssl rand -hex 32)"
    cat > "${ENV_FILE}" <<EOF
ZBB_AGENT_KEY_ID=laravel-v1
ZBB_AGENT_SECRET=${secret}
ZBB_OLLAMA_BASE_URL=http://127.0.0.1:11434
ZBB_OLLAMA_MODEL=qwen3:4b-instruct-2507-q4_K_M
ZBB_OLLAMA_VISION_MODEL=qwen3-vl:2b-instruct
ZBB_AGENT_REQUEST_MAX_BYTES=16000000
ZBB_AGENT_SIGNATURE_MAX_AGE_SECONDS=60
ZBB_OLLAMA_TIMEOUT_SECONDS=240
EOF
    unset secret
fi
if grep -q '^ZBB_OLLAMA_VISION_MODEL=' "${ENV_FILE}"; then
    sed -i 's/^ZBB_OLLAMA_VISION_MODEL=.*/ZBB_OLLAMA_VISION_MODEL=qwen3-vl:2b-instruct/' "${ENV_FILE}"
else
    printf '%s\n' 'ZBB_OLLAMA_VISION_MODEL=qwen3-vl:2b-instruct' >> "${ENV_FILE}"
fi
if grep -q '^ZBB_AGENT_REQUEST_MAX_BYTES=' "${ENV_FILE}"; then
    sed -i 's/^ZBB_AGENT_REQUEST_MAX_BYTES=.*/ZBB_AGENT_REQUEST_MAX_BYTES=16000000/' "${ENV_FILE}"
else
    printf '%s\n' 'ZBB_AGENT_REQUEST_MAX_BYTES=16000000' >> "${ENV_FILE}"
fi
chown root:"${SERVICE_USER}" "${ENV_FILE}"
chmod 0640 "${ENV_FILE}"

install -m 0644 -o root -g root "${STAGED_UNIT}" "${UNIT_FILE}"
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}"

systemd-analyze verify "${UNIT_FILE}"
systemctl daemon-reload
systemctl enable zbb-ai-agent.service
systemctl restart zbb-ai-agent.service
systemctl is-active --quiet zbb-ai-agent.service

for attempt in 1 2 3 4 5; do
    if curl --fail --silent --show-error --max-time 3 \
        'http://127.0.0.1:8000/health/live' > /dev/null; then
        break
    fi

    if [[ ${attempt} -eq 5 ]]; then
        echo 'Agent-Liveness-Check ist fehlgeschlagen.' >&2
        false
    fi
    sleep 1
done

ss -lntH | awk '
    $4 == "127.0.0.1:8000" { local_listener = 1 }
    $4 == "0.0.0.0:8000" || $4 == "*:8000" || $4 == "[::]:8000" { exposed_listener = 1 }
    END { exit !(local_listener && !exposed_listener) }
'

trap - ERR

cat > "${backup_dir}/ROLLBACK.txt" <<EOF
Rollback des ZBB-Agent-Service:

sudo systemctl stop zbb-ai-agent.service
EOF
if [[ -n "${old_release}" ]]; then
    cat >> "${backup_dir}/ROLLBACK.txt" <<EOF
sudo ln -sfn '${old_release}' '${CURRENT_LINK}'
sudo systemctl start zbb-ai-agent.service
EOF
else
    cat >> "${backup_dir}/ROLLBACK.txt" <<EOF
sudo systemctl disable zbb-ai-agent.service
EOF
fi
chmod 0600 "${backup_dir}/ROLLBACK.txt"

echo 'ZBB-Agent-Service erfolgreich bereitgestellt.'
echo "Recovery-Sicherung: ${backup_dir}"
systemctl status zbb-ai-agent.service --no-pager -l | sed -n '1,25p'
curl --fail --silent --show-error 'http://127.0.0.1:8000/health/live'
echo
systemd-analyze security zbb-ai-agent.service --no-pager | tail -n 3
