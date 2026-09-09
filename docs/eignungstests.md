# Eignungstests

Die Projektfunktion `aptitude_tests` ergänzt versionierte Testprofile, Testgruppen und Auswertungen je Projektteilnahme. Das Ausgangsprofil basiert auf „E-Test 04.08.26.docx“: Deutsch HSA-Niveau (100 Punkte) und Grundlagen Mathematik (34 + 66 Punkte). Personenbezogene Beispielergebnisse werden nicht installiert.

## Bedienung

1. Projekt → Eignungstests: aktivieren und Testprofil speichern. Neue Definitionen werden als neue Version angelegt; vorhandene Gruppen behalten ihre Version. Notenschlüssel werden ausdrücklich konfiguriert. Die Vorlage enthält nur für Deutsch eine 75-%-Hinweisgrenze.
2. Gruppe erstellen: ausschließlich den **Bereich „Eignungstest“** auswählen. Die zuletzt gespeicherte Testvorlage des Projekts wird automatisch übernommen; es gibt keine zusätzliche Auswahl von Gruppenfunktion oder Testprofil. Der Bereich wird bei Aktivierung automatisch mit dem Projekt verknüpft und ist auch für Betreuer mit zugewiesenen Berufsfeldern verfügbar. Bestehende Gruppen behalten ihre Vorlagenversion. Über „Gruppe bearbeiten“ kann der Bereich gewechselt werden, solange keine Testauswertung vorliegt.
3. Teilnehmer und Anwesenheit wie gewohnt in der Gruppe erfassen.
4. Testauswertung: Teilnehmer und neue Durchführung auswählen, Datum, Punkte und Texte erfassen. Leere Kriterien bleiben unbewertet; eine Null ist ein ausdrücklich erfasstes Ergebnis. Unterkriterien werden einmal addiert; alternative Bewertungsstufen schließen sich aus.
5. Zwischenstände speichern oder vollständigen Test abschließen. Für die fachliche Freigabe müssen alle Kriterien und die Einschätzung sowie der Förderbedarf je Testbereich vorliegen. Freigegebene Durchführungen bleiben unverändert; Wiederholungen werden separat angelegt.
6. KI-Entwürfe werden über die vorhandene lokale KI-Warteschlange erzeugt, separat angezeigt und erst nach Übernahme und Speicherung zum Feldinhalt. Die Freigabe erfolgt ausschließlich durch die Fachkraft.
7. Teilnehmer → Eignungstests zeigt alle Durchführungen dieser Projektteilnahme. Die Sichtbarkeit des Tabs wird unter Teilnehmerprofil konfiguriert.

## LuV

Die Quelle „Eignungstests (fachlich freigegeben)“ kann je LuV-Vorlage ausgeschaltet werden. Das Entwicklungstool liefert nur freigegebene Angaben der ausgewählten Projektteilnahme im Berichtszeitraum, deren Freigabe spätestens am Stichtag erfolgte. Pro benanntem Testbereich wird der neueste freigegebene Stand genutzt. Datum, Profilversion und Quellen-ID bleiben enthalten. Andere Testversionen bleiben in der Teilnehmerhistorie nachvollziehbar; aus unterschiedlichen Versionen wird kein automatischer Fortschrittswert berechnet.

Start-LuV: Einschätzung und Förderbedarf werden den konfigurierten Kompetenzbereichen zugeordnet. Verlauf: Einschätzungen nach `development.notes`, Förderbedarf nach `competence.<bereich>.current_need`. Abschluss: `support.description`. Die vorhandene Zusammenführung freigegebener Fachangaben sichert die Übernahme auch bei unvollständiger KI-Antwort. Es werden keine Aussagen zu Ausbildungsreife oder Berufseignung aus Punktzahlen abgeleitet.

## Berechtigungen und Historie

Konfiguration verlangt `projekt.update` und das aktive, zugeordnete Projekt. Die Gruppenauswertung verlangt Teilnehmer-Leserechte und eigene Gruppen beziehungsweise die bestehende Freigabe für alle Gruppen/Mitarbeiter. Bearbeitung und Freigabe verlangen `teilnehmer.update`; Änderungen an der Gruppe verlangen `gruppe.update`. Teilnehmer müssen zur Gruppe und aktuellen Projektteilnahme gehören und im vorhandenen Teilnehmer-Datenzugriff sichtbar sein. Jeder gespeicherte Stand wird in `aptitude_attempt_revisions` protokolliert; konkurrierende Änderungen werden anhand der Revision zurückgewiesen.

## Installation

Nach Datenbank- und Dateisicherung nur die Migration `2026_09_09_190000_create_aptitude_tests.php` ausführen, Frontend bauen und Views leeren. `php artisan aptitude:install-bvb <projekt-id> --enable` hinterlegt die Vorlage, verknüpft den Bereich „Eignungstest“ (Code `E-TEST`) und aktiviert Funktion und Teilnehmertab, ohne Ergebnisse anzulegen oder bestehende Testprofile zu ersetzen. Für die Vereinfachung auf die Bereichsauswahl ist keine weitere Migration erforderlich. Der vorhandene KI-Queue-Worker muss laufen.

Validierung: `AptitudeTest`, `GroupCreatePageTest`, `ProjectFeatureConfigurationTest`, `AiReportOrchestratorTest`, `AiToolRegistryTest`, `ProjectParticipantProfileConfigurationTest` sowie `npm run build`.
