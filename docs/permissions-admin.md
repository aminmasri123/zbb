# Permission-Dokumentation fuer Administratoren

Stand: 2026-07-12

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
| Anwesenheitsliste | Anwesenheitserfassung, Exporte, Archivierung und abrechnungsbezogene BOP-Anwesenheitsunterlagen |
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

## Anwesenheitsberechtigungen

Die Anwesenheit verwendet bewusst sechs fachliche Permissions. Technische Routen wie Vorschau, Entwurf laden, Entwurf speichern oder einzelne Exportformate erhalten keine eigenen Rollenrechte. Sie werden intern auf die passende fachliche Permission abgebildet.

| Permission | Genaue Wirkung |
| --- | --- |
| `anwesenheit.index` | Erlaubt das Einsehen von Anwesenheitseintraegen, Anwesenheitsstatus, Soll- und Ist-Zeiten, Bemerkungen und daraus berechneten Auswertungen. Sichtbar sind ausschliesslich Teilnehmer, die der Benutzer gemaess aktivem Projekt und rollenbezogenem Datenzugriff sehen darf. |
| `anwesenheit.manage` | Erlaubt das Erfassen neuer und das Bearbeiten bestehender Anwesenheitseintraege. Dazu gehoeren Status, Datum, Gruppe, geplante Zeiten, tatsaechliche Zeiten und Bemerkungen. Endgueltiges Loeschen, Exporte, Archivablage und BOP-Abrechnung sind nicht enthalten. |
| `anwesenheit.destroy` | Erlaubt das endgueltige Loeschen einzelner Anwesenheitseintraege. Die Permission gilt nur innerhalb des aktiven Projekts und des fuer die Rolle erlaubten Teilnehmerbereichs. |
| `anwesenheit.export` | Erlaubt normale, nicht abrechnungsbezogene Anwesenheitslisten und Auswertungen fuer erlaubte Gruppen oder Projekte zu exportieren. Dazu gehoeren insbesondere gruppen- und zeitraumbezogene Anwesenheitsexporte. |
| `anwesenheit.archiv` | Erlaubt das Erzeugen von Archivordnern und das Ablegen signierter PA- oder BIBB-PDF-Anwesenheitslisten. Diese Permission berechtigt nicht automatisch zum Oeffnen oder Bearbeiten des Abrechnungsworkflows; dafuer wird zusaetzlich `anwesenheit.abrechnung` benoetigt. |
| `anwesenheit.abrechnung` | Erlaubt alle abrechnungsbezogenen BOP-Anwesenheitsunterlagen zu oeffnen, vorzubereiten, als Entwurf zu bearbeiten und zu exportieren. Dazu gehoeren Anwesenheitsliste Vorbereitung PA, Anwesenheitsliste PA, Anwesenheitsliste BO/BIBB, Rolltag, Anwesenheitsdaten und Anwesenheitsliste Rechnung. Archivordner und signierte PDF-Ablage bleiben `anwesenheit.archiv` vorbehalten. |

Permissions beantworten die Frage, welche Aktion erlaubt ist. Das aktive Projekt und der rollenbezogene Datenzugriff beantworten getrennt davon, fuer welche Teilnehmer, Gruppen, Standorte und Partner diese Aktion ausgefuehrt werden darf. Deshalb gibt es keine doppelten Permissions wie `anwesenheit.manage.eigenes-projekt`.

## Bereichsauswahl

Die Bereichsauswahl trennt die Bearbeitung einzelner Teilnehmerwahlen von der zentralen Planung. Alle Aktionen bleiben auf Partner des aktiven Projekts begrenzt.

| Permission | Genaue Wirkung |
| --- | --- |
| `bereichsauswahl.index` | Erlaubt das Einsehen der Bereichswahlen, Zugangscodes, offenen Wahlen und des Bearbeitungsstands fuer einen Partner, ein Schuljahr und einen Teilabschnitt. Es erlaubt keine Aenderung. |
| `bereichsauswahl.store` | Erlaubt das erstmalige Erfassen einer noch nicht vorhandenen Bereichsauswahl fuer einen Teilnehmer. Sobald bereits eine Auswahl existiert, reicht dieses Recht nicht zum Aendern aus. |
| `bereichsauswahl.update` | Erlaubt das Bearbeiten und Korrigieren einer bereits vorhandenen Teilnehmerwahl. Teilnehmerzugang und Anzahl der Wahlfelder sind nicht enthalten. |
| `bereichsauswahl.destroy` | Erlaubt das Zuruecksetzen oder Loeschen einer bestehenden Teilnehmerwahl, ohne die Teilnehmer-Stammdaten zu loeschen. Dieses Recht ist fuer eine separate Loeschfunktion vorgesehen und wird nicht mit `update` zusammengelegt. |
| `bereichsauswahl.planning` | Erlaubt ausschliesslich die zentrale Planung: Teilnehmerzugang aktivieren oder deaktivieren und zwei, drei oder vier Wahlfelder festlegen. Es erlaubt nicht das Bearbeiten einzelner Teilnehmerwahlen. |

