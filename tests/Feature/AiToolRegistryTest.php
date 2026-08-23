<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\ProjektLuvTemplate;
use App\Models\User;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_rules_only_for_the_bound_user_and_active_project(): void
    {
        [$user, $project] = $this->authorizedUserAndProject();
        $context = new AiRunContext($user->id, $project->id, [GetProjectReportRulesTool::NAME]);

        $result = app(AiToolRegistry::class)->execute(
            $user,
            $context,
            GetProjectReportRulesTool::NAME,
        );

        $this->assertSame($project->id, $result['project_id']);
        $this->assertTrue($result['rules']['attendance_skip_weekends']);
        $this->assertSame($project->ruleSettings(), $result['rules']);
        $this->assertSame($project->featureSettings(), $result['features']);
    }

    public function test_it_denies_a_tool_not_allowlisted_for_the_run(): void
    {
        [$user, $project] = $this->authorizedUserAndProject();
        $context = new AiRunContext($user->id, $project->id, ['another_tool']);

        $this->expectException(AuthorizationException::class);

        app(AiToolRegistry::class)->execute($user, $context, GetProjectReportRulesTool::NAME);
    }

    public function test_it_returns_the_active_project_luv_configuration_without_a_file_path(): void
    {
        [$user, $project] = $this->authorizedUserAndProject();
        ProjektLuvTemplate::create([
            'projekt_id' => $project->id,
            'version' => 3,
            'name' => 'Projekt-LuV',
            'file_path' => 'private/secret-template.docx',
            'sections' => [[
                'key' => 'fortschritt',
                'heading' => 'Individueller Fortschritt',
                'instruction' => 'Nur belegte Fortschritte nennen.',
                'required' => true,
            ]],
            'ai_instructions' => 'Formell und knapp.',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $context = new AiRunContext($user->id, $project->id, [GetProjectReportRulesTool::NAME]);

        $result = app(AiToolRegistry::class)->execute($user, $context, GetProjectReportRulesTool::NAME);

        $this->assertSame('Projekt-LuV', $result['luv_template']['name']);
        $this->assertSame(3, $result['luv_template']['version']);
        $this->assertSame('Individueller Fortschritt', $result['luv_template']['sections'][0]['heading']);
        $this->assertArrayNotHasKey('file_path', $result['luv_template']);
    }

    public function test_it_denies_a_project_that_is_not_the_users_active_project(): void
    {
        [$user, $project] = $this->authorizedUserAndProject();
        $otherProject = Projekt::factory()->create();
        $user->projekte()->attach($otherProject->id);
        $context = new AiRunContext($user->id, $otherProject->id, [GetProjectReportRulesTool::NAME]);

        $this->expectException(AuthorizationException::class);

        app(AiToolRegistry::class)->execute($user, $context, GetProjectReportRulesTool::NAME);
    }

    public function test_it_denies_a_user_without_the_ai_permission(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $context = new AiRunContext($user->id, $project->id, [GetProjectReportRulesTool::NAME]);

        $this->expectException(AuthorizationException::class);

        app(AiToolRegistry::class)->execute($user, $context, GetProjectReportRulesTool::NAME);
    }

    public function test_the_model_cannot_supply_tool_arguments(): void
    {
        [$user, $project] = $this->authorizedUserAndProject();
        $context = new AiRunContext($user->id, $project->id, [GetProjectReportRulesTool::NAME]);

        $this->expectException(ValidationException::class);

        app(AiToolRegistry::class)->execute(
            $user,
            $context,
            GetProjectReportRulesTool::NAME,
            ['project_id' => $project->id],
        );
    }

    /** @return array{User, Projekt} */
    private function authorizedUserAndProject(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create([
            'rule_settings' => ['attendance_skip_weekends' => true],
        ]);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $this->grantTestPermission($user, GetProjectReportRulesTool::PERMISSION);

        return [$user->fresh(), $project->fresh()];
    }
}
