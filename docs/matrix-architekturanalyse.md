# Matrix – technische und fachliche Architekturanalyse

Ergänzende Nachweise: `docs/matrix-tabelleninventar.md` enthält die vollständige Zuordnung aller Live-Tabellen; `docs/matrix-abschlussaudit.md` gleicht den Masterauftrag anforderungsweise mit dem Abschlusszustand ab.

Stand: 10.07.2026  
Auftrag: zunächst Bestandsanalyse und Plan; anschließend additive, phasenweise Umsetzung ohne strukturelles Big-Bang-Refactoring

## A. Zusammenfassung

Matrix ist eine gewachsene Laravel-12-/Inertia-2-/Vue-3-Anwendung. Die Erstaufnahme umfasste 335 Anwendungsrouten; nach den additiven Modul-, Projekt- und Sicherheitsgrenzen sind es zum Abschlussaudit 376 Routen, rund 120 Models und mehr als 100 fachliche Tabellen. Die Anwendung besitzt bereits einen brauchbaren gemeinsamen Kern: `personens` speichert Personen unabhängig von einer Maßnahme; `projekt_has_personens` bildet eine eigenständige Projektteilnahme mit Status und Standort ab. Räume, Geräte, Lager und Dienstwagen haben jeweils eigene Tabellen und bleiben fachlich unabhängige Plattformmodule.

Die größte Hürde ist nicht die fehlende Ordnerstruktur, sondern die fehlende explizite Modul- und Projekttypsemantik. `projekts` enthält einzelne Feature-Schalter (`klassenbuch_aktiv`, `potenzialanalyse_aktiv`), aber keinen Projekttyp. BOP wird durch Controller-, Routen- und Dateinamen sowie durch implizite Datenkonventionen ausgedrückt. Eine zentrale, serverseitig geprüfte Modulaktivierung existiert nicht.

Die wichtigsten Risiken sind:

1. Die Testbaseline war durch eine nicht portable Potenzialanalyse-Migration blockiert. Nach dem Phase-0-Fix bestehen 52 Tests; 4 werden wegen deaktivierter Features erwartungsgemäß übersprungen. Der Produktionsbuild ist erfolgreich.
2. Zentrale Controller bündeln viel Geschäftslogik: `ProjektBopController` 2.573, `AppsController` 1.615 und `EinteilungParameterController` 1.407 Zeilen.
3. Es gibt keine Policies und keine Form-Request-Klassen. Autorisierung erfolgt überwiegend über Routennamen und globale Middleware; Datensatz- und Standortzugriff ist nur punktuell implementiert.
4. Die BOP-Legacy-Routen liegen innerhalb der authentifizierten Routengruppe und werden über den Permission-Katalog geschützt. Absichtlich öffentlich sind nur die tokenbasierte Bereichsauswahl und allgemeine Seiten; die öffentlichen Codeaktionen sind seit Phase 1 rate-limited.
5. Alte Migrationen verwenden uneinheitliche Fremdschlüssel und Löschregeln; neuere Module sind deutlich sauberer modelliert. Bestehende Migrationen dürfen nicht nachträglich geändert werden.
6. `Personen::booted()` löscht polymorphe Adressen und Kontakte beim Löschen einer Person. Das ist bei Teilnehmerdaten daten- und datenschutzkritisch und benötigt eine explizite Aufbewahrungsstrategie.

Empfohlene Reihenfolge: Baseline und Sicherheitsgrenzen stabilisieren → kleines Modulregister ergänzen → Projekttypen additiv einführen → BOP hinter bestehender Oberfläche explizit kennzeichnen → erst danach BvB Reha als separates Fachmodul entwickeln. Keine sofortige Umstellung auf `app/Core`/`app/Modules`; zunächst Services, Middleware und Namespaces nur für neuen Code verwenden.

## B. Aktuelle Architektur

### Backend

- Laravel 12, PHP 8.2, Jetstream/Fortify, Sanctum, Spatie Permission.
- 376 Anwendungsrouten in einer weiterhin großen `routes/web.php` (Erstaufnahme: 335).
- 66 Controller; Geschäftslogik, Validierung, Datei-/Office-Export und Datenzugriff liegen häufig direkt in Controllern.
- Acht Services kapseln unter anderem Raumbelegung, Dienstwagenverlauf, Benachrichtigungsempfänger, Modulstatus, Projekttypzuweisung, Mitarbeiter-Projektzuweisung und öffentlichen BOP-Zugang.
- Keine Policies, Form Requests, Jobs, fachlichen Events oder Listener.
- Exporte nutzen DOMPDF, PhpSpreadsheet und PhpWord; BOP-Exporte sind in mehreren großen Controllern verteilt.
- Transaktionen werden in kritischen neueren Workflows genutzt, unter anderem Lager, Einteilung, Dokumente, Projektpflege und Geräteausgabe. Das ist beizubehalten.

### Frontend

- Vue 3 mit Inertia; Seiten sind überwiegend nach sichtbaren Funktionsbereichen gegliedert (`BOP`, `Raum`, `ITService`, `Lager`, `Dienstwagen`, `Teilnehmer`, `Projekt`, `Dokumente`).
- `AppLayout.vue` und Sidebar-Komponenten enthalten die Navigation. Sichtbarkeit basiert auf über Inertia injizierten Permissions.
- Es gibt wiederverwendbare Permission-Hilfen sowie `useModules`; der effektive Modulkontext wird serverseitig über Inertia bereitgestellt.
- Rollen-/Modulnamen und BOP-Funktionen sind teilweise direkt in Seiten und Navigation verankert.
- Frontend-Ausblendung ist nicht der einzige Schutz: `routePermission` und die Modul-Middleware erzwingen beide Ebenen serverseitig.

### Datenbank

- Gemeinsame Person (`personens`) und Teilnahme (`projekt_has_personens`) sind bereits getrennt.
- Organisationen werden als `partners` mit Typen modelliert; Schulen scheinen fachlich als Partner-Variante behandelt zu werden. Das ist vor Einführung einer zweiten Organisationstabelle fachlich zu bestätigen.
- `standorts` ist vorhanden und wird in Projektteilnahmen, Gruppen und neueren Ressourcenmodulen verwendet, aber nicht als durchgehender Mandanten-/Zugriffsscope.
- Ressourcenmodule besitzen eigenständige Aggregate. Projektbezüge sind bei Räumen optional; IT, Lager und Dienstwagen sind nicht strukturell von BOP abhängig.
- Kritische JSON-Felder sind überwiegend Konfiguration/Audit (`channels`, `ausgabeformate`, `changes_json`). Sie sind derzeit kein Ersatz für zentrale Geschäftsobjekte.

