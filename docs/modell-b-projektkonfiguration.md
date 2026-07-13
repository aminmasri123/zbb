# Modell B: direkte Projektkonfiguration

Stand: 11.07.2026

Diese Entscheidung ersetzt für die weitere Entwicklung die früheren Vorschläge, BOP und BvB Reha als Fachmodule oder verpflichtende Projekttypen zu behandeln.

## Verbindliches Modell

- Konkrete Projekte wie `BOP 2026`, `BvB Reha 2027` und `Coaching 2028` besitzen ihre Regeln und aktivierten Funktionen direkt.
- Das aktive Projekt wird im Header gewählt. Teilnehmer-, Partner- und Gruppenansichten verwenden diesen Projektkontext.
- Projektzuweisungen legen den Datenumfang eines Mitarbeiters fest. Rollen und Berechtigungen legen fest, was er darin sehen oder bearbeiten darf.
- `project_types` bleibt als nicht destruktiver Legacy-Datenbestand erhalten, steuert aber weder Projektformulare noch Navigation, Header-Kontext oder Funktionsfreigaben.

## Systemmodule

`Teilnehmerverwaltung` bleibt als professionell sichtbares, globales Systemmodul bestehen. Bei Deaktivierung wird die Teilnehmernavigation ausgeblendet und der Backend-Zugriff auf Teilnehmerfunktionen gesperrt. Vorhandene Daten werden nicht gelöscht.

BOP und BvB Reha sind keine Systemmodule. Sie sind Projekte mit eigener Konfiguration. Deshalb werden ihre alten Moduleinträge nicht mehr in der Modulverwaltung angezeigt und nicht mehr erzwungen.

Alle sichtbaren Systemmodule gelten global. Ein Standort-Geltungsbereich wird nicht angeboten oder ausgewertet. Bereits gespeicherte Standortzuweisungen bleiben aus Gründen der Daten- und Rollback-Sicherheit in der Datenbank erhalten.

## Direkte Projektfunktionen

Auf der Projektseite werden die Funktionen des konkreten Projekts unter „Funktionen und Regeln“ konfiguriert:

- Teilnehmerverwaltung
- Gruppen und Bereiche
- Anwesenheit
- Praktika
- Abschlüsse
- Klassenbuch
- Potenzialanalyse einschließlich Anzahl der PA-Tage

Der wirksame Zugriff folgt der Reihenfolge: globales Systemmodul, aktive Projektfunktion, Projektzuweisung sowie Rolle und Berechtigung. Die Projektfunktionen werden im Header-Kontext ausgeliefert, in der Navigation berücksichtigt und an den zugehörigen Backend-Routen erneut geprüft. Für bestehende Projekte bleiben die allgemeinen Funktionen standardmäßig aktiv; Klassenbuch und Potenzialanalyse übernehmen ihre bisherigen Projektschalter.

Abhängigkeiten werden konsistent erzwungen: Gruppen, Anwesenheit, Praktika und Abschlüsse benötigen die Teilnehmerverwaltung; das Klassenbuch benötigt Gruppen; die Potenzialanalyse benötigt Teilnehmerverwaltung und Gruppen. Beim Abschalten einer Basisfunktion schaltet die Oberfläche abhängige Funktionen mit aus. Das Backend lehnt widersprüchliche Konfigurationen zusätzlich ab.

## Erste Projektregeln

Direkt am Projekt können eine maximale Zahl unterschiedlicher Teilnehmer pro Gruppe, der Standard-Anwesenheitsstatus und das Überspringen von Samstagen und Sonntagen festgelegt werden. Die Gruppengrenze und Projektzugehörigkeit werden sowohl im manuellen Zuordnungsablauf als auch zentral beim Erstellen eines Gruppeneintrags geprüft. Dadurch können alternative Eloquent-Speicherpfade die Regel nicht umgehen. Bestehende Projekte erhalten keine zusätzliche Gruppengrenze und behalten als Standardstatus `unentschuldigt`; Wochenenden werden weiterhin angelegt, bis die neue Regel ausdrücklich aktiviert wird.

Teilnehmerregeln umfassen außerdem ein verpflichtendes Geburtsdatum sowie optionales Mindest- und Höchstalter. Sie gelten einheitlich bei manueller Anlage, Bearbeitung und Excel-Import. Der Import verwendet ausschließlich das aktive Header-Projekt; eine abweichende `Projekt_ID` in der Datei wird abgelehnt. Für bestehende Projekte bleibt das Geburtsdatum optional und es gelten keine Altersgrenzen.

## Projektteilnahme und Verlauf

Neue Teilnehmer werden unmittelbar dem aktiven Header-Projekt zugeordnet und erhalten den dort konfigurierten Anfangsstatus. Der gemeinsame Lebenszyklus umfasst `angefragt`, `angemeldet` (Bestand), `aufgenommen`, `aktiv`, `pausiert`, `abgeschlossen` und `abgebrochen`. Teilnehmerdetail, Zeiträume, Statusänderungen und neue Projektzeiträume sind strikt auf das aktive Projekt begrenzt.

Praktika und andere Bildungsmaßnahmen erhalten bei der Neuanlage einen Bezug zur konkreten Projektteilnahme. Die Teilnehmerdetailseite zeigt nur Maßnahmen dieser Teilnahme. Ältere, noch nicht eindeutig zuordenbare Datensätze bleiben unverändert gespeichert, werden aber nicht projektübergreifend eingeblendet.
