# Permission-Dokumentation fuer Administratoren

Stand: 2026-07-09

Diese Dokumentation beschreibt, wie die Rollen- und Permission-Struktur im Seeder gepflegt wird und was die Administrator-Rolle darf.

## Grundsatz

Die Rolle `Administrator` erhaelt im `UserSeeder` automatisch alle Permissions mit `guard_name = web`.

Das ist bewusst so umgesetzt: Sobald eine neue Permission im Seeder-Katalog angelegt wird, wird sie beim Seed-Lauf automatisch der Administrator-Rolle zugeordnet. Dadurch muss keine zweite Admin-Liste gepflegt werden.

Relevanter Ablauf im Seeder:

1. Alle Permissions werden aus `permissionCatalog()` erzeugt.
2. Jede Permission wird per `updateOrInsert` in die Tabelle `permissions` geschrieben.
3. Danach werden alle `web`-Permissions aus der Datenbank gelesen.
4. Jede dieser Permissions wird der Rolle `Administrator` zugeordnet.
5. Zusaetzlich bekommt `Administrator` Zugriff auf alle Berechtigungskategorien.

## Pflege-Regeln

Jede neue fachliche Funktion bekommt eine eigene Permission, wenn sie mehr erlaubt als nur das Anzeigen einer bestehenden Seite.

Normale CRUD-Module verwenden dieses Muster:

| Aktion | Bedeutung |
| --- | --- |
| `modul.index` | Seite, Liste oder Uebersicht sehen |
| `modul.show` | Detailansicht eines Datensatzes sehen |
| `modul.store` | neuen Datensatz anlegen |
| `modul.update` | bestehenden Datensatz bearbeiten |
| `modul.destroy` | Datensatz loeschen |

Sonderaktionen bekommen eigene Permissions, wenn sie fachlich oder datenschutzrechtlich relevant sind:

| Aktion | Bedeutung |
| --- | --- |
| `*.import` | Daten aus Dateien oder Fremdquellen importieren |
| `*.export.*` | Daten, Listen, Word-, Excel- oder PDF-Dateien exportieren |
| `*.download` | Dateien herunterladen |
| `*.mail` | Dateien oder Inhalte per E-Mail versenden |
| `*.share` | Inhalte fuer andere Personen freigeben |
| `*.genehmigen` | Genehmigungs- oder Statusprozess ausloesen |
| `*.review` | pruefen, freigeben, sperren oder zur Korrektur zurueckgeben |
| `*.view.all` | uebergeordnete Sicht auf alle Datensaetze eines Bereichs |
| `*.view.abteilung` | Sicht auf Datensaetze der eigenen oder zugeordneten Abteilung |
| `*.view.projekt` | Sicht auf Datensaetze der eigenen oder zugeordneten Projekte |
| `*.view.standort` | Sicht auf Datensaetze der eigenen oder zugeordneten Standorte |

## Berechtigungskategorien

| Kategorie | Inhalt |
| --- | --- |
| Dashboard | Dashboard, Navigation, Apps-Uebersicht, Benachrichtigungen |
| Kooperationspartner | Partner, Schulen, Kooperationspartner und externe Stellen |
| Gruppe | Gruppen, Gruppenexporte, Klassenbuch und gruppenbezogene BOP-Ausgaben |
| Bereich | Bereichs-Stammdaten |
| Teilnehmer | Teilnehmer, Sozialdaten, Kontakte, Adressen, Bankdaten, Notizen und Teilnehmerexporte |
| TLN-GRP | Zuordnung von Teilnehmern zu Gruppen |
| Rolle | Rollen anlegen und loeschen |
| Permission | Rollenrechte, Permission-Zuweisung und Datenzugriff je Rolle |
| Benutzer | Benutzerkonten, Profile, Rollen und Projektzuweisungen |
| Auswertung | Word-, Excel- und PDF-Exports sowie Auswertungen |
| Anwesenheitsliste | Anwesenheiten, BOP-Anwesenheitslisten und BIBB-Anwesenheitslisten |
| Einteilung | Bereichseinteilung, Parameter, Runden, Gruppen-Generierung und Excel-Export |
| Bereichauswahl | BOP-Bereichsauswahl und deren Einstellungen |
| Dateimanager | Dateien, Ordner, Upload, Download, Freigabe und Versand |
| Kalender | Kalender, Kalenderereignisse, Import, Export, Verschieben und Kopieren |
| Kontakte | Kontakte im Apps-Arbeitsbereich |
| Taskmanager | Aufgaben, Workflow-Vorlagen und Workflow-Anwendung |
| Abteilung | Abteilungen, Leitungen und Assistenzzuordnungen |
| Projekt | Projekte, Kostenstellen, Projekt-Dokumente und projektweite Mitarbeitersicht |
| Geraet | Geraete, Ausgaben, Rueckgaben, Ausleihende und Geraeteexporte |
| Standort | Standorte und Standort-Stammdaten |
| Fahrkarten | Fahrtarten, Fahrtkostensaetze und Fahrtkostenabrechnungen |
| Printing | Druck- bzw. Printingbereich |
| Raeumlichkeiten | Raeume, Raumbelegung und Raummeldungen |
| Dienstwagen | Dienstwagen, Wartungen, Kosten, Berichte und Fahrtenbuch |
| Personal | Personaluebersicht, Mitarbeiterdaten und Personalzuweisungen |
| Bestellungen | Materialanforderungen, sachliche Freigabe, kaufmaennische Freigabe und Bestellwesen |