### Berechtigungen

- Spatie Permission ist die maßgebliche Rollen-/Rechtebasis.
- `AuthorizeRoutePermission` leitet Berechtigungen aus dem Routennamen ab; `RoutePermissionMap` pflegt Legacy-Aliase.
- `RoleDataAccessSetting` ergänzt Datenzugriff für Teilnehmer (`all`, Abteilung, eigene Projekte, eigene Standorte usw.).
- Keine Model-Policies: Besitz, Standort, Projektzugehörigkeit und einzelne Datensätze werden nicht konsistent an einer Stelle geprüft.
- Modulaktivierung und Permission sind getrennte, kumulative Voraussetzungen. Datensatz-/Standortregeln bleiben außerhalb der bereits abgedeckten Bereiche weiterhin uneinheitlich.

### Abhängigkeitsbild

```text
User -- person_id --> Person --< Projektteilnahme >-- Projekt
                         |               |               |
                         |             Standort          +-- BOP-spezifische Abläufe
                         +-- Dokumente/Notizen/Sozialdaten    (Gruppen, PA, Einteilung, Exporte)

Plattformressourcen
  Räume -------- optional Projekt/Gruppe
  IT-Geräte ----- Standort/Person
  Lager --------- Benutzer/Person, derzeit ohne zwingendes Projekt
  Dienstwagen --- Standort/Person/Benutzer
```

## C. Funktionsübersicht und Bewertung

| Funktion | Zustand | Bereich | Abhängigkeiten | Empfehlung | Risiko/Priorität |
|---|---|---|---|---|---|
| Benutzer/Authentifizierung | vorhanden, Baseline grün | Core | Person, Rollen | beibehalten; Schutztests ausbauen | mittel/P1 |
| Rollen und Rechte | zentral vorhanden | System | Spatie, Routennamen | beibehalten; Scope/Policies ergänzen | hoch/P0 |
| Personen | gemeinsamer Datensatz vorhanden | Core | viele Module | beibehalten; Löschung und Dubletten absichern | hoch/P0 |
| Projektteilnahmen | eigener Pivot mit Metadaten | Core | Person, Projekt, Standort | als Participation-Kern weiterentwickeln | mittel/P1 |
| Projekte | generischer Kern mit optionalem Typ | Core | fast alle Fachbereiche | Typ-/Modulgrenze beibehalten; keine automatische Klassifikation | mittel/P1 |
| Standorte | vorhanden, uneinheitlich genutzt | Core | Personen, Gruppen, Ressourcen | Scope schrittweise vereinheitlichen | hoch/P1 |
| Partner/Schulen | Partner plus Typen | Verwaltung/BOP | Projekte, Ansprechpartner | beibehalten; Organisationssemantik klären | mittel/P2 |
| BOP | umfangreich produktiv, global schaltbar | Fachmodul | Projekte, Partner, Gruppen, Teilnehmer, Exporte | geschützte Modulgrenze beibehalten | hoch/P1 |
| Potenzialanalyse | neue eigene Tabellen | BOP | Projekt, Gruppe, Person | beibehalten; Projekttyp serverseitig prüfen | hoch/P1 |
| Klassenbuch | eigener Tabellenverbund | projektübergreifend möglich | Gruppe, Benutzer | zunächst optionales Projektfeature lassen | mittel/P2 |
| Räume | eigenständig, Reservierung vorhanden, schaltbar | Ressourcen | optional Projekt/Gruppe, Standort | Standortpilot beibehalten | mittel/P1 |
| IT/Geräte | Inventar, Ausgabe/Rückgabe, Tickets, global schaltbar | Ressourcen | Person, User, Standort | beibehalten; Standortscope erst nach Aggregatprüfung | mittel/P1 |
| Lager | Artikel, Bewegung, Reservierung, global schaltbar | Ressourcen | User/Person | beibehalten; Kostenstelle/Projekt nur optional | mittel/P1 |
| Dienstwagen | Fahrzeuge, Buchung, Meldung, Historie, global schaltbar | Ressourcen | Person, User, Standort | beibehalten; Standortscope erst nach Query-Prüfung | mittel/P1 |
| Dokumente | zentral, projektbezogene Kategorien | Plattform | Projekte, Bereiche | beibehalten; Zugriff/Download policy-basiert härten | hoch/P1 |
| Berichte/Exporte | breit vorhanden, BOP-lastig | Shared/BOP | Dateien, Teilnehmerdaten | nach Use Case trennen, nicht neu schreiben | hoch/P1 |
| Apps (Dateien/Kalender/Kontakte/Aufgaben) | vorhanden, großer Controller | optionale Plattformmodule | User, Person, Shares | später lokal in Services zerlegen | mittel/P3 |
| Materialanforderung/Bestellung | eigener Workflow | Verwaltung | Projekte/Kostenstellen/Benachrichtigung | beibehalten | mittel/P2 |
| Fahrtkosten | eigener Workflow | Verwaltung | Person | beibehalten | niedrig/P3 |
| Audit | `activities`, Teilhistorien vorhanden | System | einzelne Module | einheitliches Audit-Konzept ergänzen | hoch/P1 |

Bewertungslegende: P0 vor Architekturänderung, P1 Modulgrundlage, P2 nach Stabilisierung, P3 nur bei konkretem Nutzen.

## D. Modulübersicht

| Modul | Vorhanden | Aktivierbar | Backend-Schutz | Bedarf |
|---|---:|---:|---:|---|
| BOP | ja | ja, global | Modul-Gate + Permission | Fachfunktionen erhalten; Standortscope erst nach vollständiger Datenprüfung |
| BvB Reha | Projektkontext vorbereitet | ja, global; Default aus | Modul-Gate + Projektkontext + Permission + Datenscope | schreibende Fachaggregate erst nach Fachfreigabe |
| Raumverwaltung | ja | ja, global/Standort | Modul-Gate + Permission | Standortpilot beibehalten und beobachten |
| IT-Verwaltung | ja | ja, global | Modul-Gate + Permission | Standortscope erst nach konsistenten Aggregatgrenzen |
| Lagerverwaltung | ja | ja, global | Modul-Gate + Permission | Standortmodell fehlt; optionale Projekt/Kostenstelle fachlich klären |
| Dienstwagen | ja | ja, global | Modul-Gate + Permission | Standortscope erst nach vollständiger Query-Prüfung |
| Dokumente | ja | nein | Permission | Modulstatus und Datensatz-Policy |
| Klassenbuch | ja, projektweise Flag | teilweise | Permission | Flag in Modul-/Projektfeaturemodell integrieren, später |
| Potenzialanalyse | ja, projektweise Flag | teilweise | Permission über Projekt/Gruppe | BOP-Scope explizit machen |
| Apps | ja | nein | Permission | bei Bedarf in einzelne Module teilen |
| Reporting/Export | ja | nein | Auth-/Exportrechte gehärtet, kein eigenes Modul | Audit und Retention weiter priorisieren |

