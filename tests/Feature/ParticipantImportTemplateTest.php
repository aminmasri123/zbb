<?php
namespace Tests\Feature;

use App\Models\{User,Projekt};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_download_requires_import_permission_not_only_create_permission(): void
    {
        $user=User::factory()->create();
        $project=Projekt::factory()->create(['name'=>'BVB Reha']);
        $user->projekte()->attach($project);$user->update(['current_team_id'=>$project->id]);
        $this->grantTestPermission($user,'teilnehmer.store');
        $this->actingAs($user)->get(route('teilnehmer.import.template'))->assertForbidden();
        $this->grantTestPermission($user,'teilnehmer.import');
        $this->get(route('teilnehmer.import.template'))->assertOk()->assertDownload('Teilnehmerimport_bvb-reha_'.$project->id.'.csv');
    }

    public function test_downloads_follow_the_active_project_and_its_part_setting(): void
    {
        $user=User::factory()->create();$this->grantTestPermission($user,'teilnehmer.import');
        foreach (['BVB Reha','BOP','Coaching'] as $name) {
            $project=Projekt::factory()->create(['name'=>$name]);
            $user->projekte()->attach($project);$user->update(['current_team_id'=>$project->id]);
            $response=$this->actingAs($user)->get(route('teilnehmer.import.template',['project_id'=>999999]))->assertOk();
            $csv=$response->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
            $fields=str_getcsv(trim(substr($csv,3)),';','"','');
            $this->assertContains('Vorname',$fields);$this->assertContains('Nachname',$fields);
            if($name==='BVB Reha') {
                $this->assertCount(14,$fields);
                $this->assertSame('Nachname',$fields[0]);
                $this->assertContains('Schulabschluss bei Übermittlung durch BA',$fields);
            } elseif($name==='BOP') {
                $this->assertContains('Schule_ID',$fields);$this->assertContains('Klasse',$fields);$this->assertContains('Teil',$fields);
            } else {
                $this->assertNotContains('Schule_ID',$fields);$this->assertContains('E-Mail',$fields);
            }
            $this->assertCount(1,preg_split('/\r?\n/',trim($csv)));
        }
    }
}
