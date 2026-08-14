<?php

namespace App\Services\Documents;

class HtmlPdfDocumentCombiner
{
    /**
     * @param  iterable<string>  $documents
     */
    public function combine(iterable $documents): string
    {
        $head = null;
        $bodyParts = [];

        foreach ($documents as $document) {
            if ($head === null) {
                $head = $this->extract($document, 'head');
            }

            $body = $this->extract($document, 'body');
            $bodyParts[] = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body) ?? $body;
        }

        if ($head === null || $bodyParts === []) {
            throw new \InvalidArgumentException('Es wurden keine HTML-Dokumente zum Zusammenführen übergeben.');
        }

        $pageBreak = '<div style="page-break-before: always;"></div>';

        return '<!DOCTYPE html><html lang="de"><head>'
            .$head
            .'</head><body>'
            .implode($pageBreak, $bodyParts)
            .'</body></html>';
    }

    private function extract(string $document, string $element): string
    {
        if (! preg_match('/<'.$element.'\b[^>]*>(.*?)<\/'.$element.'>/is', $document, $matches)) {
            throw new \InvalidArgumentException("Das HTML-Dokument enthält kein {$element}-Element.");
        }

        return $matches[1];
    }
}