Modul und Rolle bleiben absichtlich getrennt: Die Modulverwaltung entscheidet global beziehungsweise am Standort, ob ein Bereich technisch verfügbar ist. Die vorhandene Rollen-/Rechteverwaltung entscheidet weiterhin administrativ über Sichtbarkeit sowie Lesen, Bearbeiten, Löschen, Exportieren und Konfigurieren. Die Navigation prüft beide Ebenen; die Backend-Routen erzwingen ebenfalls Modulstatus und Permission. Ein zusätzliches rollenbezogenes Feld in `module_assignments` wäre doppelte Autorisierungslogik und wird deshalb nicht eingeführt.

### Empfohlenes Modulmodell

Additiv umgesetzt, ohne Paket und ohne Datenverschiebung:

```text
modules
  id, key(unique), name, description, category, is_system_module,
  is_enforced, supports_location_scope, default_enabled, status, timestamps

module_assignments
  id, module_id, scope_key, location_id(nullable), enabled,
  settings_json(nullable), activated_by_user_id, timestamps
  unique(module_id, scope_key)
```

Auflösung heute: spezifischer Standort → globaler Eintrag → Moduldefault. `tenant_id` wurde bewusst nicht vorbereitet, weil noch kein belastbares Mandantenobjekt oder kurzfristiger Bedarf bestätigt ist. Deaktivierung ändert ausschließlich Zugriff und Navigation, niemals Fachdaten.

Request-Prüfreihenfolge:

```text
authentifiziert? -> Modul effektiv aktiv? -> Permission? -> Datensatz/Standort/Projekt erlaubt?
```

Umgesetzte Bausteine: Datenbankkatalog `modules`, `ModuleStateResolver`, `EnsureModuleEnabled`-Middleware, Inertia-Share `enabledModules`, Admin-Seite und Featuretests. Permissions bleiben unverändert die Aktionskontrolle; ein zusätzlicher Codekatalog wurde mangels konkreten Nutzens nicht eingeführt.

## E. Bildungsprojekte

### BOP

BOP ist derzeit sowohl explizit (`ProjektBopController`, BOP-Seiten/Exporte) als auch implizit in generischen Projekten, Gruppen, Bereichen, Partnern und Teilnehmern enthalten. Es darf nicht sofort physisch verschoben werden. Zuerst wird ein Projekttyp `bop` additiv eingeführt und für bestehende Projekte über eine geprüfte Zuordnung befüllt. Alle bestehenden URLs und Oberflächen bleiben erhalten.

Konkrete Problemzonen:

- sehr großer BOP-Controller mit Export-, Draft-, Signatur-, Datei- und Fachlogik;
- umfangreiche Legacy-Exportrouten innerhalb einer großen Routengruppe; Auth- und Permission-Schutz werden durch einen Regressionstest abgesichert;
- BOP-spezifische Bereichsauswahl und Einteilung verwenden generische Tabellen;
- Gruppenexports und Potenzialanalyse sind über Gruppe/Projekt gekoppelt, ohne Projekttypprüfung;
- öffentliche Selbstwahl per Token ist fachlich sinnvoll möglich, muss aber Rate Limit, Ablauf, Audit und minimale Datenoffenlegung garantieren.

### Gemeinsamer Projektkern

Beibehalten bzw. schrittweise stabilisieren:

- `personens` als Person;
- `projekts` als konkrete Maßnahme/Projektinstanz;
- `projekt_has_personens` als Participation;
- `partners` als Organisation, sofern Fachklärung dies bestätigt;
- `standorts`, Gruppen, Dokumente und Zeiträume als gemeinsame Dienste.

Ergänzen: `project_types` (`bop`, `bvb_reha`, `bae`, …) und `project_type_id` in `projekts`. Kein `if ($type === ...)` in beliebigen Controllern; projekttypbezogene Fähigkeiten werden über Handler/Services oder klar abgegrenzte Controller aufgerufen.

### BvB Reha

BvB Reha erhält keine Spalten in BOP-Tabellen. Nach Fachworkshops entsteht ein eigener Tabellenverbund, beispielsweise Zuweisung, Aufnahme, Diagnostik, Förderplan, Ziele, Praktika, Verlauf und Abschlussbericht, jeweils an `projekt_has_personens` statt direkt nur an `personens` gebunden. So bleiben dieselbe Person und mehrere Teilnahmen sauber getrennt. Sensible Diagnostik benötigt eigene Permissions, Policies, Audit und Aufbewahrungsregeln.

## F. Verwaltungsfunktionen

### Raumverwaltung

`raeumes`, `raum_buchungen`, `raum_meldungen` und `projekt_has_raeumes` bilden einen eigenständigen Bereich. Buchungen können Projekt/Gruppe optional referenzieren; das entspricht dem Ziel. `RaumBelegungService` kapselt bereits Konfliktlogik. Beibehalten, um Modulstatus und Standortprüfung ergänzen; Konflikt- und Parallelbuchungstests priorisieren.

### IT

`geraets`, Ausgabe/Rückgabe-Pivots und `it_tickets` decken Inventar, Zuordnung und Service ab. Standort und verantwortliche Person wurden additiv ergänzt. Beibehalten; Geräteausgabe/-rückgabe und Ticketstatus als Transaktionsgrenzen testen. Lizenzen, Wartung und Reparaturhistorie nur nach bestätigtem Bedarf ergänzen.

### Lager

`lager_artikel`, `lager_bewegungen`, `lager_reservierungen` sind eigenständig und transaktional verarbeitet. Keine zwingende Projektbeziehung. Beibehalten; optional später `projekt_id` oder besser Kostenstelle/Buchungsdimension nullable ergänzen. Bestand darf ausschließlich über Bewegungen geändert werden.

### Dienstwagen

Eigenständige Tabellen für Fahrzeuge, Fahrer, Fahrtenbuch, Kosten, Wartung, Buchungen, Meldungen und Verlauf. Beibehalten. `DienstwagenVerlaufService` ist eine gute lokale Kapselung. Vor Erweiterungen Konflikt-, Kilometerstand-, Fahrer- und Berechtigungstests ergänzen.

