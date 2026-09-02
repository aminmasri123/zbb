<?php

namespace Tests\Feature;

use App\Jobs\GenerateAiReportJob;
use App\Models\AiReportRun;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\User;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiReportEndpointTest extends TestCase
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

    public function test_authorized_user_is_put_into_queue(): void
    {
        [$user, , $participant] = $this->context(true);
        Queue::fake();
        Http::fake();

        $response = $this->actingAs($user)->postJson('/ki/berichte/entwurf', $this->payload($participant->id))
            ->assertStatus(202)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'queued');

        $runId = $response->json('run_id');
        $this->assertNotEmpty($runId);
        $this->assertSame(route('ai.reports.status', ['run' => $runId]), $response->json('status_url'));

        $this->assertDatabaseHas('ai_report_runs', [
            'run_uuid' => $runId,
            'user_id' => $user->id,
            'participant_id' => $participant->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(GenerateAiReportJob::class, fn (GenerateAiReportJob $job) => $job->runUuid === $runId);
    }

    public function test_missing_permission_is_denied_before_the_agent_is_contacted(): void
    {
        [$user, , $participant] = $this->context(false);
        Queue::fake();
        Http::fake();

        $this->actingAs($user)->postJson('/ki/berichte/entwurf', $this->payload($participant->id))
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_report_runs', ['user_id' => $user->id]);
        Queue::assertNothingPushed();
    }

    public function test_invalid_period_is_rejected_before_the_agent_is_contacted(): void
    {
        [$user, , $participant] = $this->context(true);
        Queue::fake();
        Http::fake();
        $payload = $this->payload($participant->id);
        $payload['until_date'] = '2025-12-31';

        $this->actingAs($user)->postJson('/ki/berichte/entwurf', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('until_date');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_report_runs', ['user_id' => $user->id]);
        Queue::assertNothingPushed();
    }

    public function test_status_endpoint_returns_completed_and_failed_runs(): void
    {
        [$user, $project, $participant] = $this->context(true);

        $completedRun = AiReportRun::query()->create([
            'run_uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'user_id' => $user->id,
            'project_id' => $project->id,
            'participant_id' => $participant->id,
            'report_type' => 'luv',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Entwurf.',
            'status' => 'completed',
            'progress_percent' => 100,
            'report' => [
                'report_type' => 'luv',
                'title' => 'Lokaler Entwurf',
                'sections' => [[
                    'heading' => 'Datenlage',
                    'claims' => [[
                        'claim_id' => 'ok-1',
                        'text' => 'Zusammengefasste Befunde liegen vor.',
                        'status' => 'supported',
                        'source_ids' => ['document-1'],
                    ]],
                ]],
                'warnings' => [],
            ],
        ]);

        $this->actingAs($user)->getJson('/ki/berichte/entwurf/'.$completedRun->run_uuid)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('report.title', 'Lokaler Entwurf');

        $failedRun = AiReportRun::query()->create([
            'run_uuid' => '123e4567-e89b-12d3-a456-426614174002',
            'user_id' => $user->id,
            'project_id' => $project->id,
            'participant_id' => $participant->id,
            'report_type' => 'luv',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Entwurf.',
            'status' => 'failed',
            'progress_percent' => 100,
            'error_code' => 'agent_unavailable',
            'error_message' => 'Der KI-Dienst war nicht erreichbar.',
        ]);

        $this->actingAs($user)->getJson('/ki/berichte/entwurf/'.$failedRun->run_uuid)
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error_code', 'agent_unavailable')
            ->assertJsonPath(
                'error_message',
                'Der interne KI-Dienst ist nicht erreichbar. Bitte prüfen Sie den KI-Tunnel zum KI-Rechner.',
            );
    }

    public function test_queued_status_reports_real_elapsed_time_and_worker_warning(): void
    {
        Carbon::setTestNow('2026-08-23 12:01:00');
        [$user, $project, $participant] = $this->context(true);

        $run = AiReportRun::query()->create([
            'run_uuid' => '123e4567-e89b-12d3-a456-426614174003',
            'user_id' => $user->id,
            'project_id' => $project->id,
            'participant_id' => $participant->id,
            'report_type' => 'luv',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Entwurf.',
            'status' => 'queued',
            'progress_percent' => 0,
        ]);
        $run->forceFill(['created_at' => Carbon::parse('2026-08-23 12:00:00')])->save();

        $this->actingAs($user)->getJson('/ki/berichte/entwurf/'.$run->run_uuid)
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('elapsed_seconds', 60)
            ->assertJsonPath(
                'queue_warning',
                'Der Auftrag wartet noch auf den Hintergrunddienst. Bitte den Queue-Worker auf dem Webserver prüfen.',
            );

        Carbon::setTestNow();
    }

    public function test_completed_ai_run_can_be_adopted_once_as_a_versioned_luv_draft(): void
    {
        [$user, $project, $participant] = $this->context(true);
        $run = AiReportRun::query()->create([
            'run_uuid' => '123e4567-e89b-42d3-a456-426614174099',
            'user_id' => $user->id,
            'project_id' => $project->id,
            'participant_id' => $participant->id,
            'report_type' => 'interim',
            'luv_type' => 'Verlauf',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Verlauf.',
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
            'report' => [
                'report_type' => 'interim',
                'title' => 'Verlauf-LuV Entwurf',
                'sections' => [[
                    'heading' => '[development.notes] Ergänzende Erläuterungen',
                    'claims' => [[
                        'claim_id' => 'development-1',
                        'text' => 'Die dokumentierte Entwicklung wurde zusammengefasst.',
                        'status' => 'supported',
                        'source_ids' => ['documentation-1'],
                    ]],
                ]],
                'warnings' => ['Fachlich prüfen.'],
            ],
        ]);

        $first = $this->actingAs($user)->postJson(route('ai.reports.adopt', $run->run_uuid))
            ->assertCreated()
            ->assertJsonPath('luv.typ', 'Verlauf')
            ->assertJsonPath('luv.status', 'draft')
            ->assertJsonPath('luv.payload.schema', 'bvb-reha-2023')
            ->assertJsonPath('luv.payload.sections.0.claims.0.source_ids.0', 'documentation-1');

        $this->assertSame(
            'Die dokumentierte Entwicklung wurde zusammengefasst.',
            $first->json('luv.payload.fields')['development.notes'],
        );

        $second = $this->actingAs($user)->postJson(route('ai.reports.adopt', $run->run_uuid))
            ->assertCreated();

        $this->assertSame($first->json('luv.id'), $second->json('luv.id'));
        $this->assertDatabaseCount('projekt_has_teilnehmer_luvs', 1);
    }

    public function test_worker_sends_model_cast_dates_as_iso_dates(): void
    {
        [$user, $project, $participant] = $this->context(true);
        $run = AiReportRun::query()->create([
            'run_uuid' => '123e4567-e89b-42d3-a456-426614174004',
            'user_id' => $user->id,
            'project_id' => $project->id,
            'participant_id' => $participant->id,
            'report_type' => 'luv',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Entwurf.',
            'status' => 'queued',
        ]);

        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'kind' => 'final',
                'run_id' => $payload['run_id'],
                'report' => [
                    'report_type' => 'luv',
                    'title' => 'Testentwurf',
                    'sections' => [[
                        'heading' => 'Datenlage',
                        'claims' => [[
                            'claim_id' => 'missing-1',
                            'text' => 'Daten fehlen.',
                            'status' => 'insufficient_data',
                            'source_ids' => [],
                        ]],
                    ]],
                    'warnings' => [],
                ],
            ]);
        });

        (new GenerateAiReportJob($run->run_uuid))->handle(app(AiReportOrchestrator::class));

        $this->assertDatabaseHas('ai_report_runs', [
            'run_uuid' => $run->run_uuid,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return ($payload['period']['from_date'] ?? null) === '2026-01-01'
                && ($payload['period']['until_date'] ?? null) === '2026-06-30';
        });
    }

    /** @return array{User, Projekt, Personen} */
    private function context(bool $grantPermission): array
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
            'name' => 'AI-Endpoint-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#123456',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'own_projects',
        ]);
        $user->assignRole($role);

        if ($grantPermission) {
            $this->grantTestPermission($user, GetProjectReportRulesTool::PERMISSION);
        }

        return [$user->fresh(), $project->fresh(), $participant->fresh()];
    }

    /** @return array<string, mixed> */
    private function payload(int $participantId): array
    {
        return [
            'participant_id' => $participantId,
            'report_type' => 'luv',
            'from_date' => '2026-01-01',
            'until_date' => '2026-06-30',
            'request' => 'Erstelle einen belegten Entwurf.',
        ];
    }
}
