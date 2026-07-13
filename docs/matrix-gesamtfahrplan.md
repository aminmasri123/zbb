# Matrix-Gesamtfahrplan

Stand: 11.07.2026

## Zweck und Produktbild

Dieses Dokument ist die verbindliche Produkt-Roadmap für Matrix. Es ergänzt die technische Architekturanalyse und verhindert, dass geplante Funktionen nur in einzelnen Gesprächen festgehalten werden.

Matrix soll eine modulare Plattform für Bildungsträger mit getrennten Zugängen werden:

```text
Matrix
├── Mitarbeiter- und Verwaltungsportal
├── Teilnehmerportal
├── Bildungsprojekte und Projektregeln
├── Ressourcen- und Organisationsverwaltung
└── Plattformdienste und Administration
```

Das aktive Projekt im Header bestimmt für Mitarbeitende den fachlichen Datenkontext. Eine Person kann an mehreren Projekten teilnehmen; fachliche Daten werden der jeweiligen Projektteilnahme zugeordnet. Rollen und Berechtigungen bestimmen die erlaubten Aktionen. Globale Systemmodule und direkt am Projekt aktivierte Funktionen bleiben voneinander getrennt.

## Statusdefinitionen

| Status | Bedeutung |
|---|---|
| Vorhanden | Nutzbarer Ablauf ist implementiert und abgesichert |
| Teilweise | Wesentliche Grundlage ist vorhanden, der vollständige Ablauf fehlt |
| Geplant | Produktziel ist festgehalten, Implementierung fehlt |
| Fachlich zu klären | Umsetzung erst nach Entscheidungen zu Ablauf, Daten, Rechten oder Datenschutz |

## Reifegrad

| Bereich | Einschätzung | Begründung |
|---|---:|---|
| Plattform- und Verwaltungskern | 65–70 % | Viele produktive Bereiche, Modulschutz, Rollen und Tests vorhanden; Audit und Vereinheitlichung bleiben offen |
| Projekt- und Teilnehmerverwaltung | 70–75 % | Projektkontext, Teilnahmen, Gruppen, Regeln, Anwesenheit und Lebenszyklus vorhanden |
| BvB-Reha-Fachablauf | 40–50 % | Gemeinsamer Kern vorhanden; Aufnahme, Förderplanung, Ziele und Berichte fehlen |
| Teilnehmerportal | 85–90 % | Untermodul von Teilnehmer; Profil, Aufgaben, Anwesenheit, Jobs, Kurse, Dokumente, Nachrichten, Hinweise sowie versionierte Einwilligungen/Widerrufe vorhanden |
| Jobsuche und Bewerbungen | 90–95 % | BA-Adapter, Suche, Merkliste, Mitarbeiterempfehlungen, Bewerbungscockpit, Statushistorie und beidseitig freigegebene Dokumentpakete vorhanden |
| Kurse und Lernen | 90–95 % | Kurse, Lektionen, geschützte Materialien, Aufgaben, Abgaben, Bewertung sowie serverseitig ausgewertete Quiz mit Versuchslimit und Bestehensgrenze vorhanden; formale Nachweise folgen |
| Gesamtvision Matrix | 35–45 % | Interner Kern ist fortgeschritten; Teilnehmer-Self-Service und mehrere End-to-End-Fachabläufe fehlen |

Die Prozentwerte sind Orientierung und keine Abnahme. Produktionsreife wird pro vollständigem Arbeitsablauf bewertet.

## Funktionslandkarte

### Plattformkern und Administration

