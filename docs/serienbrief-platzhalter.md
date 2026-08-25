# Export-Vorlagen und Platzhalter

Der Dokumentenmanager verwaltet Vorlagen zentral. Eine Vorlage kann direkt einem Projekt zugeordnet werden oder ueber eine Kategorie wie `BOP`, `AGH`, `SGB II` oder `ESF` an mehrere Projekte gehen.

Zusatzlich hat jede Vorlage einen Anzeigeort:

- `Partner / Schule`: erscheint nicht in Gruppen.
- `Gruppe`: kann in Gruppen exportiert werden.
- `Teilnehmerseite`: erscheint im Reiter `Exportieren` des Teilnehmerprofils und wird für genau einen Teilnehmer befüllt.

Gruppen-Vorlagen koennen auf bestimmte Bereiche eingeschraenkt werden, zum Beispiel `Potenzialanalyse`. Wenn kein Bereich gesetzt ist, gilt die Vorlage fuer alle Bereiche des Projekts.

Vorlagen fuer die Teilnehmerseite muessen den Datenbezug `Teilnehmer` verwenden. Sie werden angezeigt, wenn sie dem aktiven Projekt direkt oder ueber eine Dokumentkategorie zugeordnet sind und der angemeldete Benutzer die individuelle Exportberechtigung der Vorlage besitzt.

## Vorlage vorbereiten

1. Word-Vorlagen als `.docx`, Excel-Vorlagen als `.xlsx`, PDF-Vorlagen als `.pdf` speichern.
2. Platzhalter im Dokument exakt in dieser Form eintragen: `${vorname}`, `${nachname}`, `${geburtsdatum}`.
3. Vorlage im Dokumentenmanager hochladen.
4. Kategorie oder Projekt auswaehlen.
5. Anzeigeort auswaehlen.
6. Bei Gruppen-Vorlagen optional Bereiche auswaehlen.
7. `Gruppen-Export` und `Platzhalter fuellen` aktivieren, wenn die Vorlage in Gruppen sichtbar sein soll.

## Vollstaendigkeitspruefung vor dem Export

Vor jedem befuellten Word- oder Excel-Export liest das System die tatsaechlich in der Vorlage verwendeten Platzhalter aus. Der Export startet nur, wenn alle dafuer benoetigten Daten vorhanden sind.

- Teilnehmerexporte pruefen die angeforderten Stamm-, Sozial-, Adress-, Kontakt-, Termin- und Betreuungsdaten.
- Gruppenexporte pruefen Gruppen- und Partnerdaten sowie die angeforderten Felder fuer jeden betroffenen Teilnehmer einzeln.
- Partnerexporte pruefen alle verwendeten Partner-, Schul- und Terminangaben.
- Unbekannte oder falsch geschriebene Platzhalter werden gemeldet und nicht stillschweigend leer exportiert.
- Bei Adressplatzhaltern wird eine vollstaendige Postanschrift aus Strasse, Hausnummer, PLZ und Stadt verlangt.

Fehlende Angaben werden vor der Dateierzeugung in einer Fehlermeldung aufgelistet. Statische PDF-Dateien enthalten keine befuellbaren Platzhalter und werden deshalb unveraendert heruntergeladen.

## Dokumentenpakete

Im Dokumentenmanager koennen mehrere Teilnehmer-Vorlagen zu einem benannten Paket wie `TLN Empfang` verbunden werden.

1. `Neues Paket` waehlen und einen Namen vergeben.
2. Ein oder mehrere Projekte auswaehlen.
3. Die Teilnehmer-Vorlagen markieren und ihre Export-Reihenfolge festlegen.
4. Das Paket speichern.

Im Teilnehmerprofil erscheint das Paket im Reiter `Exportieren`. Dort stehen zwei Ausgaben zur Wahl:

- `Eine PDF`: Alle enthaltenen PDFs werden in der festgelegten Reihenfolge zu einer Datei verbunden.
- `Alle als ZIP`: Jede enthaltene Vorlage wird als eigene PDF in eine ZIP-Datei geschrieben.

Ein Paket kann nur aktive Vorlagen enthalten, die auf der Teilnehmerseite angezeigt werden und PDF als Ausgabe erlauben. Vor der Erzeugung prueft das System zuerst saemtliche enthaltenen Vorlagen. Fehlt auch nur eine angeforderte Angabe, wird das gesamte Paket gestoppt und die betroffene Vorlage zusammen mit den fehlenden Daten genannt.

Word-Dokumente innerhalb eines Pakets benoetigen fuer ihre PDF-Erzeugung ebenfalls LibreOffice Writer. Der ZIP-Export ist ausserdem die Ausweichmoeglichkeit, falls eine bereits hochgeladene PDF technisch nicht mit den anderen PDFs zusammengefuehrt werden kann.

## Word

Word-Vorlagen werden pro Teilnehmer gefuellt. Der Gruppen-Export erzeugt eine ZIP-Datei mit einem Dokument pro Teilnehmer. Als Ausgabe sind `DOCX` und, wenn aktiviert, `PDF` moeglich.

Beispiele:

```text
${anrede} ${vorname} ${nachname}
geboren am ${geburtsdatum}
Projekt: ${projekt}
Gruppe: ${gruppe}
Zeitraum: ${startdatum} bis ${enddatum}
```

## Excel

Excel-Vorlagen koennen Gruppenwerte direkt in Zellen nutzen, zum Beispiel `${projekt}`, `${gruppe}`, `${startdatum}`. Fuer eine automatisch erzeugte Teilnehmerliste eine Zelle mit folgendem Platzhalter setzen:

```text
${teilnehmer_tabelle}
```

Ab dieser Zelle schreibt das System die Spalten `Nr.`, `Vorname`, `Nachname`, `Geburtsdatum`, `Adresse`, `Telefon`, `E-Mail`.

## PDF

PDF-Vorlagen koennen als feste Datei zentral verwaltet und Projekten/Kategorien zugeordnet werden. Fuer befuellte Ausgaben wird empfohlen, Word- oder Excel-Vorlagen zu verwenden und im Manager `PDF` als Ausgabeformat zu aktivieren.

Word-PDF-Ausgaben werden zur Erhaltung des DOCX-Layouts mit LibreOffice Writer erzeugt. Auf Linux-Webservern muss deshalb `libreoffice` beziehungsweise mindestens LibreOffice Writer installiert sein. Ein abweichender Programmpfad kann über `LIBREOFFICE_BINARY` konfiguriert werden, zum Beispiel `/usr/bin/soffice`.

## Verfuegbare Platzhalter

Teilnehmer:

```text
${vorname}
${nachname}
${name}
${voller_name}
${teilnehmer}
${geburtsdatum}
${geschlecht}
${anrede}
${kundennummer}
```

Adresse und Kontakt:

```text
${strasse}
${hausnummer}
${plz}
${stadt}
${ort}
${adresse}
${email}
${telefon}
```

Projekt und Gruppe:

```text
${projekt}
${projekt_name}
${gruppe}
${gruppe_id}
${bereich}
${raum}
${ort_typ}
${startdatum}
${enddatum}
${von}
${bis}
${startzeit}
${endzeit}
```

Betreuung und Export:

```text
${betreuer}
${betreuer_name}
${betreuer_anrede}
${betreuer_anrede_dativ}
${betreuer_vorname}
${betreuer_nachname}
${termin_datum}
${termin_uhrzeit}
${termin}
${erstgespraech_datum}
${erstgespraech_uhrzeit}
${datum}
${heute}
${nr}
${nummer}
```