## G. Datenbankanalyse und vorgeschlagene Änderungen

### Tabellenzuordnung

Das vollständige Einzelinventar aller 137 Tabellen mit Bereich, Zweck, Abhängigkeiten, Befund und Empfehlung befindet sich in `docs/matrix-tabelleninventar.md`. Die folgende Tabelle verdichtet dieses Inventar nach fachlichen Bereichen.

| Bereich | Tabellen | Zweck/Abhängigkeiten | Problem/Empfehlung |
|---|---|---|---|
| System/Auth | `users`, `password_resets`, `sessions`, `personal_access_tokens`, `failed_jobs`, `notifications` | Login, Tokens, Queue, Benachrichtigung | beibehalten; Testbaseline reparieren |
| Rechte | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `berechtigungskategories`, `role_berechtigungskategories`, `role_data_access_settings` | Rollen, Rechte, Datenzugriff | Modulstatus getrennt ergänzen |
| Core Person | `personens`, `adresses`, `kontaktes`, `kontakttypens`, `baenkes`, `standort_has_personens` | Stammdaten/Standorte | Dubletten, Löschung, Aufbewahrung prüfen |
| Organisation | `partners`, `partnerschaftstypens`, `partner_has_partnerschaftstypens`, `partner_has_personens` | Schulen/Betriebe/Ansprechpartner | Semantik vor Generalisierung klären |
| Projekte/Participation | `projekts`, `projektzeitraums`, `projekt_has_personens`, `projekt_has_personen_metas`, `projekt_has_ansprechpartners`, `projekt_has_partners`, `projekt_has_kostenstelles`, `projekt_has_bereiches`, `projekt_has_raeumes`, `projekt_has_dokumentes`, `projekt_has_dokument_kategories` | Projektinstanz und Zuordnungen | Projekttyp fehlt; Pivot trägt mehrere Verantwortungen |
| BOP Teilnehmer | `personen_ist_schuelers`, `gruppen`, `gruppe_has_personens`, `bereiches`, `bereich_has_personens`, `bereichsauswahls`, `bereichsauswahl_settings`, `einteilung_bereiches`, `einteilung_settings`, `einteilung_bereich_kapazitaeten` | Schule, Gruppe, Bereiche, Wahl/Einteilung | BOP-Scope implizit; später über Projekttyp schützen |
| BOP Fach-/Abschlussdaten | `anwesenheitsstatutens`, `tages`, `zeitens`, `zeitraums`, `ergebnisses`, `personen_has_egebnisses`, `austritttypens`, `verbleibteilnehmers`, `zielgruppes`, `personen_has_zielgruppes`, `abschluesse`, `personen_has_abschluesses`, `leistungsbezueges`, `personen_has_sozialedatens`, `notizvariantens`, `personen_has_notizens`, `vermitlungshemmnisses`, `projekt_has_teilnehmer_luvs`, `projekt_has_teilnehmer_abschlusses`, `personen_has_bildungsmassnahmens` | Teilnahme-, Sozial- und Abschlussdaten | teils Person statt Participation; datenfachlich prüfen, nicht blind migrieren |
| Potenzialanalyse | `potenzialanalyse_uebungen`, `potenzialanalyse_kriterien`, `potenzialanalyse_beurteilungen`, `potenzialanalyse_selbsteinschaetzungen`, `potenzialanalyse_berichte`, `potenzialanalyse_uebung_ergebnisse`, `potenzialanalyse_kompetenzbewertungen` | BOP-PA an Projekt/Gruppe/Person | Löschkaskaden datenrechtlich prüfen |
| BOP Dokumentdrafts | `bibb_attendance_list_drafts`, `pa_attendance_list_drafts` | signierte/temporäre Anwesenheitslisten | Retention/Encryption fortführen und testen |
| Klassenbuch | `klassenbuch_typen`, `klassenbuecher`, `klassenbuch_wochen`, `klassenbuch_eintraege`, `klassenbuch_kommentare` | Gruppenwochen, Freigabe, Kommentare | als optionales Projektfeature geeignet |
| Räume | `raeumes`, `raum_buchungen`, `raum_meldungen` | Räume, Reservierung, Störung | eigenständig; Modul/Standort ergänzen |
| IT | `geraets`, `geraetausgabes`, `geraet_has_ausgabes`, `geraetrueckgabes`, `geraet_has_rueckgabes`, `it_tickets` | Inventar, Ausgabe/Rückgabe, Tickets | eigenständig; Historie/Lizenz nur bei Bedarf |
| Lager | `lager_artikel`, `lager_reservierungen`, `lager_bewegungen` | Bestand und Reservierung | eigenständig; Projektbezug optional |
| Dienstwagen | `dienstwagens`, `dienstwagen_has_personens`, `dienstwagenfahrtenbuches`, `dienstwagenkostenaufzeichnungens`, `dienstwagenwartungsaufzeichnungens`, `dienstwagen_buchungen`, `dienstwagen_meldungen`, `dienstwagen_verlaeufe` | Fuhrpark | eigenständig; Cascade bei Fahrzeuglöschung prüfen |
| Dokumente/Kommunikation | `dokumentes`, `dokument_kategories`, `dokument_has_kategories`, `dokument_has_bereiches`, `briefs`, `freigabes`, `activities` | Dateien, Briefe, Freigaben, Audit | Download-Policy/Audit härten |
| Apps | `app_files`, `app_calendars`, `app_calendar_events`, `app_calendar_styles`, `app_contacts`, `app_tasks`, `app_task_workflow_templates`, `app_task_workflow_steps`, `app_popups`, `app_shares` | persönliche Arbeitswerkzeuge | großer Controller; erst bei Änderungen extrahieren |
| Finanzen/Beschaffung | `kostenstelles`, `produktes`, `bestellungs`, `materialanforderungs`, `materialanforderung_artikels`, `materialanforderung_genehmigungs`, `materialanforderung_vergabevermerks`, `fahrtartens`, `fahrtkostensaetzes`, `fahrtens`, `abrechnungens`, `abrechnung_has_fartens` | Bestellung/Fahrtkosten | beibehalten; Benennung/FKs nicht kosmetisch ändern |
| Organisation intern | `abteilungs`, `abteilungsassistents`, `abteilung_has_assistentens`, `freigabes`, `notification_rules` | Abteilungen, Freigaben, Regeln | beibehalten; Mandant ist noch nicht abgebildet |

### Additive Änderungen

