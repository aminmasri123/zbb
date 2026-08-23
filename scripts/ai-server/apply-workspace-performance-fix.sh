#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SOURCE_SERVICE="${APP_ROOT}/current/src/zbb_agent/service.py"
readonly SOURCE_CONFIG="${APP_ROOT}/current/src/zbb_agent/config.py"
readonly SOURCE_SCHEMAS="${APP_ROOT}/current/src/zbb_agent/schemas.py"
readonly PYTHON_BIN="${APP_ROOT}/current/.venv/bin/python"
readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly SERVICE_UPLOAD='/home/aminmasri/service.py'
readonly CONFIG_UPLOAD='/home/aminmasri/config.py'
readonly SCHEMAS_UPLOAD='/home/aminmasri/schemas.py'
readonly WORKSPACE_MODEL='qwen3:1.7b'
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

for file in "${SOURCE_SERVICE}" "${SOURCE_CONFIG}" "${SOURCE_SCHEMAS}" "${PYTHON_BIN}" "${ENV_FILE}" "${SERVICE_UPLOAD}" "${CONFIG_UPLOAD}" "${SCHEMAS_UPLOAD}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done
ollama list | grep -Fq "${WORKSPACE_MODEL}" || {
    echo "Schnelles Arbeitsbereichsmodell fehlt: ${WORKSPACE_MODEL}" >&2
    exit 1
}

runtime_service="$("${PYTHON_BIN}" -c 'import zbb_agent.service; print(zbb_agent.service.__file__)')"
runtime_config="$("${PYTHON_BIN}" -c 'import zbb_agent.config; print(zbb_agent.config.__file__)')"
runtime_schemas="$("${PYTHON_BIN}" -c 'import zbb_agent.schemas; print(zbb_agent.schemas.__file__)')"
runtime_service="$(readlink -f -- "${runtime_service}")"
runtime_config="$(readlink -f -- "${runtime_config}")"
runtime_schemas="$(readlink -f -- "${runtime_schemas}")"
case "${runtime_service}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/service.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_service}" >&2; exit 1 ;;
esac
case "${runtime_config}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/config.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_config}" >&2; exit 1 ;;
esac
case "${runtime_schemas}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/schemas.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_schemas}" >&2; exit 1 ;;
esac

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-workspace-performance"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SOURCE_SERVICE}" "${backup}/source-service.py"
cp --preserve=mode,ownership,timestamps "${SOURCE_CONFIG}" "${backup}/source-config.py"
cp --preserve=mode,ownership,timestamps "${SOURCE_SCHEMAS}" "${backup}/source-schemas.py"
cp --preserve=mode,ownership,timestamps "${runtime_service}" "${backup}/runtime-service.py"
cp --preserve=mode,ownership,timestamps "${runtime_config}" "${backup}/runtime-config.py"
cp --preserve=mode,ownership,timestamps "${runtime_schemas}" "${backup}/runtime-schemas.py"
cp --preserve=mode,ownership,timestamps "${ENV_FILE}" "${backup}/agent.env"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/source-service.py" "${SOURCE_SERVICE}"
    cp --preserve=mode,ownership,timestamps "${backup}/source-config.py" "${SOURCE_CONFIG}"
    cp --preserve=mode,ownership,timestamps "${backup}/source-schemas.py" "${SOURCE_SCHEMAS}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-service.py" "${runtime_service}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-config.py" "${runtime_config}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-schemas.py" "${runtime_schemas}"
    cp --preserve=mode,ownership,timestamps "${backup}/agent.env" "${ENV_FILE}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

"${PYTHON_BIN}" -m py_compile "${SERVICE_UPLOAD}" "${CONFIG_UPLOAD}" "${SCHEMAS_UPLOAD}"
install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${SOURCE_SERVICE}"
install -o root -g root -m 0644 "${CONFIG_UPLOAD}" "${SOURCE_CONFIG}"
install -o root -g root -m 0644 "${SCHEMAS_UPLOAD}" "${SOURCE_SCHEMAS}"
install -o root -g root -m 0644 "${SERVICE_UPLOAD}" "${runtime_service}"
install -o root -g root -m 0644 "${CONFIG_UPLOAD}" "${runtime_config}"
install -o root -g root -m 0644 "${SCHEMAS_UPLOAD}" "${runtime_schemas}"

if grep -q '^ZBB_OLLAMA_WORKSPACE_MODEL=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_WORKSPACE_MODEL=.*/ZBB_OLLAMA_WORKSPACE_MODEL=${WORKSPACE_MODEL}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_WORKSPACE_MODEL=${WORKSPACE_MODEL}" >> "${ENV_FILE}"
fi

systemctl restart zbb-ai-agent.service
for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        "${PYTHON_BIN}" -c 'from zbb_agent.config import Settings; assert hasattr(Settings, "ollama_workspace_model")'
        trap - ERR
        echo "Schneller KI-Arbeitsbereich aktiviert: ${WORKSPACE_MODEL}"
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
