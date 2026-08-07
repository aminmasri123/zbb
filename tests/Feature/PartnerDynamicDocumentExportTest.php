<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Dokumente;
use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PartnerDynamicDocumentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_partner_document_is_listed_for_the_active_project(): void
    {
        [$user, $project, $partner] = $this->projectContext();
        $document = $this->partnerDocument($project, 'dokumente.export.partner-test');
        $this->givePermission($user, 'kooperationspartner.index');
        $this->givePermission($user, $document->export_permission);

        $this->actingAs($user)
            ->get(route('partner.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partner/Index')
                ->has('partnerDokumente', 1)
                ->where('partnerDokumente.0.id', $document->id)
                ->where('partnerDokumente.0.name', 'Partner Vorlage')
                ->where('partnerDokumente.0.ausgabeformate', ['pdf']));
    }

    public function test_partner_document_export_requires_its_individual_permission(): void
    {
        [$user, $project, $partner] = $this->projectContext();
        $document = $this->partnerDocument($project, 'dokumente.export.partner-forbidden');

        $this->actingAs($user)
            ->get(route('partner.document.export', [
                'partner' => $partner,
                'dokument' => $document,
                'schuljahr' => '2026/2027',
                'teil' => '1',
                'format' => 'pdf',
            ]))
            ->assertForbidden();
    }

    public function test_authorized_partner_document_can_be_downloaded(): void
    {
        [$user, $project, $partner] = $this->projectContext();
        $document = $this->partnerDocument($project, 'dokumente.export.partner-download');
        $this->givePermission($user, $document->export_permission);

        $path = storage_path('app/temp/partner-document-test.pdf');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, '%PDF-1.4 test');
        $document->update(['dateipfad' => '/app/temp/partner-document-test.pdf']);

        try {
            $this->actingAs($user)
                ->get(route('partner.document.export', [
                    'partner' => $partner,
                    'dokument' => $document,
                    'schuljahr' => '2026/2027',
                    'teil' => '1',
                    'format' => 'pdf',
                ]))
                ->assertOk()
                ->assertDownload('Partner_Vorlage_Testschule.pdf');
        } finally {
            @unlink($path);
        }
    }

    private function projectContext(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);

        return [$user, $project, $partner];
    }

    private function partnerDocument(Projekt $project, string $permission): Dokumente
    {
        $this->ensurePermission($permission);

        $document = Dokumente::query()->create([
            'name' => 'Partner Vorlage',
            'typ' => 'pdf',
            'kontext' => 'partner',
            'einsatzbereich' => 'partner',
            'ausgabeformate' => ['pdf'],
            'dateipfad' => '/app/temp/not-created.pdf',
            'aktiv' => true,
            'export_permission' => $permission,
        ]);
        $project->dokumente()->attach($document->id, [
            'gruppen_export' => false,
            'serienbrief' => true,
            'sort_order' => 0,
        ]);

        return $document;
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Dokumentenexporte'],
            ['beschreibung' => '']
        )->id;
        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => null]
        );
    }
}
