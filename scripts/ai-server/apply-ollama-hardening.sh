#!/usr/bin/env bash
set -Eeuo pipefail

readonly OLLAMA_UNIT='/etc/systemd/system/ollama.service'
readonly DROPIN_DIR='/etc/systemd/system/ollama.service.d'
readonly NETWORK_DROPIN="${DROPIN_DIR}/override.conf"
readonly HARDENING_DROPIN="${DROPIN_DIR}/10-zbb-hardening.conf"
readonly BACKUP_ROOT='/var/backups/zbb-ai'
readonly EXPECTED_UNIT_SHA256='11758d469d3f103e53a9612a8ffcb3a3e61834c994c08d412bb051f3c827dbd3'
readonly EXPECTED_NETWORK_SHA256='7b624513a8681436d5b05a65b41448aea5241186ccfb04b3f803ed72cd41cfe2'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Dieses Skript muss mit sudo ausgeführt werden.' >&2
    exit 1
fi

for command in systemctl systemd-analyze sha256sum install curl ss awk date; do
    command -v "${command}" >/dev/null || {
        echo "Pflichtprogramm fehlt: ${command}" >&2
        exit 1
    }
done

[[ -f "${OLLAMA_UNIT}" ]] || {
    echo "Ollama-Unit fehlt: ${OLLAMA_UNIT}" >&2
    exit 1
}

[[ -f "${NETWORK_DROPIN}" ]] || {
    echo "Ollama-Netzwerk-Drop-in fehlt: ${NETWORK_DROPIN}" >&2
    exit 1
}

current_unit_sha256="$(sha256sum "${OLLAMA_UNIT}" | awk '{print $1}')"
current_network_sha256="$(sha256sum "${NETWORK_DROPIN}" | awk '{print $1}')"

if [[ "${current_unit_sha256}" != "${EXPECTED_UNIT_SHA256}" ]]; then
    echo 'Abbruch: Die Ollama-Unit wurde seit der Discovery verändert.' >&2
    exit 1
fi

if [[ "${current_network_sha256}" != "${EXPECTED_NETWORK_SHA256}" ]]; then
    echo 'Abbruch: Das Ollama-Netzwerk-Drop-in wurde seit der Discovery verändert.' >&2
    exit 1
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${BACKUP_ROOT}/${timestamp}-pre-hardening"
install -d -m 0700 -o root -g root "${backup_dir}"
install -m 0600 -o root -g root "${OLLAMA_UNIT}" "${backup_dir}/ollama.service"
install -m 0600 -o root -g root "${NETWORK_DROPIN}" "${backup_dir}/override.conf"

if [[ -f "${HARDENING_DROPIN}" ]]; then
    install -m 0600 -o root -g root "${HARDENING_DROPIN}" "${backup_dir}/10-zbb-hardening.conf"
fi

sha256sum "${backup_dir}"/* > "${backup_dir}/sha256sums.txt"
chmod 0600 "${backup_dir}/sha256sums.txt"

rollback() {
    echo 'Validierung fehlgeschlagen – stelle vorherige Ollama-Konfiguration wieder her.' >&2
    install -m 0644 -o root -g root "${backup_dir}/override.conf" "${NETWORK_DROPIN}"

    if [[ -f "${backup_dir}/10-zbb-hardening.conf" ]]; then
        install -m 0644 -o root -g root \
            "${backup_dir}/10-zbb-hardening.conf" "${HARDENING_DROPIN}"
    else
        rm -f -- "${HARDENING_DROPIN}"
    fi

    systemctl daemon-reload
    systemctl restart ollama
    echo "Rollback abgeschlossen. Sicherung: ${backup_dir}" >&2
}

trap rollback ERR

cat > "${NETWORK_DROPIN}" <<'EOF'
[Service]
Environment="OLLAMA_HOST=127.0.0.1:11434"
EOF

cat > "${HARDENING_DROPIN}" <<'EOF'
[Service]
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/usr/share/ollama
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectKernelLogs=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true
RestrictRealtime=true
RestrictNamespaces=true
CapabilityBoundingSet=
AmbientCapabilities=
SystemCallArchitectures=native
UMask=0077
MemoryHigh=5G
MemoryMax=6G
TasksMax=256
LimitNOFILE=65536
EOF

chown root:root "${NETWORK_DROPIN}" "${HARDENING_DROPIN}"
chmod 0644 "${NETWORK_DROPIN}" "${HARDENING_DROPIN}"

systemd-analyze verify "${OLLAMA_UNIT}"
systemctl daemon-reload
systemctl restart ollama
systemctl is-active --quiet ollama

for attempt in 1 2 3 4 5; do
    if curl --fail --silent --show-error --max-time 3 \
        'http://127.0.0.1:11434/api/version' > /dev/null; then
        break
    fi

    if [[ ${attempt} -eq 5 ]]; then
        echo 'Lokaler Ollama-Healthcheck ist fehlgeschlagen.' >&2
        false
    fi

    sleep 1
done

ss -lntH | awk '
    $4 == "127.0.0.1:11434" { local_listener = 1 }
    $4 == "0.0.0.0:11434" || $4 == "*:11434" || $4 == "[::]:11434" { exposed_listener = 1 }
    END { exit !(local_listener && !exposed_listener) }
'

trap - ERR

cat > "${backup_dir}/ROLLBACK.txt" <<EOF
Rollback für Ollama-Hardening:

sudo install -m 0644 -o root -g root '${backup_dir}/override.conf' '${NETWORK_DROPIN}'
sudo rm -f -- '${HARDENING_DROPIN}'
sudo systemctl daemon-reload
sudo systemctl restart ollama
EOF
chmod 0600 "${backup_dir}/ROLLBACK.txt"

echo 'Ollama-Hardening erfolgreich angewendet.'
echo "Recovery-Sicherung: ${backup_dir}"
systemctl status ollama --no-pager -l | sed -n '1,25p'
ss -lntH | awk '$4 ~ /:11434$/ { print }'
curl --fail --silent --show-error 'http://127.0.0.1:11434/api/version'
echo
systemd-analyze security ollama --no-pager | tail -n 3
