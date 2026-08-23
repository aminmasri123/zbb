<?php

namespace Tests\Feature;

use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\User;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_authorized_user_receives_only_a_draft_response(): void
    {
        [$user, , $participant] = $this->context(true);
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'kind' => 'final',
                'run_id' => $payload['run_id'],
                'report' => [
                    'report_type' => 'luv',
                    'title' => 'Lokaler Entwurf',
                    'sections' => [[
                        'heading' => 'Datenlage',
                        'claims' => [[
                            'claim_id' => 'missing-1',
                            'text' => 'Weitere Daten werden benoetigt.',
                            'status' => 'insufficient_data',
                            'source_ids' => [],
                        ]],
                    ]],
                    'warnings' => [],
                ],
            ]);
        });

        $this->actingAs($user)->postJson('/ki/berichte/entwurf', $this->payload($participant->id))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('report.title', 'Lokaler Entwurf');
    }

    public function test_missing_permission_is_denied_before_the_agent_is_contacted(): void
    {
        [$user, , $participant] = $this->context(false);
        Http::fake();

        $this->actingAs($user)->postJson('/ki/berichte/entwurf', $this->payload($participant->id))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_invalid_period_is_rejected_before_the_agent_is_contacted(): void
    {
        [$user, , $participant] = $this->context(true);
        Http::fake();
        $payload = $this->payload($participant->id);
        $payload['until_date'] = '2025-12-31';

        $this->actingAs($user)->postJson('/ki/berichte/entwurf', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('until_date');

        Http::assertNothingSent();
    }

    public function test_agent_failure_returns_a_generic_service_unavailable_response(): void
    {
        [$user, , $participant] = $this->context(true);
        Http::fake(['*' => Http::response(['detail' => 'sensitive-upstream-detail'], 500)]);

        $response = $this->actingAs($user)
            ->postJson('/ki/berichte/entwurf', $this->payload($participant->id))
            ->assertServiceUnavailable()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringNotContainsString('sensitive-upstream-detail', $response->getContent());
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
