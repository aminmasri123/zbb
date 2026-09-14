<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class CareerDocumentPdfImporter
{
    public function preview(UploadedFile $file, string $type): array
    {
        try {
            $pages = (new Parser)->parseFile($file->getRealPath())->getPages();
        } catch (\Exception $exception) {
            throw ValidationException::withMessages(['file' => 'Das PDF konnte nicht gelesen werden. Bitte eine gültige, nicht passwortgeschützte PDF-Datei verwenden.']);
        }

        if (count($pages) > 20) {
            throw ValidationException::withMessages(['file' => 'Bitte höchstens 20 Seiten pro Dokument importieren.']);
        }

        $texts = [];
        $emptyPages = [];
        foreach ($pages as $index => $page) {
            try {
                $text = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $page->getText()));
            } catch (\Exception $exception) {
                throw ValidationException::withMessages(['file' => 'Der Text konnte nicht vollständig gelesen werden. Bitte das PDF erneut ohne Passwort exportieren.']);
            }
            if ($text === '') {
                $emptyPages[] = $index + 1;
            }
            $texts[] = $text;
        }
        $text = trim(implode("\n\n", $texts));
        if (mb_strlen($text) > 60000 || ($type === 'cover_letter' && mb_strlen($text) > 15000)) {
            throw ValidationException::withMessages(['file' => 'Dieses Dokument enthält zu viel Text. Bitte Lebenslauf und Anschreiben getrennt oder eine kürzere Datei importieren.']);
        }

        $warnings = ['Die Zuordnung wurde automatisch vorgenommen. Bitte alle Felder mit dem Original vergleichen. Das ursprüngliche PDF-Layout wird nicht übernommen.'];
        if ($emptyPages !== []) {
            $warnings[] = 'Auf Seite '.implode(', ', $emptyPages).' wurde kein Text erkannt. Für gescannte Seiten ist noch keine automatische Texterkennung verfügbar. Bitte fehlende Angaben anhand der Vorschau ergänzen.';
        }

        return [
            'content' => $this->mapText($text, $type),
            'source_text' => $text,
            'warnings' => $warnings,
            'page_count' => count($pages),
        ];
    }

    /** Preserve unassigned text in editable fields instead of silently discarding it. */
    public function mapText(string $text, string $type): array
    {
        $content = ['full_name' => '', 'headline' => '', 'contact' => '', 'summary' => '', 'recipient' => '', 'subject' => '', 'body' => '', 'entries' => []];
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));
        if ($type === 'resume' && preg_match('/^(Lebenslauf|Curriculum Vitae|CV)$/iu', $lines[0] ?? '')) {
            array_shift($lines);
        }
        // Only a name at the beginning is a candidate; unmatched header text stays editable.
        if (mb_strlen($lines[0] ?? '') <= 255 && preg_match('/^\p{Lu}[\p{L}\x{2019}\x{0027}-]+(?:\s+(?:von\s+|van\s+|de\s+)?\p{Lu}[\p{L}\x{2019}\x{0027}-]+){1,3}$/u', $lines[0] ?? '')
            && ! preg_match('/\b(GmbH|Firma|Company|Berufliche|Persönliche|Sehr|Damen|Herren|Beruflicher|Schulische|Kenntnisse)\b/u', $lines[0])) {
            $content['full_name'] = array_shift($lines);
        }
        while ($lines !== [] && preg_match('/(@|^(?:E-?Mail|Telefon|Tel\.?|Mobil|Adresse|Anschrift)\s*:|^\+?\d[\d\s()\/-]{6,}$|^\d{5}\s+\p{L}|(?:straße|strasse|str\.|weg|platz)\s*\d)/iu', $lines[0])) {
            $contact = trim($content['contact']."\n".$lines[0]);
            if (mb_strlen($contact) > 1000) {
                break;
            }
            $content['contact'] = $contact;
            array_shift($lines);
        }
        if ($type === 'cover_letter') {
            // Keep line breaks and all unmatched text, including the signature.
            $bodyStart = null;
            foreach ($lines as $index => $line) {
                if (preg_match('/^(Sehr geehrte|Liebe[rn]?\s|Guten Tag|Hallo\s)/iu', $line)) {
                    $bodyStart = $index;
                    break;
                }
            }
            if ($bodyStart === null) {
                // No reliable letter boundaries: retain the entire source and avoid duplicates.
                $content['full_name'] = '';
                $content['contact'] = '';
                $content['body'] = $text;

                return $content;
            }
            $header = array_slice($lines, 0, $bodyStart);
            foreach ($header as $index => $line) {
                if (mb_strlen($line) <= 255 && preg_match('/^(Bewerbung|Initiativbewerbung|Betreff\s*:)/iu', $line)) {
                    $content['subject'] = $line;
                    unset($header[$index]);
                    break;
                }
            }
            // Sender/recipient boundaries vary widely: leave the complete header for review.
            $headerText = implode("\n", $header);
            if (mb_strlen($headerText) <= 1000) {
                $content['recipient'] = $headerText;
                $offset = mb_strpos($text, $lines[$bodyStart]);
                $content['body'] = mb_substr($text, $offset);
            } else {
                $content['full_name'] = '';
                $content['contact'] = '';
                $content['body'] = $text;
                $content['subject'] = '';
            }

            return $content;
        }

        $section = 'Weitere Angaben';
        $entry = null;
        $flush = function () use (&$entry, &$content): void {
            if ($entry !== null) {
                $content['entries'][] = $entry;
                $entry = null;
            }
        };
        foreach ($lines as $line) {
            if (preg_match('/^(Berufserfahrung|Beruflicher Werdegang|Berufliche Erfahrungen?|Praxiserfahrung|Ausbildung|Schulbildung|Bildungsweg|Studium|Schulische Ausbildung|Weiterbildungen?|Qualifikationen|Kenntnisse|Kenntnisse und Fähigkeiten|Sprachen|Sprachkenntnisse|EDV[- ]Kenntnisse|IT[- ]Kenntnisse|Fähigkeiten|Interessen|Hobbys|Ehrenamt|Persönliche Daten|Kontaktdaten|Kurzprofil|Profil)\s*:?$/iu', $line, $heading)) {
                $flush();
                $section = $heading[1];

                continue;
            }
            // Only treat an explicit date range as a new station; never infer dates.
            if (preg_match('/^((?:(?:\d{1,2}[.\/])?\d{4})\s*[-–—]\s*(?:(?:\d{1,2}[.\/])?\d{4}|heute|aktuell|jetzt|laufend))\s*(.*)$/iu', $line, $period)) {
                $flush();
                $entry = $this->entry($section);
                $entry['period'] = $period[1];
                $line = trim($period[2]);
                if ($line === '') {
                    continue;
                }
            }
            if (in_array(mb_strtolower($section), ['profil', 'kurzprofil'], true) && mb_strlen($content['summary']."\n".$line) <= 5000) {
                $content['summary'] = trim($content['summary']."\n".$line);

                continue;
            }
            $entry ??= $this->entry($section);
            if ($entry['title'] === '' && mb_strlen($line) <= 255) {
                $entry['title'] = $line;
            } elseif ($entry['period'] !== '' && $entry['subtitle'] === '' && $entry['description'] === '' && mb_strlen($line) <= 255) {
                $entry['subtitle'] = $line;
            } else {
                // Split long source text without truncating it or exceeding editor limits.
                foreach (mb_str_split($line, 4500) as $chunk) {
                    if (mb_strlen($entry['description']."\n".$chunk) > 5000) {
                        $flush();
                        $entry = $this->entry($section);
                    }
                    $entry['description'] = ltrim($entry['description']."\n".$chunk);
                }
            }
        }
        $flush();
        if (count($content['entries']) > 100) {
            throw ValidationException::withMessages(['file' => 'Es wurden mehr als 100 Abschnitte erkannt. Bitte eine kürzere Datei importieren.']);
        }

        return $content;
    }

    private function entry(string $section): array
    {
        return ['section' => $section, 'title' => '', 'subtitle' => '', 'period' => '', 'description' => ''];
    }
}