| Funktion | Status | Nächster Schritt |
|---|---|---|
| Anmeldung für Mitarbeitende | Vorhanden | Sicherheits- und Wiederherstellungstests fortführen |
| Benutzer, Rollen und Berechtigungen | Vorhanden | Datensatz- und Projektrechte vollständig auditieren |
| Projektzuweisung von Mitarbeitenden | Vorhanden | Berechtigungstests je Aktion ausbauen |
| Projektwechsel im Header | Vorhanden | Alle verbleibenden Fachseiten auf Projekttrennung prüfen |
| Globale Systemmodule | Vorhanden | Weitere Module nur bei konkretem Nutzen aufnehmen |
| Teilnehmerportal (`participant_portal`) | Vorhanden | Untermodul von Teilnehmer mit Admin-Schalter, Backend-Middleware, Konten, Login, Dashboard und projektbezogenen Portal-Funktionen |
| Direkte Projektfunktionen und Regeln | Vorhanden | Konfigurationskatalog kontrolliert erweitern |
| Benachrichtigungen | Teilweise | Teilnehmerkanäle und fachliche Ereignisse ergänzen |
| Kalender, Kontakte, Aufgaben, Dateien | Vorhanden/teilweise | Schrittweise mit Projektteilnahmen und Teilnehmerportal verbinden |
| Einheitliches Audit-Log | Teilweise | Änderungswerte, sensible Zugriffe und Exporte vereinheitlichen |
| Aufbewahrung und Löschfristen | Fachlich zu klären | Datenklassen und Fristen verbindlich festlegen |
| Mandantenfähigkeit | Geplant | Erst bei bestätigtem Bedarf einführen |

### Interne Teilnehmer- und Projektverwaltung

| Funktion | Status | Nächster Schritt |
|---|---|---|
| Einmalige Person, mehrere Projektteilnahmen | Vorhanden | Alte personengebundene Fachdaten kontrolliert zuordnen |
| Teilnehmeranlage und Excel-Import | Vorhanden | Dublettenstrategie und Importprotokoll verbessern |
| Teilnahme-Lebenszyklus | Vorhanden | Statusübergänge und Abschlussbedingungen konfigurierbar machen |
| Projektbezogene Gruppen und Bereiche | Vorhanden | Übersichten und Kapazitätswarnungen ausbauen |
| Anwesenheit und Statusführung | Vorhanden | Monats-/Gesamtsaldo und Projektübersicht weiter ausbauen |
| Praktika/Bildungsmaßnahmen | Vorhanden | strikt projektteilnahmebezogene Anlage und Bearbeitung, Träger/Ansprechpartner, Zeitraum, Wochenstunden, Ziel, Ergebnis, Nachverfolgungsfrist, kontrollierte Statusübergänge, Statushistorie und historienerhaltende Archivierung; Risiken in der Gesamtübersicht |
| Dokumente | Teilweise | Datensatz-Policies, Freigaben und Teilnehmerzugriff ergänzen |
| Teilnehmer-Gesamtübersicht | Vorhanden | Status, Standort, Gruppe, Betreuung, Monats-/Gesamtsaldo, Anwesenheitsquote, Fehlzeiten, offene und überfällige Aufgaben sowie nächste Frist; projektweite Kennzahlen und Aufmerksamkeitsfilter für die schnelle Steuerung |
| Aufnahmecheckliste | Vorhanden | projektbezogene Pflicht-/optionale Punkte, historienerhaltende Deaktivierung und Bearbeitungsstand je Teilnahme |
| Aufgaben und Wiedervorlagen | Vorhanden | Teilnahmebezug, Projektmitarbeiter als Verantwortliche, Priorität, Frist, Überfällig-Warnung und Taskmanager-Integration |
| Abschluss und Berichte | Vorhanden | projektbezogene Abschlusscheckliste, Pflichtpunktprüfung, versionierter Bericht mit SHA-256-Snapshot, Vier-Augen-Freigabe, Statuswechsel und geschützter JSON-Nachweis |

### Bildungsprojekte

