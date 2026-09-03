# BvB-Reha LuV: Feldquellen und KI-Befüllungsregeln

Stand: 02.09.2026  
Gültig für Start-, Verlauf- und Abschluss-LuV im Teilnehmerprofil.

## 1. Grundregel

Die KI darf ausschließlich freigegebene Daten der ausgewählten Projektteilnahme verwenden. Medizinische Diagnosen, Erkrankungen, innere Einstellungen, Vermutungen und abwertende Klassifizierungen sind ausgeschlossen. Ausbildungsreife, Berufseignung, Unterstützungsbedarf und Entscheidungen dürfen nicht aus indirekten Daten geraten werden.

Fehlt ein konkreter Beleg, lässt die KI das Feld aus. Die Prüfoberfläche zeigt dann **Daten fehlen**. Jeder erzeugte LuV bleibt ein fachlich zu prüfender Entwurf.

## 2. Datenquellen

| Kürzel | Programmdaten | Umfang und Filter |
|---|---|---|
| ID | Teilnehmerstammdaten, Sozialdaten/Kundennummer, Kontakte, Projekt, Status, Zuweisungszeitraum, Betreuer und Projektbegleitung | Aktive Projektteilnahme |
| ANW | Anwesenheitseinträge, nach Status zusammengezählt | Aktives Projekt und ausgewählter Berichtszeitraum |
| DOK | Titel, Inhalt und Datum projektbezogener Teilnehmernotizen | Höchstens 100 Notizen, deren Erstellungsdatum im Berichtszeitraum liegt |
| LUV | Frühere freigegebene LuVs mit Typ, Zeitraum, Ausgangssituation, Zielvereinbarung und Qualifikationen | Höchstens 20; Entwürfe werden nicht verwendet |
| PRA | Praktika/Bildungsmaßnahmen mit Art, Betrieb, Beruf, Zeitraum, Ziel, Tätigkeiten, Beurteilung, Ergebnis und Status | Nicht archiviert und zeitliche Überschneidung mit Berichtszeitraum |
| ABS | Eingereichte oder freigegebene Teilnahmeabschlussberichte mit Beendigungsart, Austrittsdatum, Ergebnis, Zusammenfassung und Empfehlungen | Projektteilnahme; derzeit ohne eigenen Zeitraumfilter |
| EIN | Letzter Stand jeder Einwilligungsdefinition | Projektteilnahme |
| PRJ | Aktive LuV-Vorlage, Formularversion, Projektregeln, aktivierte Quellen und KI-Anweisungen | Aktive Projektkonfiguration je LuV-Typ |
| MAN | Eingabe oder Bestätigung durch die Fachkraft | Tatsächlicher fachlicher Sachstand |

Im Projekt kann jede Quelle je LuV-Typ aktiviert oder deaktiviert werden. Eine deaktivierte Quelle wird nicht an die KI übermittelt.

## 3. Gemeinsame Felder aller drei LuVs

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| report.report_date | Leistungs- und Verhaltensbeurteilung vom | MAN | Berichtsdatum bewusst durch Fachkraft setzen; nicht durch KI erfinden. |
| report.residential_learning | Lernort Wohnen/Internat | MAN, ggf. DOK/LUV | Nur übernehmen, wenn ausdrücklich dokumentiert; derzeit kein eindeutiges Stammdatenmerkmal. |
| contact.name | Kontaktperson beim Maßnahmeträger | ID | Aus Betreuer oder Projektbegleitung; tatsächliche Zuständigkeit prüfen. |
| contact.phone | Telefonnummer der Kontaktperson | MAN | Derzeit keine dienstliche Telefonnummer der Betreuung in der KI-Quelle. Teilnehmertelefon nicht verwenden. |
| contact.email | E-Mail der Kontaktperson | MAN | Derzeit keine dienstliche E-Mail der Betreuung in der KI-Quelle. Teilnehmer-E-Mail nicht verwenden. |
| Stammdaten: Vorname | Vorname | ID | Direkt aus Teilnehmerstammdaten; nicht KI-schreibbar. |
| Stammdaten: Nachname | Nachname | ID | Direkt aus Teilnehmerstammdaten; nicht KI-schreibbar. |
| Stammdaten: Kundennummer | Kundennummer | ID | Direkt aus Sozialdaten; nicht KI-schreibbar. |
| report.discussed_on | Mit der teilnehmenden Person besprochen am | MAN | Erst nach dem tatsächlich geführten Gespräch eintragen. |
| report.copy_handed_out | Kopie wurde ausgehändigt | MAN | Erst nach der tatsächlichen Aushändigung bestätigen. |