| Änderung | Grund | Migration/Datenübernahme | Rollback | Risiko |
|---|---|---|---|---|
| `modules` | zentraler Katalog | neue Tabelle, deterministisch seeden | Tabelle nur bei ungenutzter Rücknahme löschen | niedrig |
| `module_assignments` | globaler/Standortstatus | neue Tabelle; Defaults entsprechen heutigem Zustand „aktiv“ | Middleware deaktivieren, Daten behalten | mittel |
| `project_types` | BOP/BvB sauber trennen | neue Tabelle | lesend ignorierbar | niedrig |
| `projekts.project_type_id` nullable | bestehende Projekte typisieren | zuerst nullable; Zuordnungsvorschau, Review, dann optional NOT NULL | Spalte vorerst behalten/Code zurückrollen | hoch |
| optionale Audit-Tabelle | sensible Änderungen nachvollziehen | erst Eventkatalog und Retention definieren | Schreiben deaktivieren, Logs behalten | mittel |

Keine vorhandene Tabelle oder Spalte wird gelöscht. Bestehende Projekte werden nicht anhand bloßer Namensheuristik automatisch dauerhaft klassifiziert; ein Dry Run erzeugt eine Reviewliste.

## H. Betroffene Dateien nach Phase

| Phase | Dateien | Geplante Änderung | Risiko | Tests |
|---|---|---|---|---|
| 0 Baseline | `phpunit.xml`, `tests/**`, Test-Env/Factories | Ursache der 40 Fehler beheben, keine Fachänderung | mittel | gesamte Suite |
| 1 Sicherheit | `routes/web.php`, `RoutePermissionMap.php`, relevante Controller | öffentliche BOP-Routen klassifizieren und schützen | hoch | unauth/auth/permission/export |
| 2 Module | neue Migrationen, `app/Models/Module.php`, `ModuleAssignment.php`, `app/Services/Modules/*`, `app/Http/Middleware/EnsureModuleEnabled.php`, `Kernel.php` | Register und serverseitiger Schutz | mittel | Resolver-/Middlewaretests |
| 2 UI | `HandleInertiaRequests.php`, `AppLayout.vue`, Sidebar-Komponenten, neue Admin-Seite | effektive Module teilen, Navigation/Admin | niedrig | Inertia Props, Browser/Feature |
| 3 Projekttypen | neue Migrationen/Models, `Projekt.php`, `ProjektController.php`, Projektseiten | Typ additiv verwalten | hoch | Backfill/Dry Run/CRUD |
| 4 BOP-Grenze | `ProjektBopController.php`, `BopLegacyFunctionController.php`, `BopGruppeExportController.php`, `Einteilung*`, BOP-Seiten | schrittweise Services extrahieren, Typprüfung | hoch | Golden-Master-Exporte/Workflows |
| 5 Ressourcen | Raum/IT/Lager/Dienstwagen Controller, Services, Routen, Seiten | nur Modul-/Scopeprüfung und konkrete Fehler | mittel | je Modul CRUD/Permission/disabled |
| 6 BvB Reha | neue `app/Modules/BvbReha/*` oder gleichwertiger Namespace, neue Migrationen/Seiten/Routen | neues Fachmodell | hoch | fachliche End-to-End-Tests |

Die vorgeschlagene `app/Core`-/`app/Modules`-Struktur wird nicht rückwirkend als Big Bang eingeführt. Neue BvB-Reha-Komponenten dürfen einen klaren Namespace erhalten; bestehender Code wird nur beim konkreten Use Case verschoben.

## I. Testplan

1. Baseline: Testdatenbank, APP_KEY, Fortify/Sanctum-Routen und Migrationen stabilisieren; Ziel 0 unerklärte Fehler.
2. Permissions: jede benannte geschützte Route im Katalog; disabled Modul liefert 404 oder 403 gemäß festgelegter Policy, auch bei direkter URL/API.
3. Module: globale und Standortauflösung, Default, Adminänderung, Cacheinvalidierung, keine Datenlöschung.
4. BOP: Teilnehmerimport, Projektzuordnung, Gruppen, Anwesenheit, Bereichsauswahl, Einteilung, PA, LUV, Dokumente und alle Exporte als Regressionstests.
5. Räume: Überschneidung, Randzeiten, parallele Buchung, optionales Projekt, deaktiviertes Modul.
6. IT: Inventar, Ausgabe/Rückgabe, Ticketstatus, Standort- und Rollenrechte.
7. Lager: Bewegung als einzige Bestandsänderung, Mindestbestand, Reservierung/Ausgabe, Parallelität.
8. Dienstwagen: Buchungskonflikt, Fahrerrecht, Kilometerlogik, Wartung/Meldung/Verlauf.
9. Migration: Produktionskopie anonymisiert, Dry Run, Anzahl/Checksummen vor und nach Backfill, Rollback des Codes ohne Datenverlust.
10. Datenschutz: Dokumentdownload, sensible Sozial-/Diagnostikdaten, Exportrecht, Audit, Retention.

Abnahmeschwelle vor jeder Phase: vorherige Baseline grün, keine Datenzahländerung außerhalb des erwarteten Scopes, dokumentierte manuelle Stichprobe und Rollback-Probe.

## J. Schrittweiser Umsetzungsplan

### Phase 0 – verlässliche Bestandsbaseline (abgeschlossen am 10.07.2026)

- Ziel: Tests spiegeln die laufende Anwendung wider.
- Aufgaben: 40 Fehler clustern (Testkonfiguration/Routen/echte Regression), Test-DB reproduzierbar machen, kritische Workflows ergänzen.
- DB: keine Produktionsänderung.
- Risiko: Testfix darf Fehler nicht durch schwächere Assertions kaschieren.
- Ergebnis: rohe MySQL-DDL-Anweisungen der Potenzialanalyse-Migration durch den portablen Laravel Schema Builder ersetzt; keine Produktionsdaten oder fachlichen Spaltendefinitionen geändert.
- Abnahme: erfüllt – 52 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen; Produktionsbuild erfolgreich.

### Phase 1 – Sicherheitsgrenzen und Dateninventar (Routenprüfung abgeschlossen am 10.07.2026)

- Ziel: unbekannte öffentliche und datensatzbezogene Zugriffe schließen.
- Ergebnis Routenprüfung: alle tatsächlich öffentlichen Routen klassifiziert; BOP-Legacy-Routen sind authentifiziert und permission-geschützt. Öffentliche Bereichsauswahlseiten sind auf 60 Anfragen/IP/Minute und Codeprüfung/-speicherung auf 10 Anfragen/IP/Minute begrenzt. Deaktivierter Zugang sperrt auch die Danke-Seite.
- Verbleibend in der breiteren Sicherheitsphase: Dokumentdownloads sowie Lösch-/Retentionmatrix fachlich prüfen.
- DB: keine destruktive Änderung.
- Risiko: bestehende externe Links könnten Authentifizierung benötigen.
- Abnahme Routenprüfung: erfüllt – Regressionstest prüft Rate Limits sowie Auth-/Permission-Middleware aller Legacy-BOP-Routen; Vollsuite mit 56 bestandenen und 4 erwartungsgemäß übersprungenen Tests.

