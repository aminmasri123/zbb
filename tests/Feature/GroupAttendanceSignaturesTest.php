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
        $this->deleteJson(route('gruppe.signatures.destroy', $group))->assertForbidden();
        if ($type === 'pa') $this->assertDatabaseHas('pa_attendance_signature_versions', ['person_id' => $people[0]->id, 'action' => 'captured']);
    }

    public static function types(): array { return [['bibb'], ['pa']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('denials')]
    public function test_access_is_enforced_on_every_endpoint(string $reason): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        if ($reason === 'permission') $user->revokePermissionTo('anwesenheit.manage');
        if ($reason === 'area') $group->projekt->update(['name'=>'BvB Reha']);
        if ($reason === 'role') {
            $role = Role::firstOrCreate(['name' => 'Anleiter', 'guard_name' => 'web'], ['color' => '#123456']);
            $user->syncRoles([$role]);
        }
        if ($reason === 'project') {
            $other = Projekt::factory()->create();
            $user->projekte()->attach($other->id);
            $user->update(['current_team_id' => $other->id]);
        }
        $scope = ['type' => 'bibb', 'draft_id' => $draft->id, 'date' => '2026-09-01'];
        $this->actingAs($user)->getJson(route('gruppe.signatures.index', $group))->assertForbidden();
        $this->getJson(route('gruppe.signatures.show', $group).'?'.http_build_query($scope))->assertForbidden();
        $this->getJson(route('gruppe.signatures.overview', $group).'?'.http_build_query($scope))->assertForbidden();
        $field = $scope + ['key' => 'program-2026-09-01:'.$people[0]->id, 'hash' => str_repeat('a', 64), 'expected_hash' => str_repeat('a', 64), 'removal_id' => 1];
        $this->getJson(route('gruppe.signatures.image', $group).'?'.http_build_query($field))->assertForbidden();
        $this->grantTestPermission($user, 'anwesenheit.destroy');
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $field)->assertForbidden();
        $this->postJson(route('gruppe.signatures.restore', $group), $field)->assertForbidden();
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'program-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertForbidden();
        $this->assertSame(1, $draft->fresh()->revision);
    }

    public static function denials(): array { return [['permission'], ['role'], ['project'], ['area']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('managementRoles')]
    public function test_department_management_can_collect_without_being_the_assigned_instructor(string $roleName): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['color' => '#123456']);
        RoleDataAccessSetting::updateOrCreate(['role_id' => $role->id], ['team_scope' => 'own_projects', 'participant_scope' => 'own_projects']);
        $user->syncRoles([$role]);
        $group->update(['personen_id' => User::factory()->create()->person_id]);
        $this->assertTrue(app(\App\Services\Bop\GroupAttendanceSignatures::class)->allowed($user, $group));
        $scope = ['type' => 'bibb', 'draft_id' => $draft->id, 'date' => '2026-09-01'];
        $this->actingAs($user)->getJson(route('gruppe.signatures.index', $group))->assertOk()->assertJsonCount(1, 'lists');
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['key' => 'program-2026-09-01:'.$people[0]->id, 'signature' => self::PNG])->assertOk();
    }

    public static function managementRoles(): array
    {
        return [['Administrator'], ['Abteilungsleitung'], ['Assistenz der Abt.-Leitung']];
    }

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

    #[\PHPUnit\Framework\Attributes\DataProvider('types')]
    public function test_all_group_days_have_scoped_metadata_and_separately_authorized_images(string $type): void
    {
        [$user, $group, $draft, $people] = $this->context($type);
        $this->addDay($group, '2026-09-02');
        $this->addDay($group, '2026-09-03'); // No central signature slot yet; still show this group date.
        $payload = $draft->payload;
        $payload['days'][] = ['id' => 'program-2026-09-02', 'date' => '2026-09-02', 'type' => $type === 'pa' ? 'pa_day' : 'program_day'];
        $key = 'program-2026-09-01:'.$people[0]->id;
        $payload['signatures'][$key] = 'enc:v1:'.Crypt::encryptString(self::PNG);
        $draft->update(['payload' => $payload]);
        $scope = ['type' => $type, 'draft_id' => $draft->id];
        $response = $this->actingAs($user)->getJson(route('gruppe.signatures.overview', $group).'?'.http_build_query($scope))
            ->assertOk()->assertJsonCount(2, 'rows')->assertJsonCount(1, 'participants')
            ->assertJsonPath('dates', ['2026-09-01', '2026-09-02', '2026-09-03'])->assertJsonPath('can_remove', false)
            ->assertJsonPath('rows.0.signed', true)->assertJsonPath('rows.1.signed', false);
        $this->assertStringNotContainsString('base64', $response->getContent());
        $this->assertStringNotContainsString('Foreign', $response->getContent());
        $row = $response->json('rows.0');
        $this->assertSame(hash('sha256', $payload['signatures'][$key]), $row['expected_hash']);
        $image = $this->get($row['signature_url'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(base64_decode(substr(self::PNG, 22)), $image->getContent());
        $field = $scope + ['date' => '2026-09-01', 'key' => $key, 'hash' => $row['expected_hash']];
        foreach ([['key' => 'program-2026-09-01:'.$people[1]->id], ['date' => '2026-09-03']] as $wrong) {
            $this->getJson(route('gruppe.signatures.image', $group).'?'.http_build_query(array_replace($field, $wrong)))->assertForbidden();
        }
        $this->getJson(route('gruppe.signatures.image', $group).'?'.http_build_query(array_replace($field, ['hash' => str_repeat('a', 64)])))->assertNotFound();
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $field + ['expected_hash' => $row['expected_hash']])->assertForbidden();
        $this->postJson(route('gruppe.signatures.restore', $group), $field + ['removal_id' => 1])->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('types')]
    public function test_removal_is_recoverable_scoped_and_visible_in_the_central_list(string $type): void
    {
        [$user, $group, $draft, $people] = $this->context($type);
        $this->grantTestPermission($user, 'anwesenheit.destroy');
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $key = 'program-2026-09-01:'.$people[0]->id;
        $payload = $draft->payload;
        $payload['signatures'][$key] = 'enc:v1:'.Crypt::encryptString(self::PNG);
        $draft->update(['payload' => $payload]);
        $scope = ['type' => $type, 'draft_id' => $draft->id, 'date' => '2026-09-01', 'key' => $key];
        $field = $scope + ['expected_hash' => hash('sha256', $payload['signatures'][$key])];
        $this->actingAs($user);
        foreach ([['key' => 'program-2026-09-01:'.$people[1]->id], ['date' => '2026-09-02']] as $wrong) {
            $this->deleteJson(route('gruppe.signatures.destroy', $group), array_replace($field, $wrong))->assertForbidden();
        }
        $this->deleteJson(route('gruppe.signatures.destroy', $group), array_replace($field, ['expected_hash' => str_repeat('a', 64)]))->assertConflict();
        $this->assertDatabaseCount('group_attendance_signature_removals', 0);
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $field)->assertOk();
        $removedPayload = $draft->fresh()->payload;
        $expectedPayload = $payload;
        unset($expectedPayload['signatures'][$key]);
        $this->assertSame($expectedPayload, $removedPayload);
        $this->assertSame(2, $draft->fresh()->revision);
        $removal = \App\Models\GroupAttendanceSignatureRemoval::firstOrFail();
        $this->assertSame(self::PNG, Crypt::decryptString(substr($removal->signature_ciphertext, 7)));
        $this->assertSame($user->id, $removal->removed_by);
        $this->assertArrayNotHasKey('signature_ciphertext', $removal->toArray());
        $overview = $this->getJson(route('gruppe.signatures.overview', $group).'?'.http_build_query($scope))->assertOk()
            ->assertJsonPath('can_remove', true)->assertJsonPath('rows.0.signed', false)->assertJsonPath('rows.0.removal_id', $removal->id);
        $centralScope = $type === 'pa'
            ? ['schuleId' => $draft->partner_id, 'schuljahr' => '2026', 'teil' => '1']
            : ['schuleIdInputBibb' => $draft->partner_id, 'schuljahrInputBibb' => '2026', 'teilInputBibb' => '1'];
        $centralRoute = $type === 'pa' ? 'anwesenheitsliste.PA.digital.draft.show' : 'anwesenheitsliste.POBO.bibb.draft.show';
        $central = $this->postJson(route($centralRoute), $centralScope)->assertOk();
        $this->assertEmpty($central->json('payload.signatures.'.$key));
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $field)->assertConflict();
        $restore = $scope + ['removal_id' => $removal->id];
        $this->postJson(route('gruppe.signatures.restore', $group), array_replace($restore, ['removal_id' => $removal->id + 1]))->assertNotFound();
        $this->postJson(route('gruppe.signatures.restore', $group), array_replace($restore, ['date' => '2026-09-02']))->assertForbidden();
        $this->postJson(route('gruppe.signatures.restore', $group), $restore)->assertOk();
        $this->assertSame(3, $draft->fresh()->revision);
        $this->assertSame(self::PNG, Crypt::decryptString(substr($draft->fresh()->payload['signatures'][$key], 7)));
        $this->assertNotNull($removal->fresh()->restored_at);
        $this->postJson(route($centralRoute), $centralScope)->assertOk()->assertJsonPath('payload.signatures.'.$key, self::PNG);
        $this->postJson(route('gruppe.signatures.restore', $group), $restore)->assertConflict();
        // A stale screen cannot remove a restored/new capture of the same signature.
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $field)->assertConflict();
        if ($type === 'pa') {
            $this->assertSame(['imported', 'deleted', 'restored'], \App\Models\PaAttendanceSignatureVersion::orderBy('id')->pluck('action')->all());
            $this->assertNotEmpty(\App\Models\PaAttendanceSignatureVersion::first()->signature_ciphertext);
        }
    }

    public function test_restoring_never_overwrites_a_new_capture(): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        $this->grantTestPermission($user, 'anwesenheit.destroy');
        $key = 'program-2026-09-01:'.$people[0]->id;
        $scope = ['type' => 'bibb', 'draft_id' => $draft->id, 'date' => '2026-09-01', 'key' => $key];
        $this->actingAs($user)->postJson(route('gruppe.signatures.store', $group), $scope + ['signature' => self::PNG])->assertOk();
        $hash = hash('sha256', $draft->fresh()->payload['signatures'][$key]);
        $this->deleteJson(route('gruppe.signatures.destroy', $group), $scope + ['expected_hash' => $hash])->assertOk();
        $this->postJson(route('gruppe.signatures.store', $group), $scope + ['signature' => self::PNG])->assertOk();
        $before = $draft->fresh()->payload;
        $this->postJson(route('gruppe.signatures.restore', $group), $scope + ['removal_id' => \App\Models\GroupAttendanceSignatureRemoval::first()->id])->assertConflict();
        $this->assertSame($before, $draft->fresh()->payload);
    }

    private function addDay(Gruppe $group, string $date): void
    {
        $membership = GruppeHasPersonen::where('gruppe_id', $group->id)->firstOrFail()->replicate();
        $membership->tage_id = Tage::create(['datum' => $date, 'wochentag' => 'Mittwoch'])->id;
        $membership->save();
    }

    public function test_weekends_are_hidden_by_default_and_can_be_shown_without_changing_signatures(): void
    {
        [$user, $group, $draft, $people] = $this->context('bibb');
        $group->update(['enddatum' => '2026-09-06']);
        $payload = $draft->payload;
        foreach (['2026-09-05', '2026-09-06'] as $date) {
            $this->addDay($group, $date);
            $payload['days'][] = ['id' => 'program-'.$date, 'date' => $date, 'type' => 'program_day'];
            $payload['signatures']['program-'.$date.':'.$people[0]->id] = 'enc:v1:'.Crypt::encryptString(self::PNG);
        }
        $draft->update(['payload' => $payload]);
        $url = route('gruppe.signatures.overview', $group).'?'.http_build_query(['type' => 'bibb', 'draft_id' => $draft->id]);
        $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('dates', ['2026-09-01'])
            ->assertJsonPath('weekends_hidden', true)->assertJsonCount(1, 'rows');
        $group->projekt->update(['rule_settings' => ['group_signatures_hide_weekends' => false]]);
        $this->getJson($url)->assertOk()->assertJsonPath('dates', ['2026-09-01', '2026-09-05', '2026-09-06'])
            ->assertJsonPath('weekends_hidden', false)->assertJsonCount(3, 'rows')->assertJsonPath('rows.1.signed', true);
        $this->assertSame($payload, $draft->fresh()->payload);
        $this->assertSame(1, $draft->fresh()->revision);
    }

    public function test_removed_legacy_pa_key_remains_available_for_restore(): void
    {
        [$user, $group, $draft, $people] = $this->context('pa');
        $this->grantTestPermission($user, 'anwesenheit.destroy');
        $payload = $draft->payload;
        $key = 'legacy-pa-2026-09-01:'.$people[0]->id;
        $payload['signatures'][$key] = 'enc:v1:'.Crypt::encryptString(self::PNG);
        $draft->update(['payload' => $payload]);
        $scope = ['type' => 'pa', 'draft_id' => $draft->id, 'date' => '2026-09-01', 'key' => $key];
        $this->actingAs($user)->deleteJson(route('gruppe.signatures.destroy', $group), $scope + ['expected_hash' => hash('sha256', $payload['signatures'][$key])])->assertOk();
        $row = $this->getJson(route('gruppe.signatures.overview', $group).'?'.http_build_query($scope))->assertOk()
            ->assertJsonPath('rows.0.key', $key)->assertJsonPath('rows.0.signed', false)->json('rows.0');
        $this->assertNotNull($row['removal_id']);
        $this->postJson(route('gruppe.signatures.restore', $group), $scope + ['removal_id' => $row['removal_id']])->assertOk();
        $this->assertSame(self::PNG, Crypt::decryptString(substr($draft->fresh()->payload['signatures'][$key], 7)));
    }

    private function context(string $type): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $role = Role::firstOrCreate(['name' => 'Assistenz der Abt.-Leitung', 'guard_name' => 'web'], ['color' => '#123456']);
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
