# Originaler BOP-Auswertungsbogen

## Aktuelle Überschrift

Alle aktiven PDF- und Word-Exporte verwenden seit dem 07.09.2026:
**Kompetenzeinschätzung während der praxisorientierten BO-Tage**.
Die Wordvorlage enthält den Text sowohl im Textfeld als auch in dessen
Kompatibilitätsdarstellung. Die PDF-Blade verwendet dafür
`kompetenzeinschaetzung-bo-tage.png` (1350 × 190 Pixel), mit Comic Sans MS Bold,
orangefarbener Füllung und schwarzer Kontur. Die Bildproportionen und der
Druckrand bleiben erhalten. `Auswertungsbogen_BOP.pdf` wurde aus der aktualisierten
leeren Wordvorlage neu erzeugt. Platzhalter und Bewertungsfelder sind unverändert.
Die folgenden Angaben beschreiben die Herkunft und frühere Fassungen.

## Aktive BO-Tage-Vorlage

Seit der Präzisierung durch den Nutzer wird der BO-Tage-Export mit
`resources/views/pdf/bop-bo-evaluation.blade.php` erstellt. Diese ist eine Kopie
von `C:\xampp\htdocs\bop\resources\views\pdf\auswertungsbogenBO.blade.php`.
Die Kopfzeilenanordnung und die Nummern 1–11 stammen aus dieser Blade-Vorlage.
Korrigiert sind die Datenbindung von Punkt 11 (eigene Gesamtbewertung statt
sozialer Kompetenz), ein Leerzeichen beim Vergleich mit Bewertungsstufe 1 und
die lokalen Bildpfade für den PDF-Renderer.

Für den Ausdruck erhält jede Seite oben und unten 5 mm Druckrand.
Der Abstand vor der Legende ist etwas kleiner, sodass der vollständige Bogen
weiterhin ohne Skalierung auf einer A4-Seite bleibt.

Die extern referenzierten Titel- und Sterngrafiken fehlten im alten Verzeichnis.
Sie wurden aus einem vorhandenen Originalexport des alten BOP-Programms
(`auswertungsbogen.pdf`, lokal im Downloads-Ordner) verlustfrei wiederhergestellt:
`einschaetzung_der_kompetenzen.png` aus dem Bildobjekt I4 (675 × 95 Pixel),
`star.png` aus I6 (64 × 64 Pixel), jeweils einschließlich der Transparenzmaske.
Dadurch bleiben die schwarze Kontur, orange Füllung und originalen Proportionen
der Überschrift erhalten. Die zuvor verwendete Ersatzgrafik aus Word ist ersetzt.
Die übernommenen Grafiken enthalten keine Teilnehmerdaten.
Auch `logo.png` wurde aus dem Originalexport (I2, 780 × 340 Pixel) übernommen,
damit Farbe und Transparenz exakt derselben Quelle entsprechen.
Alle Quelldateien bleiben unverändert.

SHA-256 der wiederhergestellten PNG-Dateien:
- Titel: `41c57d771788d88564c474cccb38230aa34bbeaea77c2a7a8d6f2711426d64eb`
- Stern: `b91300d60e87f211c963e3fca9b60c6ba58ca27486d7eb3c5537bed23315ed88`
- Logo: `1eff78aee36e52b059c9a8553803180a13df98365a2e0eb94099ca68fab7f8c5`

## Aufbewahrte Word-Referenz

`Auswertungsbogen_BOP.pdf` ist die leere Formularseite aus
`storage/vorlage/projekte/bop/word/Auswertungsbogen_BOP.docx`.
Die ursprüngliche Worddatei war bytegleich mit der vom Nutzer angegebenen Vorlage
`C:\xampp\htdocs\bop\storage\vorlage\word\Auswertungsbogen_BOP.docx`.
SHA-256 dieser ursprünglichen Fassung: `2abb0ee034a2542787eb88efc037aefbb931c609eea8419682f2be1cdce7806e`.
In der aktiven Word-Vorlage wurde anschließend ausschließlich die Überschrift
von „BO-Tagen“ in „BO-Tage“ korrigiert, sowohl im Word-Textfeld als auch in seiner
Kompatibilitätsdarstellung. Platzhalter und Formatierung bleiben erhalten.
SHA-256 der korrigierten Wordvorlage: `21e977d0fe3ad243c93058e235602822c80c251c7741d221c26e7d042f4f6a21`.

Erstellung: Alle Platzhalter mit PHPWord TemplateProcessor leeren, anschließend
mit LibreOffice Writer als PDF ausgeben. Keine Änderung der festen Gestaltung.
Die PDF enthält keine Teilnehmerdaten. Sie bleibt als Word-Referenz erhalten;
der aktive Renderer `BopOriginalEvaluationPdf` verwendet nun die Blade-Vorlage.
Jeder Teilnehmer/Bereich erhält eine eigene Seite; die Nummerierung startet
daher auf jeder Seite wie im Original. Die Gesamtbewertung unten ist im Original
ohne Nummer. Die Quelldatei im alten BOP-Projekt bleibt unverändert.