### Phase 2 – Modulgrundlage (Raum-Pilot umgesetzt am 10.07.2026)

- Ziel: Module ohne Datenlöschung schalten.
- Ergebnis: additive Tabellen `modules` und `module_assignments`, globaler/standortbezogener Resolver, `module`-Middleware, Inertia-Prop `enabledModules`, Admin-UI und Raumverwaltung als erster vollständig durchgesetzter Pilot.
- Sicherheitsregel: Nur Module mit `is_enforced=true` dürfen geschaltet werden. Künftige Katalogeinträge bleiben wirksam aktiv und in der Admin-UI gesperrt, bis ihre Backend-Routen vollständig geschützt sind.
- DB: `modules`, `module_assignments` additiv; alle bestehenden Module initial effektiv aktiv.
- Risiko: Fehlkonfiguration sperrt Nutzer aus; Superadmin-Notzugang und Cachetests vorsehen.
- Abnahme Raum-Pilot: automatisierte Tests belegen Default aktiv, Standort überschreibt global, direkte Backend-Sperre, getrennte Permissionprüfung, unveränderte Raumdaten und Inertia-Navigationsstatus.

### Phase 3 – Projekttypen (additiv umgesetzt am 10.07.2026)

- Ziel: Projektinstanz und Fachmodul explizit trennen.
- Ergebnis: `project_types`, nullable `projekts.project_type_id`, Beziehungen, manueller Typwähler in Projektanlage/-bearbeitung und sichtbare Typkennzeichnung in Liste/Detailansicht.
- Initialer Katalog: BOP, BvB Reha, BvB, BaE, AsA flex und Coaching. BOP und BvB Reha sind mit ihren vorgemerkten Modulkatalogeinträgen verbunden.
- DB: `project_types`, nullable `project_type_id`, Index/FK.
- Bestandsschutz: kein automatischer Backfill und keine Namensheuristik. Vorhandene Projekte bleiben `NULL`, bis eine fachlich verantwortliche Person sie manuell zuordnet.
- Risiko: falsche manuelle Bestandszuordnung; eine fachliche Reviewliste bleibt vor einer verbindlichen BOP-Klassifizierung erforderlich.
- Abnahme Technik: Tests belegen Null-Default, gültige manuelle Zuordnung, Ablehnung inaktiver Typen, Modulbeziehungen und Projekterhalt beim Entfernen eines Typs.

### Phase 4 – BOP absichern und kontrolliert modularisieren (Modulgrenze umgesetzt am 10.07.2026)

- Ziel: BOP unverändert nutzbar, aber klar begrenzt.
- Ergebnis Modulgrenze: `ProjektBopController`, `BopLegacyFunctionController`, `BopGruppeExportController`, `EinteilungParameterController` und `PotenzialanalyseController` sind vollständig serverseitig an `module:bop` gebunden. Das umfasst auch die vier öffentlichen Bereichsauswahl-Routen; bei Deaktivierung antworten direkte URLs und API-Aufrufe mit 404, während Fachdaten unverändert erhalten bleiben.
- Ergebnis Scope: additive Capability `modules.supports_location_scope`. BOP ist bewusst nur global konfigurierbar; Raumverwaltung bleibt der standortfähige Pilot. Resolver, Admin-Endpoint und UI verhindern unzulässige Standortzuweisungen konsistent.
- Kleine Extraktion: die wiederholte Prüfung öffentlicher BOP-Tokens und des aktiven Zugangs liegt nun zentral in `PublicAreaSelectionAccess`; URLs, Payloads und Validierungsverhalten bleiben gleich.
- Verbleibende Aufgaben: Golden-Master-Tests für kritische Export-/Draft-Dateien; danach nur einzeln belegte Export-, Draft- oder Dateiservices aus den großen Controllern extrahieren. Eine verpflichtende Projekttypprüfung wird erst nach fachlicher Klassifizierung der Bestandsprojekte aktiviert.
- DB: additive Spalte `modules.supports_location_scope`; keine BOP-Fachdatenmigration und keine automatische Projektklassifizierung.
- Risiko: Dokumentlayout und Dateipfade.
- Abnahme Modulgrenze: erfüllt – Routenkartentest für mindestens 60 BOP-Endpunkte, Backend- und Public-404-Tests, Datenbestandsschutz, Scope-Resolver-/Admin-Tests und erfolgreicher Produktionsbuild. Vollsuite: 82 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.

### Phase 5 – Verwaltungsbereiche schaltbar machen (IT, Lager und Dienstwagen umgesetzt am 10.07.2026)

- Ziel: Räume, IT, Lager, Dienstwagen unabhängig aktivieren.
- Ergebnis: Neben dem Raum-Pilot sind jetzt auch IT-Verwaltung, Lagerverwaltung und Dienstwagenverwaltung vollständig serverseitig schaltbar. Die Modulgrenzen umfassen 28 IT-Routen einschließlich der älteren Geräteausgabe/-rückgabe, 7 Lagerrouten und 34 Dienstwagenrouten einschließlich Scan, Buchungen, Meldungen, Wartung, Kosten und Berichte.
- Navigation: Ressourcen-Sidebar und modulbezogene Dashboard-Kennzahlen berücksichtigen den effektiven Modulstatus. Berechtigungsprüfungen bleiben zusätzlich und unverändert wirksam.
- Scopeentscheidung: Die drei neuen Modulgrenzen sind zunächst global. IT-Ausgabe/-Rückgabe kann mehrere Geräte mit unterschiedlichen Standorten bündeln, Dienstwagenlisten/-berichte arbeiten derzeit standortübergreifend und Lagerdaten besitzen keinen belastbaren Standortschlüssel. `supports_location_scope=false` verhindert deshalb eine irreführende Teilisolation. Eine Standortfreigabe erfolgt erst nach vollständiger Query-, Direktzugriffs- und Aggregatsprüfung.
- DB: keine Fachdatenmigration; nur Katalogmetadaten (`is_enforced=true`, globaler Scope) in einer additiven Migration. Bestehende Modul- und Fachdaten bleiben erhalten.
- Risiko: uneinheitliche Standorte bestehender Datensätze bleiben als Voraussetzung für eine spätere Standortaktivierung dokumentiert.
- Abnahme Technik: erfüllt – Routenkartentest, direkte 404-Sperre, Datenerhalt für Gerät/Bestand/Fahrzeug, Reaktivierung, getrennte Permissionprüfung und bestehende IT-/Geräte-/Lager-Workflowtests sind grün; Produktionsbuild erfolgreich. Vollsuite: 90 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.