## 4. Start-LuV

### 4.1 Individuelle Ausgangssituation

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| competence.school.assessment | Schulische Basiskompetenzen - Einschätzung | DOK, LUV | Beobachtete Leistungen in Schreiben, Lesen, Sprechen/Verstehen, Mathematik oder wirtschaftlichen Grundlagen. |
| competence.school.support_need | Schulische Basiskompetenzen - Förderbedarf | DOK, LUV | Nur ausdrücklich festgehaltenen Förderbedarf nennen. |
| competence.personal.assessment | Personale Kompetenz - Einschätzung | DOK, LUV; ANW nur als Fakt | Beobachtungen zu Motivation, Verantwortung, Sorgfalt, Selbsteinschätzung, Durchhaltevermögen und Zuverlässigkeit. Anwesenheit ist kein Persönlichkeitsurteil. |
| competence.personal.support_need | Personale Kompetenz - Förderbedarf | DOK, LUV | Nur dokumentierten Förderbedarf formulieren. |
| competence.methodical.assessment | Methodische Kompetenz - Einschätzung | DOK, LUV, PRA | Beobachtungen zu Lernen, Problemlösen, Medienkompetenz, Selbstständigkeit und Organisation. |
| competence.methodical.support_need | Methodische Kompetenz - Förderbedarf | DOK, LUV, PRA | Nur dokumentierten methodischen Förderbedarf nennen. |
| competence.social.assessment | Sozial-kommunikative Kompetenz - Einschätzung | DOK, LUV, PRA | Beobachtungen zu Kommunikation, Konfliktfähigkeit, Teamarbeit und Umgangsformen. |
| competence.social.support_need | Sozial-kommunikative Kompetenz - Förderbedarf | DOK, LUV, PRA | Nur ausdrücklich dokumentierte Entwicklungsziele. |
| competence.technical.assessment | Fachliche Basiskompetenzen/Erprobung - Einschätzung | PRA, DOK, LUV | Berufsfelder, Tätigkeiten, Interessen, Fertigkeiten, Rückmeldungen und Ergebnisse. |
| competence.technical.support_need | Fachliche Basiskompetenzen/Erprobung - Förderbedarf | PRA, DOK, LUV | Belegte Lernfelder oder notwendige weitere Erprobungen. |
| competence.notes | Ergänzende Erläuterungen | DOK, PRA, LUV | Nur förderrelevanter Kontext; keine Diagnosen oder geschützten Gesundheitsdaten. |

### 4.2 Förder- und Qualifizierungssequenzen

Für alle Sequenzfelder gelten als Quellen DOK und LUV, ergänzend PRA. Die Auswahl eines im Formular angebotenen Bereichs ist noch kein Beleg. Inhalt, Zeitraum und Status müssen dokumentiert sein.

