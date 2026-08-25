<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\PotenzialanalyseUebung;
use App\Services\PotenzialanalyseProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PotenzialanalyseProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_project_can_start_with_a_completely_empty_data_driven_profile(): void
    {
        $project = Projekt::factory()->create(['potenzialanalyse_aktiv' => true]);

        $profile = app(PotenzialanalyseProfileService::class)
            ->createEmptyProfile($project, 'Freies Projektprofil');

        $this->assertSame('entwurf', $profile->status);
        $this->assertSame('Freies Projektprofil', $profile->name);
        $this->assertSame($profile->id, $project->fresh()->potenzialanalyse_profil_id);
        $this->assertCount(0, $profile->kompetenzen);
        $this->assertCount(0, $profile->uebungen);
    }

    #[Test]
    public function hamet_eplus_is_only_a_editable_template_with_complete_configurable_weights(): void
    {
        $project = Projekt::factory()->create(['potenzialanalyse_aktiv' => true]);

        $profile = app(PotenzialanalyseProfileService::class)
            ->createHametEPlusProfile($project);

        $this->assertSame('entwurf', $profile->status);
        $this->assertCount(16, $profile->kompetenzen);
        $this->assertCount(10, $profile->uebungen);
        $this->assertSame(
            ['Persönliche Kompetenzen', 'Praktische Kompetenzen', 'Methodische Kompetenzen', 'Soziale Kompetenzen'],
            $profile->kompetenzen->pluck('kategorie_label')->unique()->values()->all(),
        );

        $weightSums = $profile->uebungen
            ->flatMap->kompetenzZuordnungen
            ->groupBy('merkmal')
            ->map(fn ($entries) => (float) $entries->sum('gewichtung'));

        $this->assertCount(16, $weightSums);
        $this->assertTrue($weightSums->every(fn (float $sum) => $sum === 100.0));

        $pinsel = $profile->uebungen->firstWhere('name', 'Pinsel');
        $schrauben = $profile->uebungen->firstWhere('name', 'Schrauben');
        $this->assertSame('fehler_abzug', $pinsel->berechnungsregel);
        $this->assertSame(['fehler', 'qualitaet'], $pinsel->berechnungs_config['rohwerte']);
        $this->assertFalse($pinsel->auswertung_hervorheben);
        $this->assertTrue($pinsel->im_bericht_anzeigen);
        $this->assertFalse($pinsel->zeit_erfassen);
        $this->assertTrue($schrauben->zeit_erfassen);
        $this->assertFalse($pinsel->auswertbar, 'Die Vorlage darf ohne fachlich bestätigte Maximalpunkte nicht automatisch auswerten.');
    }

    #[Test]
    public function arbitrary_future_project_competencies_and_categories_can_be_published_and_versioned(): void
    {
        $project = Projekt::factory()->create(['potenzialanalyse_aktiv' => true]);
        $service = app(PotenzialanalyseProfileService::class);
        $profile = $service->createEmptyProfile($project, 'Projekt Zukunft');
        $profile->kompetenzen()->create([
            'key' => 'lernflexibilitaet',
            'label' => 'Lernflexibilität',
            'kategorie' => 'zukunft',
            'kategorie_label' => 'Zukunftskompetenzen',
            'kategorie_code' => 'ZK',
            'sort_order' => 1,
            'aktiv' => true,
        ]);
        $exercise = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $project->id,
            'profil_id' => $profile->id,
            'name' => 'Neue Aufgabe',
            'berechnungsregel' => 'direkte_punkte',
            'auswertung_hervorheben' => true,
            'im_bericht_anzeigen' => false,
            'zeit_erfassen' => true,
            'hoechstwert' => 10,
            'auswertbar' => true,
            'aktiv' => true,
        ]);
        $exercise->kompetenzZuordnungen()->create([
            'merkmal' => 'lernflexibilitaet',
            'gewichtung' => 100,
            'aktiv' => true,
        ]);

        $published = $service->publish($profile);
        $newVersion = $service->createNewVersion($published);
        $newVersion->kompetenzen->first()->update(['label' => 'Anpassungs- und Lernfähigkeit']);

        $this->assertSame('veroeffentlicht', $published->status);
        $this->assertSame(2, $newVersion->version);
        $this->assertSame('entwurf', $newVersion->status);
        $this->assertSame('Lernflexibilität', $published->fresh('kompetenzen')->kompetenzen->first()->label);
        $this->assertSame('Anpassungs- und Lernfähigkeit', $newVersion->fresh('kompetenzen')->kompetenzen->first()->label);
        $this->assertTrue($newVersion->uebungen->first()->auswertung_hervorheben);
        $this->assertFalse($newVersion->uebungen->first()->im_bericht_anzeigen);
        $this->assertTrue($newVersion->uebungen->first()->zeit_erfassen);
    }

    #[Test]
    public function an_unused_draft_can_be_discarded_so_the_template_can_be_selected_again(): void
    {
        $project = Projekt::factory()->create(['potenzialanalyse_aktiv' => true]);
        $service = app(PotenzialanalyseProfileService::class);
        $profile = $service->createEmptyProfile($project, 'Falsche Vorlage');
        $exercise = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $project->id,
            'profil_id' => $profile->id,
            'name' => 'Testübung',
            'aktiv' => true,
        ]);

        $fallback = $service->discardDraft($profile->load('projekt'));

        $this->assertNull($fallback);
        $this->assertNull($project->fresh()->potenzialanalyse_profil_id);
        $this->assertDatabaseMissing('potenzialanalyse_profile', ['id' => $profile->id]);
        $this->assertDatabaseMissing('potenzialanalyse_uebungen', ['id' => $exercise->id]);
    }
}
