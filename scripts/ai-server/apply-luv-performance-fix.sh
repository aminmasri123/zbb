#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SERVICE_FILE="${APP_ROOT}/current/src/zbb_agent/service.py"
readonly CONFIG_FILE="${APP_ROOT}/current/src/zbb_agent/config.py"
readonly PYTHON_BIN="${APP_ROOT}/current/.venv/bin/python"
readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly SERVICE_UPLOAD='/home/aminmasri/service-luv-fast.py'
readonly CONFIG_UPLOAD='/home/aminmasri/config-luv-fast.py'
readonly REPORT_MODEL='qwen3:1.7b'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

for file in "${SERVICE_FILE}" "${CONFIG_FILE}" "${PYTHON_BIN}" "${ENV_FILE}" "${SERVICE_UPLOAD}" "${CONFIG_UPLOAD}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done
ollama list | grep -Fq "${REPORT_MODEL}" || {
    echo "Schnelles Berichtsmodell fehlt: ${REPORT_MODEL}" >&2
    exit 1
}

runtime_service_file="$("${PYTHON_BIN}" -c 'import zbb_agent.service; print(zbb_agent.service.__file__)')"
runtime_config_file="$("${PYTHON_BIN}" -c 'import zbb_agent.config; print(zbb_agent.config.__file__)')"
runtime_service_file="$(readlink -f -- "${runtime_service_file}")"
runtime_config_file="$(readlink -f -- "${runtime_config_file}")"
case "${runtime_service_file}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/service.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_service_file}" >&2; exit 1 ;;
esac
case "${runtime_config_file}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/config.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_config_file}" >&2; exit 1 ;;
esac

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-luv-performance"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SERVICE_FILE}" "${backup}/service.py"
cp --preserve=mode,ownership,timestamps "${CONFIG_FILE}" "${backup}/config.py"
cp --preserve=mode,ownership,timestamps "${runtime_service_file}" "${backup}/runtime-service.py"
cp --preserve=mode,ownership,timestamps "${runtime_config_file}" "${backup}/runtime-config.py"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/service.py" "${SERVICE_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/config.py" "${CONFIG_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-service.py" "${runtime_service_file}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-config.py" "${runtime_config_file}"
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${SERVICE_FILE}"
install -o root -g root -m 0644 "${CONFIG_UPLOAD}" "${CONFIG_FILE}"
install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${runtime_service_file}"
install -o root -g root -m 0644 "${CONFIG_UPLOAD}" "${runtime_config_file}"

if grep -q '^ZBB_OLLAMA_REPORT_MODEL=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_REPORT_MODEL=.*/ZBB_OLLAMA_REPORT_MODEL=${REPORT_MODEL}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_REPORT_MODEL=${REPORT_MODEL}" >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service
for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        "${PYTHON_BIN}" -c 'from zbb_agent.config import Settings; assert hasattr(Settings, "ollama_report_model")'
        trap - ERR
        echo "Schnelle LuV-Generierung aktiviert: ${REPORT_MODEL}"
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