| Feldschlüssel | Formularfeld | Zulässiger Inhalt |
|---|---|---|
| sequences.foundations | Allgemeiner Grundlagenbereich | Dokumentierte Grundlagenförderung und Zeitraum |
| sequences.language | Sprachförderung | Vereinbarte Sprach-, Lese- oder Schreibförderung |
| sequences.key_competencies | Schlüssel- und Selbstlernkompetenzen | Methodische, interkulturelle, grüne, Diversitäts- oder Selbstlernsequenzen |
| sequences.digital | Digitale sowie IT- und Medienkompetenzen | Geplante oder durchgeführte digitale Lernsequenzen |
| sequences.orientation | Berufsorientierung, Berufsfelder und Berufswahl | Erprobte oder geplante Berufsfelder und Berufswahlaktivitäten |
| sequences.company_phases | Betriebsnahe/betriebliche Qualifizierungsphasen | Praktikum, Betrieb, Beruf, Ziel und Zeitraum |
| sequences.work_social | Arbeits- und Sozialverhalten/betriebliche Grundfertigkeiten | Dokumentierte Trainings- oder Qualifizierungsziele |
| sequences.applications | Bewerbungstraining | Bewerbungsunterlagen, Stellensuche, Gesprächstraining und Zeitraum |
| sequences.vocational | Berufsspezifische Qualifizierung/Übergangsmanagement | Berufliche Qualifizierung, Einarbeitung oder Übergangsplanung |
| sequences.school_certificate | Hauptschulabschluss/Berufsschulunterricht | Nur bei dokumentierter Teilnahme oder Planung |

### 4.3 Eingliederungsziel

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| integration.goal | (Ausbildungs-)Zielberuf und Alternativen | DOK, LUV, PRA, ABS | Dokumentierten Hauptwunsch, Alternativen und Zielrichtung Ausbildung/Beschäftigung nennen. Abweichungen müssen belegt sein. |

### 4.4 Schritte zur Zielerreichung

Maßgeblich sind DOK und LUV, ergänzend PRA. Alle Aufgaben müssen aus dem festgestellten Förderbedarf und der aktuellen Zielvereinbarung hervorgehen.

| Feldschlüssel | Formularfeld | Regel |
|---|---|---|
| tasks.participant | Aufgaben der teilnehmenden Person | Nur konkret vereinbarte Eigenaktivitäten |
| tasks.case_management | Bildungsbegleitung/Case-Management | Dokumentierte organisatorische, koordinierende oder begleitende Aufgaben |
| tasks.trainer | Ausbilderin/Ausbilder | Vereinbarte fachpraktische Anleitung und Qualifizierung |
| tasks.teacher | Lehrkraft | Vereinbarte Unterrichts- und Lernförderung |
| tasks.social_worker | Sozialpädagogin/Sozialpädagoge | Förderbezogene, konkret vereinbarte Unterstützung |
| tasks.psychologist | Psychologin/Psychologe | Nur dokumentierte Aufgabe mit erforderlicher Einwilligung; keine Diagnose |
| tasks.other_staff | Weiteres Fachpersonal | Rolle und konkret vereinbarte Aufgabe |
| tasks.residential_staff | Lernort Wohnen/Internat | Nur bei BvB 3 und dokumentierter Zuständigkeit |
| tasks.joint | Gemeinsame Aufgaben | Gemeinsam vereinbarte Schritte und Nachhaltung |

### 4.5 Entscheidungsbedarf

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| decision.notes | Begründeter Entscheidungsbedarf/bisherige Aktivitäten | DOK, LUV, ABS; ANW nur als Fakt | Nur dokumentierten Bedarf und bereits erfolgte Aktivitäten beschreiben. Die Entscheidung bleibt bei der zuständigen Stelle. |

## 5. Verlauf-LuV

### 5.1 Anlass

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| reason.type | Anlass | MAN, ggf. ID/ABS | Fachkraft wählt den tatsächlichen Anlass; Teilnahmezeitraum kann nur bei der Prüfung helfen. |
| reason.details | Ergänzung zum Anlass | MAN, DOK, ABS | Sachliche Begründung bei Verlängerung, sonstigem Anlass oder drohendem Abbruch. |

