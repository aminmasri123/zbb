<?php
namespace Tests\Feature;
use App\Models\Berechtigungskategorie;use App\Models\ParticipantPortalDocument;use App\Models\Personen;use App\Models\Projekt;use App\Models\ProjektHasPersonen;use App\Models\Role;use App\Models\RoleDataAccessSetting;use App\Models\Standort;use App\Models\SystemModule;use App\Models\User;use App\Services\Modules\ModuleStateResolver;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Http\UploadedFile;use Illuminate\Support\Facades\Storage;use Spatie\Permission\Models\Permission;use Spatie\Permission\PermissionRegistrar;use Tests\TestCase;
class ParticipantPortalDocumentTest extends TestCase
{
 use RefreshDatabase;
 public function test_upload_review_download_and_foreign_access_are_participation_bound():void
 {
  Storage::fake('local');$staff=User::factory()->create();$this->grant($staff);app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$staff->id);$location=Standort::factory()->create();$project=Projekt::factory()->create(['portal_feature_settings'=>['profile'=>true]]);$this->assign($project,$staff->person,$location);$staff->update(['current_team_id'=>$project->id]);$participant=Personen::factory()->create(['typ'=>'teilnehmer']);$participation=$this->assign($project,$participant,$location);$portal=User::factory()->create(['person_id'=>$participant->id]);
  $response=$this->actingAs($portal)->postJson(route('participant-portal.documents.store'),['project_person_id'=>$participation->id,'category'=>'cv','file'=>UploadedFile::fake()->create('lebenslauf.pdf',100,'application/pdf')])->assertCreated()->assertJsonPath('document.status','pending');$doc=ParticipantPortalDocument::findOrFail($response->json('document.id'));Storage::disk('local')->assertExists($doc->path);
  $this->actingAs($staff)->putJson(route('teilnehmer.portal-documents.review',$doc),['status'=>'approved','review_note'=>'Dokument geprüft.','visible_to_participant'=>true])->assertOk()->assertJsonPath('document.status','approved');
  $this->actingAs($portal)->get(route('participant-portal.documents.download',$doc))->assertOk();
  $other=Personen::factory()->create(['typ'=>'teilnehmer']);$otherPortal=User::factory()->create(['person_id'=>$other->id]);$this->actingAs($otherPortal)->get(route('participant-portal.documents.download',$doc))->assertNotFound();
  $this->actingAs($portal)->deleteJson(route('participant-portal.documents.destroy',$doc))->assertForbidden();
 }
 private function assign(Projekt $p,Personen $person,Standort $s):ProjektHasPersonen{return ProjektHasPersonen::query()->create(['projekt_id'=>$p->id,'personen_id'=>$person->id,'standort_id'=>$s->id,'status'=>'aktiv']);}
 private function grant(User $u):void{$c=Berechtigungskategorie::query()->firstOrCreate(['name'=>'Portal-Dokumente'],['beschreibung'=>'']);Permission::query()->updateOrCreate(['name'=>'teilnehmer.update','guard_name'=>'web'],['berechtigungskategorie_id'=>$c->id,'beschreibung'=>null]);app(PermissionRegistrar::class)->forgetCachedPermissions();$r=Role::query()->create(['name'=>'Dokument-'.uniqid(),'guard_name'=>'web','color'=>'#123456']);RoleDataAccessSetting::query()->create(['role_id'=>$r->id,'team_scope'=>'own_projects','participant_scope'=>'all']);$u->assignRole($r);$u->givePermissionTo('teilnehmer.update');}
}
