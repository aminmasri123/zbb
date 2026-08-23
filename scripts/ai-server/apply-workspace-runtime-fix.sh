#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SOURCE_ROOT="${APP_ROOT}/current/src/zbb_agent"
readonly RUNTIME_ROOT="${APP_ROOT}/current/.venv/lib/python3.14/site-packages/zbb_agent"
readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly UPLOAD_SERVICE='/home/aminmasri/service-runtime-fix.py'
readonly UPLOAD_CONFIG='/home/aminmasri/config-runtime-fix.py'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

[[ ${EUID} -eq 0 ]] || { echo 'Bitte mit sudo ausführen.' >&2; exit 1; }
for file in "${SOURCE_ROOT}/service.py" "${SOURCE_ROOT}/config.py" "${RUNTIME_ROOT}/service.py" "${RUNTIME_ROOT}/config.py" "${ENV_FILE}" "${UPLOAD_SERVICE}" "${UPLOAD_CONFIG}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-workspace-runtime-fix"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SOURCE_ROOT}/service.py" "${backup}/service.py"
cp --preserve=mode,ownership,timestamps "${SOURCE_ROOT}/config.py" "${backup}/config.py"
cp --preserve=mode,ownership,timestamps "${RUNTIME_ROOT}/service.py" "${backup}/runtime-service.py"
cp --preserve=mode,ownership,timestamps "${RUNTIME_ROOT}/config.py" "${backup}/runtime-config.py"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/service.py" "${SOURCE_ROOT}/service.py"
    cp --preserve=mode,ownership,timestamps "${backup}/config.py" "${SOURCE_ROOT}/config.py"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-service.py" "${RUNTIME_ROOT}/service.py"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-config.py" "${RUNTIME_ROOT}/config.py"
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Fehler; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

install -o root -g root -m 0644 "${UPLOAD_SERVICE}" "${SOURCE_ROOT}/service.py"
install -o root -g root -m 0644 "${UPLOAD_CONFIG}" "${SOURCE_ROOT}/config.py"
install -o root -g root -m 0644 "${UPLOAD_SERVICE}" "${RUNTIME_ROOT}/service.py"
install -o root -g root -m 0644 "${UPLOAD_CONFIG}" "${RUNTIME_ROOT}/config.py"
if grep -q '^ZBB_OLLAMA_TIMEOUT_SECONDS=' "${ENV_FILE}"; then
    sed -i 's/^ZBB_OLLAMA_TIMEOUT_SECONDS=.*/ZBB_OLLAMA_TIMEOUT_SECONDS=240/' "${ENV_FILE}"
else
    printf '%s\n' 'ZBB_OLLAMA_TIMEOUT_SECONDS=240' >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service
for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo 'Workspace-Laufzeitkorrektur erfolgreich aktiviert.'
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done
echo 'Agent wurde nicht rechtzeitig bereit.' >&2
exit 1
