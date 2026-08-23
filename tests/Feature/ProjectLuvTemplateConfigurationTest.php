<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\ProjektLuvTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class ProjectLuvTemplateConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_admin_can_create_versions_and_restore_an_older_version(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'projekt.update');
        $project = Projekt::factory()->create();

        $first = $this->actingAs($user)->postJson(route('projekt.luv-templates.store', $project), [
            'name' => 'Förderprojekt Standard',
            'ai_instructions' => 'Schreibe wertschätzend und knapp.',
            'sections' => $this->sections('Erste Ausgangssituation'),
        ])->assertCreated()
            ->assertJsonPath('templates.0.version', 1)
            ->assertJsonPath('templates.0.is_active', true);

        $firstId = $first->json('templates.0.id');

        $this->actingAs($user)->postJson(route('projekt.luv-templates.store', $project), [
            'name' => 'Förderprojekt Kompakt',
            'ai_instructions' => 'Verwende kurze Sätze.',
            'sections' => $this->sections('Neue Ausgangssituation'),
        ])->assertCreated()
            ->assertJsonPath('templates.0.version', 2)
            ->assertJsonPath('templates.0.is_active', true)
            ->assertJsonPath('templates.1.is_active', false);

        $this->assertSame(1, ProjektLuvTemplate::query()->where('projekt_id', $project->id)->where('is_active', true)->count());

        $this->actingAs($user)->putJson(route('projekt.luv-templates.activate', [$project, $firstId]))
            ->assertOk();

        $this->assertDatabaseHas('projekt_luv_templates', [
            'id' => $firstId,
            'projekt_id' => $project->id,
            'version' => 1,
            'is_active' => true,
        ]);
        $this->assertSame(1, ProjektLuvTemplate::query()->where('projekt_id', $project->id)->where('is_active', true)->count());
    }

    public function test_template_from_another_project_cannot_be_activated(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'projekt.update');
        $project = Projekt::factory()->create();
        $otherProject = Projekt::factory()->create();
        $template = ProjektLuvTemplate::create([
            'projekt_id' => $otherProject->id,
            'version' => 1,
            'name' => 'Fremde Vorlage',
            'sections' => $this->sections('Fremd'),
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson(route('projekt.luv-templates.activate', [$project, $template]))
            ->assertNotFound();
    }

    public function test_valid_docx_is_stored_privately_and_unsupported_placeholders_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'projekt.update');
        $project = Projekt::factory()->create();

        $this->actingAs($user)->post(route('projekt.luv-templates.store', $project), [
            'name' => 'Word-Version',
            'sections' => $this->sections('Ausgangssituation'),
            'template' => $this->docxUpload('${vorname} ${nachname} ${qualifikationen}'),
        ])->assertCreated();

        $template = ProjektLuvTemplate::query()->where('projekt_id', $project->id)->sole();
        $this->assertNotNull($template->file_path);
        Storage::disk('local')->assertExists($template->file_path);

        $this->actingAs($user)->post(route('projekt.luv-templates.store', $project), [
            'name' => 'Ungültige Word-Version',
            'sections' => $this->sections('Ausgangssituation'),
            'template' => $this->docxUpload('${nichtErlaubt}'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('template');

        $this->assertSame(1, ProjektLuvTemplate::query()->where('projekt_id', $project->id)->count());
    }

    public function test_user_without_project_update_permission_cannot_change_templates(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();

        $this->actingAs($user)->postJson(route('projekt.luv-templates.store', $project), [
            'name' => 'Nicht erlaubt',
            'sections' => $this->sections('Nicht erlaubt'),
        ])->assertForbidden();

        $this->assertDatabaseEmpty('projekt_luv_templates');
    }

    private function sections(string $heading): array
    {
        return [[
            'key' => 'ausgangssituation',
            'heading' => $heading,
            'instruction' => 'Nur belegte Angaben verwenden.',
            'required' => true,
        ]];
    }

    private function docxUpload(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zbb-luv-docx-');
        $document = new PhpWord();
        $document->addSection()->addText($content);
        IOFactory::createWriter($document, 'Word2007')->save($path);

        return new UploadedFile(
            $path,
            'projekt-luv.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }
}
