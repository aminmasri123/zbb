<?php

namespace Tests\Feature;

use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\User;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Exceptions\AgentUnavailableException;
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

            return count($results) === 5
                && $results[0]['tool_name'] === GetProjectReportRulesTool::NAME
                && $results[0]['content']['project_id'] === $project->id;
        });
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
