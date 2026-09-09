<?php

namespace Tests\Feature;

use App\Models\{ParticipationCareerGoal, Personen, Projekt, ProjektHasPersonen, Role, Standort, User};
use App\Services\Participants\CareerGoalLuvSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationCareerGoalTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Berufsbegleiter', 'color' => '#123456', 'guard_name' => 'web']);
        $user->assignRole($role);
        $project = Projekt::factory()->create(['name' => 'BvB Reha']);
        $location = Standort::factory()->create();
        $user->projekte()->attach($project, ['standort_id' => $location->id, 'status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);
        $person = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = ProjektHasPersonen::create(['personen_id' => $person->id, 'projekt_id' => $project->id, 'standort_id' => $location->id, 'status' => 'aktiv']);
        $this->grantTestPermission($user, 'teilnehmer.index');
        $this->grantTestPermission($user, 'teilnehmer.update');
        $this->actingAs($user);
        return [$user, $participation];
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['previous_id' => null, 'target' => 'training', 'occupation' => 'Fachlagerist',
            'alternatives' => 'Verkäufer', 'notes' => 'Wohnortnah', 'agreement_status' => 'wish', 'documented_on' => '2026-08-01'], $overrides);
    }

    public function test_versions_are_preserved_and_concurrent_edits_are_rejected(): void
    {
        [$user, $p] = $this->context();
        $url = route('teilnehmer.career-goal.store', $p->id);
        $id = $this->postJson($url, $this->data())->assertOk()->assertJsonPath('history.0.author_name', $user->name)->json('history.0.id');
        $this->postJson($url, $this->data(['previous_id' => $id, 'target' => 'employment', 'occupation' => 'Lagerhelfer', 'agreement_status' => 'agreed', 'documented_on' => '2026-09-01']))->assertOk()->assertJsonCount(2, 'history');
        $this->postJson($url, $this->data(['previous_id' => $id]))->assertConflict();
        $this->assertDatabaseHas('participation_career_goals', ['id' => $id, 'target' => 'training', 'agreement_status' => 'wish']);
        $this->assertDatabaseCount('participation_career_goals', 2);
        $this->assertDatabaseCount('projekt_has_teilnehmer_luvs', 0);
    }

    public function test_permission_project_and_location_boundaries_are_enforced(): void
    {
        [$user, $p] = $this->context();
        $url = route('teilnehmer.career-goal.store', $p->id);
        $user->revokePermissionTo('teilnehmer.update');
        $this->postJson($url, $this->data())->assertForbidden();
        $this->getJson(route('teilnehmer.career-goal.index', $p->id))->assertOk();
        $this->grantTestPermission($user, 'teilnehmer.update');
        $other = Projekt::factory()->create();
        $foreign = ProjektHasPersonen::create(['personen_id' => $p->personen_id, 'projekt_id' => $other->id]);
        $this->postJson(route('teilnehmer.career-goal.store', $foreign->id), $this->data())->assertNotFound();
        $p->update(['standort_id' => Standort::factory()->create()->id]);
        $this->postJson($url, $this->data())->assertForbidden();
        $this->getJson(route('teilnehmer.career-goal.index', $p->id))->assertForbidden();
        $this->assertDatabaseCount('participation_career_goals', 0);
    }

    public function test_target_validation_and_open_goal(): void
    {
        [, $p] = $this->context();
        $url = route('teilnehmer.career-goal.store', $p->id);
        $this->postJson($url, $this->data(['occupation' => '']))->assertUnprocessable();
        $this->postJson($url, $this->data(['target' => 'invented']))->assertUnprocessable();
        $this->postJson($url, $this->data(['documented_on' => '2099-01-01']))->assertUnprocessable();
        $this->postJson($url, $this->data(['target' => 'undecided', 'occupation' => null]))->assertOk();
    }

    public function test_luv_uses_historical_project_goal_and_preserves_wish_status(): void
    {
        [, $p] = $this->context();
        $url = route('teilnehmer.career-goal.store', $p->id);
        $id = $this->postJson($url, $this->data())->assertOk()->json('history.0.id');
        $this->postJson($url, $this->data(['previous_id' => $id, 'target' => 'employment', 'occupation' => 'Lagerhelfer', 'agreement_status' => 'agreed', 'documented_on' => '2026-09-01']))->assertOk();
        $service = app(CareerGoalLuvSource::class);
        $old = $service->source($p->id, '2026-08-31');
        $this->assertStringContainsString('Berufswunsch', $old['text']);
        $this->assertStringContainsString('Fachlagerist', $old['text']);
        $this->assertStringNotContainsString('Lagerhelfer', $old['text']);
        $this->assertStringContainsString('vereinbartes Eingliederungsziel', $service->source($p->id, '2026-09-02')['text']);
        $this->assertNull($service->source($p->id, '2026-07-01'));
        $this->assertNull($service->source($p->id + 1000, '2026-09-02'));
        $defaults=app(\App\Services\LuvAssessmentDefaults::class);
        foreach (['Start','Verlauf'] as $type) {
            $manual=$defaults->fields($p,$type,'2026-09-02');
            $this->assertSame($service->source($p->id,'2026-09-02')['text'],$manual['fields']['integration.goal']);
            $this->assertSame('agreed',$manual['sources']['integration.goal'][0]['status']);
            $this->assertStringContainsString('Verkäufer',$manual['fields']['integration.goal']);
            $this->assertStringContainsString('Wohnortnah',$manual['fields']['integration.goal']);
        }
        $this->assertSame($old['text'],$defaults->fields($p,'Start','2026-08-31')['fields']['integration.goal']);
        $this->assertArrayNotHasKey('integration.goal',$defaults->fields($p,'Abschluss','2026-09-02')['fields']);
        $this->assertArrayNotHasKey('integration.goal',$defaults->fields($p,'Start','2026-07-01')['fields']);
        $tools = [['tool_name' => 'get_participant_identity_summary', 'content' => ['career_goal' => $old]]];
        $report = ['report_type' => 'luv', 'sections' => [['heading' => '[integration.goal] Ziel', 'claims' => [['text' => 'Erfunden']]]]];
        $merged = $service->merge($report, $tools);
        $this->assertCount(1, $merged['sections']);
        $this->assertSame($old['text'], $merged['sections'][0]['claims'][0]['text']);
        $this->assertSame([$old['source_id']], $merged['sections'][0]['claims'][0]['source_ids']);
        $this->assertSame($report, $service->merge($report, []));
        $final = ['report_type' => 'final', 'sections' => []];
        $this->assertSame($final, $service->merge($final, $tools));
    }

    public function test_luv_explains_latest_change_and_ignores_unchanged_resaves(): void
    {
        [, $p] = $this->context();
        $url=route('teilnehmer.career-goal.store',$p->id);
        $first=$this->postJson($url,$this->data())->assertOk()->json('history.0.id');
        $changed=$this->data(['previous_id'=>$first,'target'=>'employment','occupation'=>'Gärtner','agreement_status'=>'agreed','documented_on'=>'2026-09-01']);
        $second=$this->postJson($url,$changed)->assertOk()->json('history.0.id');
        $this->postJson($url,[...$changed,'previous_id'=>$second,'documented_on'=>'2026-09-02'])->assertOk();
        $service=app(CareerGoalLuvSource::class);
        $before=$service->source($p->id,'2026-08-31')['text'];
        $this->assertStringNotContainsString('Änderung dokumentiert',$before);
        $this->assertStringNotContainsString('Gärtner',$before);
        $after=$service->source($p->id,'2026-09-03')['text'];
        $this->assertStringContainsString('Beschäftigung als Gärtner',$after);
        $this->assertStringContainsString('Ausbildung als Fachlagerist',$after);
        $this->assertStringContainsString('noch nicht vereinbarter Berufswunsch',$after);
        $this->assertStringContainsString('Änderung dokumentiert am 01.09.2026 gegenüber dem Stand vom 01.08.2026',$after);
        $this->assertStringNotContainsString('Änderung dokumentiert am 02.09.2026',$after);
        $this->assertSame($after,app(\App\Services\LuvAssessmentDefaults::class)->fields($p,'Verlauf','2026-09-03')['fields']['integration.goal']);
    }

    public function test_same_day_status_and_alternative_changes_are_reported_without_inventing_a_new_occupation(): void
    {
        [, $p] = $this->context();
        $url=route('teilnehmer.career-goal.store',$p->id);
        $id=$this->postJson($url,$this->data())->assertOk()->json('history.0.id');
        $this->postJson($url,$this->data(['previous_id'=>$id,'agreement_status'=>'agreed','alternatives'=>'','notes'=>'Neue Ergänzung']))->assertOk();
        $text=app(CareerGoalLuvSource::class)->source($p->id,'2026-08-01')['text'];
        $this->assertStringContainsString('bisherige Berufswunsch',$text);
        $this->assertStringContainsString('Alternativen: zuvor „Verkäufer“, jetzt „keine Angaben“',$text);
        $this->assertStringContainsString('Ergänzende Angaben: zuvor „Wohnortnah“, jetzt „Neue Ergänzung“',$text);
        $this->assertStringNotContainsString('Zuvor war „',$text);
    }
}
