<?php

namespace Tests\Feature;

use App\Models\AiWorkspaceRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiWorkspaceEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.zbb_ai_agent',['base_url'=>'http://127.0.0.1:18000','key_id'=>'laravel','secret'=>'test-secret-that-is-at-least-32-bytes-long','connect_timeout'=>3,'timeout'=>130,'max_response_bytes'=>1000000]);
        Http::preventStrayRequests();
    }

    public function test_authorized_user_can_generate_and_audit_a_chat_result(): void
    {
        $user=User::factory()->create();$this->grantTestPermission($user,'ai.report.use');
        Http::fake(function(Request $request){$payload=$request->data();return Http::response(['run_id'=>$payload['run_id'],'task'=>'chat','title'=>'Antwort','content'=>'Lokale KI-Antwort','citations'=>[],'warnings'=>[]]);});
        $this->actingAs($user)->postJson('/ki/generieren',['task'=>'chat','instruction'=>'Formuliere einen Testsatz.'])
            ->assertOk()->assertJsonPath('run.task','chat')->assertJsonPath('run.content','Lokale KI-Antwort');
        $this->assertDatabaseHas('ai_workspace_runs',['user_id'=>$user->id,'task'=>'chat','status'=>'completed']);
    }

    public function test_user_without_permission_cannot_reach_the_agent(): void
    {
        $user=User::factory()->create();Http::fake();
        $this->actingAs($user)->postJson('/ki/generieren',['task'=>'chat','instruction'=>'Nicht erlaubt'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_workspace_and_history_routes_require_the_ai_permission(): void
    {
        $user = User::factory()->create();
        $run = AiWorkspaceRun::create([
            'user_id' => $user->id,
            'run_uuid' => '123e4567-e89b-42d3-a456-426614174001',
            'task' => 'chat',
            'instruction' => 'Nicht freigegeben',
            'title' => 'Gesperrt',
            'content' => 'Gesperrt',
            'citations' => [],
            'warnings' => [],
            'status' => 'completed',
        ]);

        $this->actingAs($user)->get('/ki')->assertForbidden();
        $this->actingAs($user)->get("/ki/laeufe/{$run->id}/pdf")->assertForbidden();
        $this->actingAs($user)->deleteJson("/ki/laeufe/{$run->id}")->assertForbidden();
        $this->actingAs($user)->deleteJson('/ki/laeufe')->assertForbidden();

        $this->assertDatabaseHas('ai_workspace_runs', ['id' => $run->id]);
    }

    public function test_guest_is_redirected_to_login_from_the_workspace(): void
    {
        $this->get('/ki')->assertRedirect('/login');
    }

    public function test_user_cannot_export_another_users_result(): void
    {
        $owner=User::factory()->create();$other=User::factory()->create();$this->grantTestPermission($other,'ai.report.use');
        $run=AiWorkspaceRun::create(['user_id'=>$owner->id,'run_uuid'=>'123e4567-e89b-42d3-a456-426614174000','task'=>'chat','instruction'=>'Test','title'=>'Privat','content'=>'Privat','citations'=>[],'warnings'=>[],'status'=>'completed']);
        $this->actingAs($other)->get('/ki/laeufe/'.$run->id.'/pdf')->assertNotFound();
    }
}