### Phase 6 – gemeinsamer Kern nur nach Bedarf (erste Teilnahme-Invariante umgesetzt am 10.07.2026)

- Ziel: wiederverwendbare Personen-, Organisations-, Projekt-, Teilnahme- und Dokumentdienste.
- Kernbefund: `personens` bildet die natürliche Person und `projekt_has_personens` die projektbezogene Zuordnung bereits getrennt ab. Eine Person kann damit technisch getrennte BOP- und BvB-Reha-Teilnahmen besitzen. Das ist als gemeinsames Fundament geeignet.
- Bestätigte Modellgrenze: `projekt_has_personens` wird zugleich für fachliche Teilnehmer-Teilnahmen und für Mitarbeiter-Zugriffszuweisungen mit mehreren Standorten verwendet. Deshalb wird vorerst weder eine globale Unique-Constraint ergänzt noch die Tabelle umbenannt oder aufgeteilt. Eine solche Änderung benötigt Bestandsanalyse, fachliche Semantik und eine eigene Datenmigration.
- Behobenes Datenrisiko: `UserController` und `PersonalController` löschten bei jeder Profilspeicherung alle Mitarbeiter-Projektzuweisungen und legten sie neu an. Dadurch wechselten auch unveränderte Zuordnungen ihre ID; abhängige Meta-, Zeitraum-, LUV- oder Abschlussdaten konnten verloren gehen beziehungsweise verwaisen.
- Ergebnis: `StaffProjectAssignmentSynchronizer` synchronisiert ausschließlich Mitarbeiter-Zuordnungen differenzbasiert. Unveränderte IDs und abhängige Daten bleiben bestehen; nur echte Ergänzungen oder Entfernungen werden geschrieben. Historische Dubletten werden bewusst nicht automatisch gelöscht.
- Gemeinsamer Kern für BvB Reha: Person, Projekttyp, Projekt, Projektzuordnung, Standort und allgemeiner Dokumentmanager können wiederverwendet werden. Diagnostik, Förderplanung, Ziele, Praktika, Verlauf und Abschlussbericht bleiben neue BvB-Reha-Fachaggregate und werden nicht in BOP-Tabellen ergänzt.
- Verbleibende fachliche Sperren vor BvB-Reha-Produktivdaten: Rollen-/Aktionsmatrix, Statusmodell der Teilnahme, verantwortliche Organisationen, Auditumfang sowie Lösch- und Aufbewahrungsfristen.
- DB: keine Migration. Constraints oder eine spätere Trennung von Mitarbeiterzuweisung und Teilnahme erst nach Bestands-Dry-Run.
- Abnahme Technik: erfüllt – Tests belegen stabil bleibende Zuordnungs-IDs und Metadaten über beide Update-Endpunkte, differenzbasierte Synchronisierung ohne neue Dubletten, Schutz vor Nutzung für Teilnehmer sowie getrennte BOP-/BvB-Reha-Teilnahmen derselben Person. Produktionsbuild erfolgreich; Vollsuite: 97 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.

### Phase 7 – BvB Reha planen und implementieren (Projektkontext umgesetzt am 11.07.2026)

- Ziel: getrenntes Fachmodul auf gemeinsamem Kern.
- Ergebnis 7A/7K: BvB Reha ist kein separater Benutzer-Arbeitsbereich. Matrix bleibt der einzige Arbeitsbereich; der Projektwechsler im Header setzt das aktive Projekt und dessen Typ `bvb_reha`. Die allgemeine Teilnehmer-, Partner- und Gruppennavigation arbeitet in diesem Kontext. Das Modul ist vollständig serverseitig geschützt, global konfigurierbar und standardmäßig deaktiviert.
- Rechte: `bvb_reha.workspace.index` und `bvb_reha.participants.index`; Modulaktivierung und Permission sind getrennte Voraussetzungen. Zusätzlich begrenzt `RoleDataAccessSetting` die sichtbaren Projekte.
- Datentrennung: Die allgemeine Teilnehmerübersicht verwendet ausschließlich das aktive, dem Benutzer zugeordnete Header-Projekt. Fremde Query- oder Legacy-Projektparameter werden abgewiesen; Mitarbeiter und Teilnahmen anderer Projekte erscheinen nicht.
- DB 7A: nur additive Modul-/Permission-Metadaten; keine BvB-Reha-Fachtabelle und keine sensiblen Felder.
- Fachplanung: `docs/bvb-reha-fachmodul-plan.md` dokumentiert Aggregate, Berechtigungszerlegung, Datenschutz-/Auditfreigaben und die kontrollierte Reihenfolge 7B bis 7F.
- Verbleibend: Zuweisung, Aufnahme, Diagnostik, Förderplanung, Ziele, Praktika, Verlauf und Abschlussbericht erst nach fachlicher Status-, Rollen-, Audit- und Retentionfreigabe.
- Kompatibilität: Die früheren `/bvb-reha`-Einstiegsrouten bleiben geschützt erhalten, leiten bei passendem aktivem BvB-Projekt aber nur noch auf die normale Teilnehmerübersicht weiter. Die separaten Vue-Seiten und der zusätzliche Menüpunkt wurden entfernt.
- Risiko: besonders sensible Diagnostik- und Förderdaten; deshalb werden weiterhin keine unbestätigten BvB-Schreibaggregate ergänzt.
- Abnahme Projektkontext: Tests belegen autorisierten Projektwechsel, Modulstatus, Inertia-Typkontext, Teilnehmerisolation, Schutz von Partnern/Gruppen und sichere Legacy-Weiterleitung.
- Abnahme Technik am 11.07.2026: Produktionsbuild erfolgreich mit 1289 Modulen; Vollsuite nach Bindung der Teilnehmeranlage an das aktive Header-Projekt: 113 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.

### Phase 8 – weitere Maßnahmen/Mandantenfähigkeit

