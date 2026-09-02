#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_ROOT='/opt/zbb-ai-agent'
readonly SOURCE_FILE="${APP_ROOT}/current/src/zbb_agent/schemas.py"
readonly PYTHON_BIN="${APP_ROOT}/current/.venv/bin/python"
readonly BACKUP_ROOT='/var/backups/zbb-ai-agent'
readonly NEEDLE='    DOCUMENTATION = "get_documentation_entries"'
readonly INSERT='    DEVELOPMENT = "get_participant_development_data"'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Bitte mit sudo ausführen.' >&2
    exit 1
fi

for file in "${SOURCE_FILE}" "${PYTHON_BIN}"; do
    [[ -f "${file}" ]] || { echo "Datei fehlt: ${file}" >&2; exit 1; }
done

runtime_file="$("${PYTHON_BIN}" -c 'import zbb_agent.schemas; print(zbb_agent.schemas.__file__)')"
runtime_file="$(readlink -f -- "${runtime_file}")"
case "${runtime_file}" in
    "${APP_ROOT}"/releases/*/.venv/lib/python*/site-packages/zbb_agent/schemas.py) ;;
    *) echo "Unsicherer Python-Laufzeitpfad: ${runtime_file}" >&2; exit 1 ;;
esac

if grep -Fqx "${INSERT}" "${SOURCE_FILE}" && grep -Fqx "${INSERT}" "${runtime_file}"; then
    echo 'Schema unterstützt get_participant_development_data bereits.'
    exit 0
fi

grep -Fqx "${NEEDLE}" "${SOURCE_FILE}" || { echo 'Einfügeposition im Quellschema fehlt.' >&2; exit 1; }
grep -Fqx "${NEEDLE}" "${runtime_file}" || { echo 'Einfügeposition im Laufzeitschema fehlt.' >&2; exit 1; }

backup="${BACKUP_ROOT}/$(date -u +%Y%m%dT%H%M%SZ)-pre-development-tool-schema"
install -d -m 0700 "${backup}"
cp --preserve=mode,ownership,timestamps "${SOURCE_FILE}" "${backup}/source-schemas.py"
cp --preserve=mode,ownership,timestamps "${runtime_file}" "${backup}/runtime-schemas.py"

rollback() {
    cp --preserve=mode,ownership,timestamps "${backup}/source-schemas.py" "${SOURCE_FILE}"
    cp --preserve=mode,ownership,timestamps "${backup}/runtime-schemas.py" "${runtime_file}"
    systemctl restart zbb-ai-agent.service || true
    echo "Aktualisierung fehlgeschlagen; Rollback aus ${backup} wurde durchgeführt." >&2
}
trap rollback ERR

patch_schema() {
    local target="$1"
    local temporary
    temporary="$(mktemp)"
    awk -v needle="${NEEDLE}" -v insert="${INSERT}" '
        { print }
        $0 == needle { print insert }
    ' "${target}" > "${temporary}"
    install --owner=root --group=root --mode=0644 "${temporary}" "${target}"
    rm -f -- "${temporary}"
}

grep -Fqx "${INSERT}" "${SOURCE_FILE}" || patch_schema "${SOURCE_FILE}"
grep -Fqx "${INSERT}" "${runtime_file}" || patch_schema "${runtime_file}"

"${PYTHON_BIN}" -m py_compile "${SOURCE_FILE}" "${runtime_file}"
systemctl restart zbb-ai-agent.service

for _ in {1..30}; do
    if curl -fsS --max-time 2 http://127.0.0.1:8000/health/live >/dev/null; then
        trap - ERR
        echo 'Schema für get_participant_development_data wurde aktiviert.'
        echo "Recovery-Sicherung: ${backup}"
        exit 0
    fi
    sleep 1
done

echo 'Agent wurde nach dem Neustart nicht rechtzeitig bereit.' >&2
exit 1
