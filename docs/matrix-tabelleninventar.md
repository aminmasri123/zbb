# Matrix – vollständiges Tabelleninventar

Stand: 10.07.2026. Quelle ist das tatsächlich verbundene Schema nach Migration `2026_07_10_094000`. Das Inventar ist lesend erhoben; es wurden keine Tabellen oder Daten verändert. „Beibehalten“ bedeutet ausdrücklich: keine Strukturänderung ohne konkreten Use Case und eigene Migration.

## Plattformkern, Personen und Organisation

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `personens` | Core Person | zentrale natürliche Person; Referenz vieler Fachbereiche | Beibehalten; Dubletten-, Lösch- und Retentionregeln fachlich klären. |
| `adresses` | Core Person | Adressen von Personen/Organisationen | Beibehalten; Datenscope über Eigentümer erzwingen. |
| `kontaktes` | Core Person | Kontaktdaten mit Kontakttyp | Beibehalten; sensible Werte nicht unnötig exportieren. |
| `kontakttypens` | Core Person | Katalog der Kontaktarten | Beibehalten. |
| `baenkes` | Core Person | Bank-/Zahlungsdaten | Sensibel; eigene Ansichts-/Änderungsrechte und Retention priorisieren. |
| `standorts` | Core Standort | Standortstamm | Beibehalten; ist kein Mandant. |
| `standort_has_personens` | Core Standort | Person-Standort-Zuordnung | Beibehalten; Scope-Semantik vereinheitlichen. |
| `abteilungs` | Organisation intern | Abteilungen | Beibehalten. |
| `abteilungsassistents` | Organisation intern | Assistenz-Zuordnungen | Beibehalten; Namens-/Modellsemantik später lokal bereinigen. |
| `abteilung_has_assistentens` | Organisation intern | Abteilung-Assistenz-Pivot | Beibehalten; Dublettenconstraint bei bestätigter Kardinalität prüfen. |
| `partners` | Organisation extern | Schulen, Betriebe und weitere Partner | Beibehalten; vor neuer Organisationstabelle fachlich bestätigen. |
| `partnerschaftstypens` | Organisation extern | Typisierung externer Partner | Beibehalten. |
| `partner_has_partnerschaftstypens` | Organisation extern | Partner-Typ-Pivot | Beibehalten. |
| `partner_has_personens` | Organisation extern | Ansprechpartner/Personen eines Partners | Beibehalten; Rollenbedeutung dokumentieren. |
| `zielgruppes` | Teilnehmerstamm | Zielgruppenkatalog | Beibehalten. |
| `personen_has_zielgruppes` | Teilnehmerstamm | Person-Zielgruppe | Beibehalten; projektbezogene Gültigkeit prüfen. |
| `personen_ist_schuelers` | BOP/Schule | Schülerrolle/-daten einer Person | BOP-Nähe dokumentieren; nicht für BvB Reha umdeuten. |
| `austritttypens` | Teilnahme | Katalog von Austrittsarten | Für mehrere Maßnahmen wiederverwendbar, Bedeutung je Projektart bestätigen. |
| `leistungsbezueges` | Sozialdaten | Leistungsbezug einer Person | Besonders sensibel; Datensatzrechte, Audit und Retention erforderlich. |
| `vermitlungshemmnisses` | Sozialdaten | Vermittlungshemmnisse | Besonders sensibel; nicht ohne Fach-/Datenschutzfreigabe erweitern. |
| `personen_has_sozialedatens` | Sozialdaten | Sozialdaten-Zuordnung | Sicherheitskritisch; Export- und Zugriffsschutz priorisieren. |
| `personen_has_bildungsmassnahmens` | Altbestand Teilnahme | ältere Maßnahmezuordnung einer Person | Nicht löschen; Semantik gegen `projekt_has_personens` analysieren, bevor migriert wird. |

