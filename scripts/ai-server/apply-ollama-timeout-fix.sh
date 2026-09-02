#!/usr/bin/env bash
set -Eeuo pipefail

readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'
readonly TIMEOUT_SECONDS='600'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

[[ -f "${ENV_FILE}" ]] || { echo "Datei fehlt: ${ENV_FILE}" >&2; exit 1; }

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-ollama-timeout"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

if grep -q '^ZBB_OLLAMA_TIMEOUT_SECONDS=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_TIMEOUT_SECONDS=.*/ZBB_OLLAMA_TIMEOUT_SECONDS=${TIMEOUT_SECONDS}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_TIMEOUT_SECONDS=${TIMEOUT_SECONDS}" >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service

for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo "Ollama-Zeitlimit wurde auf ${TIMEOUT_SECONDS} Sekunden gesetzt."
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
