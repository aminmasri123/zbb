<?php
namespace Tests\Unit\Services\Bop;

use App\Services\Bop\BibbAttendanceWordTemplate;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;
use ZipArchive;

class BibbAttendanceWordTemplateTest extends TestCase
{
    public static function sizes(): array { return [['A3',3,1],['A4',3,2],['A3',75,1],['A4',75,2],['A3',101,1],['A4',101,2]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('sizes')]
    public function test_exact_participant_rows_and_current_logo(string $format, int $count, int $tables): void
    {
        $source=storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_'.$format.'.docx');
        $hash=hash_file('sha256',$source);
        $processor=(new BibbAttendanceWordTemplate)->processor($source,$count);
        $target=tempnam(sys_get_temp_dir(),'bibb-test-');
        try {
            $processor->saveAs($target);
            $zip=new ZipArchive;
            $this->assertTrue($zip->open($target)===true);
            $document=new DOMDocument;
            $document->loadXML($zip->getFromName('word/document.xml'));
            $xpath=new DOMXPath($document);
            $xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $found=0;
            foreach($xpath->query('//w:body/w:tbl') as $table) {
                $numbers=[];
                $displayNumbers=[];
                foreach($xpath->query('./w:tr',$table) as $row) {
                    if(preg_match('/\$\{nachname(\d+)\}/',$row->textContent,$match)) {
                        $numbers[]=(int)$match[1];
                        $displayNumbers[]=(int)$xpath->query('./w:tc[1]', $row)->item(0)->textContent;
                    }
                }
                if($numbers) { $found++; $this->assertSame(range(1,$count),$numbers); $this->assertSame(range(1,$count),$displayNumbers); }
            }
            $this->assertSame($tables,$found);
            $this->assertSame(file_get_contents(public_path('img/bop/kooperationspartner.png')),$zip->getFromName('word/media/bop-attendance-footer.png'));
            $zip->close();
            $zip=null;
            $this->assertSame($hash,hash_file('sha256',$source));
        } finally { if (isset($zip)) $zip->close(); unlink($target); }
    }
}
