# Agentic-AI-Plattform – Discovery und Zielarchitektur

Stand: 23.08.2026  
Status: Phase 1 und 2 abgeschlossen; Ollama-Hardening aus Phase 3 angewendet

## 1. Sicherheitsleitlinie

Laravel bleibt die einzige Instanz, die Benutzer-, Rollen-, Projekt- und
Teilnehmerberechtigungen bestimmt. Weder der Agent-Service noch Ollama erhalten
einen allgemeinen Datenbankzugang. Personenbezogene Daten werden nur für einen
konkreten, autorisierten Vorgang und möglichst nur für dessen Zeitraum
bereitgestellt.

Vor Änderungen an Diensten, Firewall, Netzwerk oder Produktivdaten gelten diese
Gates:

1. Ist-Zustand und Zielzustand dokumentieren.
2. Backup und Wiederherstellungsweg nachweisen.
3. Änderung und Rollback-Befehl vorbereiten.
4. Sicherheitskritische Änderung freigeben lassen.
5. Änderung einzeln ausführen und unmittelbar testen.
6. Bei Fehlern zuerst zurückrollen, nicht mehrere unbekannte Änderungen stapeln.

## 2. Discovery

### 2.1 Lokaler Web-/Datenserver

- Host: `ZBB-MO-MASRI`, Windows/XAMPP
- Projekt: Laravel 12.63.0, PHP 8.2.12
- Webserver-Binary: Apache 2.4.58
- Datenbank-Binary: MariaDB 10.4.32
- Authentifizierung: Laravel Jetstream/Sanctum
- Autorisierung: Spatie Permission plus eigene Middleware und
  `RoleDataAccessSetting`
- Zentraler Datensatzfilter: `Personen::visibleForUser(User $user)`
- Aktiver Projektkontext: `ActiveProjectContext`
- Bestehende LuV-Daten und DOCX-Vorlage sind vorhanden.
- Ein versionierter Abschlussbericht mit Snapshot, SHA-256 und Vier-Augen-
  Freigabe ist bereits vorhanden und kann als fachliches Muster dienen.
- Es existiert noch keine AI-, Agent-, RAG- oder Ollama-Integration.
- `routes/api.php` enthält derzeit nur den Sanctum-geschützten Benutzer-Endpunkt.
- Persönliche API-Tokenverwaltung ist absichtlich deaktiviert.
- Die lokale Konfiguration verwendet `QUEUE_CONNECTION=sync`; lange AI-Aufträge
  dürfen daher nicht direkt in normalen Webrequests ausgeführt werden.
- Apache und MariaDB waren während der Discovery lokal nicht aktiv. Sie wurden
  bewusst nicht gestartet.
- Im Projekt wurde kein aktuelles Datenbankbackup gefunden; vorhanden ist nur
  eine manuelle Backup-Checkliste.

Vorhandene, nicht von dieser Arbeit stammende Änderungen bleiben unangetastet:

- `config/filesystems.php`
- `tests/Unit/ProfilePhotoUrlConfigurationTest.php`

### 2.2 Relevante bestehende Sicherheitsbausteine

- Berechtigungen auf Routenebene über `can`, `canAnyPermission` und
  `routePermission`
- Teilnehmer-Sichtbarkeit nach Rolle, Projekten, Standorten und Abteilung
- Projektzuordnung über `projekt_has_personens`
- Projektkontext über den angemeldeten Benutzer
- Abschlussberichte mit Version, Status, Ersteller, Prüfer, Snapshot und Hash
- DOCX-Erzeugung über PHPWord sowie PDF-Erzeugung über Dompdf/FPDI

### 2.3 Bestehende LuV-Risiken vor einer AI-Erweiterung

Die aktuelle LuV-Implementierung darf nicht unverändert als AI-Tool verwendet
werden:

- `store()` prüft zwar das aktive Projekt, verwendet aber nicht den zentralen
  Teilnehmer-Sichtbarkeitsfilter.
- `destroy()` lädt einen LuV-Datensatz nur anhand seiner ID.
- `export()` lädt einen LuV-Datensatz nur anhand seiner ID.
- Für diese beiden Aktionen ist im Controller keine erneute Projekt- und
  Teilnehmerprüfung erkennbar.
- `update()` ist noch nicht implementiert.
- Die Tabelle besitzt noch keinen Draft-/Freigabe-, Quellen-, Regelversions-
  oder Faktenvalidierungsstatus.