## Projekte, Teilnahmen und gemeinsame Zuordnungen

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `projekts` | Core Projekt | generisches Projekt, optionaler Projekttyp | Beibehalten; keine automatische Typklassifikation. |
| `project_types` | Core Projekt | additiver Katalog BOP/BvB/BaE/AsA/Coaching | Beibehalten; Fachmodule optional verknüpfen. |
| `projektzeitraums` | Core Projekt | ältere/projektspezifische Zeiträume | Neben `zeitraums` vorhanden; erst nach Datenvergleich konsolidieren. |
| `zeitraums` | Core Zeitraum | polymorphe/aktuelle Projektzeiträume | Beibehalten; Überschneidungsregeln je Use Case. |
| `tages` | Core Kalender | Tages-/Datumsdaten | Beibehalten. |
| `zeitens` | Core Kalender | Zeitangaben/-raster | Beibehalten. |
| `projekt_has_personens` | Core Teilnahme | Projektteilnahme und Mitarbeiter-Projektzugriff mit Standort | Datenkritischer Kern; IDs erhalten, keine unbestätigte Unique-Constraint. |
| `projekt_has_personen_metas` | Teilnahme/BOP | Metadaten einer Projekt-Person-Zuordnung | An Teilnahme-ID gebunden lassen; BOP-Felder nicht für BvB verwenden. |
| `projekt_has_ansprechpartners` | Core Projekt | Projekt-Ansprechpartner | Beibehalten. |
| `projekt_has_bereiches` | Core/BOP | Projekt-Bereiche | Beibehalten; Fachbedeutung überwiegend BOP. |
| `projekt_has_kostenstelles` | Core Finanzen | Projekt-Kostenstellen mit Gültigkeit | Beibehalten; Zeitraumvalidierung serverseitig. |
| `kostenstelles` | Core Finanzen | Kostenstellenkatalog | Beibehalten; optionale Projektzuordnung über Pivot. |
| `projekt_has_partners` | Core Projekt | Projekt-Partner-Zuordnung | Beibehalten. |
| `projekt_has_raeumes` | Ressourcen/Projekt | optionale Raumzuordnung | Beibehalten; Raum bleibt unabhängig vom Projekt. |
| `gruppe_has_personens` | Gruppen | Personen in Gruppen | Beibehalten; Teilnahme-/Standortscope prüfen. |
| `gruppes` | Gruppen | Gruppen eines Projekts/Standorts | Beibehalten; projektartabhängige Funktionen getrennt halten. |
| `bereiches` | BOP/Katalog | BOP-Bereiche/Berufsfelder | BOP-spezifisch behandeln. |
| `bereich_has_personens` | BOP | Person-Bereich-Zuordnung | BOP-spezifisch; nicht als allgemeine Teilnahme verwenden. |

## BOP, Potenzialanalyse, Einteilung und Abschluss

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `bereichsauswahl_settings` | BOP | Konfiguration öffentlicher Bereichsauswahl | BOP-Modulgrenze, Token- und Rate-Limit-Schutz beibehalten. |
| `bereichsauswahls` | BOP | abgegebene Bereichswahlen | Personenbezogen; Retention und Audit definieren. |
| `einteilung_settings` | BOP | Einteilungsparameter | BOP-spezifisch beibehalten. |
| `einteilung_bereiches` | BOP | Ergebnis/Zuordnung der Einteilung | BOP-spezifisch; Transaktion und Kapazität schützen. |
| `einteilung_bereich_kapazitaeten` | BOP | Bereichskapazitäten | BOP-spezifisch; Konflikt-/Grenztests beibehalten. |
| `ergebnisses` | BOP Altbestand | Ergebnisdaten | Schreibfehler/Altmodell nicht destruktiv korrigieren; Nutzung inventarisieren. |
| `personen_has_egebnisses` | BOP Altbestand | Person-Ergebnis-Pivot | Offensichtlicher Altname; nicht umbenennen ohne sichere Migration. |
| `abschluesses` | BOP/Teilnahme | Abschlusskatalog/-daten | BOP-Nutzung von allgemeiner Austrittslogik trennen. |
| `personen_has_abschluesses` | BOP | Person-Abschluss-Zuordnung | Bestehende Daten erhalten; Projektreferenz prüfen. |
| `projekt_has_teilnehmer_abschlusses` | BOP | teilnahmebezogener Abschluss | Gegen personenbasierten Altbestand abgrenzen; bevorzugte Teilnahmegrenze. |
| `projekt_has_teilnehmer_luvs` | BOP | LUV einer Projektteilnahme | BOP-spezifisch; nicht für BvB-Förderpläne verwenden. |
| `verbleibteilnehmers` | BOP | Teilnehmerverbleib | Personenbezogen; Retention und Exportrechte definieren. |
| `potenzialanalyse_berichte` | BOP PA | PA-Berichte | Sensibel; BOP-Modul, Rechte und kontrollierte Exporte. |
| `potenzialanalyse_beurteilungen` | BOP PA | Beurteilungen | Sensibel; Änderungsaudit prüfen. |
| `potenzialanalyse_kompetenzbewertungen` | BOP PA | Kompetenzbewertungen | Sensibel; keine Wiederverwendung für BvB-Diagnostik. |
| `potenzialanalyse_kriterien` | BOP PA | Kriterienkatalog | BOP-spezifisch beibehalten. |
| `potenzialanalyse_selbsteinschaetzungen` | BOP PA | Selbsteinschätzungen | Sensibel; Zugriff nur im BOP-Kontext. |
| `potenzialanalyse_uebungen` | BOP PA | Übungskatalog | BOP-spezifisch beibehalten. |
| `potenzialanalyse_uebung_ergebnisse` | BOP PA | Übungsergebnisse | Sensibel; Teilnahmebezug/Audit absichern. |
| `bibb_attendance_list_drafts` | BOP Export | BIBB-Anwesenheitslistenentwürfe | Temporäre/personenbezogene Daten; Löschfrist festlegen. |
| `pa_attendance_list_drafts` | BOP Export | PA-Anwesenheitslistenentwürfe | Temporäre/personenbezogene Daten; Löschfrist festlegen. |