### 5.2 Individuelle Entwicklung

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| development.compared_to | Gegenüber der LuV vom | LUV, MAN | Datum der tatsächlich herangezogenen, freigegebenen LuV; fachlich bestätigen. |
| competence.school.previous_need | Schulisch - bisheriger Förderbedarf | LUV | Aus herangezogener früherer LuV. |
| competence.school.current_need | Schulisch - aktueller Förderbedarf | DOK, LUV | Aktuelle belegte Beobachtungen gegenüberstellen. |
| competence.personal.previous_need | Personal - bisheriger Förderbedarf | LUV | Aus früherer freigegebener LuV. |
| competence.personal.current_need | Personal - aktueller Förderbedarf | DOK, LUV; ANW nur als Fakt | Bedarf aus Beobachtungen; Fehlzeiten nicht als Charaktereigenschaft bewerten. |
| competence.methodical.previous_need | Methodisch - bisheriger Förderbedarf | LUV | Aus früherer freigegebener LuV. |
| competence.methodical.current_need | Methodisch - aktueller Förderbedarf | DOK, LUV, PRA | Entwicklung und verbleibenden Bedarf belegt beschreiben. |
| competence.social.previous_need | Sozial-kommunikativ - bisheriger Förderbedarf | LUV | Aus früherer freigegebener LuV. |
| competence.social.current_need | Sozial-kommunikativ - aktueller Förderbedarf | DOK, LUV, PRA | Beobachtete Entwicklung und aktuellen Bedarf beschreiben. |
| competence.technical.previous_need | Fachlich - bisheriger Förderbedarf | LUV | Aus früherer freigegebener LuV. |
| competence.technical.current_need | Fachlich - aktueller Förderbedarf | PRA, DOK, LUV | Praktikums-/Qualifizierungsergebnisse und aktuelle Lernfelder. |
| development.notes | Ergänzende Erläuterungen | DOK, PRA, LUV | Förderrelevanter Kontext; keine Diagnosen oder Vermutungen. |

### 5.3 Förderzielbereiche und Sequenzen

Die zehn Felder sequences.foundations, sequences.language, sequences.key_competencies, sequences.digital, sequences.orientation, sequences.company_phases, sequences.work_social, sequences.applications, sequences.vocational und sequences.school_certificate folgen denselben Quellen und Regeln wie bei der Start-LuV. Im Verlauf muss zwischen geplant, laufend und nachweislich abgeschlossen unterschieden werden.

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| sequences.completed_notes | Abgeschlossene Sequenzen/Nachweise | DOK, PRA, LUV, ABS | Nur ausdrücklich abgeschlossene oder nachgewiesene Sequenzen, Bausteine und Ergebnisse. |

### 5.4 Eingliederungsziel und Aufgaben

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| integration.goal | (Ausbildungs-)Zielberuf und Alternativen | DOK, LUV, PRA, ABS | Aktuellen dokumentierten Zielberuf nennen und belegte Änderungen kenntlich machen. |

Die neun Felder tasks.participant, tasks.case_management, tasks.trainer, tasks.teacher, tasks.social_worker, tasks.psychologist, tasks.other_staff, tasks.residential_staff und tasks.joint folgen den Regeln der Start-LuV. Im Verlauf müssen sie zum aktuellen Förderbedarf passen.

### 5.5 Entscheidungsbedarf

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| decision.notes | Abbruch, Teilzeit, Verlängerung oder sonstiger Entscheidungsbedarf | DOK, ABS, LUV; ANW nur als Fakt | Gefährdung und bereits unternommene Aktivitäten konkret dokumentieren. Unentschuldigtes Fehlen darf als Fakt, nicht ohne weitere Belege als Ursache oder Charakterurteil erscheinen. |

## 6. Abschluss-LuV

### 6.1 Anlass

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| completion.reason | Beendigungsart | ABS, ID, MAN | Abschlussbericht/Austrittsdatum kann Ende oder Abbruch belegen; Fachkraft bestätigt die Auswahl. |