| Funktion | Status | Nächster Schritt |
|---|---|---|
| BOP-Bestandsfunktionen | Vorhanden | schützen, testen und nur lokal verbessern |
| Potenzialanalyse | Vorhanden/teilweise | Projektgrenzen und vollständigen Fachablauf testen |
| Klassenbuch | Vorhanden | als direkte Projektfunktion weiterführen |
| BvB Reha: Zuweisung und Teilnahme | Teilweise | Aufnahmeablauf vervollständigen |
| BvB Reha: Eingangsdiagnostik | Fachlich zu klären | Datenklassen, Sichtrechte und Audit bestimmen |
| BvB Reha: Förderplanung und Ziele | Fachlich zu klären | Versionierung, Freigabe und Verantwortlichkeiten bestimmen |
| BvB Reha: Verlaufsdokumentation | Fachlich zu klären | strukturierte Einträge statt unkontrollierter Freitexte planen |
| BvB Reha: Abschlussbericht | Fachlich zu klären | Vorlage, Freigabe, Export und Aufbewahrung definieren |
| BvB, BaE, AsA flex, Coaching | Geplant | über gemeinsame Funktionen und direkte Projektregeln abbilden |

### Allgemeine Verwaltungs- und Ressourcenbereiche

| Funktion | Status | Nächster Schritt |
|---|---|---|
| Partner und Schulen | Vorhanden | Organisationsrollen und projektbezogene Sicht verbessern |
| Raumverwaltung | Vorhanden | Konflikt-, Standort- und Berechtigungstests ausbauen |
| IT-Geräte und Tickets | Vorhanden | Historie, Wartung und Lizenzen nur nach Bedarf ergänzen |
| Lager | Vorhanden | Projekt/Kostenstelle optional halten; Inventur verbessern |
| Dienstwagen | Vorhanden | Buchungskonflikte, Fahrer und Historie weiter absichern |
| Materialanforderungen | Vorhanden | beibehalten und mit Aufgaben/Benachrichtigungen verbinden |
| Fahrtkosten | Vorhanden | projektbezogene Auswertung prüfen |
| Berichte und Exporte | Vorhanden/teilweise | Rechte, Audit, Vorlagen und Aufbewahrung vereinheitlichen |

## Teilnehmerportal

Das Teilnehmerportal ist ein eigener Zugang und keine freigeschaltete Mitarbeiteroberfläche. Ein Teilnehmerkonto muss eindeutig und widerrufbar mit einer Person verknüpft sein. Es darf ausschließlich freigegebene Daten der eigenen Projektteilnahmen sehen.

### Verbindliche Modulgrenze

Das Teilnehmerportal wird als eigenes globales Systemmodul `participant_portal` umgesetzt. Es ist unabhängig vom internen Modul `participant_management`:

```text
Teilnehmerverwaltung aktiv?
└── steuert die interne Verwaltung durch Mitarbeitende

Teilnehmer aktiv?
└── Nein: Teilnehmerverwaltung und Teilnehmerportal vollständig gesperrt
└── Ja: Teilnehmerportal kann zusätzlich aktiviert werden
    └── steuert Self-Service-Zugang, Portal-Routen und Teilnehmer-APIs
```

Das Teilnehmerportal ist technisch ein Untermodul des Moduls `participant_management`. Es kann niemals wirksam aktiviert werden, wenn das Hauptmodul Teilnehmer deaktiviert ist. Eine Deaktivierung des Teilnehmerportals bewirkt:

- Portal-Navigation und Teilnehmer-Login werden nicht angeboten;
- Portal-Routen und APIs werden serverseitig gesperrt;
- bestehende Teilnehmerkonten, Profile, Bewerbungen, Kursteilnahmen und Dokumente bleiben erhalten;
- interne Mitarbeiterfunktionen bleiben unverändert verfügbar.

Innerhalb des aktiven Portalmoduls sind mindestens folgende Funktionen einzeln konfigurierbar:

| Portal-Funktion | Vorgesehener Schlüssel | Steuerung |
|---|---|---|
| Profil und Dokumente | `profile` | Portal-Basisfunktion |
| Eigene Anwesenheit | `attendance_self_service` | je Projekt aktivierbar |
| Aufgaben und Termine | `tasks_and_appointments` | je Projekt aktivierbar |
| Jobsuche | `job_search` | global technisch und je Projekt fachlich aktivierbar |
| Bewerbungsmanagement | `application_management` | je Projekt aktivierbar; benötigt Jobsuche nicht zwingend |
| Kurse und Lernen | `learning` | global technisch und je Projekt fachlich aktivierbar |
| Nachrichten | `messaging` | global technisch und je Projekt fachlich aktivierbar |

Die erste Umsetzung verwendet das vorhandene Modulregister und die bestehende Backend-Middleware. Rollen beziehungsweise Portalberechtigungen regeln weiterhin die erlaubten Aktionen; Modulstatus und Berechtigung werden nicht vermischt.

### Geplanter Umfang

| Funktion | Status | Abhängigkeiten/Schutz |
|---|---|---|
| Einladung und Aktivierung eines Teilnehmerkontos | Vorhanden | einmaliger Hash-Token, 7 Tage Ablauf, Projektteilnahmebindung und Aktivierungsnachweis |
| Eigenes Dashboard | Vorhanden | aktive Teilnahmen, Aufgaben sowie Zugänge zu Anwesenheit, Bewerbungen, Kursen, Dokumenten und Nachrichten; zentrale Erinnerungsübersicht |
| Profil vervollständigen | Vorhanden | berufliches Ziel, Kenntnisse, Interessen, Verfügbarkeit, Suchradius sowie Zugang zu verifizierten Kontaktdaten |
| Kontaktdaten selbst ändern | Teilweise | E-Mail-Änderung mit Versand an neue Adresse, Einmal-Token, Ablauf, Fremdzugriffsschutz und Änderungsverlauf vorhanden; Mobilfunk-Verifikation benötigt einen SMS-Anbieter |
| Lebenslauf, Kenntnisse und Interessen | Vorhanden | strukturierte Erfahrung, Bildung, Qualifikation, Sprache und Kenntnisse; unveränderliche Snapshots mit SHA-256, JSON-Export, Druck/PDF-Ansicht und freigabegesteuerte Mitarbeitersicht |
| Dokumente hoch-/herunterladen | Vorhanden | Teilnahmebindung, Dateityp- und Größenprüfung, geschützter Speicher, Mitarbeiterfreigabe und Fremdzugriffsschutz; Virenscanner später ergänzen |
| Eigene Anwesenheit einsehen | Vorhanden | read-only, projektbezogen, Saldo und nachvollziehbare Korrekturanfrage statt Direktänderung |
| Termine und Aufgaben | Teilweise | freigegebene Aufgaben und projektöffentliche Kalendertermine vorhanden; Dashboard-Erinnerungen für fällige Aufgaben vorhanden |
| Nachrichten mit zuständigen Mitarbeitenden | Vorhanden | Projektteilnahmebezug, Projektfreigabe, Rollen-/Sichtbarkeitsschutz, getrennte Lesestatus und Fremdzugriffsschutz |
| Hinweise und Erinnerungen | Teilweise | ungelesene Nachrichten, fällige Aufgaben, Bewerbungsschritte und Kursstarts im Dashboard; externe Zustellung und Präferenzen folgen |
| Einwilligungen und Freigaben | Vorhanden | projektbezogene Definitionen, unveränderliche Versionen, Zweck/Volltext, bewusste Zustimmung, optionaler Widerruf, Zeit-/Akteur-/IP-Nachweis und Inhalts-Hash |
| Datenexport und Auskunft | Vorhanden | Antrag statt Direktfreigabe, dokumentierte Identitätsprüfung und Entscheidung, projektübergreifender JSON-Export ausschließlich eigener Daten, Downloadnachweis; Berichtigung/Löschung niemals automatisch |

### Jobsuche und Bewerbungsmanagement

