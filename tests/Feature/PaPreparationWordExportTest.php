<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class PaPreparationWordExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_pa_exports_a_word_document_with_saved_signatures_and_15mm_margins(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::query()->create(['name' => 'Testschule Vorbereitung']);

        DB::table('projekt_has_partners')->insert([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $project->id]);

        $person = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Mina',
            'nachname' => 'Muster',
        ]);
        PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => '7.1',
            'schule_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
        ]);

        $day = [
            'id' => 'pa-vorbereitung-2026-09-03',
            'date' => '2026-09-03',
            'type' => 'preparation',
            'selected' => true,
        ];
        $scope = [
            'schuleId' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'listType' => 'pa_preparation',
            'exportMode' => 'klasse',
            'klasse' => '7.1',
        ];
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $this->actingAs($user)->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + [
            'payload' => [
                'version' => 1,
                'form' => ['exportFormat' => 'A4', 'exportMode' => 'klasse', 'klasse' => '7.1'],
                'days' => [$day],
                'selectedDayId' => $day['id'],
                'signatures' => [$day['id'] . ':' . $person->id => $signature],
            ],
        ])->assertOk();

        $response = $this->actingAs($user->fresh())->post(route('anwesenheitsliste.PA.preparation.export.word'), $scope + [
            'exportFormat' => 'A4',
        ]);

        $response->assertOk()->assertDownload();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);

        try {
            $documentXml = (string) $zip->getFromName('word/document.xml');
            $this->assertStringContainsString('Anwesenheitsliste Vorbereitung Potenzialanalyse', $documentXml);
            $this->assertStringContainsString('Mina', $documentXml);
            $this->assertStringContainsString('Muster', $documentXml);
            $this->assertMatchesRegularExpression('/<w:pgMar[^>]*w:top="850"[^>]*w:right="850"[^>]*w:bottom="1250"[^>]*w:left="850"/', $documentXml);

            $mediaFiles = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);
                if ($entry && str_starts_with($entry, 'word/media/')) {
                    $mediaFiles[] = $entry;
                }
            }
            $this->assertGreaterThanOrEqual(2, count($mediaFiles));
        } finally {
            $zip->close();
        }
    }
}