- Ziel: neue Projekttypen ohne Kopieren des BOP-Moduls; Mandanten nur bei realem Bedarf.
- Ergebnis 8A: Projekttyp und Modulstatus sind nun auch bei der Projektanlage serverseitig gekoppelt. Typen deaktivierter oder inaktiver Fachmodule werden nicht angeboten und können weder neu vergeben noch als Wechselziel gespeichert werden. Projekttypen ohne Fachmodul bleiben nutzbar.
- Bestandsschutz 8A: Eine bereits vorhandene Typzuordnung bleibt bei unveränderter Projektbearbeitung erhalten, auch wenn das Modul inzwischen deaktiviert wurde. Es erfolgt keine automatische Umklassifizierung und keine Datenlöschung; im Bearbeitungsdialog wird die Altzuordnung als deaktiviert gekennzeichnet.
- Technische Grenze 8A: `ProjectTypeAssignmentService` ist die zentrale Zuweisbarkeitsregel. Ein inaktiver Modulkatalogeintrag bleibt selbst bei einer alten aktivierten Standortzuweisung unverfügbar.
- Verbleibende Aufgaben: erst nach einer realen zweiten Maßnahme deren eigenes Fachmodul als Architekturprobe ergänzen; danach Tenant-Entität und globale Scopes nur bei bestätigtem Bedarf entwerfen.
- DB: Tenant-FKs nur mit Migrations- und Isolationstest.
- Risiko: vorzeitige Multi-Tenancy erhöht Komplexität und Sicherheitsrisiko.
- Abnahme 8A: erfüllt – automatisierte Tests für deaktivierte/inaktive Module, ausgeblendete Auswahl, serverseitige Ablehnung, modulunabhängige Typen und Erhalt bestehender Zuordnungen. Keine Migration; Produktionsbuild erfolgreich; Vollsuite: 109 Tests bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.
- Spätere Gesamtabnahme: nachweisbare Datenisolation und modulare Aktivierung pro Standort/Mandant.

## Offene fachliche Entscheidungen vor Implementierung

1. Welche bestehenden Projekte sind sicher BOP, und gibt es bereits andere Projekttypen?
2. Sind die BOP-Legacy-Exporte außerhalb der Auth-Gruppe absichtlich öffentlich?
3. Ist `partners` das verbindliche Organisationsmodell für Schulen, Betriebe, Jobcenter und Agentur für Arbeit?
4. Muss echte Mandantenfähigkeit kurzfristig umgesetzt werden oder genügt Standortfähigkeit?
5. Welche Lösch- und Aufbewahrungsfristen gelten für Teilnehmer-, Sozial-, Signatur-, Export- und BvB-Reha-Daten?
6. Soll ein deaktiviertes Modul 403 (sichtbare Existenz) oder 404 (verbergen) liefern?
7. Welche Module sind Systemmodule und dürfen nie deaktiviert werden (mindestens Auth/Rollen/Modulverwaltung)?

## Lösch- und Aufbewahrungsmatrix

Technischer Ist-Stand nach Phase-1-Prüfung; fachliche Fristen sind noch durch Datenschutz/Fachverantwortliche festzulegen.

| Datenart | Aktuelles Löschverhalten | Risiko | Empfehlung vor weiterer Implementierung |
|---|---|---|---|
| Person/Teilnehmer | `Personen::deleting` löscht polymorphe Adressen und Kontakte unmittelbar; weitere Beziehungen folgen uneinheitlichen FKs | sehr hoch: Stammdaten und Nachweise können unvollständig werden | Löschung bis zur Fachfreigabe als kritisch behandeln; Sperren/Anonymisieren und gesetzliche Fristen definieren |
| Projektteilnahme | eigener Datensatz in `projekt_has_personens`; abhängige Meta-, LUV- und Abschlussdaten teils direkt gekoppelt | hoch: Teilnahmehistorie kann von Person oder Projekt getrennt werden | Aufbewahrung an Teilnahme statt nur Person binden; Löschsimulation mit Bestandsdaten |
| BOP-Potenzialanalyse | mehrere `cascadeOnDelete` von Projekt, Gruppe, Person, Übung/Kriterium | hoch: Löschen eines Stammdatensatzes entfernt Bewertungen/Berichte | vor Produktivlöschung Restrict/Archivstrategie fachlich entscheiden; keine Cascade nachträglich ändern ohne Datenmigration |
| BIBB-/PA-Drafts | `expires_at`, Purge-Commands und verschlüsselte Signaturen vorhanden | mittel | Scheduler und Löschprotokoll testen; verbindliche Retentiondauer dokumentieren |
| Dokumentvorlagen | kein fachlicher Delete-Endpunkt; ersetzte verwaltete Uploaddatei wird gelöscht | niedrig/mittel | Versionierung/Archivierung statt Löschen prüfen; Pfad bleibt auf `storage/` begrenzt |
| Generierte Exporte/Tempdateien | mehrere Controller schreiben nach `storage/app/tmp`, `storage/app/temp`, `storage/exports` und teils modulspezifische Ordner | hoch: personenbezogene Artefakte können liegen bleiben | zentralen, zeitgesteuerten Cleanup mit Audit und je Exportklasse definieren |
| App-Dateimanager | Eigentümer kann Baum rekursiv hart löschen; physische Datei und Shares werden entfernt | mittel | Papierkorb/Soft Delete nur bei bestätigtem Bedarf; Freigabeempfänger dürfen nicht löschen |
| Räume/IT/Lager/Dienstwagen | neue FKs nutzen überwiegend `nullOnDelete` für Personen/User, Aggregate teils Cascade auf Detailhistorie | mittel/hoch | Historien vor Stammdatenlöschung schützen; je Modul gesetzliche/betriebliche Frist festlegen |
| Audit/Activities/Verläufe | uneinheitlich pro Modul, keine zentrale unveränderliche Retention | hoch | Auditkatalog, Zugriffsschutz, Integrität und Löschfrist vor BvB Reha festlegen |

Phase-1-Dokumentzugriff (10.07.2026): Alle benannten Download-/Exportrouten sind per Regressionstest an Authentifizierung und Permission-Middleware gebunden. Der Dokumentmanager akzeptiert für Downloads ausschließlich existierende Dateien innerhalb `storage/`; das eigene Recht `dokumente.download` ist die maßgebliche Controllerprüfung.

## Unverändert lassen

- bestehende Tabellen, Daten, URLs und Benutzerabläufe, solange kein konkretes Problem belegt ist;
- Trennung Person/Projektteilnahme;
- eigenständige Tabellen für Räume, IT, Lager und Dienstwagen;
- Spatie Permission als Rechtebasis;
- bestehende Transaktionsgrenzen und lokale Services;
- vorhandene BOP-Funktionen und Exporte bis zu abgesicherten, einzeln freigegebenen Änderungen.
