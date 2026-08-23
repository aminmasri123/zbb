# ZBB AI Agent

Interner, zustandsarmer FastAPI-Service zwischen Laravel und dem ausschließlich
lokal gebundenen Ollama-Dienst.

## Sicherheitsgrenzen

- Der Service besitzt keinen MySQL-Zugang.
- Laravel bleibt für Benutzer- und Datensatzberechtigungen verantwortlich.
- Projekt-, Teilnehmer- und Zeitraum-IDs sind Bestandteil des unveränderlichen
  Laufkontexts.
- Tools sind fest vorgegeben und akzeptieren keine vom Modell gelieferten IDs.
- Jede Anfrage benötigt eine kurzlebige HMAC-Signatur und einen einmaligen Nonce.
- Unterstützte Berichtsaussagen müssen bekannte Quellen-IDs zitieren.
- Unbelegte Aussagen müssen als `insufficient_data` gekennzeichnet werden.
- OpenAPI- und Dokumentationsendpunkte sind im Service deaktiviert.

## Lokale Tests

Aus dem Repository-Root:

```powershell
.\.venv-ai-agent\Scripts\python.exe -m pytest ai-agent\tests
```

## HMAC-Vertrag

Pflichtheader:

- `X-ZBB-Key-Id`
- `X-ZBB-Timestamp`: Unix-Zeit in Sekunden
- `X-ZBB-Nonce`: pro Anfrage neuer Wert
- `X-ZBB-Signature`: hexadezimales HMAC-SHA256

Zu signierende kanonische Bytes:

```text
timestamp\nnonce\nHTTP_METHOD\n/path\nsha256(raw_body)
```

Der Service akzeptiert standardmäßig höchstens 60 Sekunden Zeitabweichung. Für
den geplanten Single-Worker-Betrieb wird der Nonce-Replay-Schutz im Prozess
geführt. Mehrere Worker erfordern später einen gemeinsamen Nonce Store.

## Endpunkte

- `GET /health/live`: keine internen Details
- `GET /health/ready`: HMAC-geschützt, prüft Ollama
- `POST /v1/agent/turn`: HMAC-geschützter Agent-Schritt

## Erforderliche Umgebungsvariablen

- `ZBB_AGENT_KEY_ID`
- `ZBB_AGENT_SECRET`: mindestens 32 Bytes, niemals im Repository speichern
- `ZBB_OLLAMA_BASE_URL`: muss eine HTTP-Loopback-Adresse sein
- `ZBB_OLLAMA_MODEL`