Die Bereichsauswahl-Seite ist fuer Benutzer mit `index`, `store`, `update` oder `planning` erreichbar, damit auch aufgabenspezifische Rollen ihre erlaubte Funktion ausfuehren koennen. Auf der Seite werden Wahlfelder je Teilnehmer nur mit `store` beziehungsweise `update` aktiv; die zentralen Planungsfelder nur mit `planning`.

## Einteilung

Die Einteilung verwendet sechs fachliche Permissions. Technische Routen fuer Parameter, Rundentausch und Gruppengenerierung werden gemeinsam auf `einteilung.planning` abgebildet.

| Permission | Genaue Wirkung |
| --- | --- |
| `einteilung.index` | Erlaubt das reine Einsehen vorhandener Einteilungen, Runden, Kapazitaeten und Teilnehmerzuordnungen im aktiven Projekt. |
| `einteilung.store` | Erlaubt das manuelle Anlegen einer neuen Einteilung und das automatische Berechnen und Speichern einer Einteilung aus den vorhandenen Bereichswahlen. |
| `einteilung.update` | Erlaubt das Bearbeiten einer bestehenden Einteilung sowie das Verschieben oder Neuzuordnen einzelner Teilnehmer zwischen Bereichen und Runden. |
| `einteilung.destroy` | Erlaubt das Zuruecksetzen oder Loeschen der Einteilungen des gewaehlten Partners, Schuljahrs und Teilabschnitts. Verknuepfte Zuordnungen in bereits generierten Gruppen werden synchron bereinigt. |
| `einteilung.export` | Erlaubt den Excel-Export der Einteilung mit Runden, Bereichen und Teilnehmerzuordnungen. Es erlaubt keine Datenveraenderung. |
| `einteilung.planning` | Erlaubt Einteilungsparameter wie Rundenzahl und Bereichskapazitaeten zu setzen, komplette Runden zu tauschen und aus der fertigen Einteilung echte Gruppen mit Zeitraum, Uhrzeit, Raum und Betreuer zu generieren. Die Anzahl der Wahlfelder gehoert ausdruecklich zu `bereichsauswahl.planning`. |

Die Einteilungsseite ist erreichbar, sobald mindestens eine dieser sechs Permissions vorhanden ist. Jeder Button und jede Teilnehmerzeile wird separat nach der dazugehoerigen Permission freigeschaltet. Direkte Requests bleiben durch das zentrale Route-Permission-Mapping ebenfalls geschuetzt.

## Schul- und Ansprechpartnerdokumente

Diese Permissions sind bewusst allgemein benannt. Weder ein Projektname noch Bezeichnungen wie BOP, PA oder POBO sind Bestandteil des Permission-Namens. Dadurch koennen dieselben fachlichen Rechte projektartenuebergreifend verwendet werden. Das aktive Projekt, die ausgewaehlte Schule, das Schuljahr, der Teilabschnitt und der rollenbezogene Datenzugriff begrenzen weiterhin, welche Daten tatsaechlich verarbeitet werden duerfen.

| Permission | Genaue Wirkung |
| --- | --- |
| `dokumente.schule.export` | Erlaubt den Export schulweiter Dokumente fuer alle Teilnehmer der ausgewaehlten Schule. Enthalten sind Hausordnung, beide Varianten des PA-Auswertungsbogens, POBO-Zertifikate als Word und PDF sowie die POBO-Auswertung insgesamt oder nach Runde. Das Recht erlaubt nur Erzeugung oder Download. Dauerhafte Ablage auf dem Server und Teilnehmerlisten sind nicht enthalten. |
| `teilnehmer.liste.export` | Erlaubt den Export einer schulweiten Teilnehmerliste mit personenbezogenen Stammdaten wie Vorname, Nachname, Geschlecht, Geburtsdatum und Klasse. Wegen dieses personenbezogenen Umfangs bleibt die Teilnehmerliste von den allgemeinen Schuldokumenten getrennt. Andere Teilnehmerdokumente oder Auswertungen sind nicht enthalten. |
| `dokumente.ansprechpartner.manage` | Erlaubt das Erzeugen und dauerhafte Ablegen der fuer schulische Ansprechpartner bestimmten Unterlagen. Enthalten sind die Liste fehlender Elterneinverstaendniserklaerungen, das Anlegen der vorgesehenen Ordnerstruktur sowie das Generieren von BO-Auswertungen und PA-Berichten in diese Ordner. Dieses Recht beinhaltet Schreibzugriffe auf die serverseitige Dokumentablage, aber keine sonstigen Datei- oder Teilnehmeraenderungen. |

Technische Routen und Dateiformate erhalten keine eigenen Rollenrechte. Word- und PDF-Varianten desselben fachlichen Schuldokuments werden gemeinsam ueber `dokumente.schule.export` gesteuert. Funktionen, die Dateien dauerhaft in Ordner schreiben, bleiben wegen ihrer zusaetzlichen Wirkung unter `dokumente.ansprechpartner.manage` getrennt.

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

Bei der letzten Pruefung enthielt der Katalog `303` Permissions, ohne doppelte Namen und ohne leere Beschreibungen.

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
