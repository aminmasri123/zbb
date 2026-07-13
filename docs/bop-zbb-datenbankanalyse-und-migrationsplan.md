# Datenbankanalyse BOP -> ZBB

Stand: 13.07.2026. Der zuletzt ausgefuehrte Live-Dry-Run sah 11 Schulen und 2.289 Teilnehmer in der weiterhin veraenderlichen BOP-Quelle. Aeltere Bestandszahlen in diesem Dokument sind Momentaufnahmen der Erstanalyse.

## Ziel und Untersuchungsumfang

Diese Analyse vergleicht die lokal vorhandenen MariaDB-Datenbanken `bop` und `zbb` rein lesend. Verglichen wurden Tabellen, Spalten, Datentypen, Schluessel, Fremdschluessel, ungefaehre Datenmengen und zentrale Datenwerte. Personenbezogene Inhalte wurden nicht ausgegeben.

Das Ergebnis ist keine Freigabe fuer einen direkten Import. Vor dem Produktivimport muessen die fachlichen Zuordnungen, insbesondere Anwesenheit und Einwilligung, bestaetigt werden.

## Verbindlich festgelegte Importregeln

- Alle aus BOP importierten Teilnehmer und ihre Projektteilnahmen werden dem ZBB-Standort `Ernst-Abbe-9` zugeordnet.
- Alle aus `bop.schules` importierten Schulen erhalten den Partnerschaftstyp `Kooperationsschule`.
- Anwesenheitsdaten werden ab `bop.gruppes.anfangsdatum` auf Arbeitstage gelegt. Samstage, Sonntage und gesetzliche Feiertage im Saarland werden uebersprungen. Kein rekonstruiertes Datum darf `gruppes.enddatum` ueberschreiten.
- `Kooperationsschule` ist in ZBB bereits als `partnerschaftstypens.id = 2` vorhanden. Der Import soll trotzdem ueber die stabile Bezeichnung aufloesen und die ID nicht fest im Programm verdrahten.
- Der Standort `Ernst-Abbe-9` ist derzeit noch nicht in `zbb.standorts` vorhanden und muss vor dem Teilnehmerimport kontrolliert angelegt werden. Auch hier soll der Import anschliessend ueber den Namen aufloesen.

## Bestandsaufnahme

| Datenbank | Tabellen | ungefaehre Zeilen laut Metadaten |
|---|---:|---:|
| BOP | 47 | 15.076 |
| ZBB | 177 | 2.056 |

Wichtige BOP-Datenmengen:

| BOP-Tabelle | Zeilen | Bedeutung |
|---|---:|---|
| `teilnehmers` | 1.581 | Teilnehmerstammdaten und BOP-Statusfelder |
| `gruppe_has_teilnehmer` | 4.400 | Teilnahme an BOP-Gruppen inkl. Anwesenheits- und Bewertungsbezug |
| `gruppes` | 481 | historische Gruppen/Durchlaeufe |
| `bewertungsbogens` | 1.207 | BO-Beurteilungen |
| `orientierungs` | 1.031 | vier priorisierte Bereichswahlen |
| `einteilungs` | 830 | drei Bereichseinteilungen je Teilnehmer |
| `teilnehmeranwesenheitsdatens` | 434 | PA-/Werkstatttag-Anwesenheit in festen Boolean-Spalten |
| `teilnehmer_has_uebungens` | 321 | PA-Uebungsergebnisse |
| `auswertung_pas` | 97 | Fremdeinschaetzung mit 11 Merkmalen |
| `selbsteinschaetzungs` | 114 | Selbsteinschaetzung mit 11 Merkmalen |
| `schules` | 10 | Schulen, Ansprechpartner und BOP-Zeitangaben |
| `notifications` | 3.246 | historische Anwendungsbenachrichtigungen |

Qualitaetsbefunde im BOP-Bestand:

