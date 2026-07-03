# Export-Vorlagen und Platzhalter

Der Dokumentenmanager verwaltet Vorlagen zentral. Eine Vorlage kann direkt einem Projekt zugeordnet werden oder ueber eine Kategorie wie `BOP`, `AGH`, `SGB II` oder `ESF` an mehrere Projekte gehen.

Zusatzlich hat jede Vorlage einen Anzeigeort:

- `Partner / Schule`: erscheint nicht in Gruppen.
- `Gruppe`: kann in Gruppen exportiert werden.

Gruppen-Vorlagen koennen auf bestimmte Bereiche eingeschraenkt werden, zum Beispiel `Potenzialanalyse`. Wenn kein Bereich gesetzt ist, gilt die Vorlage fuer alle Bereiche des Projekts.

## Vorlage vorbereiten

1. Word-Vorlagen als `.docx`, Excel-Vorlagen als `.xlsx`, PDF-Vorlagen als `.pdf` speichern.
2. Platzhalter im Dokument exakt in dieser Form eintragen: `${vorname}`, `${nachname}`, `${geburtsdatum}`.
3. Vorlage im Dokumentenmanager hochladen.
4. Kategorie oder Projekt auswaehlen.
5. Anzeigeort auswaehlen.
6. Bei Gruppen-Vorlagen optional Bereiche auswaehlen.
7. `Gruppen-Export` und `Platzhalter fuellen` aktivieren, wenn die Vorlage in Gruppen sichtbar sein soll.

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
${betreuer_vorname}
${betreuer_nachname}
${datum}
${heute}
${nr}
${nummer}
```
