# Teilnehmerimport mit Identitätsprüfung

Der Import speichert eindeutig neue Teilnehmer und stellt mögliche Bestandstreffer zunächst zurück. Geprüfte Treffer können mit einer bestehenden Person verknüpft oder ausdrücklich als andere Person neu angelegt werden. Bestehende Projektteilnahmen werden dabei weder ersetzt noch reaktiviert.

## Projekt und Standort

Das aktive Projekt des angemeldeten Nutzers wird automatisch verwendet und in der Importübersicht angezeigt. Vor dem Hochladen ist ein Standort auszuwählen. Die Liste enthält ausschließlich die aktiven Mitarbeiterzuordnungen dieses Nutzers im aktiven Projekt aus `projekt_has_personens` (Person, Projekt und Standort). Die allgemeine Standortliste aus `standort_has_personens` ist hierfür nicht maßgeblich. Auch ein weitreichender Teilnehmer-Datensichtbereich erweitert diese Auswahl nicht. Ohne Standortzuweisung im aktiven Projekt ist kein Import möglich.

Der ausgewählte Standort gilt für alle übernommenen Zeilen. Die herunterladbaren Vorlagen benötigen keine Projekt- oder Standortspalte. In älteren Dateien werden `Projekt_ID` und `Standort_ID` nicht zur Zuordnung verwendet; die Vorschau zeigt die tatsächlich gewählte Zuordnung. Jeder Import benötigt eine bestätigte Vorschau. Die Bestätigung ist zusätzlich an den Standort gebunden. Serverseitig werden Projektkontext und Standortzuweisung beim Speichern erneut geprüft. Zurückgestellte Zeilen behalten den ausgewählten Standort als Vorschlag; eine inzwischen entzogene Zuweisung wird nicht umgangen.

## Berechtigungen

- Import und eigene zurückgestellte Dateien: bestehendes Recht `teilnehmer.import` oder `teilnehmer.store` innerhalb des aktiven Projekts.
- Identitätsprüfung und Zuordnung: **beide** Rechte `teilnehmer.import` und `teilnehmer.update`. Zusätzlich gelten der Teilnehmer-Datensichtbereich und die Projektmitgliedschaft des Nutzers. Die Vorschau nennt ausschließlich Projekte, denen der Nutzer selbst zugeordnet ist.
- Zurückgestellte Importe sind nur für den ursprünglichen Importeur im ursprünglichen Zielprojekt verfügbar. Eine erratene Import-ID gewährt keinen Zugriff.

## Datenverarbeitung

Der Abgleich berücksichtigt Vorname, Nachname und Geburtsdatum. Fehlt ein Geburtsdatum, wird der Namensgleichstand als unsicherer Treffer behandelt. Ein Treffer ist keine automatische Identitätsbestätigung. Die Vorschau bindet die Bestätigung an Datei, Nutzer, Projekt und Importprofil; beim Speichern wird der Bestand erneut geprüft.

Eine bestätigte Zuordnung legt ausschließlich eine neue Projektteilnahme an. Bestehende Namen, Adressen und Kontakte bleiben erhalten; abweichende Angaben aus der Importdatei werden nicht automatisch aktualisiert. Die Entscheidung, der bestätigende Nutzer und der Zeitpunkt stehen in `projekt_has_personens.import_entry_data`. Frühere Berichte und Notizen bleiben bei ihrer ursprünglichen Teilnahme. Es gibt keine automatische Übertragung oder Freigabe für das neue Projekt.

Datensätze mit noch global gespeicherten Sozial-, Bank-, Fahrt-, Abschluss- oder Lebenslaufdaten können vorerst nicht über diesen Import einem weiteren Projekt zugeordnet werden. Auch deaktivierte Personen und bereits im Zielprojekt vorhandene Teilnahmen benötigen eine gesonderte Prüfung. Das vermeidet eine unbeabsichtigte Erweiterung der Sichtbarkeit.

Nur offene Importzeilen liegen verschlüsselt in `participant_import_reviews`. Sie sind 30 Tage ab dem ersten Zurückstellen verfügbar; erneutes Öffnen verlängert die Frist nicht. `participants:purge-expired-import-reviews` entfernt täglich abgelaufene **temporäre Importkopien**. Der Befehl muss über den Laravel-Scheduler oder einen eigenen täglichen Cronjob aktiviert sein. Der Befehl löscht keine Teilnehmer, Projektteilnahmen, Berichte oder Unterschriften.

## Datenschutzgrenze

Diese technischen Begrenzungen ersetzen keine Prüfung der Rechtsgrundlage und Zweckbindung durch den Verantwortlichen. Insbesondere darf eine Identitätsbestätigung nicht als Einwilligung oder Freigabe früherer Berichte behandelt werden. Eine spätere projektübergreifende Nutzung solcher Unterlagen benötigt eine dokumentierte Prüfung und einen gesonderten Berechtigungs- und Freigabeprozess.

Rechtsgrundlage für diese Abgrenzung: [DSGVO, insbesondere Art. 5, 6 und 9](https://eur-lex.europa.eu/eli/reg/2016/679/oj/?locale=de).

## Veröffentlichung

Die Migration `2026_09_09_140000_create_participant_import_reviews.php` ergänzt ausschließlich die Tabelle für temporäre Prüfungen. Bestehende Fachtabellen werden nicht geändert. Die Standortauswahl benötigt keine zusätzliche Migration. Anschließend Frontend bauen und Laravel-Scheduler prüfen. Tests: `ParticipantImportLocationTest`, `ParticipantImportDecisionTest`, `BvbParticipantImportTest`, `ParticipantAddressImportTest`, `ParticipantImportTemplateTest`, `ProjectRuleConfigurationTest` und `ParticipantListEnhancementsTest`.