Diese Punkte werden vor der Freigabe eines AI-LuV-Workflows durch einen zentralen
LuV-Autorisierungsservice und Feature-Tests abgesichert.

### 2.4 AI-/Ollama-Server

- Hostname: `ollama`
- IP: `10.100.1.30`
- Betriebssystem: Ubuntu 26.04 LTS
- Kernel: Linux 7.0.0-30-generic
- Hardware: Fujitsu Lifebook A3510
- CPU: Intel Core i3-1005G1, 2 Kerne/4 Threads
- RAM: 6,9 GiB, Swap: 4 GiB
- GPU: Intel Iris Plus Graphics G1, keine dedizierte NVIDIA-GPU
- Storage: 256-GB-NVMe; Root-LV 100 GB, davon etwa 82 GB frei
- Ollama: 0.32.14, systemd-aktiv und aktiviert
- Modell: `qwen3:1.7b`, Q4_K_M, Tool-Calling-fähig
- Ollama-Service-User: `ollama`, Login-Shell deaktiviert
- Docker ist installiert; der Benutzer `aminmasri` besitzt keinen Docker-Socket-
  Zugriff. Podman ist vorhanden, derzeit ohne Container.
- Python 3.14.4 ist vorhanden; FastAPI, Uvicorn, Pydantic und Vector-Store-
  Bibliotheken sind nicht installiert.
- Kein Reverse Proxy und kein Agent-Service gefunden.
- UFW ist aktiviert. Die konkreten Regeln konnten ohne privilegierten Zugriff
  nicht gelesen werden.
- Automatische Sicherheitsupdates sind aktiv; normale Paketupdates stehen aus.
- Kein Neustart ist erforderlich.
- Kein Anwendungs-/Modellbackup und keine erkennbare Snapshot-Lösung vorhanden;
  `/var/backups` enthält nur Paketdatenbank-Sicherungen.

### 2.5 Kritische AI-Server-Befunde

1. `/etc/systemd/system/ollama.service.d/override.conf` setzt
   `OLLAMA_HOST=0.0.0.0`.
2. Ollama lauscht dadurch auf `*:11434` und ist aus dem Netzwerk erreichbar.
3. Ollama besitzt keine eigene Authentifizierungsschicht.
4. `systemd-analyze security ollama` bewertet den Dienst mit `9.2 UNSAFE`.
5. Es fehlen unter anderem `NoNewPrivileges`, `ProtectSystem`, `ProtectHome`,
   `PrivateTmp`, Capability-Begrenzungen und Ressourcenlimits.
6. Der Dienst hatte bereits einen RAM-Spitzenverbrauch von ungefähr 4,6 GiB.
7. Das vorhandene 2B-Modell ist für Infrastrukturtests geeignet, aber seine
   fachliche Qualität für faktenkritische Berichte muss separat und mit
   realistischen, anonymisierten Testfällen bewertet werden.

### 2.5.1 Umgesetztes Ollama-Hardening am 23.08.2026

- Recovery-Baseline:
  `/home/aminmasri/zbb-ai-backups/20260823T102000Z`
- Privilegierte Recovery-Sicherung:
  `/var/backups/zbb-ai/20260823T102032Z-pre-hardening`
- Bindung von `0.0.0.0:11434` auf `127.0.0.1:11434` geändert.
- `NoNewPrivileges`, `PrivateTmp`, `ProtectSystem`, `ProtectHome`, Kernel-
  Schutzoptionen, leere Capability-Sets und restriktive UMask aktiviert.
- Ressourcen begrenzt: `MemoryHigh=5G`, `MemoryMax=6G`, `TasksMax=256`.
- API, Modellliste und Listener nach dem Neustart erfolgreich geprüft.
- Port 11434 ist vom Webserver/LAN nicht mehr erreichbar.
- Keine neuen Ollama-Warnungen nach der Änderung.
- systemd-Exposure von `9.2 UNSAFE` auf `3.9 OK` reduziert.
- Ein Rollback-Hinweis und die vorherigen Dateien liegen in der privilegierten
  Recovery-Sicherung.
- Ollama Cloud wurde mit `OLLAMA_NO_CLOUD=1` deaktiviert und der Logwert
  `Ollama cloud disabled: true` verifiziert.
- Ein synthetischer Tool-Calling-Test mit `qwen3:1.7b` war erfolgreich: Das
  Modell forderte ausschließlich `get_project_report_rules` mit leerem
  Argumentobjekt an.
- Der Modelltest benötigte rund 5 Sekunden, etwa 1,9 GB Ollama-RAM und erzeugte
  keine Warnungen oder nennenswerte Swap-Nutzung.