## Anwesenheit und Klassenbuch

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `anwesenheitsstatutens` | Anwesenheit | Statuskatalog | Potenziell projektübergreifend; beibehalten. |
| `klassenbuecher` | Klassenbuch | Klassenbuch je Projekt/Gruppe | Optionales Projektfeature beibehalten. |
| `klassenbuch_wochen` | Klassenbuch | Wochenstruktur | Beibehalten. |
| `klassenbuch_eintraege` | Klassenbuch | Unterrichts-/Anwesenheitseinträge | Datensatzscope und Audit prüfen. |
| `klassenbuch_kommentare` | Klassenbuch | Kommentare | Personenbezug und Sichtbarkeit begrenzen. |
| `klassenbuch_typen` | Klassenbuch | Eintragstypen | Beibehalten. |

## Räume

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `raeumes` | Raumverwaltung | Raumstamm mit Standort/Ausstattung | Eigenständiges Modul; Daten bei Deaktivierung erhalten. |
| `raum_buchungen` | Raumverwaltung | Reservierungen/Belegung | Serverseitige Konfliktprüfung beibehalten und erweitern. |
| `raum_meldungen` | Raumverwaltung | Störungen/Meldungen | Eigenständig; Rechte und Statusworkflow beibehalten. |

## IT und Geräte

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `geraets` | IT | Inventargeräte | Eigenständiges Modul; Standort-/Personscope später vertiefen. |
| `geraetausgabes` | IT | Ausgabevorgänge | Transaktionsgrenze beibehalten. |
| `geraet_has_ausgabes` | IT | Geräte einer Ausgabe | Beibehalten; Dubletten verhindern. |
| `geraetrueckgabes` | IT | Rückgabevorgänge | Transaktionsgrenze beibehalten. |
| `geraet_has_rueckgabes` | IT | Geräte einer Rückgabe | Beibehalten. |
| `it_tickets` | IT | Service-/Störungstickets | Eigenständig; Status-/Lösungsdaten testen. |

