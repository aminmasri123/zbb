<?php

namespace Tests\Feature;

use App\Models\{Anwesenheitsstatuten, Bereich, BibbAttendanceListDraft, Gruppe, GruppeHasPersonen, PaAttendanceListDraft, Partner, Personen, PersonenIstSchueler, Projekt, Raeume, Role, RoleDataAccessSetting, Standort, Tage, User, Zeiten};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class GroupAttendanceSignaturesTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';

    #[\PHPUnit\Framework\Attributes\DataProvider('types')]
    public function test_group_capture_is_shared_additive_and_limited_to_members(string $type): void
    {
        [$user, $group, $draft, $people] = $this->context($type);
        $scope = ['type' => $type, 'draft_id' => $draft->id, 'date' => '2026-09-01'];
        $key = 'program-2026-09-01:'.$people[0]->id;
        $before = $draft->payload;
        $this->actingAs($user)->getJson(route('gruppe.signatures.index', $group))->assertOk()->assertJsonCount(1, 'lists');
        $response = $this->getJson(route('gruppe.signatures.show', $group).'?'.http_build_query($scope))->assertOk()->assertJsonCount(1, 'rows');
        $this->assertSame($people[0]->id, $response->json('rows.0.person_id'));
        $this->assertStringNotContainsString('Foreign', $response->getContent());
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => $key, 'signature' => self::PNG])->assertOk();
        $after = $draft->fresh()->payload;
        $this->assertSame(self::PNG, Crypt::decryptString(substr($after['signatures'][$key], 7)));
        unset($after['signatures'][$key]);
        $this->assertSame($before, $after);
        $this->getJson(route('gruppe.signatures.show', $group).'?'.http_build_query($scope))->assertOk()->assertJsonPath('rows.0.signature', self::PNG);
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => $key, 'signature' => self::PNG])->assertOk();
        $this->assertSame(2, $draft->fresh()->revision);
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $centralScope = $type === 'pa'
            ? ['schuleId' => $draft->partner_id, 'schuljahr' => '2026', 'teil' => '1']
            : ['schuleIdInputBibb' => $draft->partner_id, 'schuljahrInputBibb' => '2026', 'teilInputBibb' => '1'];
        $this->postJson(route($type === 'pa' ? 'anwesenheitsliste.PA.digital.draft.show' : 'anwesenheitsliste.POBO.bibb.draft.show'), $centralScope)
            ->assertOk()->assertJsonPath('payload.signatures.'.$key, self::PNG);
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'program-2026-09-01:'.$people[1]->id, 'signature' => self::PNG])->assertForbidden();
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => $key, 'signature' => ''])->assertUnprocessable();
        $this->deleteJson(route('gruppe.signatures.store', $group))->assertStatus(405);
        if ($type === 'pa') $this->assertDatabaseHas('pa_attendance_signature_versions', ['person_id' => $people[0]->id, 'action' => 'captured']);
    }

    public static function types(): array { return [['bibb'], ['pa']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('denials')]
    public function test_access_is_enforced_on_every_endpoint(string $reason): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        if ($reason === 'permission') $user->revokePermissionTo('anwesenheit.manage');
        if ($reason === 'trainer') $group->update(['personen_id' => User::factory()->create()->person_id]);
        if ($reason === 'project') {
            $other = Projekt::factory()->create();
            $user->projekte()->attach($other->id);
            $user->update(['current_team_id' => $other->id]);
        }
        $scope = ['type' => 'bibb', 'draft_id' => $draft->id, 'date' => '2026-09-01'];
        $this->actingAs($user)->getJson(route('gruppe.signatures.index', $group))->assertForbidden();
        $this->getJson(route('gruppe.signatures.show', $group).'?'.http_build_query($scope))->assertForbidden();
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'program-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertForbidden();
        $this->assertSame(1, $draft->fresh()->revision);
    }

    public static function denials(): array { return [['permission'], ['trainer'], ['project']]; }

    public function test_an_existing_signature_cannot_be_replaced(): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        $key = 'program-2026-09-01:'.$people[0]->id;
        $payload = $draft->payload;
        $payload['signatures'][$key] = 'enc:v1:'.Crypt::encryptString('existing-protected-signature');
        $draft->update(['payload' => $payload]);
        $this->actingAs($user)->postJson(route('gruppe.signatures.store', $group), [
            'type' => 'bibb', 'draft_id' => $draft->id, 'date' => '2026-09-01', 'key' => $key, 'signature' => self::PNG,
        ])->assertStatus(409);
        $this->assertSame($payload, $draft->fresh()->payload);
    }

    public function test_pa_class_schedule_and_participant_visibility_are_respected(): void
    {
        [$user, $group, $draft, $people] = $this->context('pa');
        $payload = $draft->payload;
        $payload['classSchedules']['7.1'] = ['days' => [['id' => 'class-day-2026-09-01', 'date' => '2026-09-01', 'type' => 'pa_day']]];
        $draft->update(['payload' => $payload]);
        $scope = ['type' => 'pa', 'draft_id' => $draft->id, 'date' => '2026-09-01'];
        $this->actingAs($user)->getJson(route('gruppe.signatures.show', $group).'?'.http_build_query($scope))
            ->assertOk()->assertJsonPath('rows.0.key', 'class-day-2026-09-01:'.$people[0]->id);
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'program-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertForbidden();
        $this->postJson(route('gruppe.signatures.store', $group), array_replace($scope, ['date' => '2026-09-02']) + ['key' => 'class-day-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertForbidden();
        RoleDataAccessSetting::where('role_id', $user->roles->first()->id)->update(['participant_scope' => 'none']);
        $this->getJson(route('gruppe.signatures.index', $group))->assertOk()->assertJsonCount(0, 'lists');
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'class-day-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertForbidden();
    }

    private function context(string $type): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $role = Role::firstOrCreate(['name' => 'Group signature test', 'guard_name' => 'web'], ['color' => '#123456']);
        RoleDataAccessSetting::updateOrCreate(['role_id' => $role->id], ['team_scope' => 'own_projects', 'participant_scope' => 'own_projects']);
        $user->assignRole($role);
        $this->grantTestPermission($user, 'anwesenheit.manage');
        $project->partners()->attach($school->id);
        $location = Standort::factory()->create();
        $room = Raeume::create(['name' => 'Raum', 'standort_id' => $location->id, 'typ' => 'Werkstatt']);
        $area = Bereich::create(['name' => 'Bereich']);
        $group = Gruppe::create(['personen_id' => $user->person_id, 'projekt_id' => $project->id,
            'bereich_id' => $area->id, 'partner_id' => $school->id, 'standort_id' => $location->id, 'raum_id' => $room->id,
            'anfangsdatum' => '2026-09-01', 'enddatum' => '2026-09-03']);
        $day = Tage::create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::create(['status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e']);
        $people = [];
        foreach (['Member', 'Foreign'] as $i => $name) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'nachname' => $name]);
            $project->teilnehmer()->attach($person->id);
            PersonenIstSchueler::create(['person_id' => $person->id, 'schule_id' => $school->id, 'schuljahr' => '2026', 'teil' => '1', 'klasse' => '7.1']);
            if ($i === 0) GruppeHasPersonen::create(['personen_id' => $person->id, 'user_id' => $user->id, 'gruppe_id' => $group->id,
                'tage_id' => $day->id, 'zeitgeplant_id' => $time->id, 'zeittatsaechlich_id' => $time->id, 'anwesenheitsstatuten_id' => $status->id]);
            $people[] = $person;
        }
        $model = $type === 'bibb' ? BibbAttendanceListDraft::class : PaAttendanceListDraft::class;
        $hashParts = [$project->id, $school->id, '2026', '1', ...($type === 'pa' ? ['alle', '', 'pa', 'pa-attendance-list'] : ['bibb-attendance-list'])];
        $draft = $model::create(['draft_hash' => hash('sha256', implode('|', $hashParts)), 'projekt_id' => $project->id, 'partner_id' => $school->id,
            'schuljahr' => '2026', 'teil' => '1', 'revision' => 1, 'payload' => [
                'form' => ['keep' => 'unchanged'], 'days' => [['id' => 'program-2026-09-01', 'date' => '2026-09-01', 'type' => $type === 'pa' ? 'pa_day' : 'program_day']],
                'signatures' => ['program-2026-09-01:'.$people[1]->id => 'enc:v1:'.Crypt::encryptString('foreign-private-signature')],
            ]]);
        return [$user, $group, $draft, $people];
    }
}
