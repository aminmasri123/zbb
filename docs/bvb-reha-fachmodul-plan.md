# BvB-Reha-Projektablauf nach Modell B

Stand: 11.07.2026

BvB Reha ist kein eigenes Fachmodul und benötigt keinen verpflichtenden Projekttyp. Es ist ein konkretes Projekt, dessen Funktionen und Regeln direkt am Projekt konfiguriert werden. Der Projektwechsler im Header bestimmt den aktiven Datenkontext; Projektzuweisung, Rolle und Berechtigung bestimmen den Zugriff.

## Vorhandener gemeinsamer Kern

- Eine natürliche Person wird in `personens` nur einmal gespeichert.
- Die Teilnahme an BvB Reha ist ein eigener Datensatz in `projekt_has_personens`.
- Teilnehmerübersicht und Teilnehmerdetail zeigen ausschließlich die Teilnahme des aktiven Header-Projekts.
- Zeiträume, Betreuer, externe Ansprechpartner, Praktika, Abschlüsse, Anwesenheit und Dokumente werden aus dem gemeinsamen Matrix-Kern wiederverwendet.
- BOP-spezifische PA-, LUV-, Bereichsauswahl- oder Einteilungsdaten werden nicht für BvB Reha umgedeutet.

## Teilnahme-Lebenszyklus

Ein Projekt legt den Anfangsstatus neuer Teilnahmen selbst fest. Unterstützt werden:

- angefragt
- angemeldet (Bestandskompatibilität)
- aufgenommen
- aktiv
- pausiert
- abgeschlossen
- abgebrochen

Status, Zeiträume, Standort und zuständige Personen werden an der konkreten Projektteilnahme geführt. Schreibzugriffe auf eine Teilnahme eines anderen als des aktiven Header-Projekts werden abgelehnt. Eine fremde Projekt-ID kann weder über Formulare noch über direkte Requests eingeschleust werden.

## Funktionen und Regeln

Das Projekt kann Teilnehmerverwaltung, Gruppen, Anwesenheit, Praktika, Abschlüsse, Klassenbuch und Potenzialanalyse direkt aktivieren. Zusätzlich sind unter anderem Anfangsstatus, Pflicht-Geburtsdatum, Altersgrenzen, Gruppenkapazität, Standard-Anwesenheitsstatus und Wochenendbehandlung konfigurierbar.

## Datenschutzgrenze

Der aktuelle Ablauf speichert keine Diagnosen, Gesundheitsdaten oder freie Reha-Dokumentation. Förderplanung, Entwicklungsziele, Eingangsdiagnostik und Abschlussberichte werden erst umgesetzt, wenn Datenklassen, Sichtberechtigte, Versionierung, Auditierung und Aufbewahrungsfristen fachlich bestätigt sind.

## Nächste fachliche Ausbaustufen

1. Aufnahmecheckliste mit bestätigten Pflichtnachweisen.
2. Versionierte Förderplanung und Entwicklungsziele mit eigener Berechtigungsmatrix.
3. Projektbezogene Praktikums- und Verlaufsansicht auf Basis der bestehenden gemeinsamen Daten.
4. Freigabefähiger Abschlussbericht und kontrollierte Exporte.

Für diese Stufen werden keine separaten Arbeitsbereiche, Fachmodule oder Projekttypzwänge eingeführt.