- Vier Gruppen gleicher Kombinationen aus Vorname, Nachname und Geburtsdatum enthalten mehr als einen Datensatz. Das sind Dubletten-Kandidaten, aber keine automatische Loeschfreigabe.
- 977 von 1.581 Teilnehmern besitzen keinen Bezug zu `teilnehmeranwesenheitsdatens`.
- 1.466 Teilnehmer besitzen keine PA-Fremdauswertung; 1.469 keine Selbsteinschaetzung.
- Die geprueften Teilnehmerbeziehungen zu Gruppen sowie die gespeicherten Verweise auf Anwesenheit, PA-Auswertung und Selbsteinschaetzung enthalten keine verwaisten Referenzen.
- Die PA- und Selbsteinschaetzungsskalen reichen von 1 bis 5 und passen damit grundsaetzlich in die normalisierte ZBB-PA-Struktur.
- BOP enthaelt Teilnehmer aus den Schuljahren 2024 und 2025, jeweils in mehreren Teilabschnitten.

## Fachliches Tabellen-Mapping

| BOP | ZBB-Ziel | Bewertung |
|---|---|---|
| `teilnehmers` | `personens`, `personen_ist_schuelers`, `projekt_has_personens` | weitgehend abbildbar; Standort verbindlich `Ernst-Abbe-9`; mehrere Status-/Legacyfelder offen |
| `schules` | `partners`, `adresses`, `kontaktes`, Partner-Typ | Stammdaten abbildbar; jede Schule erhaelt verbindlich `Kooperationsschule`; BOP-spezifische Schulfelder fehlen |
| `projektes` | `projekts`, Abteilung, Kostenstellen-Zuordnung | transformierbar; BOP speichert Kostenstelle als Text |
| `gruppes` | `gruppes` | Kern abbildbar; Schule und verantwortlicher Benutzer werden anders modelliert |
| `gruppe_has_teilnehmer` | `gruppe_has_personens` und PA-/BO-Tabellen | nicht 1:1; ZBB-Zeile steht fuer Person, Gruppe und konkreten Kalendertag |
| `bereiches` | `bereiches` | direkt transformierbar (`abkuerzung` -> `code`) |
| `orientierungs` | `bereichsauswahls` | nahezu direkt abbildbar |
| `einteilungs` | `einteilung_bereiches` | in bis zu drei normalisierte Zeilen aufzuteilen |
| `bewertungsbogens` | `potenzialanalyse_kompetenzbewertungen` oder neue BO-Bewertung | technisch normalisierbar; fachlich ist BO von PA zu trennen |
| `auswertung_pas` | `potenzialanalyse_kompetenzbewertungen` | als Typ Fremdeinschaetzung abbildbar |
| `selbsteinschaetzungs` | `potenzialanalyse_kompetenzbewertungen` oder `potenzialanalyse_selbsteinschaetzungen` | abbildbar, Zielmodell fachlich festlegen |
| `uebungs` | `potenzialanalyse_uebungen` | direkt mit Projektzuordnung erweiterbar |
| `teilnehmer_has_uebungens` | `potenzialanalyse_uebung_ergebnisse` | abbildbar, Gruppe und Benutzer muessen hergeleitet werden |
| `anwesenheitslistes` | `gruppe_has_personens` | nicht verlustfrei ohne Datumsregel oder Legacy-Snapshot |
| `teilnehmeranwesenheitsdatens` | `gruppe_has_personens` | nicht verlustfrei ohne Zuordnung der festen Tags zu echten Daten |
| `infos` | teilweise Notizen/Portal-Lebenslauf | Hobby, Wunschberuf und Perspektive haben kein eindeutiges Ziel |
| `events` | `app_calendar_events` | abbildbar; Besitzer, Kalender und Sichtbarkeit benoetigen Defaults |
| `ordnermanagers`, `dateimanagers` | `app_files` | Metadaten abbildbar; physische Dateien und Pfade separat pruefen |
| `taskmanagers` | `app_tasks` | weitgehend abbildbar; Bearbeitungswert und Statusmapping offen |
| `geraets`, Ausgabe-/Rueckgabetabellen | ZBB-Geraeteverwaltung | weitgehend vorhanden; Kontakt muss in Person/Partner ueberfuehrt werden |
| Benutzer/Rollen/Rechte | ZBB-Benutzer und Spatie-Rechte | keine ID-Uebernahme; Konten und Rechte kontrolliert neu zuordnen |
| `notifications` | optional ZBB `notifications` | technisch gleichartig, aber alte PHP-Klassennamen koennen nicht mehr renderbar sein |

## Fehlende Tabellen beziehungsweise notwendige Migrationsstrukturen

Folgende Strukturen fehlen fuer einen nachweisbar verlustfreien und wiederholbaren Import:

### 1. Importlaeufe

Empfohlen: `legacy_import_runs`

- Quelle und Quellversion
- Start, Ende und Status
- Dry-Run/Produktiv-Kennzeichen
- Quell-Backup-Pruefsumme
- Anzahl gelesen, importiert, uebersprungen und fehlerhaft
- Fehlerbericht und verwendete Mapping-Version

### 2. Alte zu neuen IDs

Empfohlen: `legacy_id_mappings`

- Importlauf
- Quelltabelle und Quell-ID
- Zieltabelle und Ziel-ID
- Datensatz-Pruefsumme
- Importstatus und Fehlermeldung

Diese Tabelle verhindert Dubletten und macht jeden Ziel-Datensatz bis zur BOP-ID rueckverfolgbar.

### 3. Unveraenderter Legacy-Snapshot fuer nicht eindeutig abbildbare Werte

Empfohlen: `legacy_record_snapshots`

- Quelltabelle und Quell-ID
- Originaldaten als JSON
- Pruefsumme
- Klassifikation: importiert, teilweise importiert, archiviert oder fachlich verworfen
- Begruendung und Zeitstempel

Der Snapshot ist kein Ersatz fuer fachliche ZBB-Felder, verhindert aber, dass bei offenen Zuordnungen Informationen verschwinden.

### 4. Historische BOP-Anwesenheit

ZBB modelliert Anwesenheit kalendertagsbezogen. BOP speichert dagegen teilweise nur Positionen wie `tag1`, `pa_tag1` oder `wt_tag1` bis `wt_tag9`. Falls sich aus Gruppe, Schuljahr und Ablaufplan kein eindeutiges Datum herleiten laesst, wird eine Tabelle fuer historische Anwesenheit benoetigt, zum Beispiel:

- Person und optional Gruppe/Projekt
- Phase (`vorbereitung_pa`, `pa`, `rolltag`, `werkstatttag`, `bo`)
- laufende Tagnummer
- Anwesenheitswert
- optional hergeleitetes Datum
- Kennzeichen, ob das Datum sicher oder nur rekonstruiert ist
- BOP-Quell-ID

### 5. BOP-/BO-Bewertungsbogen

Die 1.207 `bewertungsbogens` sind BO-Bewertungen mit elf festen Kriterien. Sie sollten nicht ohne fachliche Entscheidung als PA-Daten bezeichnet werden. Moeglichkeiten:

- eigene normalisierte Tabellen fuer BO-Beurteilungen und Kriterien; oder
- eine allgemeine Assessment-Struktur mit Assessment-Typ `BO`/`PA`.

Die zweite Variante ist langfristig skalierbarer, erfordert aber eine groessere Modellanpassung.

### 6. Teilnehmer-Zusatzprofil

Fuer `infos.hobby`, `wunschberuf`, `perspektive` und `notizen` fehlt ein eindeutig passendes, gemeinsames ZBB-Ziel. Empfohlen ist ein Teilnehmerprofil beziehungsweise projektbezogenes Profil, weil sich Wunschberuf und Perspektive mit der Zeit oder pro Projekt aendern koennen.

## Fehlende oder fachlich offene Felder

### Schule/Partner

In `schules`, aber nicht eindeutig im ZBB-Partnermodell vorhanden:

- `eintritt`, `austritt`
- `foederschule`
- `schuelerempfang`
- `außeneinsatz`

Ansprechpartner, Telefon, Mobiltelefon, E-Mail, Webseite und Adresse koennen normalisiert in Partner, Person, Kontakt und Adresse ueberfuehrt werden.

### Teilnehmer und Teilnahme

Nicht direkt beziehungsweise nicht beweissicher abgebildet:

- `adresse` als unstrukturierte einzelne Zeichenkette; ZBB erwartet strukturierte Adressbestandteile
- `eltereklaerung`: Ein einfaches historisches Boolean ist kein vollwertiger Nachweis fuer die versionierte ZBB-Einwilligung mit Text-Pruefsumme und Ereigniszeitpunkt
- `teilnahme_pa`
- `bo_tag1`
- `zusammenfassung`

`klasse`, `schule_id`, `schuljahr` und `teil` passen in `personen_ist_schuelers`. Der historische Zustand sollte nicht durch ein spaeteres Schuljahr ueberschrieben werden; dafuer sind Eindeutigkeits- und Historisierungsregeln notwendig.