### 2.6 Netzwerkbefund

- Der lokale Rechner kann den AI-Server per SSH erreichen.
- Der AI-Server sieht die SSH-Verbindung mit der Quelladresse `172.19.10.7`.
- Vom AI-Server waren `172.19.10.7:80` und `:443` während der Discovery nicht
  erreichbar.
- Eine Architektur, in der der Agent unkontrolliert zum Laravel-Webserver
  zurückruft, ist daher weder erforderlich noch aktuell funktionsfähig.

## 3. Zielarchitektur

```text
Benutzerbrowser
      |
      | bestehende Laravel-Session
      v
Laravel AI-Orchestrator (Authorization Authority)
      |
      | HTTPS, mTLS oder kurzlebig signierte Service-Anfrage
      v
AI-Gateway auf 10.100.1.30:443
      |
      v
FastAPI Agent-Service auf 127.0.0.1:8000
      |
      +--> Ollama auf 127.0.0.1:11434
      |
      +--> projektisolierte RAG-Datenbank

Tool-Anforderung des Modells
      |
      v
Laravel führt das Tool lokal aus
      |
      +--> Permission erneut prüfen
      +--> aktives Projekt erneut prüfen
      +--> Personen::visibleForUser erneut anwenden
      +--> Zeitraum und erlaubte Felder begrenzen
      |
      v
nur minimales Tool-Ergebnis zurück an den Agent-Service
```

### 3.1 Warum Laravel die Tools lokal ausführt

Der Agent-Service erhält keine Laravel- oder MySQL-Zugangsdaten. Er meldet nur
eine strukturierte Tool-Anforderung zurück. Laravel validiert Toolname und
Argumente, autorisiert erneut und führt die fachliche Abfrage lokal aus.

Vorteile:

- keine eingehende AI-Server-Verbindung zum Web-/Datenserver erforderlich;
- keine allgemeine Datenbankberechtigung außerhalb von Laravel;
- bestehende Session, Rollen, Permissions und Scopes bleiben maßgeblich;
- jeder Tool-Schritt kann erneut 403 liefern;
- Toolargumente können nicht unbemerkt Projekt oder Teilnehmer wechseln;
- personenbezogene Daten können vor der Übertragung minimiert werden.

### 3.2 Unveränderlicher Run-Kontext

Laravel erzeugt für jeden Lauf einen serverseitigen Kontext:

- `run_id`
- `user_id`
- `project_id`
- `participant_id` beziehungsweise `project_person_id`
- `report_type`
- `period_from` und `period_until`
- erlaubte Toolnamen
- Erstellzeit und Ablaufzeit

Das Modell darf diese Identitäten nicht ändern. Toolargumente werden gegen den
serverseitigen Kontext geprüft; fremde Projekt- oder Teilnehmer-IDs führen zu
403 beziehungsweise einem abgebrochenen Lauf.

### 3.3 Erste erlaubte Tools

- `get_participant_identity_summary`
- `get_participant_luv_data`
- `get_attendance_summary`
- `get_documentation_entries`
- `get_goals_and_progress`
- `get_project_report_rules`
- `get_report_template_metadata`

Es gibt kein `execute_sql`, kein beliebiges HTTP-Tool, keinen Shell-Zugriff und
keinen freien Dateizugriff.

## 4. Projektisoliertes RAG

Personenbezogene operative Daten werden nicht in den allgemeinen Vector Store
übernommen. Der RAG-Bestand enthält nur freigegebene Regeln, Templates und
Referenzmaterial.

Jeder Chunk besitzt mindestens:

- `scope`: `global` oder `project`
- `project_id`: bei Projektwissen zwingend gesetzt
- `document_type`
- `document_id`
- `version`
- `valid_from`
- `valid_until`
- `status`: nur `approved` ist abrufbar
- `source_sha256`
- `chunk_index`

Die Filterung erfolgt im Code beziehungsweise in der Datenbankabfrage vor der
Vektorsuche. Ein Promptfilter allein ist unzulässig. Für einen historischen
Bericht muss die Regelversion den Berichtszeitraum abdecken.

Für den kleinen Server wird zunächst ein ressourcenschonender Vector Store mit
strikter relationaler Metadatenfilterung eingesetzt. Ein separates
Embedding-Modell wird erst nach Speicher- und Qualitätsbenchmark ausgewählt.

## 5. Bericht und Anti-Halluzination

