#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SERVICE_FILE="${APP_ROOT}/current/src/zbb_agent/service.py"
readonly CONFIG_FILE="${APP_ROOT}/current/src/zbb_agent/config.py"
readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly SERVICE_UPLOAD='/home/aminmasri/service-luv-fast.py'
readonly CONFIG_UPLOAD='/home/aminmasri/config-luv-fast.py'
readonly REPORT_MODEL='qwen3:1.7b'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

for file in "${SERVICE_FILE}" "${CONFIG_FILE}" "${ENV_FILE}" "${SERVICE_UPLOAD}" "${CONFIG_UPLOAD}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done
ollama list | grep -Fq "${REPORT_MODEL}" || {
    echo "Schnelles Berichtsmodell fehlt: ${REPORT_MODEL}" >&2
    exit 1
}

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-luv-performance"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SERVICE_FILE}" "${backup}/service.py"
cp --preserve=mode,ownership,timestamps "${CONFIG_FILE}" "${backup}/config.py"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/service.py" "${SERVICE_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/config.py" "${CONFIG_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${SERVICE_FILE}"
install -o root -g root -m 0644 "${CONFIG_UPLOAD}" "${CONFIG_FILE}"

if grep -q '^ZBB_OLLAMA_REPORT_MODEL=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_REPORT_MODEL=.*/ZBB_OLLAMA_REPORT_MODEL=${REPORT_MODEL}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_REPORT_MODEL=${REPORT_MODEL}" >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service
for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo "Schnelle LuV-Generierung aktiviert: ${REPORT_MODEL}"
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