### Gruppe und Teilnahmebeziehung

BOP `gruppes.user_id` entspricht fachlich vermutlich einer verantwortlichen Person. ZBB `gruppes.personen_id` kann das Ziel sein, jedoch nur ueber das Benutzer-Person-Mapping.

Die BOP-Verknuepfung enthaelt Referenzen auf einen aggregierten Anwesenheitsdatensatz und einen Bewertungsbogen. ZBB besitzt dafuer keine identische 1:1-Beziehung. Beide muessen normalisiert und ihre BOP-Herkunft gespeichert werden.

### Aufgaben

`taskmanagers.bearbeitung` hat kein eindeutiges Ziel in `app_tasks`. Die sechs Legacy-Statuswerte muessen kontrolliert auf `open`, `progress` und `done` abgebildet werden.

### Benutzerprofil

Aus `user_logs` sind Titel, Raum, Buero, Telefon, Geburtsdatum und Bereich nicht vollstaendig als ein ZBB-Mitarbeiterprofil modelliert. Ein Teil kann in Person, Kontakt und Bereichsbeziehungen uebergehen; fuer Titel/Buero/Raum ist eine fachliche Zielstruktur festzulegen.

## Tabellen ohne zu migrierende Fachdaten

Mehrere BOP-Tabellen sind leer, darunter `alert_messages`, `auswahloptionens`, `faehigkeitens`, `wahls`, `schule_schuljahrs`, `permission_kategories`, `failed_jobs` und `personal_access_tokens`. Fuer sie ist aktuell keine Datenmigration erforderlich. Das Mapping sollte dennoch dokumentiert werden, falls sich der Bestand vor dem Stichtag aendert.

Frameworktabellen wie `migrations`, Passwort-Reset-Token, fehlgeschlagene Jobs und API-Tokens werden nicht uebernommen. Sie werden von ZBB selbst verwaltet.

## Migrationsplan

### Phase 0: Entscheidungen und Schutzmassnahmen

1. Vollstaendigen SQL-Dump und Dateibestand von BOP sichern; Pruefsummen dokumentieren.
2. BOP waehrend der Entwicklung weiter betreiben, aber einen spaeteren Schreibstopp festlegen.
3. Aufbewahrung, Rechtsgrundlage und Zugriff fuer historische personenbezogene Daten klaeren.
4. Fachliche Entscheidungen fuer Anwesenheitsdatum, BO-Bewertungen, Einwilligung und Teilnehmer-Zusatzprofil treffen.

### Phase 1: ZBB-Schema ergaenzen

1. Standort `Ernst-Abbe-9` idempotent anlegen; vorhandenen Partnerschaftstyp `Kooperationsschule` validieren.
2. Importlauf-, ID-Mapping- und Snapshot-Tabellen anlegen.
3. Historische Anwesenheitsstruktur oder verbindliche Datumsrekonstruktion umsetzen.
4. BO-/Assessment-Zielmodell ergaenzen.
5. Fehlende Partner-, Teilnehmer- und Mitarbeiter-Metadaten fachlich sinnvoll ergaenzen.
6. Eindeutigkeitsregeln und Indizes fuer wiederholbare Imports definieren.

### Phase 2: Importprogramm erstellen

Ein eigener Laravel-Command mit getrennten Modi:

```text
php artisan bop:import
php artisan bop:import --execute
```

Ohne `--execute` ist der Befehl immer ein schreibfreier Dry-Run. Mit `--execute` verlangt er zusaetzlich eine interaktive Bestaetigung; nur fuer kontrollierte Automatisierung kann diese mit `--force` uebersprungen werden. Vor dem Execute-Modus muessen die ZBB-Migrationen ausgefuehrt sein.

Der Import liest BOP ueber eine eigene, nur lesbare Datenbankverbindung. Er darf niemals BOP aktualisieren. Jeder Schritt verwendet Upserts oder das ID-Mapping und ist damit idempotent.

Der aktuelle Dry-Run rekonstruiert 21.598 gruppenbezogene Anwesenheitszeilen eindeutig. 2.849 Tagpositionen liegen ausserhalb des jeweiligen Gruppenzeitraums. Diese werden nicht als datierte ZBB-Anwesenheit angelegt, aber unveraendert im Legacy-Snapshot erhalten und im Importbericht ausgewiesen.

