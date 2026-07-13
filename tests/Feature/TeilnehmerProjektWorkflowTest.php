<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeilnehmerProjektWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_be_created_with_project_and_location(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.store');
        $projekt = Projekt::factory()->create();
        $standort = Standort::factory()->create();
        ProjektHasPersonen::query()->create([
            'projekt_id' => $projekt->id,
            'personen_id' => $user->person_id,
            'standort_id' => $standort->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $projekt->id]);

        $response = $this->actingAs($user)->postJson(route('teilnehmer.store'), [
            'vorname' => 'Max',
            'nachname' => 'Muster',
            'geschlecht' => 'm',
            'projekt' => $projekt->id,
            'standort' => $standort->id,
        ]);

        $response->assertCreated();

        $teilnehmer = Personen::query()
            ->where('vorname', 'Max')
            ->where('nachname', 'Muster')
            ->firstOrFail();

        $this->assertSame('teilnehmer', $teilnehmer->typ);
        $this->assertDatabaseHas('projekt_has_personens', [
            'personen_id' => $teilnehmer->id,
            'projekt_id' => $projekt->id,
            'standort_id' => $standort->id,
        ]);
    }

    public function test_specific_project_assignment_permission_can_add_period_without_general_update_permission(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekthasteilnehmer.store');

        [$teilnehmer, $projekt, $standort] = $this->assignmentData();
        $this->activateContext($user, $teilnehmer, $projekt, $standort);

        $response = $this->actingAs($user)->post(route('projekthasteilnehmer.store'), [
            'teilnehmer_id' => $teilnehmer->id,
            'projekt_id' => $projekt->id,
            'standort_id' => $standort->id,
            'model_type' => ProjektHasPersonen::class,
            'antragsdatum' => '2026-07-09',
            'starttermin' => '2026-08-01',
            'endtermin' => '2026-12-31',
        ]);

        $response->assertRedirect();

        $pivot = ProjektHasPersonen::query()
            ->where('personen_id', $teilnehmer->id)
            ->where('projekt_id', $projekt->id)
            ->firstOrFail();

        $this->assertSame($standort->id, $pivot->standort_id);
        $this->assertDatabaseHas('zeitraums', [
            'model_type' => ProjektHasPersonen::class,
            'model_id' => $pivot->id,
            'starttermin' => '2026-08-01 00:00:00',
            'endtermin' => '2026-12-31 00:00:00',
        ]);
    }

    public function test_duplicate_project_assignment_adds_period_without_duplicate_pivot(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekthasteilnehmer.store');

        [$teilnehmer, $projekt, $standort] = $this->assignmentData();
        $this->activateContext($user, $teilnehmer, $projekt, $standort);
        $betreuer = Personen::factory()->create(['typ' => 'mitarbeiter']);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $projekt->id,
            'personen_id' => $betreuer->id,
            'standort_id' => $standort->id,
            'status' => 'aktiv',
        ]);

        $payload = [
            'teilnehmer_id' => $teilnehmer->id,
            'projekt_id' => $projekt->id,
            'standort_id' => $standort->id,
            'model_type' => ProjektHasPersonen::class,
        ];

        $this->actingAs($user)->post(route('projekthasteilnehmer.store'), $payload + [
            'starttermin' => '2026-08-01',
            'endtermin' => '2026-12-31',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('projekthasteilnehmer.store'), $payload + [
            'betreuer' => $betreuer->id,
            'starttermin' => '2027-01-01',
            'endtermin' => '2027-06-30',
        ])->assertRedirect();

        $pivot = ProjektHasPersonen::query()
            ->where('personen_id', $teilnehmer->id)
            ->where('projekt_id', $projekt->id)
            ->firstOrFail();

        $this->assertSame(1, ProjektHasPersonen::query()
            ->where('personen_id', $teilnehmer->id)
            ->where('projekt_id', $projekt->id)
            ->count());

        $this->assertSame(2, $pivot->zeitraume()->count());
        $this->assertSame($betreuer->id, $pivot->fresh()->meta->betreuer_id);
    }

    private function assignmentData(): array
    {
        return [
            Personen::factory()->create(['typ' => 'teilnehmer']),
            Projekt::factory()->create(),
            Standort::factory()->create(),
        ];
    }

    private function activateContext(User $user, Personen $participant, Projekt $project, Standort $location): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Teilnehmer'],
            ['beschreibung' => '']
        )->id;

        Permission::query()->updateOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'web',
            ],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->givePermissionTo($permissionName);
    }
}
