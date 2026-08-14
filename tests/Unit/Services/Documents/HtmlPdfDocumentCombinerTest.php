<?php

namespace Tests\Unit\Services\Documents;

use App\Services\Documents\HtmlPdfDocumentCombiner;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class HtmlPdfDocumentCombinerTest extends TestCase
{
    public function test_it_combines_documents_in_the_given_order_with_page_breaks(): void
    {
        $combiner = new HtmlPdfDocumentCombiner();

        $html = $combiner->combine([
            '<!DOCTYPE html><html><head><style>.name{font-weight:bold}</style></head><body><p class="name">Albrecht, Anna</p><script>ignored()</script></body></html>',
            '<!DOCTYPE html><html><head><style>.other{color:red}</style></head><body><p class="name">Zander, Zoe</p></body></html>',
        ]);

        $this->assertSame(1, substr_count($html, '<head>'));
        $this->assertSame(1, substr_count($html, '<body>'));
        $this->assertSame(1, substr_count($html, 'page-break-before: always'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertLessThan(strpos($html, 'Zander, Zoe'), strpos($html, 'Albrecht, Anna'));

        $output = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
        $this->assertStringStartsWith('%PDF-', $output);
    }
}
