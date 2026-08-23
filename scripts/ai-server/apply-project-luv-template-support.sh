#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SOURCE_FILE="${APP_ROOT}/current/src/zbb_agent/service.py"
readonly PYTHON_BIN="${APP_ROOT}/current/.venv/bin/python"
readonly SERVICE_UPLOAD='/home/aminmasri/service-project-luv.py'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

for file in "${SOURCE_FILE}" "${PYTHON_BIN}" "${SERVICE_UPLOAD}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done

runtime_file="$("${PYTHON_BIN}" -c 'import zbb_agent.service; print(zbb_agent.service.__file__)')"
runtime_file="$(readlink -f -- "${runtime_file}")"
case "${runtime_file}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/service.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_file}" >&2; exit 1 ;;
esac

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-project-luv-template"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SOURCE_FILE}" "${backup}/source-service.py"
cp --preserve=mode,ownership,timestamps "${runtime_file}" "${backup}/runtime-service.py"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/source-service.py" "${SOURCE_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-service.py" "${runtime_file}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

"${PYTHON_BIN}" -m py_compile "${SERVICE_UPLOAD}"
install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${SOURCE_FILE}"
install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${runtime_file}"
systemctl restart zbb-ai-agent.service

for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo 'Projektbezogene LuV-KI-Konfiguration aktiviert.'
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
