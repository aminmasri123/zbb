# Originaler BOP-Auswertungsbogen

## Aktive BO-Tage-Vorlage

Seit der Präzisierung durch den Nutzer wird der BO-Tage-Export mit
`resources/views/pdf/bop-bo-evaluation.blade.php` erstellt. Diese ist eine Kopie
von `C:\xampp\htdocs\bop\resources\views\pdf\auswertungsbogenBO.blade.php`.
Die Kopfzeilenanordnung und die Nummern 1–11 stammen aus dieser Blade-Vorlage.
Korrigiert sind die Datenbindung von Punkt 11 (eigene Gesamtbewertung statt
sozialer Kompetenz), ein Leerzeichen beim Vergleich mit Bewertungsstufe 1 und
die lokalen Bildpfade für den PDF-Renderer.

Die extern referenzierten Titel- und Sterngrafiken waren im alten Verzeichnis
nicht vorhanden. `star.png` wurde daher aus `word/media/image2.png` der identischen
BOP-Wordvorlage entnommen; die Titelgrafik stammt aus der Überschrift ihrer
leeren PDF-Seite. `logo.png` stammt aus `bop/storage/img/logo.png`.
Die Quelldateien im alten BOP-Projekt bleiben unverändert.

## Aufbewahrte Word-Referenz

`Auswertungsbogen_BOP.pdf` ist die leere Formularseite aus
`storage/vorlage/projekte/bop/word/Auswertungsbogen_BOP.docx`.
Diese Worddatei ist bytegleich mit der vom Nutzer angegebenen Vorlage
`C:\xampp\htdocs\bop\storage\vorlage\word\Auswertungsbogen_BOP.docx`.
SHA-256 der Wordvorlage: `2abb0ee034a2542787eb88efc037aefbb931c609eea8419682f2be1cdce7806e`.

Erstellung: Alle Platzhalter mit PHPWord TemplateProcessor leeren, anschließend
mit LibreOffice Writer als PDF ausgeben. Keine Änderung der festen Gestaltung.
Die PDF enthält keine Teilnehmerdaten. Sie bleibt als Word-Referenz erhalten;
der aktive Renderer `BopOriginalEvaluationPdf` verwendet nun die Blade-Vorlage.
Jeder Teilnehmer/Bereich erhält eine eigene Seite; die Nummerierung startet
daher auf jeder Seite wie im Original. Die Gesamtbewertung unten ist im Original
ohne Nummer. Die ursprüngliche Wordvorlage bleibt unverändert.