Reihenfolge:

1. Referenzdaten: Standort `Ernst-Abbe-9`, Partnerschaftstyp `Kooperationsschule`, Bereiche, Abteilungen, Kostenstellen, Projekte
2. Benutzer -> Personen -> Rollen/Rechte
3. Schulen -> Partner, Adressen, Kontakte, Ansprechpartner
4. Teilnehmer -> Personen, Schuelerhistorie, Projektteilnahmen
5. Gruppen und Gruppenmitgliedschaften
6. Wahlen und Einteilungen
7. PA-Uebungen, Ergebnisse, Fremd- und Selbsteinschaetzungen
8. BO-Bewertungen
9. Anwesenheit
10. Zusatzinfos, Kalender, Aufgaben, Dateien und optional Benachrichtigungen
11. Geraete, Ausgaben und Rueckgaben, sofern diese BOP-Nebenmodule ebenfalls abgeloest werden

### Phase 3: Probelauf

1. Frische Kopie der ZBB-Testdatenbank verwenden.
2. Zunaechst genau eine Schule und einen Teilabschnitt importieren.
3. Automatische Soll-Ist-Berichte erzeugen.
4. Teilnehmerlisten, Gruppen, Anwesenheit, Bereichswahl, PA und BO fachlich gegen BOP pruefen.
5. Gefundene Mappingfehler korrigieren und den Import vollstaendig wiederholen.

### Phase 4: Generalprobe

1. Gesamten BOP-Datenbestand in eine neue ZBB-Staging-Datenbank importieren.
2. Zeilensummen, Schluesselmengen und Pruefsummen vergleichen.
3. Jede BOP-Zeile muss genau einen Status besitzen: importiert, bewusst zusammengefuehrt, archiviert oder begruendet verworfen.
4. Stichproben durch BOP-Fachanwender abnehmen lassen.
5. Laufzeit und notwendiges Wartungsfenster messen.

### Phase 5: Produktivumschaltung

1. BOP in den Nur-Lesen-Modus setzen.
2. Finales Backup und Pruefsumme erstellen.
3. Delta seit der Generalprobe oder einen vollstaendigen idempotenten Import ausfuehren.
4. Verifikation ohne offene Fehler abschliessen.
5. ZBB freigeben; BOP fuer eine definierte Frist schreibgeschuetzt als Referenz behalten.
6. Erst nach fachlicher und rechtlicher Freigabe eine spaetere Archivierung beziehungsweise Loeschung planen.

## Abnahmekriterien gegen Datenverlust

- Jede nicht-technische BOP-Zeile ist im Importbericht nachgewiesen.
- Alle alten IDs sind auf Ziel-IDs oder einen begruendeten Archivstatus abgebildet.
- Keine verwaisten Fremdschluessel im Ziel.
- Keine unbeabsichtigten Dubletten nach wiederholtem Import.
- Alle Texte sind ohne Abschneiden und mit korrektem UTF-8-Zeichensatz uebernommen.
- Summen je Schule, Schuljahr, Teil, Gruppe und Teilnehmer stimmen zwischen Quelle und Ziel beziehungsweise sind erklaert.
- PA-/BO-Werte und Skalen stimmen auf Feldebene.
- Physische Dateien sind vorhanden und ihre Pruefsummen stimmen.
- Der Import kann aus einem Backup reproduziert werden.
- Ein dokumentierter Rollback auf den Zustand vor der Umschaltung wurde getestet.

## Naechste fachliche Entscheidungen

Vor der Implementierung sind insbesondere diese vier Punkte zu beantworten:

1. Welche echten Kalenderdaten entsprechen den BOP-Spalten `tag1`, `pa_tag1` und `wt_tag1` bis `wt_tag9`?
2. Sollen BO-Bewertungsboegen eigenstaendig bleiben oder in ein allgemeines Assessment-Modell uebergehen?
3. Wie sollen historische Boolean-Einwilligungen rechtssicher dargestellt werden, wenn Textversion und Ereigniszeit fehlen?
4. Werden auch BOP-Nebenmodule wie Dateien, Kalender, Aufgaben, Benachrichtigungen und Geraeteverwaltung vollstaendig abgeloest oder nur die BOP-Fachdaten?
