# Matrix – Abschlussaudit zum Masterauftrag

Stand: 10.07.2026. Dieser Audit bewertet den ursprünglichen Masterauftrag gegen den aktuellen Arbeitsbaum, das verbundene Schema, die Routentabelle, die Migrationen, den Produktionsbuild und die vollständige Testsuite. Er erklärt fachlich offene Entscheidungen nicht zu technischen Erfolgen.

## 1. Geforderte Analyseartefakte

| Anforderung | Status | Maßgeblicher Nachweis |
|---|---|---|
| A. Zusammenfassung | erfüllt | `docs/matrix-architekturanalyse.md`, Abschnitt A |
| B. Backend, Frontend, Datenbank, Rechte und Kopplungen | erfüllt | Architekturbericht, Abschnitt B; aktualisiert auf 376 Routen, 66 Controller und acht Services |
| C. Funktionsübersicht und Bewertung | erfüllt | Architekturbericht, Abschnitt C mit Zustand, Bereich, Abhängigkeiten, Empfehlung und Risiko/Priorität |
| D. Modulübersicht | erfüllt | Architekturbericht, Abschnitt D; Ist-Zustand nach Phasen 2–8A aktualisiert |
| E. BOP, gemeinsamer Kern und BvB Reha | erfüllt | Architekturbericht, Abschnitt E; ergänzend `docs/bvb-reha-fachmodul-plan.md` |
| F. Räume, IT, Lager und Dienstwagen | erfüllt | Architekturbericht, Abschnitt F |
| G. Datenbankanalyse | erfüllt | Architekturbericht, Abschnitt G; vollständiges Einzelinventar in `docs/matrix-tabelleninventar.md` |
| H. Betroffene Dateien je Phase | erfüllt | Architekturbericht, Abschnitt H |
| I. Testplan | erfüllt | Architekturbericht, Abschnitt I sowie ausführbare Unit-/Featuretests |
| J. Schrittweiser Umsetzungsplan | erfüllt | Architekturbericht, Abschnitt J mit Ziel, DB, Risiko und Abnahme je Phase |

## 2. Acht Abschlussdeliverables

| Nr. | Deliverable | Status | Evidenz |
|---:|---|---|---|
| 1 | Übersicht aller vorhandenen Funktionen | erfüllt | Funktionsübersicht und Verwaltungsanalyse |
| 2 | Übersicht aller Module | erfüllt | aktualisierte Modultabelle einschließlich noch nicht modularisierter Kandidaten |
| 3 | Tabellen, Models, Controller, Routen und Vue-Seiten zuordnen | erfüllt | Einzelinventar, Bestandsabschnitte und phasenbezogene Dateimatrix |
| 4 | Kopplungen analysieren | erfüllt | Abhängigkeitsbild sowie BOP-/Core-/Ressourcenanalyse |
| 5 | Verbessern versus unverändert lassen bewerten | erfüllt | Bewertungstabelle und Abschnitt „Unverändert lassen“ |
| 6 | Plan für aktivierbare Module | erfüllt und Kern umgesetzt | Modulmodell, Resolver, Middleware, Adminseite, Navigation und Tests |
| 7 | Plan für BOP, BvB Reha und weitere Projekte | erfüllt und technisch vorbereitet | Projekttypkatalog, BOP-Grenze, lesende BvB-Probe und Ausbauplan |
| 8 | risikoarmer Umsetzungsplan | erfüllt; Phasen 0–8A abgenommen | Abschnitt J und Test-/Buildnachweise |

## 3. Zentrale Invarianten

| Invariante | Ergebnis | Nachweis |
|---|---|---|
| Keine vorhandene Funktion oder Tabelle entfernen | erfüllt | ausschließlich additive Migrationen; Schema-Inventar vollständig |
| Deaktivierung löscht keine Daten | erfüllt | Raum-, BOP-, IT-, Lager-, Dienstwagen- und BvB-Datenerhaltstests |
| Modulstatus nicht nur im Frontend | erfüllt | `EnsureModuleEnabled`, Routengates und Boundary-Tests |
| Modulstatus und Permission getrennt | erfüllt | Resolver/Middleware plus Spatie-Routenrechte; Tests für aktiv aber nicht berechtigt |
| Rollen steuern Sichtbarkeit sowie Lesen/Bearbeiten | erfüllt über vorhandenes Rechtesystem | Permission-Katalog, Rollenverwaltung, Navigation und `PermissionCatalogTest` |
| Verwaltungsbereiche bleiben unabhängig von Bildungsprojekten | erfüllt | getrennte Tabellen/Controller; eigene globale Modulgrenzen, Raum zusätzlich standortfähig |
| Person ist nicht Teilnahme | erfüllt | `personens` versus `projekt_has_personens`; getrennte BOP-/BvB-Teilnahmen getestet |
| BvB Reha nicht in BOP-Tabellen einbauen | erfüllt | eigene Modul-/Routengrenze; lesende Probe gibt keine BOP-Meta-/PA-/LUV-Daten aus |
| Projekttyp eines deaktivierten Fachmoduls nicht neu vergeben | erfüllt | `ProjectTypeAssignmentService` und Workflowtests |
| Bestehende Typ-/Teilnahmezuordnungen nicht unbemerkt zerstören | erfüllt | Bestandsschutz und differenzbasierter `StaffProjectAssignmentSynchronizer` |
| Dokumentdownloads und öffentliche BOP-Endpunkte härten | erfüllt für identifizierte Risiken | Pfad-/Permissiontests, Tokenservice und Rate Limits |

