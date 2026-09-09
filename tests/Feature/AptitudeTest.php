<?php

namespace Tests\Feature;

use App\Models\{AptitudeAttempt,AptitudeProfile,Gruppe,GruppeHasPersonen,Personen,Projekt,ProjektHasPersonen,Role,RoleDataAccessSetting,Standort,Tage,User,Zeiten,Anwesenheitsstatuten,Bereich};
use App\Services\Aptitude\{AptitudeGroupSetup,AptitudeScoring,BvbAptitudeProfile,AptitudeLuvSources};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Queue};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AptitudeTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->travelTo(now()->setDate(2026,9,9)->startOfDay());
        $user=User::factory()->create();
        foreach (['projekt.update','gruppe.update','gruppe.store','teilnehmer.update','teilnehmer.index'] as $permission) $this->grantTestPermission($user,$permission);
        $role=Role::create(['name'=>'Testfachkraft','guard_name'=>'web','color'=>'#123456']);
        RoleDataAccessSetting::create(['role_id'=>$role->id,'team_scope'=>'own_projects','participant_scope'=>'own_projects']);
        $user->assignRole($role);
        $project=Projekt::factory()->create(['feature_settings'=>['aptitude_tests'=>true]]);
        $location=Standort::factory()->create();
        $user->projekte()->attach($project,['standort_id'=>$location->id]);
        $user->update(['current_team_id'=>$project->id]);
        $person=Personen::factory()->create(['typ'=>'teilnehmer','aktiv'=>1]);
        $participation=ProjektHasPersonen::create(['projekt_id'=>$project->id,'personen_id'=>$person->id,'standort_id'=>$location->id,'status'=>'aktiv']);
        $profile=AptitudeProfile::create(['projekt_id'=>$project->id,'name'=>'BvB Eingangstest','version'=>1,'definition'=>BvbAptitudeProfile::definition()]);
        $area=app(AptitudeGroupSetup::class)->ensureArea($project);
        ProjektHasPersonen::where('projekt_id',$project->id)->where('personen_id',$user->person_id)->firstOrFail()
            ->bereichZuweisungen()->create(['bereich_id'=>$area->id,'is_default'=>false]);
        $project->standorte()->syncWithoutDetaching([$location->id]);
        $group=Gruppe::create(['projekt_id'=>$project->id,'personen_id'=>$user->person_id,'bereich_id'=>$area->id,'raum_id'=>null,'standort_id'=>$location->id,'ort_typ'=>'extern','externer_ort'=>'Testraum','anfangsdatum'=>'2026-09-09','enddatum'=>'2026-09-09','aptitude_profile_id'=>$profile->id]);
        $day=Tage::create(['datum'=>'2026-09-09','wochentag'=>'Mittwoch']);
        $time=Zeiten::create(['startzeit'=>'08:00','endzeit'=>'12:00']);
        $status=Anwesenheitsstatuten::firstOrCreate(['status'=>'anwesend'],['abkuerzung'=>'A','farben'=>'#fff']);
        GruppeHasPersonen::create(['gruppe_id'=>$group->id,'personen_id'=>$person->id,'user_id'=>$user->id,'tage_id'=>$day->id,'zeitgeplant_id'=>$time->id,'anwesenheitsstatuten_id'=>$status->id]);
        $this->actingAs($user);
        return compact('user','project','person','participation','profile','group','area','location');
    }

    public function test_saved_test_texts_prefill_manual_luv_without_ai_permission(): void
    {
        extract($this->context());
        $payload=$this->payload();$payload['status']='started';
        $attempt=$this->postJson(route('aptitude.attempt.save',[$group,$person]),$payload)->assertOk()->json('attempt');
        $params=['teilnehmer_id'=>$person->id,'typ'=>'Start','bis'=>'2026-09-09'];
        $response=$this->getJson(route('projekthasteilnehmer.luv.defaults',$params))->assertOk();
        $fields=$response->json('fields');
        $this->assertStringContainsString($payload['reports']['german']['assessment'],$fields['competence.school.assessment']);
        $this->assertStringContainsString($payload['reports']['math']['assessment'],$fields['competence.school.assessment']);
        $this->assertStringContainsString($payload['reports']['german']['support_need'],$fields['competence.school.support_need']);
        $this->assertSame('started',$response->json('sources')['competence.school.assessment'][0]['status']);
        $progress=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'typ'=>'Verlauf']))->assertOk()->json('fields');
        $this->assertArrayHasKey('development.notes',$progress);
        $this->assertArrayHasKey('competence.school.current_need',$progress);
        $final=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'typ'=>'Abschluss']))->assertOk()->json('fields');
        $this->assertStringContainsString($payload['reports']['german']['support_need'],$final['support.description']);
        $this->assertStringNotContainsString($payload['reports']['german']['assessment'],$final['support.description']);
        $past=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'bis'=>'2026-09-08']))->assertOk()->json('fields');
        $this->assertArrayNotHasKey('competence.school.assessment',$past);
        // A blank newer report must not resurrect an older assessment.
        $copy=AptitudeAttempt::find($attempt['id'])->replicate();$copy->reports=['german'=>[],'math'=>[]];$copy->save();
        $blank=$this->getJson(route('projekthasteilnehmer.luv.defaults',$params))->assertOk()->json('fields');
        $this->assertArrayNotHasKey('competence.school.assessment',$blank);
        $this->actingAs(User::factory()->create())->getJson(route('projekthasteilnehmer.luv.defaults',$params))->assertNotFound();
    }

    public function test_pa_prefill_keeps_approved_fields_separate_and_ignores_unapproved_content(): void
    {
        extract($this->context());
        $project->update(['potenzialanalyse_aktiv'=>true, 'feature_settings'=>['aptitude_tests'=>true,'potential_analysis'=>true], 'participant_profile_settings'=>['enabled_tabs'=>['stammdaten','luv'],'tab_order'=>['stammdaten','luv']]]);
        $this->assertTrue($project->fresh()->supportsLuvPotentialAnalysis());
        \App\Models\PotenzialanalyseBericht::create(['gruppe_id'=>$group->id,'personen_id'=>$person->id,'user_id'=>$user->id,'status'=>'fertig','fertiggestellt_at'=>'2026-09-08','luv_foerderbedarfe'=>[
            'personal'=>['freigegeben'=>true,'status'=>'foerderbedarf','begruendung'=>'Beginnt Aufgaben nach einer Erinnerung.','foerderbedarf'=>'Selbstständigkeit stärken.','freigegeben_am'=>'2026-09-08'],
            'methodical'=>['freigegeben'=>true,'status'=>'kein_foerderbedarf','begruendung'=>'Plant die Arbeitsschritte selbstständig.','freigegeben_am'=>'2026-09-08'],
            'social'=>['freigegeben'=>false,'status'=>'foerderbedarf','begruendung'=>'Ungeprüfte Beobachtung.','foerderbedarf'=>'Nicht freigegebener Text.','freigegeben_am'=>'2026-09-08'],
        ]]);
        $params=['teilnehmer_id'=>$person->id,'typ'=>'Start','bis'=>'2026-09-09'];
        $fields=$this->getJson(route('projekthasteilnehmer.luv.defaults',$params))->assertOk()->json('fields');
        $this->assertStringContainsString('Selbstständigkeit stärken.',$fields['competence.personal.support_need']);
        $this->assertStringContainsString('Beginnt Aufgaben nach einer Erinnerung.',$fields['competence.personal.assessment']);
        $this->assertStringNotContainsString('Beginnt Aufgaben',$fields['competence.personal.support_need']);
        $this->assertStringContainsString('Plant die Arbeitsschritte selbstständig.',$fields['competence.methodical.assessment']);
        $this->assertArrayNotHasKey('competence.social.assessment',$fields);
        $this->assertArrayNotHasKey('competence.social.support_need',$fields);
        $progress=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'typ'=>'Verlauf']))->assertOk()->json('fields');
        $this->assertStringContainsString('Beginnt Aufgaben nach einer Erinnerung.',$progress['development.notes']);
        $final=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'typ'=>'Abschluss']))->assertOk()->json('fields');
        $this->assertStringNotContainsString('Beginnt Aufgaben',$final['support.description']);
        $past=$this->getJson(route('projekthasteilnehmer.luv.defaults',[...$params,'bis'=>'2026-09-07']))->assertOk()->json('fields');
        $this->assertArrayNotHasKey('competence.personal.support_need',$past);
    }

    public function test_daily_tasks_assign_reuse_and_summarize_only_the_participants_own_work(): void
    {
        extract($this->context());$group->update(['anfangsdatum'=>'2026-09-07']);
        $this->assertTrue($project->featureEnabled('daily_documentation'));
        $url=route('daily-tasks.store',$group);
        $payload=['revision'=>0,'performed_on'=>'2026-09-08','description'=>'Gemüse vorbereiten','assignments'=>[['project_person_id'=>$participation->id,'observation'=>'Selbstständig gearbeitet.']]];
        $id=$this->postJson($url,$payload)->assertOk()->json('id');
        $this->postJson($url,[...$payload,'performed_on'=>'2026-09-09','assignments'=>[['project_person_id'=>$participation->id]]])->assertOk();
        $response=$this->getJson(route('daily-tasks.index',[$group,'date'=>'2026-09-08']))->assertOk()->assertJsonCount(1,'tasks')->assertJsonCount(1,'library');
        $this->getJson(route('daily-tasks.index',[$group,'from'=>'2026-09-07','until'=>'2026-09-13']))->assertOk()->assertJsonCount(2,'tasks');
        $this->getJson(route('daily-tasks.index',[$group,'from'=>'2026-09-01','until'=>'2026-09-09']))->assertUnprocessable();
        $this->assertSame('Gemüse vorbereiten',$response->json('library.0.description'));
        $otherPerson=Personen::factory()->create(['typ'=>'teilnehmer']);
        $other=ProjektHasPersonen::create(['personen_id'=>$otherPerson->id,'projekt_id'=>$project->id]);
        $this->postJson($url,[...$payload,'assignments'=>[['project_person_id'=>$other->id]]])->assertUnprocessable();
        $this->postJson($url,[...$payload,'performed_on'=>'2026-09-10'])->assertUnprocessable();
        $this->postJson($url,[...$payload,'performed_on'=>'2026-09-01'])->assertUnprocessable();
        $this->postJson($url,[...$payload,'id'=>$id,'revision'=>0])->assertConflict();
        $summary=app(\App\Services\DailyTaskLuvSummary::class);
        $entry=$summary->entry($participation,'2026-09-07','2026-09-09','Start');
        $this->assertStringContainsString('Gemüse vorbereiten (2 Tage)',$entry['observation']);
        $this->assertSame(1,substr_count($entry['observation'],'Selbstständig gearbeitet.'));
        $this->assertNull($summary->entry($other,'2026-09-07','2026-09-09','Start'));
        $oneDay=$summary->entry($participation,'2026-09-09','2026-09-09','Verlauf');
        $this->assertStringContainsString('(1 Tag)',$oneDay['observation']);
        $this->assertStringNotContainsString('Selbstständig',$oneDay['observation']);
        $this->assertSame('development.notes',$oneDay['field_key']);
        $fields=$this->getJson(route('projekthasteilnehmer.luv.defaults',['teilnehmer_id'=>$person->id,'von'=>'2026-09-07','bis'=>'2026-09-09','typ'=>'Start']))->assertOk()->json('fields');
        $this->assertStringContainsString('Gemüse vorbereiten (2 Tage)',$fields['competence.technical.assessment']);
        $merged=app(\App\Services\Ai\ApprovedPaSupportNeedMerger::class)->merge(['sections'=>[]],[['tool_name'=>'get_participant_development_data','content'=>['daily_documentation'=>[$entry]]]]);
        $this->assertStringContainsString($entry['observation'],$merged['sections'][0]['claims'][0]['text']);
        $membership=GruppeHasPersonen::where('gruppe_id',$group->id)->first()->replicate();$membership->personen_id=$otherPerson->id;$membership->save();
        $shared=[...$payload,'description'=>'Arbeitsflächen reinigen','assignments'=>[['project_person_id'=>$participation->id],['project_person_id'=>$other->id,'observation'=>'Benötigte eine Erklärung.']]];
        $this->postJson($url,$shared)->assertOk();
        $ownText=$summary->entry($participation,'2026-09-07','2026-09-09','Start')['observation'];
        $otherText=$summary->entry($other,'2026-09-07','2026-09-09','Start')['observation'];
        $this->assertStringContainsString('Arbeitsflächen reinigen',$ownText);
        $this->assertStringContainsString('Arbeitsflächen reinigen',$otherText);
        $this->assertStringNotContainsString('Benötigte eine Erklärung.',$ownText);
        $this->assertStringNotContainsString('Gemüse',$otherText);
        $this->postJson($url,[...$payload,'id'=>$id,'revision'=>1,'description'=>'Gemüse waschen'])->assertOk();
        $this->deleteJson(route('daily-tasks.destroy',[$group,$id]),['revision'=>1])->assertConflict();
        $this->deleteJson(route('daily-tasks.destroy',[$group,$id]),['revision'=>2])->assertOk();
        $this->assertDatabaseMissing('group_daily_task_participants',['task_id'=>$id]);
        $project->update(['feature_settings'=>['daily_documentation'=>false]]);
        $this->postJson($url,$payload)->assertForbidden();
        $this->assertNull($summary->entry($participation->fresh(),'2026-09-07','2026-09-09','Start'));
    }

    public function test_daily_documentation_bop_default_can_be_overridden(): void
    {
        $bop=Projekt::factory()->create(['name'=>'BOP','feature_settings'=>[]]);
        $this->assertFalse($bop->featureEnabled('daily_documentation'));
        $bop->update(['feature_settings'=>['daily_documentation'=>true]]);
        $this->assertTrue($bop->fresh()->featureEnabled('daily_documentation'));
    }

    private function scores(): array
    {
        return ['german'=>['opposites'=>6,'errors'=>8,'verbs'=>8.5,'vocabulary'=>0,'adjectives'=>1,'endings'=>3.5,'plural'=>3,'punctuation'=>3.5,'meanings'=>6,'analogies'=>2.5,'reading'=>12,'trips'=>1,'clothing'=>1,'gift'=>1,'extra'=>0,'greeting'=>0.5,'expression'=>1,'connections'=>0.5,'structure'=>0.5,'correctness'=>6],
            'math'=>['simple_add'=>8,'simple_subtract'=>4,'simple_multiply'=>4,'simple_divide'=>4,'medium_add'=>9,'medium_subtract'=>0,'medium_multiply'=>3,'medium_divide'=>0]];
    }

    private function payload(): array
    {
        return ['revision'=>0,'tested_on'=>'2026-09-09','status'=>'approved','scores'=>$this->scores(),'reports'=>[
            'german'=>['assessment'=>'Stärken im Wortverständnis, Schwierigkeiten im schriftlichen Ausdruck.','support_need'=>'Förderung im schriftlichen Ausdruck.'],
            'math'=>['assessment'=>'Schwierigkeiten bei mittelschweren Aufgaben.','support_need'=>'Rechenverfahren üben.'],
        ]];
    }

    public function test_original_document_scores_and_missing_values_are_calculated_correctly(): void
    {
        $scoring=app(AptitudeScoring::class);
        $definition=$scoring->validateDefinition(BvbAptitudeProfile::definition());
        $results=$scoring->calculate($definition,$this->scores());
        $this->assertEquals(65.5,$results['german']['points']);
        $this->assertEquals(100,$results['german']['max']);
        $this->assertEquals(32,$results['math']['points']);
        $this->assertEquals(100,$results['math']['max']);
        $this->assertNull($results['german']['grade']);
        $this->assertTrue($results['german']['below_threshold']);
        $scores=$this->scores();$scores['german']['vocabulary']=null;
        $partial=$scoring->calculate($definition,$scores);
        $this->assertFalse($partial['german']['complete']);
        $this->assertNull($partial['german']['percent']);
        $this->assertNull($partial['german']['below_threshold']);
    }

    public function test_alternative_ratings_and_bounds_are_enforced(): void
    {
        foreach (['correctness'=>7,'vocabulary'=>4,'opposites'=>-1,'unknown'=>2,'writing'=>12] as $key=>$value) {
            $scores=$this->scores();$scores['german'][$key]=$value;
            try { app(AptitudeScoring::class)->calculate(BvbAptitudeProfile::definition(),$scores);$this->fail('Invalid score accepted'); }
            catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
        }
    }

    public function test_profile_versions_and_feature_configuration_do_not_change_existing_groups(): void
    {
        extract($this->context());
        $definition=BvbAptitudeProfile::definition();$definition['sections'][0]['threshold']=80;
        $this->putJson(route('aptitude.config.save',$project),['name'=>'Neuer Test','enabled'=>true,'definition'=>$definition])->assertOk();
        $this->assertSame(2,AptitudeProfile::where('projekt_id',$project->id)->max('version'));
        $this->assertEquals(75,$profile->fresh()->definition['sections'][0]['threshold']);
        $this->assertEquals($profile->id,$group->fresh()->aptitude_profile_id);
        $this->getJson(route('aptitude.group',$group))->assertOk()->assertJsonCount(1,'participants');
    }

    public function test_attempt_freigabe_history_and_luv_are_scoped_and_immutable(): void
    {
        extract($this->context());
        $response=$this->postJson(route('aptitude.attempt.save',[$group,$person]),$this->payload())->assertOk()->assertJsonPath('attempt.status','approved');
        $id=$response->json('attempt.id');
        $this->assertDatabaseCount('aptitude_attempt_revisions',1);
        $this->getJson(route('aptitude.history',$person))->assertOk()->assertJsonCount(1,'attempts');
        $this->postJson(route('aptitude.attempt.save',[$group,$person]),[...$this->payload(),'id'=>$id,'revision'=>1])->assertStatus(409);
        $sources=app(AptitudeLuvSources::class);
        $entries=$sources->entries($participation->id,'2026-09-01','2026-09-09','luv');
        $this->assertCount(4,$entries);
        $this->assertSame('competence.school.assessment',$entries[0]['field_key']);
        $this->assertCount(0,$sources->entries($participation->id,'2026-09-01','2026-09-08','luv'));
        $this->assertCount(0,$sources->entries(999999,'2026-09-01','2026-09-09','luv'));
        $merged=app(\App\Services\Ai\ApprovedPaSupportNeedMerger::class)->merge(['sections'=>[]],[['role'=>'tool','tool_name'=>'get_participant_development_data','content'=>['aptitude_tests'=>$entries]]]);
        $this->assertCount(2,$merged['sections']);
        $this->assertStringContainsString('Eignungstest',json_encode($merged,JSON_UNESCAPED_UNICODE));
        $this->postJson(route('aptitude.attempt.save',[$group,$person]),$this->payload())->assertOk();
        $this->assertDatabaseCount('aptitude_attempts',2);
    }

    public function test_incomplete_tests_cannot_be_approved_and_stale_edits_are_rejected(): void
    {
        extract($this->context());
        $data=$this->payload();$data['scores']['math']['medium_subtract']=null;
        $this->postJson(route('aptitude.attempt.save',[$group,$person]),$data)->assertUnprocessable();
        $data['status']='started';
        $response=$this->postJson(route('aptitude.attempt.save',[$group,$person]),$data)->assertOk();
        $data['id']=$response->json('attempt.id');
        $this->postJson(route('aptitude.attempt.save',[$group,$person]),$data)->assertStatus(409);
        $this->assertEmpty(app(AptitudeLuvSources::class)->entries($participation->id,'2026-01-01','2026-12-31','luv'));
    }

    public function test_cross_project_and_unassigned_participants_are_denied(): void
    {
        extract($this->context());
        $other=Personen::factory()->create(['typ'=>'teilnehmer']);
        $this->postJson(route('aptitude.attempt.save',[$group,$other]),$this->payload())->assertForbidden();
        $project->update(['feature_settings'=>['aptitude_tests'=>false]]);
        $this->getJson(route('aptitude.group',$group))->assertForbidden();
        $user->projekte()->detach($project);
        $this->getJson(route('aptitude.config',$project))->assertNotFound();
    }

    public function test_ai_drafts_use_saved_test_data_and_remain_separate_from_approved_reports(): void
    {
        extract($this->context());Queue::fake();
        $data=$this->payload();$data['status']='completed';
        $id=$this->postJson(route('aptitude.attempt.save',[$group,$person]),$data)->assertOk()->json('attempt.id');
        $uuid=$this->postJson(route('aptitude.generate',$id),['section'=>'german','field'=>'assessment'])->assertStatus(202)->json('run_id');
        Queue::assertPushed(\App\Jobs\GenerateAiWorkspaceJob::class);
        $run=\App\Models\AiWorkspaceRun::where('run_uuid',$uuid)->firstOrFail();
        $this->assertStringNotContainsString('65.5',$run->request_payload['sources'][0]['text']);
        $this->assertStringContainsString('keine Punkte, Prozentwerte, Noten',$run->instruction);
        $this->assertStringContainsString('nicht gelungene Leistungen sind keine fehlenden Bewertungen',$run->instruction);
        $source=json_decode($run->request_payload['sources'][0]['text'],true);
        $this->assertSame('Deutschkenntnisse',$source['fachbereich']);
        $this->assertStringNotContainsString('HSA',$run->request_payload['sources'][0]['text']);
        $this->assertStringContainsString('Nenne kein Schulabschlussniveau',$run->instruction);
        $this->assertSame('',$source['fachliche_einschaetzung']);
        $this->assertContains('Wortschatz',$source['unsicherheiten_im_test']);
        $this->assertNotContains('Wortschatz',$source['nicht_bewertet']);
        $run->update(['status'=>'completed','content'=>'Testentwurf']);
        $this->getJson(route('aptitude.generate.status',[$id,$uuid]))->assertOk()->assertJsonPath('content','Testentwurf');
        $this->assertSame('completed',AptitudeAttempt::find($id)->status);
    }

    private function groupPayload(User $user, Bereich $area, Standort $location): array
    {
        return ['bereich'=>$area->id,'betreuer'=>$user->person_id,'ort_typ'=>'extern',
            'standort_id'=>$location->id,'externer_ort'=>'Testraum','groupType'=>'1-day',
            'startDate'=>'2026-09-09','startZeit'=>'08:00','endZeit'=>'12:00',
            'anfangsdatum'=>'2026-09-09','enddatum'=>'2026-09-09','startzeit'=>'08:00','endzeit'=>'12:00'];
    }

    public function test_aptitude_groups_do_not_offer_or_accept_potential_analysis(): void
    {
        extract($this->context());
        $project->update(['name'=>'BvB Reha','potenzialanalyse_aktiv'=>true]);
        $this->grantTestPermission($user,'potenzialanalyse.update');
        $this->grantTestPermission($user,'potenzialanalyse.view');
        $controller=app(\App\Http\Controllers\GruppeHasTeilnehmerController::class);
        $method=new \ReflectionMethod($controller,'potenzialanalysePayload');
        $payload=$method->invoke($controller,$group->fresh(),$user);
        $this->assertFalse($payload['aktiv']);
        $this->assertEmpty($payload['teilnehmer']);
        $this->putJson(route('potenzialanalyse.gruppe.teilnehmer.update',[$group,$person]),[])->assertForbidden();
        $group->update(['aptitude_profile_id'=>null]);
        $this->assertTrue($group->fresh()->isAptitudeTest());
        $this->putJson(route('potenzialanalyse.gruppe.teilnehmer.update',[$group,$person]),[])->assertForbidden();
        $regularArea=Bereich::create(['name'=>'Hauswirtschaft','code'=>'HW']);
        $group->update(['bereich_id'=>$regularArea->id]);
        $this->assertFalse($group->fresh()->isPotentialAnalysisGroup());
        $this->assertFalse($method->invoke($controller,$group->fresh(),$user)['aktiv']);
        $this->putJson(route('potenzialanalyse.gruppe.teilnehmer.update',[$group,$person]),[])->assertForbidden();
        $this->assertFalse(app(\App\Services\Bop\GroupAttendanceSignatures::class)->allowed($user,$group->fresh()));
        $paArea=Bereich::create(['name'=>'Potenzialanalyse','code'=>'PA']);
        $group->update(['bereich_id'=>$paArea->id]);
        $method=new \ReflectionMethod($controller,'istPotenzialanalyseAuswertungsgruppe');
        $this->assertTrue($method->invoke($controller,$group->fresh()));
    }

    public function test_selecting_the_test_area_assigns_the_latest_project_template_automatically(): void
    {
        extract($this->context());
        $newProfile=AptitudeProfile::create(['projekt_id'=>$project->id,'name'=>'Aktuelle Vorlage','version'=>2,'definition'=>BvbAptitudeProfile::definition()]);
        $data=$this->groupPayload($user,$area,$location);
        $this->postJson(route('gruppe.store'),$data)->assertCreated()->assertJsonPath('gruppe.aptitude_profile_id',$newProfile->id);
        $this->assertEquals($profile->id,$group->fresh()->aptitude_profile_id);

        $regular=Bereich::create(['name'=>'Gartenbau']);$project->bereiche()->attach($regular);
        $data['bereich']=$regular->id;
        $this->postJson(route('gruppe.store'),$data)->assertCreated()->assertJsonPath('gruppe.aptitude_profile_id',null);
        $data['aptitude_profile_id']=$newProfile->id;
        $this->postJson(route('gruppe.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('aptitude_profile_id');
    }

    public function test_test_area_requires_an_enabled_feature_and_a_project_template(): void
    {
        extract($this->context());
        $data=$this->groupPayload($user,$area,$location);
        $project->update(['feature_settings'=>['aptitude_tests'=>false]]);
        $this->postJson(route('gruppe.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('bereich');
        $project->update(['feature_settings'=>['aptitude_tests'=>true]]);
        $group->update(['aptitude_profile_id'=>null]);$profile->delete();
        $this->postJson(route('gruppe.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('bereich');
    }

    public function test_changing_a_group_area_updates_its_test_function_and_preserves_recorded_tests(): void
    {
        extract($this->context());
        $regular=Bereich::create(['name'=>'Gartenbau']);$project->bereiche()->attach($regular);
        $data=$this->groupPayload($user,$regular,$location);
        $this->putJson(route('gruppe.update',$group),$data)->assertOk();
        $this->assertNull($group->fresh()->aptitude_profile_id);
        $data['bereich']=$area->id;
        $this->putJson(route('gruppe.update',$group),$data)->assertOk();
        $this->assertEquals($profile->id,$group->fresh()->aptitude_profile_id);
        AptitudeProfile::create(['projekt_id'=>$project->id,'name'=>'Neue Vorlage','version'=>2,'definition'=>BvbAptitudeProfile::definition()]);
        $this->putJson(route('gruppe.update',$group),$data)->assertOk();
        $this->assertEquals($profile->id,$group->fresh()->aptitude_profile_id);
        $this->postJson(route('aptitude.attempt.save',[$group,$person]),$this->payload())->assertOk();
        $data['bereich']=$regular->id;
        $this->putJson(route('gruppe.update',$group),$data)->assertUnprocessable()->assertJsonValidationErrors('bereich');
        $this->assertEquals($area->id,$group->fresh()->bereich_id);
        $this->assertDatabaseCount('aptitude_attempts',1);
    }

    public function test_test_area_is_available_to_staff_with_an_assigned_vocational_area(): void
    {
        extract($this->context());
        $regular=Bereich::create(['name'=>'Gartenbau']);$project->bereiche()->attach($regular);
        $staffParticipation=ProjektHasPersonen::where('projekt_id',$project->id)->where('personen_id',$user->person_id)->firstOrFail();
        $staffParticipation->bereichZuweisungen()->create(['bereich_id'=>$regular->id,'is_default'=>true]);
        $this->get(route('gruppe.create'))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('betreuer.0.bereiche',2)
            ->where('betreuer.0.bereiche',fn ($areas) => collect($areas)->contains(fn ($item) => (int) $item['id'] === (int) $area->id))
            ->where('betreuer.0.default_bereich_id',$regular->id));
        app(AptitudeGroupSetup::class)->ensureArea($project);
        $this->assertSame(1,Bereich::where('code',AptitudeGroupSetup::AREA_CODE)->count());
        $this->assertSame(1,$project->bereiche()->where('code',AptitudeGroupSetup::AREA_CODE)->count());
    }

    public function test_test_area_is_hidden_and_rejected_for_a_supervisor_without_area_assignment(): void
    {
        extract($this->context());
        $this->grantTestPermission($user, 'gruppe.view.all');
        $regular = Bereich::create(['name' => 'Gartenbau']);
        $project->bereiche()->attach($regular);

        $matrixTester = User::factory()->create();
        $assignment = ProjektHasPersonen::create([
            'projekt_id' => $project->id,
            'personen_id' => $matrixTester->person_id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $assignment->bereichZuweisungen()->create(['bereich_id' => $regular->id, 'is_default' => true]);

        $method = new \ReflectionMethod(\App\Http\Controllers\GruppeController::class, 'betreuerOptions');
        $options = $method->invoke(app(\App\Http\Controllers\GruppeController::class), $project->fresh(), $user, true);
        $testerOption = $options->firstWhere('id', $matrixTester->person_id);
        $this->assertNotContains($area->id, collect($testerOption['bereiche'])->pluck('id'));

        $payload = $this->groupPayload($matrixTester, $area, $location);
        $this->postJson(route('gruppe.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('betreuer');
    }
}
