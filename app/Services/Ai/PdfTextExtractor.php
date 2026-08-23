<?php

namespace App\Services\Ai;

use RuntimeException;
use Symfony\Component\Process\Process;

final class PdfTextExtractor
{
    /** @return list<array{page:int,text:string}> */
    public function pages(string $path): array
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $document=(new \Smalot\PdfParser\Parser)->parseFile($path);$pages=[];
            foreach(array_slice($document->getPages(),0,50) as $index=>$page){$text=trim(preg_replace('/\s+/u',' ',$page->getText())??'');if($text!=='')$pages[]=['page'=>$index+1,'text'=>mb_substr($text,0,18000)];}
            if($pages===[])throw new RuntimeException('Das PDF enthält keinen maschinenlesbaren Text (OCR ist erforderlich).');
            return $pages;
        }
        $info = new Process([(string) config('services.zbb_ai_workspace.pdfinfo', 'pdfinfo'), $path]);
        $info->setTimeout(20); $info->run();
        if (! $info->isSuccessful() || ! preg_match('/^Pages:\s+(\d+)/mi', $info->getOutput(), $match)) {
            throw new RuntimeException('PDF konnte nicht gelesen werden. Auf dem Server wird poppler-utils benötigt.');
        }
        $count = min((int) $match[1], 50); $pages = [];
        for ($page=1; $page <= $count; $page++) {
            $process = new Process([(string) config('services.zbb_ai_workspace.pdftotext', 'pdftotext'), '-f', (string)$page, '-l', (string)$page, '-enc', 'UTF-8', $path, '-']);
            $process->setTimeout(20); $process->run();
            if (! $process->isSuccessful()) throw new RuntimeException('PDF-Text konnte nicht extrahiert werden.');
            $text = trim(preg_replace('/\s+/u', ' ', $process->getOutput()) ?? '');
            if ($text !== '') $pages[] = ['page'=>$page, 'text'=>mb_substr($text, 0, 18000)];
        }
        if ($pages === []) throw new RuntimeException('Das PDF enthält keinen maschinenlesbaren Text (OCR ist erforderlich).');
        return $pages;
    }
}
