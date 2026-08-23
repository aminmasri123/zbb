# KI-Plattform – manuelles Produktionsdeployment

Die Webanwendung und der Ollama-/Agent-Rechner bleiben getrennt. Ollama und der Agent lauschen ausschließlich auf Loopback. Der Webserver erreicht den Agenten nur über einen überwachten SSH-Tunnel.

## 1. KI-Rechner (`10.100.1.30`)

Das Release-Archiv 0.2.1, die Unit und das Deployment-Skript müssen zunächst nach `/home/aminmasri` kopiert werden. Danach:

```bash
ollama pull qwen3:1.7b
ollama pull qwen3-vl:2b-instruct
sudo /home/aminmasri/deploy-agent-service.sh
sudo python3 /home/aminmasri/verify-agent-service.py
```

Erwartet werden `readiness=ok`, `agent_turn=ok`, `workspace_chat=ok` und `secret_exposed=false`. Der Listener muss ausschließlich `127.0.0.1:8000` sein.

## 2. Produktions-Webserver

Vorher Datenbank und `.env` sichern. Danach im Laravel-Release:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --path=database/migrations/2026_08_23_120000_add_ai_report_permission.php --force
php artisan migrate --path=database/migrations/2026_08_23_130000_create_ai_workspace_runs_table.php --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Die beiden HMAC-Werte werden aus `/etc/zbb-ai-agent/agent.env` übernommen, ohne sie in Shell-History, Tickets oder Logs auszugeben:

```dotenv
ZBB_AI_AGENT_BASE_URL=http://127.0.0.1:18000
ZBB_AI_AGENT_KEY_ID=laravel-v1
ZBB_AI_AGENT_SECRET=<identisches, mindestens 32 Byte langes Secret>
ZBB_AI_AGENT_CONNECT_TIMEOUT=3
ZBB_AI_AGENT_TIMEOUT=130
ZBB_AI_AGENT_MAX_RESPONSE_BYTES=1000000
AI_REPORT_QUEUE_CONNECTION=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1260
```

Damit LuV-Generierungen wirklich im Hintergrund laufen, muss der Worker als dauerhafter systemd-Dienst aktiv sein. Nach dem Pull genügt auf dem Webserver:

```bash
sudo bash scripts/web-server/install-zbb-queue-worker.sh /var/www/matrix
```

Das Skript migriert die versionierte `jobs`-Tabelle, cached die Konfiguration und installiert `zbb-laravel-queue.service`. Ein mit `&` gestarteter Worker reicht nicht, weil er beim Logout oder Neustart verschwinden kann.

Prüfung:

```bash
systemctl is-active zbb-laravel-queue.service
systemctl status zbb-laravel-queue.service --no-pager -l
sudo -u www-data php artisan queue:failed
```

Der bestehende Tunnel wird als systemd-Service mit `-L 127.0.0.1:18000:127.0.0.1:8000`, eigenem SSH-Schlüssel, `ExitOnForwardFailure=yes`, `ServerAliveInterval=30` und `Restart=always` eingerichtet. Niemals Agent oder Ollama an `0.0.0.0` binden.

## 3. Abnahme

1. Als Administrator anmelden und den Menüpunkt **KI-Arbeitsbereich** öffnen.
2. Textchat testen.
3. Ein maschinenlesbares PDF zusammenfassen und Seitenquellen prüfen.
4. Zwei PDFs vergleichen.
5. Ein unkritisches Testbild analysieren.
6. Im Teilnehmerportal ein Anschreiben aus einer Test-Stellenbeschreibung erstellen, bearbeiten und als PDF und DOCX herunterladen.
7. Prüfen, dass Benutzer ohne `ai.report.use` den internen KI-Arbeitsbereich weder sehen noch aufrufen können.

Alle Ergebnisse sind Entwürfe und müssen fachlich geprüft werden. Bild- und Dokumentinhalte werden nicht als vertrauenswürdige Anweisungen behandelt.