Die Stellensuche soll über `https://jobsuche.api.bund.dev/` beziehungsweise den zum Implementierungszeitpunkt offiziell verfügbaren und zulässigen Dienst angebunden werden. Vor der Entwicklung werden Nutzungsbedingungen, Authentifizierung, Rate Limits, Datenfelder, Verfügbarkeit und zulässige Speicherung erneut geprüft.

| Funktion | Status | Fachliche Regel |
|---|---|---|
| Stellensuche und Filter | Vorhanden | externe Treffer werden live über einen austauschbaren Adapter abgerufen und gekennzeichnet |
| Merkliste | Vorhanden | teilnehmerbezogen; nur minimale Stellen-Metadaten gespeichert |
| Stellenempfehlung durch Mitarbeitende | Vorhanden | projektteilnahmebezogene Empfehlung mit Verantwortlichem, Datum, Gesehen-/Verworfenstatus und kontrollierter Übernahme als Bewerbung |
| Bewerbungscockpit | Vorhanden | eigene projektteilnahmebezogene Bewerbung statt externem Treffer als Datensatz |
| Bewerbungsstatus | Vorhanden | Entwurf, Vorbereitung, versendet, Rückmeldung, Gespräch, Zu-/Absage und Rückzug mit Historie |
| Unterlagen zusammenstellen | Vorhanden | ausschließlich geprüfte Dokumente derselben Projektteilnahme; Teilnehmer sieht nur für ihn freigegebene Unterlagen |
| Bewerbung prüfen/freigeben lassen | Vorhanden | getrennte Paketfreigabe durch Teilnehmer und Projektteam; jede Dokumentänderung setzt beide Freigaben zurück |
| Fristen und Vorstellungsgespräche | Teilweise | nächster Schritt und Datum vorhanden; Kalender und Benachrichtigungen folgen |
| Statistik und Verlauf | Fachlich zu klären | Zweckbindung, Sichtrechte und Aufbewahrung festlegen |
| Automatisches Versenden | Nicht vorgesehen ohne Freigabe | keine Bewerbung ohne bewusste Teilnehmeraktion versenden |

### Kurse und Lernen

| Funktion | Status | Nächster Schritt |
|---|---|---|
| Kurskatalog | Vorhanden | ausschließlich veröffentlichte Kurse freigeschalteter eigener Projekte |
| Kurszuweisung und Selbstanmeldung | Vorhanden | Mitarbeiterzuweisung, optionale Selbstanmeldung, Kapazität und Zeitraum |
| Module, Lektionen und Materialien | Vorhanden | geordnete Textlektionen und geschützte veröffentlichbare Dateimaterialien innerhalb der Einschreibung |
| Aufgaben und Abgaben | Vorhanden | Fristen, Text-/Dateiabgabe, geschützter Download, Punktgrenze, Feedback, Bewertung und Überarbeitungsanforderung |
| Teilnahme und Lernfortschritt | Vorhanden | Fortschritt je Einschreibung/Lektion und automatischer Kursabschluss |
| Tests/Quiz | Vorhanden | Einzel-/Mehrfachauswahl, Punktgewichtung, sichere serverseitige Lösung, Versuchslimit, Ergebnisverlauf und Bestehensgrenze |
| Zertifikate/Nachweise | Fachlich zu klären | Voraussetzungen, Signatur und Vorlagen bestimmen |
| Präsenz-, Online- und Hybridtermine | Vorhanden | projektbezogene Terminverwaltung, Pflichtangaben je Modus, Teilnehmeransicht, Erinnerungen und Teilnahmeerfassung je Einschreibung; Kalender-Synchronisation bleibt eine optionale Erweiterung |

## Verbindliche Umsetzungsreihenfolge

### Phase 1 – Interne End-to-End-Basis fertigstellen

