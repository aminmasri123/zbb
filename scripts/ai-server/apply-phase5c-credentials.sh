#!/usr/bin/env bash
set -euo pipefail

credentials=/home/aminmasri/zbb-agent-credentials.env
environment=/etc/zbb-ai-agent/agent.env
service=zbb-ai-agent.service
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="/var/backups/zbb-ai-agent/${stamp}-pre-phase5c"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Dieses Skript muss mit sudo ausgefuehrt werden." >&2
    exit 1
fi

if [[ ! -f "${credentials}" || ! -f "${environment}" ]]; then
    echo "Credential- oder Agent-Environment-Datei fehlt." >&2
    exit 1
fi

key_id="$(sed -n 's/^ZBB_AGENT_KEY_ID=//p' "${credentials}")"
secret="$(sed -n 's/^ZBB_AGENT_SECRET=//p' "${credentials}")"

if [[ "${key_id}" != "laravel" || ! "${secret}" =~ ^[A-Za-z0-9_-]{43,128}$ ]]; then
    echo "Credential-Datei ist ungueltig." >&2
    exit 1
fi

install -d -m 0700 -o root -g root "${backup_dir}"
install -m 0600 -o root -g root "${environment}" "${backup_dir}/agent.env"

temporary="$(mktemp /etc/zbb-ai-agent/agent.env.phase5c.XXXXXX)"
cleanup() { rm -f "${temporary}"; }
trap cleanup EXIT

grep -vE '^ZBB_AGENT_(KEY_ID|SECRET)=' "${environment}" > "${temporary}"
printf 'ZBB_AGENT_KEY_ID=%s\nZBB_AGENT_SECRET=%s\n' "${key_id}" "${secret}" >> "${temporary}"
chown root:root "${temporary}"
chmod 0600 "${temporary}"
mv -f "${temporary}" "${environment}"

if ! systemctl restart "${service}" || ! systemctl is-active --quiet "${service}"; then
    install -m 0600 -o root -g root "${backup_dir}/agent.env" "${environment}"
    systemctl restart "${service}"
    echo "Rotation fehlgeschlagen; vorherige Konfiguration wiederhergestellt." >&2
    exit 1
fi

rm -f "${credentials}"
ready=false
for _ in {1..20}; do
    if curl --fail --silent --show-error --max-time 2 http://127.0.0.1:8000/health/live >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 0.25
done

if [[ "${ready}" != true ]]; then
    echo "Agent wurde gestartet, war aber nicht rechtzeitig erreichbar." >&2
    exit 1
fi

echo "Phase-5c-Credentials erfolgreich aktiviert."
echo "Recovery-Sicherung: ${backup_dir}"
