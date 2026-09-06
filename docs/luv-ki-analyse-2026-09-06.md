# LUV-KI: Befund vom 6. September 2026

## Gesamturteil

Die Quellen- und Berechtigungsarchitektur ist eine brauchbare Grundlage. Die aktuell getestete Generierung ist noch nicht zuverlässig genug für einen vollständigen LUV-Entwurf. Quellenreferenzen belegen technisch, dass eine Quelle existiert; sie beweisen nicht, dass jede Aussage inhaltlich durch diese Quelle gedeckt ist.

Testdatensatz: BVB Test, Teilnehmer 2539, BvB Reha. Zwei ausdrücklich fiktive Verlaufsnotizen beschreiben Ausgangslage, messbaren Fortschritt, ein noch nicht vollständig erreichtes Ziel und ein lediglich geplantes Praktikum. Lauf ca4fefdd-a8ac-43e1-a687-7854cdf1517d dauerte 402 Sekunden und lieferte nur drei wiederhergestellte Abschnitte. Die Ausgabe war abgeschnitten. Fortschrittsvergleich, konkrete Förderziele und wesentliche Inhalte fehlten. Trotzdem wurde der Lauf als completed geführt. Kein Bericht wurde fachlich freigegeben oder versendet.

## Datenweg

Browser → Laravel-Hintergrundauftrag → berechtigte Datenabfragen für Teilnehmer und aktives Projekt → signierter HTTP-Aufruf über SSH-Tunnel vom Webserver 10.100.1.47 zum KI-Server 10.100.1.30 → Agent → lokales Ollama-Modell → Prüfung der Antwort und Quellen-IDs → Entwurf zur menschlichen Prüfung.

Im beobachteten Test war qwen3:1.7b mit CPU-Verarbeitung geladen. Die LUV-Abfragen lesen die Anwendungsdatenbank, nicht das Internet. Es wird nicht automatisch die gesamte Teilnehmerakte oder der Dateimanager an das Modell übergeben. Die aktive Start-, Verlauf- oder Abschluss-LUV-Vorlage bestimmt, welche Quellen eingeschaltet sind. Laravel lädt alle erlaubten Quellen vor dem Modellaufruf.

## Tatsächlich angebundene Quellen

| Quelle | Auswahl und Inhalt |
| --- | --- |
| Projektregeln und LUV-Vorlage | Aktives Projekt, Berichtstyp, Regeln, aktivierte Funktionen und Feldkonfiguration. |
| Stammdaten | Name, Geburtsdatum, Kundennummer, Kontakte, Projektstatus, jüngster Teilnahmezeitraum und Betreuung. Diese Daten werden aktuell mit an die KI gegeben, sofern die Quelle aktiviert ist. |
| Frühere LUV | Bis zu 20 freigegebene Berichte derselben Projektteilnahme; Beginn spätestens am Berichtsende. Keine reine Beschränkung auf den aktuellen Zeitraum. |
| Anwesenheit | Gruppenteilnahmen desselben Projekts im Zeitraum, als Anzahl von Datensätzen je Status. Dies sind nicht automatisch eindeutige Anwesenheitstage oder Stunden. |
| Notizen | Erste 100 chronologisch sortierte Notizen derselben Person und Projektteilnahme, gefiltert nach created_at. Titel und Inhalt gehen an die KI. |
| Praktika/Maßnahmen | Bis zu 50 nicht archivierte Einträge derselben Projektteilnahme, die den Zeitraum überlappen; Betrieb, Tätigkeit, Ziel, Beurteilung, Ergebnis und Status. |
| Abschlussberichte | Eingereichte oder freigegebene Abschlussberichte derselben Projektteilnahme. Aktuell ohne Datumsfilter. Die Quelloption education meint hier nicht automatisch Schulnoten oder den Lebenslauf. |
| Einwilligungen | Je Definition das letzte Ereignis granted/revoked derselben Projektteilnahme; aktuell ohne Begrenzung auf das Berichtsende. |
| PA-Förderbedarfe | Projekt muss diese Quelle unterstützen. Nur fachlich freigegebene Entscheidungen aus fertigen/geprüften PA-Berichten; Freigabedatum im Berichtszeitraum, neueste Entscheidung je Kategorie. Diese werden nach der Generierung zusätzlich passenden LUV-Feldern zugeordnet. |

