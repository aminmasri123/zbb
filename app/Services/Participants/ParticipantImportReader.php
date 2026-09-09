<?php

namespace App\Services\Participants;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Translate project-specific column names into the existing participant import schema. */
class ParticipantImportReader
{
    public const FIELDS = ['Vorname','Nachname','Geschlecht','Geburtsdatum','Projekt_ID','Standort_ID','Schule_ID','Schuljahr','Teil','Klasse','Foerderschueler','EEE','Straße','Hausnummer','PLZ','Stadt','Land','Adresszusatz','Namenszusatz','Telefon','E-Mail','Telefax','Schulabschluss bei Übermittlung durch BA'];

    public function read(UploadedFile $file, string $profile = 'auto'): array
    {
        if (!in_array($profile, ['auto','standard','bop','bvb_reha'], true)) $this->fail('Unbekanntes Importprofil.');
        $rows = [];
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $text = file_get_contents($file->getRealPath());
            if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $text); rewind($stream);
            $first = strtok($text, "\r\n") ?: '';
            $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) $rows[] = $row;
            fclose($stream);
        } else {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
            if ($sheet->getHighestDataRow() > 5010 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 100) $this->fail('Die Datei ist zu groß. Bitte höchstens 5000 Teilnehmer pro Datei verwenden.');
            $rows = $sheet->toArray(null, false, false, false);
        }
        if (count($rows) > 5010) $this->fail('Bitte höchstens 5000 Teilnehmer pro Datei verwenden.');
        $aliases = [];
        foreach (self::FIELDS as $index => $label) $aliases[$this->key($label)] = $index;
        foreach (['strasse'=>12,'nr'=>13,'ort'=>15,'zusatzinfo'=>17,'email'=>20,'fax'=>21,'schulabschluss'=>22] as $key=>$index) $aliases[$key]=$index;
        $mapping = null; $data = []; $labels = [];
        $legacyBop = in_array($this->key($rows[1][1] ?? ''), ['bop','berufsorientierungsprogramm'], true);
        foreach ($rows as $index => $row) {
            if ($mapping === null) {
                $keys = array_map(fn($value) => $this->key($value), $row);
                if (!in_array('vorname', $keys, true) || !in_array('nachname', $keys, true)) continue;
                $mapping = [];
                foreach ($keys as $column => $key) {
                    if ($key === '') continue;
                    if (!array_key_exists($key, $aliases)) $this->fail('Unbekannte Spalte: '.(string)$row[$column].'. Bitte die Spalte zuordnen oder aus der Importdatei entfernen.');
                    $target = $aliases[$key];
                    if (in_array($target, $mapping, true)) $this->fail('Doppelte Spalte: '.self::FIELDS[$target]);
                    $mapping[$column]=$target;
                    $labels[]=['source'=>(string)$row[$column], 'target'=>self::FIELDS[$target]];
                }
                continue;
            }
            if (!array_filter($row, fn($value) => trim((string)$value) !== '')) continue;
            $values = array_fill(0, count(self::FIELDS), null);
            foreach ($row as $column => $value) {
                if (!isset($mapping[$column])) {
                    if (trim((string)$value) !== '') $this->fail('Zeile '.($index+1).': Wert in einer Spalte ohne Überschrift.');
                    continue;
                }
                $values[$mapping[$column]] = is_string($value) ? trim($value) : $value;
            }
            $data[]=['row_number'=>$index+1, 'values'=>$values];
        }
        if ($mapping === null) $this->fail('Die Kopfzeile wurde nicht gefunden. Vorname und Nachname müssen als Spalten vorhanden sein.');
        if (!$data) $this->fail('Die Datei enthält nur Überschriften oder keine Teilnehmerdaten.');
        $detected = in_array(22, $mapping, true) ? 'bvb_reha' : ($legacyBop || in_array(6, $mapping, true) && array_filter(array_column(array_column($data, 'values'), 6)) ? 'bop' : 'standard');
        return ['data'=>$data, 'mapping'=>$labels, 'profile'=>$profile === 'auto' ? $detected : $profile];
    }

    private function key($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(trim((string)$value))));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file'=>$message]);
    }
}
