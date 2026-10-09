<?php

namespace App\Services\Bop;

use RuntimeException;
use ZipArchive;

class MinistryParticipantExport
{
    public const CAPACITY = 403;

    /**
     * Fill only the ministry's input cells. Copying the XLSX package and patching
     * cells avoids an Office-library round trip that can alter native features.
     */
    public function write(string $template, string $path, array $participants): void
    {
        if (count($participants) > self::CAPACITY) {
            throw new RuntimeException('Die Ministeriumsvorlage bietet Platz für maximal 403 Teilnehmer.');
        }
        if (! copy($template, $path)) {
            throw new RuntimeException('Die Ministeriumsvorlage konnte nicht kopiert werden.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            @unlink($path);
            throw new RuntimeException('Die Ministeriumsvorlage konnte nicht geöffnet werden.');
        }

        try {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($xml === false) {
                throw new RuntimeException('Das Tabellenblatt der Ministeriumsvorlage fehlt.');
            }
            $subsidy = 0;
            foreach (array_values($participants) as $index => $values) {
                $row = $index + 6;
                foreach ($values as $column => $value) {
                    if (! in_array($column, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'M', 'N'], true)) {
                        throw new RuntimeException('Unzulässiges Datenfeld der Ministeriumsvorlage.');
                    }
                    $xml = $this->fillCell($xml, $column.$row, $value);
                }
                // Refresh cached results, preserving the original formula text.
                $amount = ($values['M'] ?? '') === 'Ja' ? ($values['N'] ?? 0) * 11 : 0;
                $subsidy += $amount;
                $xml = $this->cacheValue($xml, 'A'.$row, $index + 1);
                $xml = $this->cacheValue($xml, 'O'.$row, $amount);
            }
            $xml = $this->cacheValue($xml, 'O5', $subsidy);
            if (! $zip->addFromString('xl/worksheets/sheet1.xml', $xml)) {
                throw new RuntimeException('Die Teilnehmerdaten konnten nicht gespeichert werden.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);
            throw $exception;
        }
        if (! $zip->close()) {
            @unlink($path);
            throw new RuntimeException('Die Ministeriumstabelle konnte nicht gespeichert werden.');
        }
    }

    private function fillCell(string $xml, string $coordinate, string|int|float|null $value): string
    {
        return $this->replaceCell($xml, $coordinate, function (array $match) use ($value) {
            $attributes = preg_replace('/\s+t="[^"]*"/', '', $match[1]);
            if (is_int($value) || is_float($value)) {
                return '<c'.$attributes.'><v>'.$value.'</v></c>';
            }
            $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return '<c'.$attributes.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
        });
    }

    private function cacheValue(string $xml, string $coordinate, int|float $value): string
    {
        return $this->replaceCell($xml, $coordinate, function (array $match) use ($value) {
            $body = preg_replace('/<v\b[^>]*(?:\/>|>.*?<\/v>)/s', '<v>'.$value.'</v>', $match[2]);

            return '<c'.$match[1].'>'.$body.'</c>';
        });
    }

    private function replaceCell(string $xml, string $coordinate, callable $replace): string
    {
        $pattern = '/<c\b([^>]*\br="'.preg_quote($coordinate, '/').'"[^>]*?)(?:\/>|>(.*?)<\/c>)/s';
        $result = preg_replace_callback($pattern, $replace, $xml, 1, $count);
        if ($result === null || $count !== 1) {
            throw new RuntimeException('Das Datenfeld '.$coordinate.' fehlt in der Ministeriumsvorlage.');
        }

        return $result;
    }
}