Separate Aufgaben, Bewerbungen, Vermittlungen, Nachrichten, Bankdaten, Kinder, Lebenslauf und beliebige Anhänge werden in diesem LUV-Ablauf nicht als eigenständige Quellen abgefragt. Informationen daraus können nur mittelbar vorkommen, etwa wenn sie in einer ausgewählten Notiz stehen. Ein Toolname für Ziele existiert im Python-Schema, wird im aktuellen Laravel-LUV-Ablauf aber nicht als eigenständiges Tool ausgeführt; Ziele stammen insbesondere aus früheren LUV und Notizen.

## Konkrete Schwächen

1. **Unvollständigkeit wird als Erfolg geführt.** Python versucht nach ungültigem/abgeschnittenem JSON, vollständige Teilabschnitte zu retten. Das ersetzt keine Prüfung aller erforderlichen Vorlagenfelder.
2. **Festes Ausgabelimit von 1.100 Tokens.** Es berücksichtigt weder Feldanzahl noch Quellenmenge. Das passt zum beobachteten Abbruch; die genaue Laufzeitursache ist ohne Profiling nicht bewiesen. CPU-Verarbeitung wurde direkt beobachtet.
3. **Datumslogik ist uneinheitlich.** Unsere am 06.09. erstellten Notizen wären bei Berichtsende 04.09. ausgeschlossen, obwohl der Text Beobachtungen bis 04.09. enthält. Abschlussberichte und Einwilligungen können dagegen zeitlich spätere Informationen enthalten.
4. **Quellenprüfung ist vor allem formal.** Bekannte IDs und Antwortschema werden geprüft, nicht die sachliche Deckung jeder Formulierung. Menschliche Prüfung bleibt erforderlich.
5. **Fehlerdiagnose war irreführend.** HTTP 422 aufgrund eines veralteten Tool-Schemas erschien als Tunnel-/Erreichbarkeitsfehler. Der fehlende PA-Toolname wurde auf dem KI-Server ergänzt und der Dienst erfolgreich geprüft.
6. **Datenminimierung und Transparenz fehlen.** Identitäts- und Kontaktfelder könnten häufig deterministisch in die Vorlage eingesetzt werden, statt sie zur Textgenerierung zu übertragen. Eine Vorschau der ausgewählten Quellen und Ausschlussgründe würde Fehler früher sichtbar machen.

## Empfohlene Reihenfolge

1. Unvollständige Ergebnisse gesondert kennzeichnen; erforderliche Felder gegen die konkrete Vorlage prüfen und Freigabe unvollständiger Ergebnisse verhindern.
2. Bericht in kleine fachliche Abschnitte aufteilen, Ausgabelimit je Abschnitt festlegen, fehlende Abschnitte gezielt nachgenerieren und Fortschritt anzeigen.
3. Beobachtungsdatum für Notizen führen; Stichtagslogik aller Quellen vereinheitlichen. Quellenübersicht mit Anzahl, Zeitraum und ausgeschlossenen Einträgen anzeigen.
4. Fakten, beobachtete Entwicklung, offene Ziele und geplante Schritte getrennt aufbereiten. Strukturierte Stammdaten direkt einsetzen.
5. Mit denselben Testfällen Modelle und Hardware vergleichen: Vollständigkeit, belegte Aussagen, keine erfundenen Erfolge, Laufzeit. Ein größeres Modell ist ohne diesen Vergleich keine belegte Lösung.

## Codebelege

- app/Services/Ai/AiReportOrchestrator.php
- app/Services/Ai/Tools/GetProjectReportRulesTool.php
- app/Services/Ai/Tools/GetParticipantIdentitySummaryTool.php
- app/Services/Ai/Tools/GetParticipantLuvDataTool.php
- app/Services/Ai/Tools/GetAttendanceSummaryTool.php
- app/Services/Ai/Tools/GetDocumentationEntriesTool.php
- app/Services/Ai/Tools/GetParticipantDevelopmentDataTool.php
- app/Services/Ai/Tools/GetParticipantPotentialAnalysisSupportNeedsTool.php
- app/Services/Ai/ApprovedPaSupportNeedMerger.php
- app/Services/Ai/AgentClient.php
- app/Jobs/GenerateAiReportJob.php
- ai-agent/src/zbb_agent/service.py
