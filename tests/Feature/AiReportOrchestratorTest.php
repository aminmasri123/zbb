<?php

namespace Tests\Feature;

use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\PotenzialanalyseBericht;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\ProjektLuvTemplate;
use App\Models\Raeume;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use App\Services\Ai\Tools\GetParticipantPotentialAnalysisSupportNeedsTool;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiReportOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.zbb_ai_agent', [
            'base_url' => 'http://127.0.0.1:18000',
            'key_id' => 'laravel',
            'secret' => 'test-secret-that-is-at-least-32-bytes-long',
            'connect_timeout' => 3,
            'timeout' => 130,
            'max_response_bytes' => 1000000,
        ]);
        Http::preventStrayRequests();
    }

    public function test_it_prefetches_all_authorized_tools_and_uses_one_agent_request(): void
    {
        [$user, $project, $participant] = $this->context();
        $runId = null;
        $requestNumber = 0;

        Http::fake(function (Request $request) use (&$runId, &$requestNumber) {
            $requestNumber++;
            $payload = $request->data();
            $runId ??= $payload['run_id'];

            return Http::response([
                'kind' => 'final',
                'run_id' => $runId,
                'report' => [
                    'report_type' => 'luv',
                    'title' => 'Entwurf',
                    'sections' => [[
                        'heading' => 'Datenlage',
                        'claims' => [[
                            'claim_id' => 'missing-1',
                            'text' => 'Es liegen noch keine Teilnehmerfakten vor.',
                            'status' => 'insufficient_data',
                            'source_ids' => [],
                        ]],
                    ]],
                    'warnings' => [],
                ],
            ]);
        });

        $result = app(AiReportOrchestrator::class)->draft(
            $user,
            $participant->id,
            'luv',
            '2026-01-01',
            '2026-06-30',
            'Erstelle einen belegten Entwurf.',
        );

        $this->assertSame('Entwurf', $result['report']['title']);
        $this->assertSame(1, $requestNumber);
        Http::assertSent(function (Request $request) use ($project): bool {
            $results = $request->data()['tool_results'] ?? [];

            return count($results) === 6
                && $results[0]['tool_name'] === GetProjectReportRulesTool::NAME
                && $results[0]['content']['project_id'] === $project->id;
        });
    }

    public function test_career_goal_is_sent_as_evidence_and_mapped_to_the_luv_field(): void
    {
        [$user, $project, $participant] = $this->context();
        $participation = ProjektHasPersonen::where('projekt_id', $project->id)->where('personen_id', $participant->id)->firstOrFail();
        $goal = \App\Models\ParticipationCareerGoal::create([
            'project_person_id' => $participation->id, 'target' => 'training', 'occupation' => 'Fachlagerist',
            'agreement_status' => 'wish', 'documented_on' => '2026-02-01', 'author_name' => 'Fachkraft', 'created_by' => $user->id,
        ]);
        Http::fake(fn (Request $request) => Http::response([
            'kind' => 'final', 'run_id' => $request->data()['run_id'],
            'report' => ['report_type' => 'luv', 'title' => 'Entwurf', 'sections' => [[
                'heading' => '[integration.goal] Eingliederungsziel',
                'claims' => [['claim_id' => 'missing', 'text' => 'Daten fehlen.', 'status' => 'insufficient_data', 'source_ids' => []]],
            ]], 'warnings' => []],
        ]));
        $result = app(AiReportOrchestrator::class)->draft($user, $participant->id, 'luv', '2026-01-01', '2026-06-30', 'Erstelle einen belegten Entwurf.');
        Http::assertSent(function (Request $request) use ($goal) {
            $identity = collect($request->data()['tool_results'])->firstWhere('tool_name', 'get_participant_identity_summary');
            return data_get($identity, 'content.career_goal.source_id') === 'career-goal-'.$goal->id;
        });
        $section = collect($result['report']['sections'])->firstWhere('heading', '[integration.goal] (Ausbildungs-)Zielberuf und Alternativen');
        $this->assertStringContainsString('Berufswunsch', $section['claims'][0]['text']);
        $this->assertStringContainsString('Ausbildung als Fachlagerist', $section['claims'][0]['text']);
        $this->assertSame(['career-goal-'.$goal->id], $section['claims'][0]['source_ids']);
    }

    public function test_it_denies_an_unrelated_participant_before_contacting_the_agent(): void
    {
        [$user] = $this->context();
        $foreignParticipant = Personen::factory()->create(['typ' => 'teilnehmer']);
        Http::fake();

        try {
            app(AiReportOrchestrator::class)->draft(
                $user,
                $foreignParticipant->id,
                'luv',
                '2026-01-01',
                '2026-06-30',
                'Erstelle einen Entwurf.',
            );
            $this->fail('Expected AuthorizationException.');
        } catch (AuthorizationException) {
            Http::assertNothingSent();
        }
    }

    public function test_it_guarantees_approved_pa_fields_even_when_the_model_omits_them(): void
    {
        [$user, $project, $participant] = $this->context();
        $project->update(['potenzialanalyse_aktiv' => true]);
        $standort = Standort::factory()->create();
        $gruppe = Gruppe::query()->create([
            'personen_id' => Personen::factory()->create()->id,
            'bereich_id' => Bereich::query()->create(['name' => 'Potenzialanalyse'])->id,
            'projekt_id' => $project->id,
            'raum_id' => Raeume::query()->create([
                'name' => 'PA-Test-Raum',
                'standort_id' => $standort->id,
                'typ' => 'Seminarraum',
                'aktiv' => true,
            ])->id,
            'standort_id' => $standort->id,
        ]);
        $bericht = PotenzialanalyseBericht::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $participant->id,
            'user_id' => $user->id,
            'status' => 'geprueft',
            'fertiggestellt_at' => '2026-08-25 12:00:00',
            'luv_foerderbedarfe' => [
                'personal' => [
                    'status' => 'kein_foerderbedarf',
                    'begruendung' => 'Arbeitet in vertrauten Situationen zunehmend selbstständig.',
                    'foerderbedarf' => '',
                    'freigegeben' => true,
                    'freigegeben_von' => $user->id,
                    'freigegeben_am' => '2026-09-03T20:59:00+02:00',
                ],
                'methodical' => [
                    'status' => 'unprueft',
                    'begruendung' => '',
                    'foerderbedarf' => '',
                    'freigegeben' => false,
                ],
                'social' => [
                    'status' => 'foerderbedarf',
                    'begruendung' => 'Der mündliche Ausdruck gelingt noch nicht durchgehend verständlich.',
                    'foerderbedarf' => 'Mündlichen Ausdruck und grammatische Sicherheit stärken.',
                    'freigegeben' => true,
                    'freigegeben_von' => $user->id,
                    'freigegeben_am' => '2026-09-03T20:59:03+02:00',
                ],
            ],
        ]);

        $failResponse = false;
        Http::fake(function (Request $request) use (&$failResponse) {
            if ($failResponse) {
                return Http::response(['detail' => 'invalid structured report'], 502);
            }
            $payload = $request->data();

            return Http::response([
                'kind' => 'final',
                'run_id' => $payload['run_id'],
                'report' => [
                    'report_type' => 'luv',
                    'title' => 'Start-LuV Entwurf',
                    'sections' => [[
                        'heading' => '[competence.school.assessment] Schulische Basiskompetenzen – Einschätzung',
                        'claims' => [[
                            'claim_id' => 'school-1',
                            'text' => 'Eine belegte schulische Beobachtung liegt vor.',
                            'status' => 'supported',
                            'source_ids' => ['participant-development-summary'],
                        ]],
                    ]],
                    'warnings' => ['Die KI-Ausgabe wurde am Ende abgeschnitten. Vollständig erzeugte Felder wurden wiederhergestellt; fehlende Felder bitte fachlich ergänzen.'],
                ],
            ]);
        });

        $result = app(AiReportOrchestrator::class)->draft(
            $user,
            $participant->id,
            'luv',
            '2026-09-01',
            '2026-09-05',
            'Erstelle einen menschlich formulierten Start-LuV-Entwurf.',
        );
        $sections = collect($result['report']['sections'])
            ->keyBy(fn (array $section) => preg_match('/^\[([^]]+)\]/', $section['heading'], $match) ? $match[1] : '');

        $this->assertTrue($sections->has('competence.personal.support_need'));
        $this->assertSame('partial', $result['report']['generation_status']);
        $this->assertFalse($sections->has('competence.methodical.support_need'));
        $this->assertTrue($sections->has('competence.social.support_need'));
        $this->assertSame(
            ['potential-analysis-support-'.$bericht->id.'-social'],
            $sections['competence.social.support_need']['claims'][0]['source_ids'],
        );
        $failResponse = true;
        $partial = app(AiReportOrchestrator::class)->draft($user, $participant->id, 'luv', '2026-09-01', '2026-09-05', 'Entwurf erstellen');
        $this->assertSame('Unvollständiger Entwurf – nur belegte PA-Angaben', $partial['report']['title']);
        $this->assertSame('partial', $partial['report']['generation_status']);
        $this->assertCount(2, $partial['report']['sections']);
        $this->assertStringContainsString('KI-Textgenerierung ist fehlgeschlagen', $partial['report']['warnings'][0]);
        $this->assertStringNotContainsString('competence.school.assessment', json_encode($partial['report']));
    }

    public function test_invalid_model_output_without_pa_evidence_still_fails(): void
    {
        [$user, $project, $participant] = $this->context();
        Http::fake(['*' => Http::response(['detail' => 'invalid structured report'], 502)]);
        $this->expectException(AgentUnavailableException::class);
        $this->expectExceptionMessage('Berichts-JSON entspricht nicht dem vereinbarten Schema');
        app(AiReportOrchestrator::class)->draft($user, $participant->id, 'luv', '2026-01-01', '2026-06-30', 'Entwurf erstellen');
    }

    public function test_it_does_not_send_project_disabled_sources_to_the_agent(): void
    {
        [$user, $project, $participant] = $this->context();
        ProjektLuvTemplate::query()->create([
            'projekt_id' => $project->id,
            'luv_type' => 'Start',
            'version' => 1,
            'name' => 'Datensparsame Vorlage',
            'sections' => ProjektLuvTemplate::defaultSectionsFor('Start'),
            'source_settings' => array_fill_keys(array_keys(ProjektLuvTemplate::DEFAULT_SOURCE_SETTINGS), false),
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'kind' => 'final',
                'run_id' => $payload['run_id'],
                'report' => [
                    'report_type' => 'luv',
                    'title' => 'Entwurf ohne Teilnehmerquellen',
                    'sections' => [[
                        'heading' => 'Datenlage',
                        'claims' => [[
                            'claim_id' => 'missing-1',
                            'text' => 'Es wurden keine Teilnehmerquellen bereitgestellt.',
                            'status' => 'insufficient_data',
                            'source_ids' => [],
                        ]],
                    ]],
                    'warnings' => [],
                ],
            ]);
        });

        app(AiReportOrchestrator::class)->draft(
            $user,
            $participant->id,
            'luv',
            '2026-01-01',
            '2026-06-30',
            'Erstelle einen belegten Entwurf.',
        );

        Http::assertSent(fn (Request $request): bool => collect($request->data()['tool_results'] ?? [])
            ->pluck('tool_name')->all() === [GetProjectReportRulesTool::NAME]);
    }

    public function test_it_rejects_tool_calls_after_all_evidence_was_prefetched(): void
    {
        [$user, , $participant] = $this->context();
        $runId = null;
        Http::fake(function (Request $request) use (&$runId) {
            $runId ??= $request->data()['run_id'];

            return Http::response([
                'kind' => 'tool_calls',
                'run_id' => $runId,
                'calls' => [[
                    'call_id' => 'same-call',
                    'name' => GetProjectReportRulesTool::NAME,
                    'arguments' => [],
                ]],
            ]);
        });

        $this->expectException(AgentUnavailableException::class);

        app(AiReportOrchestrator::class)->draft(
            $user,
            $participant->id,
            'luv',
            '2026-01-01',
            '2026-06-30',
            'Erstelle einen Entwurf.',
        );
    }

    public function test_it_allowlists_the_pa_support_source_only_when_luv_and_pa_are_both_enabled(): void
    {
        [$user, $project, $participant] = $this->context();
        $contextWithoutPa = app(AiReportOrchestrator::class)->createDraftContext(
            $user,
            $participant->id,
            '2026-01-01',
            '2026-06-30',
        );
        $this->assertNotContains(GetParticipantPotentialAnalysisSupportNeedsTool::NAME, $contextWithoutPa->allowedTools);

        $project->update(['potenzialanalyse_aktiv' => true]);

        $context = app(AiReportOrchestrator::class)->createDraftContext(
            $user,
            $participant->id,
            '2026-01-01',
            '2026-06-30',
        );
        $this->assertContains(GetParticipantPotentialAnalysisSupportNeedsTool::NAME, $context->allowedTools);

        $project->update([
            'participant_profile_settings' => [
                'enabled_tabs' => ['stammdaten'],
                'tab_order' => Projekt::participantProfileTabKeys(),
            ],
        ]);

        $contextWithoutLuv = app(AiReportOrchestrator::class)->createDraftContext(
            $user,
            $participant->id,
            '2026-01-01',
            '2026-06-30',
        );
        $this->assertNotContains(GetParticipantPotentialAnalysisSupportNeedsTool::NAME, $contextWithoutLuv->allowedTools);
    }

    public function test_pa_tool_returns_only_completed_and_explicitly_approved_support_needs(): void
    {
        [$user, $project, $participant] = $this->context();
        $project->update(['potenzialanalyse_aktiv' => true]);
        $standort = Standort::factory()->create();
        $gruppe = Gruppe::query()->create([
            'personen_id' => Personen::factory()->create()->id,
            'bereich_id' => Bereich::query()->create(['name' => 'PA-LuV-Test'])->id,
            'projekt_id' => $project->id,
            'raum_id' => Raeume::query()->create([
                'name' => 'PA-LuV-Raum',
                'standort_id' => $standort->id,
                'typ' => 'Seminarraum',
                'aktiv' => true,
            ])->id,
            'standort_id' => $standort->id,
        ]);
        PotenzialanalyseBericht::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $participant->id,
            'user_id' => $user->id,
            'status' => 'geprueft',
            'fertiggestellt_at' => '2026-03-01 10:00:00',
            'luv_foerderbedarfe' => [
                'personal' => [
                    'status' => 'foerderbedarf',
                    'begruendung' => 'Neue Aufgaben werden noch zögerlich begonnen.',
                    'foerderbedarf' => 'Selbstständigen Aufgabenbeginn schrittweise einüben.',
                    'freigegeben' => true,
                    'freigegeben_von' => $user->id,
                    'freigegeben_am' => '2026-03-01T10:00:00+01:00',
                ],
                'methodical' => [
                    'status' => 'foerderbedarf',
                    'foerderbedarf' => 'Arbeitsschritte strukturieren.',
                    'freigegeben' => false,
                ],
                'social' => [
                    'status' => 'kein_foerderbedarf',
                    'begruendung' => 'Kommuniziert situationsgerecht.',
                    'freigegeben' => true,
                    'freigegeben_von' => $user->id,
                    'freigegeben_am' => '2026-03-01T10:00:00+01:00',
                ],
            ],
        ]);
        $newerGroup = $gruppe->replicate();
        $newerGroup->save();
        PotenzialanalyseBericht::query()->create([
            'gruppe_id' => $newerGroup->id,
            'personen_id' => $participant->id,
            'user_id' => $user->id,
            'status' => 'geprueft',
            'fertiggestellt_at' => '2026-04-01 10:00:00',
            'luv_foerderbedarfe' => PotenzialanalyseBericht::defaultLuvFoerderbedarfe(),
        ]);
        $context = new AiRunContext(
            $user->id,
            $project->id,
            [GetParticipantPotentialAnalysisSupportNeedsTool::NAME],
            $participant->id,
            '2026-01-01',
            '2026-06-30',
        );

        $result = app(AiToolRegistry::class)->execute(
            $user,
            $context,
            GetParticipantPotentialAnalysisSupportNeedsTool::NAME,
        );

        $entries = collect($result['entries'])->keyBy('field_key');
        $this->assertCount(2, $entries);
        $this->assertSame(
            'Selbstständigen Aufgabenbeginn schrittweise einüben.',
            $entries['competence.personal.support_need']['support_need'],
        );
        $this->assertSame('no_support_need', $entries['competence.social.support_need']['decision']);
        $this->assertFalse($entries->has('competence.methodical.support_need'));

        $interimContext = new AiRunContext(
            $user->id,
            $project->id,
            [GetParticipantPotentialAnalysisSupportNeedsTool::NAME],
            $participant->id,
            '2026-01-01',
            '2026-06-30',
            'interim',
        );
        $interimResult = app(AiToolRegistry::class)->execute(
            $user,
            $interimContext,
            GetParticipantPotentialAnalysisSupportNeedsTool::NAME,
        );
        $this->assertSame('competence.personal.current_need', $interimResult['entries'][0]['field_key']);

        $finalContext = new AiRunContext(
            $user->id,
            $project->id,
            [GetParticipantPotentialAnalysisSupportNeedsTool::NAME],
            $participant->id,
            '2026-01-01',
            '2026-06-30',
            'final',
        );
        $finalResult = app(AiToolRegistry::class)->execute(
            $user,
            $finalContext,
            GetParticipantPotentialAnalysisSupportNeedsTool::NAME,
        );
        $this->assertSame('support.description', $finalResult['entries'][0]['field_key']);
    }

    public function test_completed_pa_assessments_keep_individual_ratings_and_map_to_each_report_type(): void
    {
        [$user, $project, $participant] = $this->context();
        $project->update(['potenzialanalyse_aktiv' => true]);
        $location = Standort::factory()->create();
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => Bereich::query()->create(['name' => 'PA'])->id,
            'projekt_id' => $project->id,
            'raum_id' => Raeume::query()->create(['name' => 'PA', 'standort_id' => $location->id, 'typ' => 'Seminarraum'])->id,
            'standort_id' => $location->id,
        ]);
        $report = PotenzialanalyseBericht::query()->create([
            'gruppe_id' => $group->id, 'personen_id' => $participant->id, 'user_id' => $user->id,
            'status' => 'entwurf', 'fertiggestellt_at' => '2026-03-01 12:00:00',
        ]);
        foreach (['motivation_leistungsbereitschaft' => 5, 'arbeitsplanung' => 4, 'teamfaehigkeit' => 5, 'kommunikation' => 1, 'feinmotorik' => 4] as $key => $rating) {
            \App\Models\PotenzialanalyseKompetenzbewertung::query()->forceCreate([
                'gruppe_id' => $group->id, 'personen_id' => $participant->id, 'user_id' => $user->id,
                'typ' => 'anleiter', 'merkmal' => $key, 'bewertung' => $rating,
                'bemerkung' => 'Gespeicherte Beobachtung '.$key,
                'created_at' => '2026-03-01 11:00:00', 'updated_at' => '2026-03-01 11:00:00',
            ]);
        }
        $context = new AiRunContext($user->id, $project->id, [GetParticipantPotentialAnalysisSupportNeedsTool::NAME], $participant->id, '2026-01-01', '2026-06-30');
        $tool = app(GetParticipantPotentialAnalysisSupportNeedsTool::class);
        $this->assertSame([], $tool->execute($user, $context, [])['entries']);
        $report->update(['status' => 'geprueft']);

        foreach (['luv', 'interim', 'final'] as $type) {
            $context = new AiRunContext($user->id, $project->id, [GetParticipantPotentialAnalysisSupportNeedsTool::NAME], $participant->id, '2026-01-01', '2026-06-30', $type);
            $result = $tool->execute($user, $context, []);
            $this->assertCount(5, $result['entries']);
            $merged = app(\App\Services\Ai\ApprovedPaSupportNeedMerger::class)->merge(['sections' => [], 'warnings' => []], [['tool_name' => $tool->name(), 'content' => $result]]);
            $sections = collect($merged['sections'])->keyBy('heading');
            $text = json_encode($merged, JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString('Teamfähigkeit – dokumentierte Einschätzung 5 von 5', $text);
            $this->assertStringContainsString('Kommunikation – dokumentierte Einschätzung 1 von 5', $text);
            $this->assertStringNotContainsString('kein zusätzlicher Förderbedarf', $text);
            if ($type === 'luv') {
                $this->assertCount(4, $sections);
                foreach (['personal', 'methodical', 'social'] as $key) {
                    $this->assertStringContainsString("[competence.{$key}.assessment]", $text);
                }
                $this->assertStringContainsString('[competence.notes]', $text);
                $this->assertStringNotContainsString('competence.technical.assessment', $text);
            } else {
                $this->assertCount(1, $sections);
                $this->assertStringContainsString($type === 'interim' ? '[development.notes]' : '[support.description]', $text);
            }
        }
        // A later edit is not evidence for an earlier reporting period.
        \App\Models\PotenzialanalyseKompetenzbewertung::query()->where('gruppe_id', $group->id)->where('merkmal', 'kommunikation')->update(['updated_at' => '2026-07-01 10:00:00']);
        $this->assertCount(4, $tool->execute($user, $context, [])['entries']);
    }

    public function test_previous_luv_source_includes_structured_fields_only_from_approved_reports(): void
    {
        [$user, $project, $participant] = $this->context();
        $participation = ProjektHasPersonen::where('projekt_id', $project->id)->where('personen_id', $participant->id)->firstOrFail();
        foreach (['draft', 'approved'] as $status) {
            $participation->luv()->create([
                'ausgangssituation' => '', 'zielvereinbarung' => '', 'qualifikationen' => '',
                'version' => $status === 'draft' ? 1 : 2, 'typ' => 'Start', 'status' => $status, 'von' => '2026-01-01', 'bis' => '2026-02-01',
                'payload' => ['fields' => ['competence.social.support_need' => 'Kommunikation üben'], 'sections' => [['key' => 'zielvereinbarung', 'heading' => 'Ziele', 'value' => 'Wöchentliches Gespräch']]],
            ]);
        }
        $tool = \App\Services\Ai\Tools\GetParticipantLuvDataTool::class;
        $context = new AiRunContext($user->id, $project->id, [$tool::NAME], $participant->id, '2026-01-01', '2026-06-30');
        $result = app($tool)->execute($user, $context, []);
        $this->assertCount(1, $result['entries']);
        $this->assertSame('Kommunikation üben', $result['entries'][0]['form_fields']['competence.social.support_need']);
        $this->assertSame('Wöchentliches Gespräch', $result['entries'][0]['form_sections'][0]['value']);
    }

    /** @return array{User, Projekt, Personen} */
    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
        ]);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);

        $role = Role::query()->create([
            'name' => 'AI-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#123456',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'own_projects',
        ]);
        $user->assignRole($role);
        $this->grantTestPermission($user, GetProjectReportRulesTool::PERMISSION);

        return [$user->fresh(), $project->fresh(), $participant->fresh()];
    }
}