## Lager und Beschaffung

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `lager_artikel` | Lager | Artikel und Bestand | Eigenständiges Modul; Mindestbestand/Inventur nur nach Bedarf erweitern. |
| `lager_bewegungen` | Lager | Bestandsbuchungen | Unveränderliche Historie und Transaktionen priorisieren. |
| `lager_reservierungen` | Lager | Reservierung/Ausgabe | Bestandsgrenzen serverseitig sichern. |
| `produktes` | Beschaffung | Produktkatalog | Von Lagerartikeln fachlich abgrenzen, nicht vorschnell zusammenführen. |
| `bestellungs` | Beschaffung | Bestellungen | Beibehalten; Kosten-/Freigaberechte prüfen. |
| `materialanforderungs` | Beschaffung | Anforderungsworkflow | Eigenständig beibehalten. |
| `materialanforderung_artikels` | Beschaffung | Artikel einer Anforderung | Beibehalten. |
| `materialanforderung_genehmigungs` | Beschaffung | Genehmigungen | Audit-/Unveränderlichkeit prüfen. |
| `materialanforderung_vergabevermerks` | Beschaffung | Vergabevermerke | Dokumentations-/Retentionpflicht fachlich klären. |
| `freigabes` | Verwaltung | allgemeine Freigaben | Rollen-, Objekt- und Änderungsnachweis prüfen. |

## Dienstwagen und Fahrtkosten

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `dienstwagens` | Dienstwagen | Fahrzeugstamm | Eigenständiges Modul; Daten bei Deaktivierung erhalten. |
| `dienstwagen_buchungen` | Dienstwagen | Reservierungen | Konflikt-, Fahrer- und Rechteprüfung beibehalten. |
| `dienstwagen_has_personens` | Dienstwagen | Fahrzeug-Person-Zuordnung | Zweck/Fahrerrolle dokumentieren. |
| `dienstwagen_meldungen` | Dienstwagen | Schaden/Störung | Sensible Schadensdaten; Zugriff begrenzen. |
| `dienstwagen_verlaeufe` | Dienstwagen | Fahrzeughistorie | Historie beibehalten; nicht destruktiv bereinigen. |
| `dienstwagenfahrtenbuches` | Dienstwagen | Fahrtenbuch | Personenbezogen; Retention und Exportkontrolle definieren. |
| `dienstwagenkostenaufzeichnungens` | Dienstwagen | Kosten/Tankvorgänge | Finanzdaten; Änderungsrechte/Audit prüfen. |
| `dienstwagenwartungsaufzeichnungens` | Dienstwagen | Wartungen | Historie beibehalten. |
| `fahrtartens` | Fahrtkosten | Fahrtartenkatalog | Beibehalten. |
| `fahrtens` | Fahrtkosten | einzelne Fahrten | Personen-/Finanzdaten schützen. |
| `fahrtkostensaetzes` | Fahrtkosten | Erstattungssätze | Gültigkeitszeiträume/Audit prüfen. |
| `abrechnungens` | Fahrtkosten | Abrechnungen | Finanzdaten; Freigabe und Audit. |
| `abrechnung_has_fartens` | Fahrtkosten | Abrechnung-Fahrt-Pivot | Altname nicht ohne Migration ändern; Integrität testen. |

## Dokumente, Kommunikation und Notizen

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `dokumentes` | Dokumente | Datei-/Vorlagenmetadaten | Pfadschutz umgesetzt; Datensatzrechte/Audit weiter ausbauen. |
| `dokument_kategories` | Dokumente | Kategorien | Beibehalten. |
| `dokument_has_kategories` | Dokumente | Dokument-Kategorie-Pivot | Beibehalten. |
| `dokument_has_bereiches` | Dokumente/BOP | Dokument-Bereich-Pivot | BOP-Bezug optional halten. |
| `projekt_has_dokumentes` | Dokumente/Projekt | Projekt-Dokument-Pivot | Beibehalten. |
| `projekt_has_dokument_kategories` | Dokumente/Projekt | freigegebene Kategorien je Projekt | Beibehalten; Zugriff serverseitig prüfen. |
| `briefs` | Kommunikation | Briefe/Seriendokumente | Personenbezogene Exporte; Retention/Audit definieren. |
| `personen_has_notizens` | Notizen | Notizen einer Person | Sensibel; Datensatzscope und Löschfrist. |
| `notizvariantens` | Notizen | Notiztypen | Beibehalten. |
| `notifications` | System/Kommunikation | Laravel-Benachrichtigungen | Beibehalten; personenbezogene Payloads minimieren. |
| `notification_rules` | System/Kommunikation | konfigurierbare Empfängerregeln | Beibehalten; Adminrechte erforderlich. |