## Administrator

`Administrator` ist die einzige Rolle, die pauschal alle Permissions erhalten soll.

Das bedeutet:

- Administratoren koennen alle Seiten und Funktionen sehen, sobald die Backend-Routen die jeweilige Permission pruefen.
- Administratoren koennen alle Rollen- und Permission-Einstellungen bearbeiten.
- Administratoren koennen Rollen anlegen und loeschen.
- Administratoren koennen Datenzugriffe je Rolle setzen.
- Administratoren koennen alle Exporte, Genehmigungen, Loeschaktionen und Sonderfunktionen ausfuehren.

Wichtig: Die Permission-Vergabe allein ersetzt keine fachliche Datensatzpruefung. Bei sensiblen Modulen wie Teilnehmer, Materialanforderung, Klassenbuch, Apps-Dateien oder Gruppen muss weiterhin geprueft werden, ob der konkrete Datensatz fuer den Benutzer erlaubt ist.

## Vollstaendiger Einzelkatalog

Der vollstaendige Einzelkatalog ist in `database/seeders/UserSeeder.php` in der Methode `permissionCatalog()` gepflegt.

Dort gilt:

- Jede Permission hat einen eindeutigen Namen.
- Jede Permission hat `guard_name = web`.
- Jede Permission ist einer Berechtigungskategorie zugeordnet.
- Jede Permission hat eine ausfuehrliche Beschreibung im Feld `beschreibung`.

Bei der letzten Pruefung enthielt der Katalog `298` Permissions, ohne doppelte Namen und ohne leere Beschreibungen.

## Vue-Oberflaeche

Vue verwendet den zentralen Helper `resources/js/utils/permissions.js`.

Dieser Helper stellt bereit:

| Helper | Zweck |
| --- | --- |
| `can(permission)` | Prueft, ob der aktuelle Benutzer eine einzelne Permission besitzt |
| `canAny([permissions])` | Prueft, ob mindestens eine Permission aus einer Liste vorhanden ist |
| `canAll([permissions])` | Prueft, ob alle Permissions aus einer Liste vorhanden sind |

Regel fuer Vue:

- Menues, Links und Buttons werden mit `can(...)` oder `canAny(...)` ein- oder ausgeblendet.
- Vue darf keine direkten Rollenpruefungen wie `roles.includes('Administrator')` fuer fachliche Funktionen verwenden.
- Vue darf keine eigene Sicherheitsentscheidung treffen. Die echte Absicherung bleibt immer in Route, Controller, Policy oder Gate.
- Permission-Namen in Vue muessen den Namen aus `permissionCatalog()` entsprechen.
- Administratoren sehen diese Elemente automatisch, weil die Administrator-Rolle im Seeder alle `web`-Permissions erhaelt.

## Vergabe an andere Rollen

Andere Rollen sollen nicht pauschal alle Rechte bekommen. Empfohlene Vergabe:

| Rolle | Empfohlener Ansatz |
| --- | --- |
| Abteilungsleitung | Uebersichten und Bearbeitung fuer eigene Abteilung, Pruefrechte fuer Klassenbuch und sachliche Freigaben |
| Assistenz der Abt.-Leitung | Aehnlich Abteilungsleitung, aber Loesch- und Adminrechte restriktiver |
| Sozialpaedagoge | Teilnehmer- und Gruppenzugriff fuer eigene Projekte oder Standorte, keine globalen Adminrechte |
| Anleiter | Eigene Gruppen, Anwesenheit, Klassenbuch und relevante Teilnehmerdaten |
| Sekretariat | Stammdaten, Teilnehmeranlage, Dokumente, Materialanforderungen und ausgewaehlte Exporte |
| Personalabteilung | Personal- und Benutzeruebersicht, Mitarbeiterdaten und Rollen nur nach Bedarf |
| Bestellwesen | Materialanforderungen im Bestellstatus, Bestellwesen-Update und ggf. kaufmaennische Sicht |
| Buchhaltung | Finanzbereich, Fahrtkosten, Materialanforderung kaufmaennische Sicht und relevante Exporte |
| Developer | Nur technische Pruef- und Wartungsrechte, nicht automatisch alle produktiven Adminrechte |

## Checkliste fuer neue Permissions

1. Permission in `permissionCatalog()` anlegen.
2. Aussagekraeftige Beschreibung eintragen.
3. Passende Kategorie setzen.
4. Backend-Route oder Controller mit `can(...)`, `$user->can(...)`, `authorize(...)` oder vergleichbarer Logik absichern.
5. Frontend nur zusaetzlich ausblenden, niemals als alleinige Sicherheit verwenden.
6. Rolle im Berechtigungsbereich gezielt berechtigen.
7. Bei sensiblen Daten immer zusaetzlich Datensatz- oder Scope-Pruefung verwenden.