Das LLM liefert kein freies endgültiges Dokument, sondern ein validiertes JSON-
Objekt. Jede fachliche Aussage enthält intern:

- stabile `claim_id`
- Aussage beziehungsweise Abschnitt
- verwendete `source_ids`
- Kennzeichnung `supported`, `insufficient_data` oder `rejected`

Eine zweite deterministische Validierungsstufe prüft:

1. Jede Tatsachenbehauptung besitzt mindestens eine erlaubte Quelle.
2. Quelldatum liegt im Berichtszeitraum oder ist ausdrücklich als historischer
   Bezug zugelassen.
3. Quelle gehört zum unveränderlichen Teilnehmer- und Projektkontext.
4. Zahlen wie Anwesenheiten werden im Laravel-Code berechnet, nicht vom LLM.
5. Nicht belegte Aussagen werden entfernt oder durch den definierten
   Datenmangel-Hinweis ersetzt.

DOCX und PDF werden deterministisch aus versionierten Vorlagen erzeugt. Der
Status lautet zunächst immer `draft`. Eine menschliche Freigabe und ein vom
Ersteller getrennter Prüfer sind für `final` erforderlich.

## 6. Audit-Konzept

Technische Logs enthalten keine vollständigen Teilnehmertexte. Eine eigene
Audit-Tabelle erfasst mindestens:

- `run_id`, Aktion, Zeitpunkt und Ergebnis
- Benutzer-, Projekt- und Datensatz-ID
- Berichtszeitraum
- Toolname und normalisierte, nicht sensible Argumentmetadaten
- erlaubt/verweigert und Grundcode
- Modell- und Promptversion
- RAG-Regel- und Templateversion
- Quell-IDs und Hashes
- erzeugte Berichtsversion und Status
- Laufzeit, Token-/Ressourcennutzung und Fehlerklasse

Tokens, Cookies, Passwörter, Dokumentvolltexte und Modell-Rohprompts mit
personenbezogenen Inhalten werden nicht in technische Logs geschrieben.

## 7. Geplante Umsetzung mit Gates

### Gate A – Recovery vor Serveränderung

Vor der ersten Änderung:

- vollständige Kopie der Ollama-Unit und des Drop-ins;
- Liste und Prüfsummen der vorhandenen Modelle;
- dokumentierter Rückweg zur bisherigen Unit;
- Entscheidung über LVM-Snapshot oder dateibasierte Sicherung;
- Datenbankbackup des Webservers vor Migrationen;
- Test, dass das Backup lesbar ist;
- unveränderte Git-Arbeitskopie beziehungsweise separater Arbeitszweig.

### Gate B – Ollama-Netzwerk und Hardening

Geplanter Zielzustand:

- Ollama bindet ausschließlich `127.0.0.1:11434`;
- ein HTTPS-Gateway exponiert nur den Agent-Service;
- Firewall erlaubt Gateway-Zugriff nur von der festgelegten Webserver-IP;
- eigener Service-User für den Agent-Service;
- restriktive Dateirechte und getrennte Datenverzeichnisse;
- systemd-Hardening und RAM-/CPU-Limits;
- Healthcheck und Rollbacktest.

Die Änderung erfolgt erst nach ausdrücklicher Freigabe und mit weiterhin
geöffneter SSH-Sitzung.

### Gate C – Lokaler Laravel-Prototyp

- neue AI-Permissions getrennt nach Anzeigen, Erstellen, Bearbeiten, Freigeben
  und Löschen;
- zentraler `AiRunContext` und autorisierte Tool Registry;
- keine generische SQL- oder HTTP-Funktion;
- Testdatenbank, nicht die Produktionsdatenbank;
- erste Tests: 403, Projektisolation, Zeitraum und Toolargument-Manipulation.

### Gate D – Agent-Service

- minimale FastAPI-Anwendung mit strukturierten Ein-/Ausgaben;
- keine personenbezogenen Inhalte in Logs;
- Modell-Timeout, Größenlimits und Abbruch;
- Tool Calling nur aus expliziter Allowlist;
- Prompt-Injection-Inhalte werden als Daten gekennzeichnet;
- Ollama nur über localhost.

### Gate E – LuV-MVP

- ausschließlich anonymisierte beziehungsweise freigegebene Testdaten;
- projekt- und versionsgefiltertes RAG;
- Claim-zu-Quelle-Nachweis;
- Faktenvalidator;
- Draft-Workflow und Vier-Augen-Freigabe;
- deterministischer DOCX-/PDF-Export.

## 8. Verbindliche Tests

