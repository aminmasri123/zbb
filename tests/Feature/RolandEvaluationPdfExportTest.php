<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class RolandEvaluationPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_accepts_start_year_and_downloads_only_selected_class(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);

        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'dokumente.schule.export');
        $this->grantTestPermission($user, 'potenzialanalyse.index');

        foreach ([['Alpha', '8a'], ['Beta', '8a'], ['Gamma', '8b']] as [$lastName, $class]) {
            $person = Personen::factory()->create([
                'vorname' => 'Erika',
                'nachname' => $lastName,
                'typ' => 'teilnehmer',
            ]);

            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'klasse' => $class,
                'schule_id' => $partner->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
            ]);
        }

        $response = $this->actingAs($user)->get(route(
            'export.auswertungsbogenPA.roland.schule.pdf',
            ['partnerId' => $partner->id, 'schuljahr' => '2026', 'teil' => '1', 'klasse' => '8a']
        ));

        $response->assertOk()->assertDownload('Auswertungsbogen_PA_neu_Roland_Testschule_2026_Teil_1_Klasse_8a.pdf');
        $path = $response->baseResponse->getFile()->getPathname();

        try {
            $pdf = new Fpdi();
            $this->assertSame(2, $pdf->setSourceFile($path));
        } finally {
            File::delete($path);
        }
    }
}