### 6.2 Ergebnisse der BvB

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| results.school_certificate | Hauptschulabschluss oder vergleichbarer Abschluss | ABS, DOK, LUV | Erreicht, nicht erreicht oder nicht angestrebt nur bei ausdrücklichem Beleg. |
| results.training_maturity | Allgemeine Ausbildungsreife erreicht | MAN; nur ausdrücklich ABS/DOK/LUV | Fachliche Gesamtbeurteilung; niemals aus Fehlzeiten, einem Praktikum oder allgemeinen Notizen raten. |
| results.suitable_occupations | Berufseignung/Qualifikationsniveau | ABS, PRA, DOK, LUV | Nur ausdrücklich festgestellte Eignung; Praktikumserfolg allein ist kein Eignungsnachweis. |
| results.modules | Qualifizierungs-/Ausbildungsbausteine | PRA, DOK, LUV, ABS | Bezeichnung, Zeitraum und nachgewiesenes Ergebnis; Planung nicht als Abschluss darstellen. |
| results.placement_ability | Aussagen zur Vermittlungsfähigkeit | ABS, DOK, LUV, MAN | Nur fachlich dokumentierte Aussage, ggf. Teilzeit; nicht aus Anwesenheit berechnen. |
| results.integration | Eingliederungsergebnis | ABS, PRA, DOK, LUV | Belegte Angaben zu Betrieb, Beruf, Beginn und Ausbildungs-/Beschäftigungsstatus. |

### 6.3 Unterstützungsbedarf und Stabilisierung

| Feldschlüssel | Formularfeld | Quelle | Regel |
|---|---|---|---|
| support.required | Weiterer Unterstützungsbedarf | MAN; nur ausdrücklich ABS/DOK/LUV | Fachliche Ja/Nein-Entscheidung, nicht automatisch aus Kompetenz- oder Fehlzeitdaten setzen. |
| support.description | Beschreibung des Unterstützungsbedarfs | ABS, DOK, LUV | Nur bestätigten Bedarf nennen, zum Beispiel Assistierte Ausbildung oder Lernunterstützung. |
| support.stabilization | Absprachen zur Stabilisierung und Festigung | ABS, DOK, LUV | Dokumentierte Absprachen, Kontaktformat, Zuständigkeit und Häufigkeit. |
| support.recommendations | Ergänzende Erläuterungen/Empfehlungen | ABS, DOK, LUV, PRA | Belegte realistische Empfehlung; keine neue fachliche Entscheidung erfinden. |

## 7. Technischer Quellenbezug

Jede KI-Aussage trägt intern mindestens eine Quellen-ID, zum Beispiel:

- documentation-123 für eine Teilnehmernotiz,
- luv-45 für eine frühere freigegebene LuV,
- development-internship-67 für ein Praktikum,
- development-completion-8 für einen Abschlussbericht,
- attendance-summary für die zusammengefasste Anwesenheit.

Beim Übernehmen werden die verwendeten Quellen-IDs im source_snapshot des LuV-Datensatzes gespeichert. Dadurch bleibt nachvollziehbar, welche Datenquellen der KI-Lauf verwendet hat.

## 8. Bekannte Datenlücken und Empfehlungen

Noch ohne eindeutige strukturierte Automatikquelle sind:

1. Lernort Wohnen/Internat als Ja/Nein-Stammdatum.
2. Dienstliche Telefonnummer und E-Mail der zuständigen Kontaktperson.
3. Berichtsdatum und Anlass als bewusst bestätigte Angaben.
4. Gesprächsdatum und tatsächliche Aushändigung einer Kopie.
5. Fachliche Entscheidungen zu Ausbildungsreife und Unterstützungsbedarf.
6. Strukturierte Kompetenzbeobachtungen mit Bereich, Datum, Beobachtung und Förderbedarf.
7. Strukturierte Zielvereinbarungen und Aufgaben nach Rolle.
8. Strukturierte Fördersequenzen mit Zeitraum, Status und Nachweis.

Für eine höhere und reproduzierbarere KI-Qualität sollten besonders die Punkte 6 bis 8 als eigene Programmfelder eingeführt werden. Freitextnotizen bleiben nutzbar, sind aber weniger eindeutig.

## 9. Fachliche Grundlage

Die Feldstruktur wurde mit den bereitgestellten neuen Formularen und den Start-, Verlauf- und Abschluss-Beispielen abgeglichen. Die Formulierungsregeln folgen den Ausfüllhinweisen: beobachtetes Verhalten statt Vermutungen, Relevanz für die individuelle Förderung, objektive Darstellung, keine negativen Klassifizierungen und keine medizinischen Diagnosen. Schritte zur Zielerreichung müssen aus dem festgestellten Förderbedarf und der aktuellen Zielvereinbarung hervorgehen.
