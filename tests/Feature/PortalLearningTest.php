<?php
namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\PortalCourse;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseLesson;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_lesson_enrollment_capacity_and_progress_are_project_participation_bound(): void
    {
        $staff=User::factory()->create();$this->givePermission($staff,'projekt.update');
        app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$staff->id);
        $location=Standort::factory()->create();
        $project=Projekt::factory()->create(['portal_feature_settings'=>['learning'=>true]]);
        $this->assign($project,$staff->person,$location);$staff->update(['current_team_id'=>$project->id]);
        $participant=Personen::factory()->create(['typ'=>'teilnehmer']);$participation=$this->assign($project,$participant,$location);
        $portalUser=User::factory()->create(['person_id'=>$participant->id]);

        $courseResponse=$this->actingAs($staff)->postJson(route('projekt.courses.store',$project),[
            'title'=>'Bewerbungstraining','description'=>'Schritt für Schritt','status'=>'published','starts_at'=>'2026-07-20','ends_at'=>'2026-08-20','capacity'=>1,'self_enrollment'=>true,
        ])->assertCreated()->assertJsonPath('course.project_id',$project->id);
        $course=PortalCourse::findOrFail($courseResponse->json('course.id'));
        $lessonResponse=$this->postJson(route('projekt.courses.lessons.store',$course),[
            'title'=>'Lebenslauf','content'=>'Erstellen Sie einen vollständigen Lebenslauf.','sort_order'=>0,'published'=>true,
        ])->assertCreated();
        $lesson=PortalCourseLesson::findOrFail($lessonResponse->json('lesson.id'));

        $this->actingAs($portalUser)->get(route('participant-portal.learning.index'))->assertOk()->assertInertia(fn($page)=>$page->has('courses',1)->where('courses.0.title','Bewerbungstraining'));
        $enrollmentResponse=$this->postJson(route('participant-portal.learning.enroll',$course),['project_person_id'=>$participation->id])->assertCreated();
        $enrollment=PortalCourseEnrollment::findOrFail($enrollmentResponse->json('enrollment.id'));
        $this->putJson(route('participant-portal.learning.progress.update',[$enrollment,$lesson]),['completed'=>true])->assertOk()->assertJsonPath('enrollment.status','completed');
        $this->assertDatabaseHas('portal_lesson_progress',['enrollment_id'=>$enrollment->id,'lesson_id'=>$lesson->id,'completed'=>true]);

        $other=Personen::factory()->create(['typ'=>'teilnehmer']);$otherParticipation=$this->assign($project,$other,$location);$otherUser=User::factory()->create(['person_id'=>$other->id]);
        $this->actingAs($otherUser)->postJson(route('participant-portal.learning.enroll',$course),['project_person_id'=>$otherParticipation->id])->assertUnprocessable();

        $foreign=Projekt::factory()->create(['portal_feature_settings'=>['learning'=>true]]);$foreignParticipation=$this->assign($foreign,$participant,$location);
        $this->actingAs($portalUser)->postJson(route('participant-portal.learning.enroll',$course),['project_person_id'=>$foreignParticipation->id])->assertNotFound();
    }

    private function assign(Projekt $project,Personen $person,Standort $location): ProjektHasPersonen{return ProjektHasPersonen::query()->create(['projekt_id'=>$project->id,'personen_id'=>$person->id,'standort_id'=>$location->id,'status'=>'aktiv']);}
    private function givePermission(User $user,string $name):void{$category=Berechtigungskategorie::query()->firstOrCreate(['name'=>'Kurse'],['beschreibung'=>'']);Permission::query()->updateOrCreate(['name'=>$name,'guard_name'=>'web'],['berechtigungskategorie_id'=>$category->id,'beschreibung'=>null]);app(PermissionRegistrar::class)->forgetCachedPermissions();$user->givePermissionTo($name);}
}
