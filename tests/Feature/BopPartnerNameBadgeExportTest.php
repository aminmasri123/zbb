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

class BopPartnerNameBadgeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_page_exports_the_same_name_badge_document_for_the_selected_bop_school_part(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'gruppe.bop.export.namensschilder');
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::query()->create(['name' => 'Testschule']);

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

        foreach ([
            ['Anna', 'Muster', '1'],
            ['Ben', 'Beispiel', '1'],
            ['Nicht', 'Exportieren', '2'],
        ] as [$firstName, $lastName, $part]) {
            $person = Personen::factory()->create([
                'typ' => 'teilnehmer',
                'vorname' => $firstName,
                'nachname' => $lastName,
            ]);
            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'klasse' => '7.1',
                'schule_id' => $school->id,
                'schuljahr' => '2026/2027',
                'teil' => $part,
            ]);
        }

        $response = $this->actingAs($user->fresh())->get(route('partner.bop.export.namensschilder', [
            'partner' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
        ]));

        $response->assertOk()->assertDownload();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);

        try {
            $documentXml = (string) $zip->getFromName('word/document.xml');
            $this->assertStringContainsString('Anna Muster', $documentXml);
            $this->assertStringContainsString('Ben Beispiel', $documentXml);
            $this->assertStringNotContainsString('Nicht Exportieren', $documentXml);
            $this->assertSame(4, substr_count($documentXml, '-----------------------'));
        } finally {
            $zip->close();
        }
    }
}
