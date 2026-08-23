#!/usr/bin/env bash
set -Eeuo pipefail

readonly MODEL='qwen3:4b-instruct-2507-q4_K_M'
readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SERVICE_FILE="${APP_ROOT}/current/src/zbb_agent/service.py"
readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly UPLOAD_FILE='/home/aminmasri/service-factual-safety.py'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

[[ -f "${SERVICE_FILE}" ]] || { echo "Agent-Datei fehlt: ${SERVICE_FILE}" >&2; exit 1; }
[[ -f "${ENV_FILE}" ]] || { echo "Environment-Datei fehlt: ${ENV_FILE}" >&2; exit 1; }
[[ -f "${UPLOAD_FILE}" ]] || { echo "Upload-Datei fehlt: ${UPLOAD_FILE}" >&2; exit 1; }
ollama list | grep -Fq "${MODEL}" || { echo "Modell fehlt: ${MODEL}" >&2; exit 1; }

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-factual-safety"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SERVICE_FILE}" "${backup}/service.py"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/service.py" "${SERVICE_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

install -o root -g root -m 0644 "${UPLOAD_FILE}" "${SERVICE_FILE}"
if grep -q '^ZBB_OLLAMA_MODEL=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_MODEL=.*/ZBB_OLLAMA_MODEL=${MODEL}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_MODEL=${MODEL}" >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service
for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo "Faktenschutz und Modell aktiviert: ${MODEL}"
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