1. Erlaubter Teilnehmer liefert Daten; fremder Teilnehmer liefert 403.
2. Manipulierte `participant_id` im Tool Call liefert 403.
3. Projekt-17-Lauf kann keine Projekt-18-Regeln abrufen.
4. Globale Regeln werden nur aus explizit global freigegebenen Quellen geladen.
5. Historische Regelversion wird anhand des Berichtszeitraums gewählt.
6. Ereignis außerhalb des Zeitraums erscheint nicht als aktuelles Ereignis.
7. Fehlende Fakten erzeugen keine erfundene Aussage.
8. Prompt Injection in Notiz oder Dokument verändert keine Toolberechtigung.
9. Modell- oder Agent-Ausfall verändert keine Produktivdaten.
10. Bericht bleibt bis zur menschlichen Freigabe `draft`.
11. Ersteller kann seinen eigenen finalen Bericht nicht allein freigeben.
12. Audit enthält IDs und Hashes, aber keine Secrets oder unnötigen Volltexte.

## 9. Noch offene Entscheidungen vor Implementierung

- endgültige feste IP beziehungsweise Netzwerkidentität des lokalen Webservers;
- TLS-Variante: interne CA mit mTLS oder TLS plus kurzlebige signierte Requests;
- Backupziel und Aufbewahrungsdauer für AI-Service, RAG und Modelle;
- freigegebene offizielle LuV-Vorlagen und Projektregel-Dokumente;
- repräsentative anonymisierte Qualitätsfälle für Modellvergleich;
- privilegierte Prüfung der aktuellen UFW-Regeln;
- Wahl einer asynchronen Laravel-Queue für längere AI-Aufträge.

## 10. Phase 4 – lokaler Agent-Service

Der erste FastAPI-Service liegt unter `ai-agent/` und wurde zunächst ohne
Deployment auf den AI-Server implementiert.

Umgesetzt:

- HMAC-SHA256-Serviceauthentifizierung mit Zeitfenster und Nonce-Replay-Schutz
- maximale Requestgröße
- fest definierte Tool-Allowlist
- keine Modellparameter für Projekt-, Teilnehmer- oder Zeitraum-IDs
- Validierung, dass Tool-Ergebnisse zur Allowlist des Laufs gehören
- strukturierter Draft-Report
- Pflichtquellen für unterstützte Aussagen
- Ablehnung unbekannter Quellen-IDs
- expliziter `insufficient_data`-Status
- ausschließlich loopbackfähige Ollama-Konfiguration
- deaktivierte OpenAPI-/Dokumentationsendpunkte
- Liveness- und authentifizierter Readiness-Endpunkt

Lokale Verifikation:

```text
10 Tests bestanden
```

Geprüft wurden insbesondere Signaturmanipulation, Replay, fehlende Signatur,
Tool-Allowlist, manipulierte Teilnehmerargumente, unbekannte Quellen und
korrekte Datenmangel-Markierung.

### 10.1 Deployment und Ende-zu-Ende-Verifikation

Der Agent-Service wurde am 23.08.2026 als Version `0.1.1` auf dem AI-Server
bereitgestellt:

- Release: `/opt/zbb-ai-agent/releases/0.1.1`
- aktiver Symlink: `/opt/zbb-ai-agent/current`
- Service-User: `zbb-agent` mit gesperrter Login-Shell
- systemd-Dienst: `zbb-ai-agent.service`
- Listener: ausschließlich `127.0.0.1:8000`
- Ollama: ausschließlich `127.0.0.1:11434`
- beide Ports vom LAN nicht erreichbar
- Agent-systemd-Exposure: `3.0 OK`
- Ressourcenlimit: 384 MB High / 512 MB Maximum, 64 Tasks
- normale Laufzeitnutzung: ungefähr 39–45 MB RAM
- Release `0.1.0` bleibt als Rollback erhalten
- Recovery-Sicherungen:
  `/var/backups/zbb-ai-agent/20260823T104800Z-pre-deploy` und
  `/var/backups/zbb-ai-agent/20260823T105556Z-pre-deploy`

Ende-zu-Ende geprüft:

```text
readiness=ok ollama_version=0.32.14
agent_turn=ok tool=get_project_report_rules arguments={}
secret_exposed=false
```

Ein im Test gefundener Konflikt zwischen Ollamas `format`-Parameter und Tool
Calling wurde in Version `0.1.1` behoben. Das JSON-Schema wird nun im Prompt
vorgegeben und die Antwort anschließend weiterhin strikt durch Pydantic
validiert. Das Deployment-Skript startet einen bereits laufenden Dienst bei
Releasewechsel jetzt explizit neu.

