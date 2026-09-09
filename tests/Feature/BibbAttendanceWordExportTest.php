<?php
namespace Tests\Feature;
use App\Models\{User,Projekt,Partner,Personen,PersonenIstSchueler};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;
class BibbAttendanceWordExportTest extends TestCase
{
    use RefreshDatabase;
    public function test_both_word_exports_end_at_the_last_participant(): void
    {
        $user=User::factory()->create();
        $project=Projekt::factory()->create(['name'=>'BOP']);
        $school=Partner::create(['name'=>'Testschule']);
        $user->projekte()->attach($project);
        $user->update(['current_team_id'=>$project->id]);
        $project->partners()->attach($school);
        $this->grantTestPermission($user,'anwesenheit.abrechnung');
        foreach([3,1,2] as $i) {
            $person=Personen::factory()->create(['typ'=>'teilnehmer','nachname'=>'Testname'.$i]);
            PersonenIstSchueler::create(['person_id'=>$person->id,'schule_id'=>$school->id,'schuljahr'=>'2026','teil'=>'1','klasse'=>'7.'.(4-$i)]);
        }
        foreach(['A3','A4'] as $format) {
            $response=$this->actingAs($user)->post(route('anwesenheitsliste.POBO.bibb.export.word'),[
                'exportFormat'=>$format,'schuleIdInputBibb'=>$school->id,'schuljahrInputBibb'=>'2026','teilInputBibb'=>'1',
                'days'=>[['date'=>'2026-09-08','selected'=>true]],
            ])->assertOk();
            $path=$response->getFile()->getPathname();
            $zip=new ZipArchive;$zip->open($path);
            try {
                $doc=new \DOMDocument;$doc->loadXML($zip->getFromName('word/document.xml'));
                $xp=new \DOMXPath($doc);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $tables=$xp->query('//w:body/w:tbl');
                $this->assertSame(5,$xp->query('./w:tr',$tables->item(0))->length);
                if($format==='A4')$this->assertSame(5,$xp->query('./w:tr',$tables->item(1))->length);
                foreach (range(0, $format === 'A4' ? 1 : 0) as $tableIndex) {
                    $names=[];
                    foreach ($xp->query('./w:tr[position()>2]/w:tc[2]', $tables->item($tableIndex)) as $cell) $names[]=trim($cell->textContent);
                    $this->assertSame(['Testname1','Testname2','Testname3'], $names);
                }
                $this->assertStringNotContainsString('${nachname',$doc->textContent);
                $this->assertSame(file_get_contents(public_path('img/bop/kooperationspartner.png')),$zip->getFromName('word/media/bop-attendance-footer.png'));
            } finally {$zip->close();unlink($path);}
        }
    }
}
