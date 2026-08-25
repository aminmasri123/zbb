<?php

namespace Tests\Feature;

use App\Models\Dokumente;
use App\Models\DokumentPaket;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_and_delete_an_ordered_document_package(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'projekt.index');
        $project = Projekt::factory()->create();
        $first = $this->participantPdfDocument('Erstes Dokument');
        $second = $this->participantPdfDocument('Zweites Dokument');
        $project->dokumente()->attach([$first->id, $second->id]);

        $this->actingAs($user)->post(route('dokumente.pakete.store'), [
            'name' => 'TLN Empfang',
            'beschreibung' => 'Alle Unterlagen für den Empfang',
            'aktiv' => true,
            'projekt_ids' => [$project->id],
            'dokument_ids' => [$second->id, $first->id],
        ])->assertRedirect(route('dokumente.index'));

        $package = DokumentPaket::query()->where('name', 'TLN Empfang')->firstOrFail();
        $this->assertSame([$second->id, $first->id], $package->dokumente()->pluck('dokumentes.id')->all());
        $this->assertTrue($package->projekte()->whereKey($project->id)->exists());

        $this->actingAs($user)->put(route('dokumente.pakete.update', $package), [
            'name' => 'TLN Aufnahme',
            'beschreibung' => null,
            'aktiv' => false,
            'projekt_ids' => [$project->id],
            'dokument_ids' => [$first->id],
        ])->assertRedirect(route('dokumente.index'));

        $this->assertDatabaseHas('dokument_pakete', [
            'id' => $package->id,
            'name' => 'TLN Aufnahme',
            'aktiv' => false,
        ]);
        $this->assertDatabaseCount('dokument_paket_has_dokumentes', 1);

        $this->actingAs($user)
            ->delete(route('dokumente.pakete.destroy', $package))
            ->assertRedirect(route('dokumente.index'));
        $this->assertDatabaseMissing('dokument_pakete', ['id' => $package->id]);
    }

    public function test_package_rejects_templates_without_pdf_output(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'projekt.index');
        $project = Projekt::factory()->create();
        $document = $this->participantPdfDocument('Nur Word');
        $document->update(['typ' => 'word', 'ausgabeformate' => ['docx']]);
        $project->dokumente()->attach($document->id);

        $this->actingAs($user)->post(route('dokumente.pakete.store'), [
            'name' => 'Ungültiges Paket',
            'aktiv' => true,
            'projekt_ids' => [$project->id],
            'dokument_ids' => [$document->id],
        ])->assertSessionHasErrors('dokument_ids');

        $this->assertDatabaseMissing('dokument_pakete', ['name' => 'Ungültiges Paket']);
    }

    private function participantPdfDocument(string $name): Dokumente
    {
        return Dokumente::query()->create([
            'name' => $name,
            'typ' => 'pdf',
            'kontext' => 'teilnehmer',
            'einsatzbereich' => 'teilnehmer',
            'ausgabeformate' => ['pdf'],
            'dateipfad' => '/app/temp/test.pdf',
            'aktiv' => true,
        ]);
    }
}