## 11. Phase 5 – Laravel-Tool-Autorisierung

Der erste kontrollierte Laravel-Toolpfad ist lokal implementiert. Es wurde
bewusst noch keine HTTP-Route und keine Produktionsmigration aktiviert.

Umgesetzt:

- unveränderlicher `AiRunContext` mit serverseitig gebundener Benutzer-ID,
  Projekt-ID und expliziter Tool-Allowlist;
- zentrale `AiToolRegistry`, die ausschließlich registrierte Tools ausführt;
- erneute Berechtigungsprüfung unmittelbar bei jeder Tool-Ausführung;
- direkte Prüfung der Projektzuordnung ohne automatischen Projekt-Fallback;
- Bindung an das aktuell aktive und aktive Projekt;
- Permission `ai.report.use` als Fail-Closed-Voraussetzung;
- erstes argumentloses Tool `get_project_report_rules`;
- vollständige Ablehnung aller vom Modell gelieferten Argumente;
- Ausgabe nur von Projekt-ID, effektiven Projektregeln und Features.

Verifikation:

```text
8 neue Tests bestanden (12 Assertions)
5 ActiveProjectContext-Regressionstests bestanden (49 Assertions)
2 Abschlussbericht-Regressionstests bestanden (19 Assertions)
```

Die Negativtests decken eine fehlende Lauf-Allowlist, ein fremdes bzw. nicht
aktives Projekt, fehlende KI-Permission und eingeschleuste Modellargumente ab.

### 11.1 Signierter Laravel-Client und Orchestrator

Der Laravel-Client ist lokal implementiert, aber noch nicht per Route oder
Produktionskonfiguration aktiviert.

- Ziel ausschließlich `http://127.0.0.1:18000` über einen später überwachten
  SSH-Tunnel zum Loopback-Port des AI-Servers;
- direkte LAN-Ziele werden bei der Konfigurationsprüfung abgelehnt;
- mit dem Python-Agent identische HMAC-Kanonisierung aus Zeitstempel, Nonce,
  HTTP-Methode, Pfad und SHA-256 des exakt gesendeten Bodys;
- keine automatischen POST-Wiederholungen;
- feste Connect-, Gesamtzeit- und Antwortgrößenlimits;
- generische Fehler ohne Weitergabe von Upstream-Antworten oder Secrets;
- strikter Payload-Vertrag für UUID, Projekt, Teilnehmer, Berichtstyp,
  Zeitraum, Prompt, Allowlist und Tool-Ergebnisse;
- Antwort muss an dieselbe Run-ID gebunden sein;
- Toolaufrufe außerhalb der Allowlist werden abgewiesen.

Der Orchestrator prüft Projekt, Permission und Teilnehmerzugriff vor dem ersten
Agent-Aufruf. Tool Calls werden nur über die Laravel-Registry ausgeführt. Er
begrenzt einen Lauf auf sechs Agent-Turns und verwirft wiederholte Call-IDs.
Damit kann auch eine sofortige Modellantwort die Laravel-Autorisierung nicht
umgehen.

```text
20 fokussierte AI-Tests bestanden (28 Assertions)
```

### 11.2 Geschuetzter Entwurfsendpunkt

Phase 5c ist lokal vorbereitet und noch nicht produktiv migriert oder
konfiguriert.

- authentifizierter `POST /ki/berichte/entwurf`;
- zentrale Route-Permission `ai.report.use`;
- Rate-Limit von drei Anforderungen pro Minute und Benutzer/IP-Kontext;
- validierte Teilnehmer-ID, Berichtstyp, Zeitraum und Anforderung;
- Antwort immer mit Status `draft` und `Cache-Control: no-store, private`;
- generische `503`-Antwort bei Agentfehlern;
- reversible Permission-Migration;
- initiale Zuweisung nur an vorhandene Rollen `Administrator` und `Developer`;
- Permission und Kategorie ebenfalls im zentralen Seeder-Katalog;
- Laravel validiert finale Berichtsstruktur, unveraenderlichen Berichtstyp,
  Claim-Status und jede zitierte Quellen-ID erneut.

```text
26 fokussierte AI-Tests bestanden (45 Assertions)
5 Permission-Katalogtests bestanden (6 Assertions)
7 Projekt- und Berichtsregressionstests bestanden (68 Assertions)
Laravel route:list funktioniert ohne konfigurierte AI-Credentials
```