## 4. Modulstatus im Abschlusszustand

| Modul | Scope | Default | Backend | Daten bei Aus |
|---|---|---:|---|---|
| Raumverwaltung | global und Standort | an | Modul + Permission | bleiben erhalten |
| IT-Verwaltung | global | an | Modul + Permission | bleiben erhalten |
| Lagerverwaltung | global | an | Modul + Permission | bleiben erhalten |
| Dienstwagen | global | an | Modul + Permission | bleiben erhalten |
| BOP | global | an | Modul + Permission; Public Token/Throttle | bleiben erhalten |
| BvB Reha | global | aus | Modul + eigene Permissions + Datenscope | bleiben erhalten |

Dokumente, Apps, Klassenbuch und Reporting sind im Inventar als mögliche spätere Modulgrenzen bewertet. Sie wurden nicht allein zur formalen Vollständigkeit hinter neue Gates gesetzt: Ihre Routen und Aggregate überlappen mehrere Workflows, und der Masterauftrag verbietet Änderungen ohne konkreten Nutzen und abgesicherte Grenze.

## 5. Bewusst nicht implementiert

Die folgenden Punkte sind keine übersehenen technischen Aufgaben, sondern benötigen neue fachliche Autorität oder bestätigten Bedarf:

1. **Schreibendes BvB-Reha-Fachmodell:** Statusmodell, verantwortliche Organisation, Pflichtfelder, Rollen/Aktionen, Auditereignisse und Aufbewahrung sind nicht bestätigt. Deshalb bleiben Diagnostik, Förderplanung, Ziele, Praktika, Verlauf und Abschlussbericht unimplementiert.
2. **Echte Mandantenfähigkeit:** Ein Mandantenobjekt, Tenant-Zuordnung und Isolationserwartung sind nicht bestätigt. Standortfähigkeit wird unterstützt; `tenant_id` und globale Tenant-Scopes wurden bewusst nicht spekulativ ergänzt.
3. **Standortaktivierung für IT, Lager und Dienstwagen:** Aktuelle Aggregate/Queries besitzen keine durchgängig belastbare Standortgrenze. Eine scheinbare Teilisolation wäre sicherheitskritischer als der dokumentierte globale Scope.
4. **Zentrale Audit-/Retentionplattform:** Vor einer Umsetzung müssen Eventkatalog, Unveränderlichkeit, Einsichtsrechte und Löschfristen feststehen. Die identifizierten Datenklassen stehen in der Aufbewahrungsmatrix.
5. **Automatische Klassifikation historischer Projekte:** Projektnamen sind kein sicherer Beleg für einen Projekttyp. Bestehende Projekte bleiben bis zur fachlichen Zuordnung unverändert.

Diese Grenzen folgen unmittelbar den Masterregeln „keine Annahmen als Tatsachen“, „keine unnötigen Änderungen“ und „keine Datenverluste“.

## 6. Technische Abnahme

- Migrationen `2026_07_10_090000` bis `2026_07_10_094000` sind angewendet.
- Produktionsbuild nach Projektkontext-Integration: erfolgreich, 1289 Module transformiert; die zwei separaten BvB-Seiten sind nicht mehr Bestandteil des Builds.
- Vollsuite nach vollständiger Projektkontext-Integration einschließlich Teilnehmeranlage am 11.07.2026: 113 bestanden, 4 erwartungsgemäß übersprungen, 0 fehlgeschlagen.
- Live-Schemaabgleich: 137 Tabellen, 137 eindeutige Inventareinträge, 0 fehlend, 0 zusätzlich, 0 doppelt.
- BvB-Routenaudit: zwei Controller-Routen, beide modulgeschützt, keine ungeschützte Route.
- `git diff --check`: keine Whitespacefehler; ausschließlich nicht-blockierende Windows-Zeilenendenhinweise.

## 7. Abschlussbewertung

Der Analyse-, Architektur- und Planungsauftrag ist vollständig nachgewiesen. Die freigegebene technische Vorbereitung wurde additiv und regressionsgeprüft bis Phase 8A umgesetzt. Darüber hinausgehende Fachfunktionen sind ohne die in Abschnitt 5 genannten Entscheidungen nicht sicher spezifizierbar und werden deshalb nicht erfunden.
