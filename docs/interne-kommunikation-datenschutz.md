# Interne Kommunikation und Datenschutz

## Technische Umsetzung

- Chatunterhaltungen sind standardmäßig nicht öffentlich. Zugriff erhalten ausschließlich explizite Mitglieder.
- Teilnehmendenkonten sind vom Mitarbeitenden-Chat ausgeschlossen, auch wenn versehentlich eine Berechtigung gesetzt wird.
- Administratoren besitzen kein technisch verstecktes Leserecht auf fremde Unterhaltungen.
- Nachrichtenbenachrichtigungen enthalten keinen Nachrichtentext.
- Anhänge liegen auf dem privaten Laravel-Datenträger und werden nur nach erneuter Mitgliedschaftsprüfung ausgeliefert.
- Chatnachrichten erhalten beim Senden ein festes Löschdatum. `chat:purge-expired` entfernt Nachricht und Anhänge physisch; der Scheduler führt dies täglich aus.
- Eine Materialanforderung kann im Chat nur verknüpft werden, wenn jedes Mitglied der Unterhaltung den Vorgang bereits fachlich einsehen darf.
- Die Standardfrist beträgt 365 Tage und wird über `INTERNAL_CHAT_RETENTION_DAYS` festgelegt. Bestehende Unterhaltungen behalten die bei ihrer Anlage geltende Frist.
- Mitarbeitende können eigene Nachrichten sofort physisch löschen und ihre erreichbaren Chatdaten als JSON exportieren.
- Materialanforderungs-Kommentare sind fachliche Vorgangsdokumentation. Sie folgen der Aufbewahrung des Vorgangs und werden einschließlich privater Anhänge beim Löschen der Materialanforderung entfernt.
- Offene fachliche Rückfragen sperren den Statuswechsel zu „Bestellt“. Chatnachrichten können keine Freigabe oder Preisänderung auslösen.

## Vor dem Produktivbetrieb organisatorisch festzulegen

1. Rechtsgrundlage und konkrete dienstliche Zwecke der Verarbeitung dokumentieren.
2. Betriebs- oder Dienstvereinbarung mit zulässiger Nutzung, Verbot privater Nutzung, Empfängerkreisen und Kontrollgrenzen beschließen.
3. Datenschutzinformation für Mitarbeitende ergänzen (Zwecke, Rechtsgrundlage, Speicherdauer, Empfänger, Betroffenenrechte und Kontakt des Datenschutzbeauftragten).
4. Löschfrist fachlich freigeben und `INTERNAL_CHAT_RETENTION_DAYS` entsprechend setzen.
   Außerdem muss der Laravel-Scheduler auf dem Server tatsächlich minütlich aufgerufen werden.
5. Backup-Aufbewahrung und Wiederherstellung so gestalten, dass gelöschte Inhalte nicht unkontrolliert dauerhaft zurückkehren.
6. HTTPS, sichere Sitzungen, regelmäßige Sicherheitsupdates, Backup-/Restore-Tests und Berechtigungsreviews gewährleisten.
7. Verfahren für Auskunft, Berichtigung, Löschung, Aufbewahrungssperren und Datenschutzvorfälle festlegen.
8. Keine Leistungs- oder Verhaltenskontrolle aus Chat-Metadaten durchführen; jede ausnahmsweise Protokollauswertung benötigt einen vorher festgelegten Zweck und begrenzte Zugriffsrechte.

## Maßgebliche Leitlinien

- Art. 5 DSGVO: Zweckbindung, Datenminimierung, Speicherbegrenzung, Integrität und Vertraulichkeit.
- Art. 25 DSGVO: Datenschutz durch Technikgestaltung und datenschutzfreundliche Voreinstellungen.
- Art. 32 DSGVO: angemessene technische und organisatorische Sicherheitsmaßnahmen.
- § 26 BDSG und Art. 88 DSGVO: Verarbeitung im Beschäftigungskontext.

Primärquellen:

- https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX:32016R0679
- https://www.bfdi.bund.de/DE/Buerger/Inhalte/Arbeit-Beschaeftigung/Beschaeftigtendatenschutz/FAQ_Beschaeftigtendatenschutz.html
- https://www.bfdi.bund.de/SharedDocs/Downloads/DE/DokumenteBfDI/AccessForAll/2023/2021_Loeschkonzept-BfDI.html

Die Anwendung unterstützt diese Anforderungen technisch. Ob der konkrete Betrieb DSGVO-konform ist, entscheidet zusätzlich die tatsächliche Konfiguration und die organisatorische Umsetzung durch den Verantwortlichen.
