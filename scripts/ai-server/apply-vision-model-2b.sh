#!/usr/bin/env bash
set -Eeuo pipefail

readonly ENV_FILE='/etc/zbb-ai-agent/agent.env'
readonly MODEL='qwen3-vl:2b-instruct'

if [[ ${EUID} -ne 0 ]]; then echo 'Dieses Skript muss mit sudo ausgeführt werden.' >&2; exit 1; fi
for command in systemctl install grep sed curl ollama date; do command -v "${command}" >/dev/null || { echo "Pflichtprogramm fehlt: ${command}" >&2; exit 1; }; done
[[ -f "${ENV_FILE}" ]] || { echo "Environment-Datei fehlt: ${ENV_FILE}" >&2; exit 1; }
ollama list | grep -Fq "${MODEL}" || { echo "Visionmodell fehlt: ${MODEL}" >&2; exit 1; }

backup="/var/backups/zbb-ai-agent/$(date -u +%Y%m%dT%H%M%SZ)-pre-vision-model.env"
install -D -m 0600 -o root -g root "${ENV_FILE}" "${backup}"
if grep -q '^ZBB_OLLAMA_VISION_MODEL=' "${ENV_FILE}"; then
    sed -i "s/^ZBB_OLLAMA_VISION_MODEL=.*/ZBB_OLLAMA_VISION_MODEL=${MODEL}/" "${ENV_FILE}"
else
    printf '%s\n' "ZBB_OLLAMA_VISION_MODEL=${MODEL}" >> "${ENV_FILE}"
fi
systemctl restart zbb-ai-agent.service
systemctl is-active --quiet zbb-ai-agent.service
for attempt in 1 2 3 4 5; do curl -fsS --max-time 3 http://127.0.0.1:8000/health/live >/dev/null 2>&1 && break; [[ ${attempt} -eq 5 ]] && exit 1; sleep 1; done
echo "Visionmodell aktiviert: ${MODEL}"
echo "Recovery-Sicherung: ${backup}"