## Apps

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `app_files` | Apps/Dateien | persönlicher Dateimanager | Pfad-, Share- und Downloadrechte schützen. |
| `app_shares` | Apps/Teilen | Freigaben von App-Objekten | Ablauf/Widerruf und Objektberechtigung prüfen. |
| `app_calendars` | Apps/Kalender | Kalender | Beibehalten. |
| `app_calendar_events` | Apps/Kalender | Termine | Eigentümer-/Freigaberechte prüfen. |
| `app_calendar_styles` | Apps/Kalender | Farbstile | Beibehalten. |
| `app_contacts` | Apps/Kontakte | persönliche Kontakte | Personenbezogen; Scope nach Besitzer. |
| `app_tasks` | Apps/Aufgaben | Aufgaben | Besitzer-/Freigaberechte prüfen. |
| `app_task_workflow_templates` | Apps/Aufgaben | Workflowvorlagen | Beibehalten; Admin-/Besitzsemantik klären. |
| `app_task_workflow_steps` | Apps/Aufgaben | Schritte einer Vorlage | Beibehalten. |
| `app_popups` | Apps | Popup-/Hinweisdaten | Sichtbarkeit und Ablauf prüfen. |

## Module, Rollen und Berechtigungen

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `modules` | Systemmodule | Modulkatalog und Capabilities | Additiv umgesetzt; Auth/Rollen/Modulverwaltung nicht als abschaltbare Fachmodule behandeln. |
| `module_assignments` | Systemmodule | globale/Standort-Aktivierung | Beibehalten; keine Datenlöschung bei Deaktivierung. |
| `permissions` | Rechte | Aktionsrechte | Maßgebliche Lesen-/Schreiben-Kontrolle beibehalten. |
| `roles` | Rechte | Rollen | Beibehalten. |
| `model_has_permissions` | Rechte | direkte Modellrechte | Beibehalten; sparsam verwenden. |
| `model_has_roles` | Rechte | Modell-Rollen | Beibehalten. |
| `role_has_permissions` | Rechte | Rolle-Permission | Beibehalten. |
| `berechtigungskategories` | Rechte/UI | Permission-Kategorien | Beibehalten. |
| `role_berechtigungskategories` | Rechte/UI | Rollen-Kategorie-Zuordnung | Gegen Spatie-Rechte konsistent halten. |
| `role_data_access_settings` | Datenscope | Teilnehmerdaten-Scope je Rolle | Beibehalten; auf neue sensible Aggregate nicht ungeprüft übertragen. |

## System- und Frameworktabellen

| Tabelle | Bereich | Zweck und Abhängigkeiten | Befund / Empfehlung |
|---|---|---|---|
| `users` | Auth/Core | Benutzerkonto mit Personenbezug | Beibehalten; Person nicht mit Teilnahme gleichsetzen. |
| `password_resets` | Auth | Passwort-Reset-Tokens | Frameworktabelle; Ablauf/Cleanup beibehalten. |
| `personal_access_tokens` | Auth/API | Sanctum-Tokens | Fähigkeiten und Widerruf beibehalten. |
| `sessions` | Auth | Datenbanksitzungen | Frameworktabelle; Retention/Cleanup. |
| `failed_jobs` | System | fehlgeschlagene Jobs | Frameworktabelle; derzeit geringe Nutzung, Monitoring bei Jobs. |
| `migrations` | System | Migrationsstand | Niemals fachlich verändern. |
| `activities` | Audit | vorhandene Aktivitäts-/Änderungsprotokolle | Uneinheitlich; Eventkatalog, Integrität, Zugriff und Retention vor sensiblen BvB-Schreibvorgängen definieren. |

## Vollständigkeitsnachweis

Das Live-Schema enthält 137 Tabellen; jede ist oben genau einmal einem Bereich zugeordnet. Die Zuordnung ersetzt keine fachliche Freigabe. Insbesondere Alt- und Schreibfehlernamen werden nicht allein zur kosmetischen Bereinigung migriert. Für Fremdschlüssel, Löschregeln, Indizes und Datenmengen bleibt vor jeder konkreten Strukturänderung ein eigener Dry-Run auf einer Produktionskopie erforderlich.
