<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\PortalCourse;
use App\Models\PortalCourseEnrollment;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalCourseSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_manages_sessions_and_records_only_enrollments_of_the_course(): void
    {
        [$staff, $portal, $project, $course, $enrollment] = $this->context();

        $this->actingAs($staff)->postJson(route('projekt.courses.sessions.store', $course), [
            'title' => 'Online-Bewerbungstraining', 'starts_at' => now()->addDay()->toISOString(),
            'ends_at' => now()->addDay()->addHours(2)->toISOString(), 'mode' => 'online',
            'online_url' => 'https://meet.example.org/raum', 'published' => true,
        ])->assertCreated()->assertJsonPath('session.mode', 'online');

        $session = $course->sessions()->firstOrFail();
        $this->actingAs($portal)->get(route('participant-portal.learning.sessions.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('ParticipantPortal/CourseSessions')->has('sessions', 1)->where('sessions.0.online_url', 'https://meet.example.org/raum'));

        $this->actingAs($staff)->putJson(route('projekt.courses.sessions.attendance', $session), ['attendance' => [[
            'enrollment_id' => $enrollment->id, 'status' => 'attended', 'attended_minutes' => 120, 'note' => 'Vollständig',
        ]]])->assertOk();
        $this->assertDatabaseHas('portal_course_session_attendance', ['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'attended']);

        $foreignCourse = PortalCourse::query()->create(['project_id' => $project->id, 'created_by_user_id' => $staff->id, 'title' => 'Anderer Kurs', 'status' => 'published']);
        $foreignEnrollment = PortalCourseEnrollment::query()->create(['course_id' => $foreignCourse->id, 'project_person_id' => $enrollment->project_person_id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        $this->actingAs($staff)->putJson(route('projekt.courses.sessions.attendance', $session), ['attendance' => [['enrollment_id' => $foreignEnrollment->id, 'status' => 'attended']]])->assertUnprocessable();
    }

    public function test_session_rules_visibility_and_module_boundary_are_enforced(): void
    {
        [$staff, $portal, $project, $course] = $this->context();

        $base = ['title' => 'Termin', 'starts_at' => now()->addDay()->toISOString(), 'ends_at' => now()->addDay()->addHour()->toISOString(), 'published' => true];
        $this->actingAs($staff)->postJson(route('projekt.courses.sessions.store', $course), $base + ['mode' => 'online'])->assertUnprocessable()->assertJsonValidationErrors('online_url');
        $this->actingAs($staff)->postJson(route('projekt.courses.sessions.store', $course), $base + ['mode' => 'presence'])->assertUnprocessable()->assertJsonValidationErrors('location');

        $course->sessions()->create(array_merge($base, ['mode' => 'presence', 'location' => 'Raum 3', 'published' => false]));
        $this->actingAs($portal)->get(route('participant-portal.learning.sessions.index'))->assertInertia(fn (Assert $page) => $page->has('sessions', 0));

        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_management')->firstOrFail(), false, null, $staff->id);
        $this->actingAs($portal)->get(route('participant-portal.learning.sessions.index'))->assertNotFound();
        $this->actingAs($staff)->postJson(route('projekt.courses.sessions.store', $course), $base + ['mode' => 'presence', 'location' => 'Raum 3'])->assertNotFound();
    }

    private function context(): array
    {
        $staff = User::factory()->create(); $this->grant($staff);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_portal')->firstOrFail(), true, null, $staff->id);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_management')->firstOrFail(), true, null, $staff->id);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create(['portal_feature_settings' => ['learning' => true]]);
        $this->assign($project, $staff->person, $location); $staff->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);
        $portal = User::factory()->create(['person_id' => $participant->id]);
        $course = PortalCourse::query()->create(['project_id' => $project->id, 'created_by_user_id' => $staff->id, 'title' => 'Bewerbungstraining', 'status' => 'published']);
        $enrollment = PortalCourseEnrollment::query()->create(['course_id' => $course->id, 'project_person_id' => $participation->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        return [$staff, $portal, $project, $course, $enrollment];
    }

    private function assign(Projekt $project, Personen $person, Standort $location): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create(['projekt_id' => $project->id, 'personen_id' => $person->id, 'standort_id' => $location->id, 'status' => 'aktiv']);
    }

    private function grant(User $user): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Kurstermine'], ['beschreibung' => '']);
        Permission::query()->updateOrCreate(['name' => 'projekt.update', 'guard_name' => 'web'], ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->create(['name' => 'CourseSessions-'.uniqid(), 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role); $user->givePermissionTo('projekt.update');
    }
}
