<?php

use App\Services\Ai\AgentClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$agent=$app->make(AgentClient::class);

$requests=[
    ['task'=>'chat','instruction'=>'Antworte auf Deutsch in genau einem kurzen Satz: Was ist ein Bewerbungsanschreiben?','sources'=>[],'image_base64'=>null],
    ['task'=>'summarize','instruction'=>'Fasse die Kernaussagen knapp zusammen und zitiere die Seitenquelle.','sources'=>[
        ['source_id'=>'document-1-page-1','label'=>'Testdokument.pdf','page'=>1,'text'=>'Das Bewerbungscoaching findet montags statt. Teilnehmende bringen ihren Lebenslauf mit.'],
        ['source_id'=>'document-1-page-2','label'=>'Testdokument.pdf','page'=>2,'text'=>'Ziel ist ein individuelles Anschreiben. Alle KI-Entwürfe werden vor Verwendung fachlich geprüft.'],
    ],'image_base64'=>null],
    ['task'=>'compare','instruction'=>'Vergleiche die Dokumente und nenne Gemeinsamkeiten sowie Unterschiede mit Quellen.','sources'=>[
        ['source_id'=>'document-1-page-1','label'=>'Version-A.pdf','page'=>1,'text'=>'Coaching ist montags. Lebenslauf ist erforderlich.'],
        ['source_id'=>'document-2-page-1','label'=>'Version-B.pdf','page'=>1,'text'=>'Coaching ist dienstags. Lebenslauf und Zeugnisse sind erforderlich.'],
    ],'image_base64'=>null],
    ['task'=>'image_analysis','instruction'=>'Beschreibe kurz, was auf diesem Bild oder Symbol erkennbar ist.','sources'=>[],'image_base64'=>base64_encode(file_get_contents(dirname(__DIR__).'/public/img/logo/zbb-app-icon-192.png'))],
];

$requestedTask=$argv[1] ?? null;
if($requestedTask!==null){
    $requests=array_values(array_filter($requests,static fn(array $request): bool=>$request['task']===$requestedTask));
    if($requests===[]){
        fwrite(STDERR,"Unbekannte Aufgabe: {$requestedTask}\n");
        exit(2);
    }
}

foreach($requests as $request){
    $result=$agent->generate(['run_id'=>(string)Str::uuid(),...$request]);
    printf("task=%s ok title=%s content_chars=%d citations=%d\n",$result['task'],$result['title'],mb_strlen($result['content']),count($result['citations']));
}
