<?php

namespace App\Services\Bop;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;

class BopEvaluationWordTemplate extends TemplateProcessor
{
    public function __construct($documentTemplate)
    {
        parent::__construct($documentTemplate);

        $document = new DOMDocument;
        $document->loadXML($this->tempDocumentMainPart, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('mc', 'http://schemas.openxmlformats.org/markup-compatibility/2006');

        // Its decorative box and stars are a separate, textless drawing right
        // after the five k placeholders in the original template.
        foreach ($xpath->query('//w:r[mc:AlternateContent and not(.//w:t) and preceding-sibling::w:r[1][contains(., "${k-")]]') as $run) {
            $run->parentNode->removeChild($run);
        }

        // The original form stores point 11 and its five boxes in floating
        // drawings, with a second copy for older Word versions (mc:Fallback).
        // Remove their complete runs so borders, stars and boxes disappear too.
        foreach ($xpath->query('//w:r[mc:AlternateContent]') as $run) {
            if (str_contains($run->textContent, 'Einschätzung der Befähigung')
                || preg_match('/\$\{k-[1-5]\}/', $run->textContent)) {
                $run->parentNode->removeChild($run);
            }
        }

        // Also support table-based or plain-text copies of the form.
        foreach ($xpath->query('//w:tr[not(.//w:tr)] | //w:p[not(ancestor::w:tr) and not(.//w:p)]') as $node) {
            if (str_contains($node->textContent, 'Einschätzung der Befähigung')) {
                $node->parentNode->removeChild($node);
            }
        }

        $this->tempDocumentMainPart = $document->saveXML();
        foreach (range(1, 5) as $score) {
            $this->setValue('k-'.$score, '');
        }
    }
}