1. Teilnehmer-Gesamtübersicht des aktiven Projekts.
2. Aufnahmecheckliste und vollständiger Aufnahmeablauf.
3. Projektbezogene Aufgaben, Fristen und Wiedervorlagen.
4. Änderungs- und Zugriffsaudit für Teilnehmerdaten.
5. Abschlussbedingungen und kontrollierter Bericht/Export.

Abnahme: Ein Mitarbeiter kann eine Teilnahme vom Eingang bis zum Abschluss ohne unverbundene Nebenlisten führen.

### Phase 2 – Teilnehmerkonto und Self-Service-Grundlage

1. Systemmodul `participant_portal` mit Navigation, Middleware und Tests ergänzen.
2. Eigenes Teilnehmer-Guard-/Zugriffskonzept festlegen.
3. Sichere Einladung, Aktivierung, Sperrung und Passwortwiederherstellung.
4. Teilnehmer-Dashboard.
5. Freigegebene Profilfelder und Dokumente.
6. Eigene Termine, Aufgaben, Anwesenheit und Benachrichtigungen.

Abnahme: Ein Teilnehmer sieht ausschließlich seine freigegebenen Daten und kann sein Profil kontrolliert ergänzen.

### Phase 3 – Jobsuche und Bewerbungen

1. API-Vertrag und technische Machbarkeit verifizieren.
2. Read-only-Stellensuche und Merkliste.
3. Bewerbungsdatensatz, Statusverlauf und Fristen.
4. Dokumentauswahl und unterstützte Freigabe.
5. Dashboard für Teilnehmer und zuständige Mitarbeitende.

Abnahme: Eine Stelle kann gefunden, gespeichert und als vollständige Bewerbung mit nachvollziehbarem Verlauf geführt werden.

### Phase 4 – Kurse und Lernbereich

1. Kurs, Durchführung, Lektion und Einschreibung modellieren.
2. Projekt-/Zielgruppenfreigaben und Kapazitäten.
3. Materialien, Aufgaben und Abgaben.
4. Fortschritt, Erinnerungen und Nachweise.

Abnahme: Ein Teilnehmer kann einem Kurs beitreten, Inhalte bearbeiten und seinen Fortschritt sehen; Mitarbeitende können Teilnahme und Fortschritt auswerten.

### Phase 5 – Vertiefte Fachprozesse

1. BvB-Reha-Förderplanung und Entwicklungsziele.
2. strukturierte Verlaufsdokumentation.
3. Abschlussberichte und kontrollierte Freigaben.
4. weitere Projekte über gemeinsame Funktionen und direkte Projektregeln.

## Übergreifende Qualitätsregeln

- Keine Personendaten zwischen Projekten vermischen.
- Teilnehmer sehen niemals interne Notizen, Diagnosen oder Verwaltungsfelder ohne ausdrückliche fachliche Freigabe.
- Navigation ist kein Sicherheitsschutz; jede Route und jeder Datensatz wird im Backend geprüft.
- Externe API-Daten werden nur im notwendigen Umfang gespeichert.
- Keine automatische Bewerbung oder Datenweitergabe ohne bewusste Aktion und nachvollziehbare Einwilligung.
- Neue sensible Funktionen erhalten eigene Berechtigungen, Audit, Aufbewahrung und Tests.
- Bestehende BOP-, Raum-, IT-, Lager- und Dienstwagenfunktionen bleiben erhalten.
- Jede Phase erhält Featuretests, Berechtigungstests, Projekttrennungstests und einen Produktions-Build.

## Nächste konkrete Umsetzung

Teilnehmer-Gesamtübersicht, Aufnahmecheckliste und projektbezogene Aufgaben/Wiedervorlagen aus Phase 1 sind vorhanden. Als nächster fachlich klarer End-to-End-Block folgt der kontrollierte Teilnahmeabschluss mit projektbezogenen Abschlussbedingungen, Freigabe, versioniertem Bericht und Export. Datenschutz-, Aufbewahrungs- und BvB-Reha-spezifische Inhalte werden dabei nicht ohne bestätigte Fachregeln erfunden.
