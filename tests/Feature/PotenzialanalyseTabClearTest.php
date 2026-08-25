<?php

namespace Tests\Feature;

use App\Http\Controllers\PotenzialanalyseController;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseBeurteilung;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Models\PotenzialanalyseKriterium;
use App\Models\PotenzialanalyseSelbsteinschaetzung;
use App\Models\PotenzialanalyseUebung;
use App\Models\PotenzialanalyseUebungErgebnis;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use App\Services\Bop\PotenzialanalyseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PotenzialanalyseTabClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_only_exercises_configured_for_display(): void
    {
        [$gruppe, $teilnehmer, $sichtbareUebung] = $this->paContext();
        $sichtbareUebung->update(['im_bericht_anzeigen' => true]);
        $verdeckteUebung = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $gruppe->projekt_id,
            'name' => 'Interne Übung',
            'im_bericht_anzeigen' => false,
            'aktiv' => true,
        ]);

        foreach ([$sichtbareUebung, $verdeckteUebung] as $uebung) {
            PotenzialanalyseUebungErgebnis::query()->create([
                'gruppe_id' => $gruppe->id,
                'personen_id' => $teilnehmer->id,
                'uebung_id' => $uebung->id,
                'punkte' => 10,
            ]);
        }

        $report = app(PotenzialanalyseReportService::class)->reportData($gruppe, $teilnehmer);

        $this->assertSame([$sichtbareUebung->name], $report['uebungen']->pluck('name')->all());
    }

    public function test_exercise_results_ignore_submitted_time_when_time_capture_is_disabled(): void
    {
        [$gruppe, $teilnehmer, $uebung] = $this->paContext();
        $user = User::factory()->create();
        $this->actingAs($user);
        $uebung->update([
            'berechnungsregel' => 'direkte_punkte',
            'zeit_erfassen' => false,
            'hoechstwert' => 20,
            'auswertbar' => true,
        ]);

        $controller = app(PotenzialanalyseController::class);
        $this->invokePrivate($controller, 'syncUebungErgebnisse', [[
            $uebung->id => ['punkte' => 15, 'zeit_min' => 1, 'zeit_sec' => 30],
        ], $gruppe, $teilnehmer, collect([$uebung->id => $uebung->fresh()])]);

        $this->assertDatabaseHas('potenzialanalyse_uebung_ergebnisse', [
            'gruppe_id' => $gruppe->id,
            'personen_id' => $teilnehmer->id,
            'uebung_id' => $uebung->id,
            'punkte' => 15,
            'zeit' => 0,
        ]);
    }

    public function test_all_pa_tabs_are_really_deleted_by_an_empty_full_snapshot(): void
    {
        [$gruppe, $teilnehmer, $uebung, $kriterium] = $this->paContext();
        $user = User::factory()->create();
        $this->actingAs($user);

        PotenzialanalyseUebungErgebnis::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $teilnehmer->id,
            'uebung_id' => $uebung->id,
            'user_id' => $user->id,
            'punkte' => 10,
            'zeit' => 90,
        ]);

        foreach (['selbst', 'anleiter'] as $typ) {
            PotenzialanalyseKompetenzbewertung::query()->create([
                'gruppe_id' => $gruppe->id,
                'personen_id' => $teilnehmer->id,
                'user_id' => $user->id,
                'typ' => $typ,
                'merkmal' => 'feinmotorik',
                'bewertung' => 5,
                'bemerkung' => 'Vorhanden',
            ]);
        }

        PotenzialanalyseBeurteilung::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $teilnehmer->id,
            'kriterium_id' => $kriterium->id,
            'user_id' => $user->id,
            'bewertung' => 5,
            'bemerkung' => 'Vorhanden',
        ]);
        PotenzialanalyseSelbsteinschaetzung::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $teilnehmer->id,
            'kriterium_id' => $kriterium->id,
            'user_id' => $user->id,
            'bewertung' => 5,
            'bemerkung' => 'Vorhanden',
        ]);
        PotenzialanalyseBericht::query()->create([
            'gruppe_id' => $gruppe->id,
            'personen_id' => $teilnehmer->id,
            'user_id' => $user->id,
            'status' => 'fertig',
            'bericht_text' => 'Vorhanden',
        ]);

        $controller = app(PotenzialanalyseController::class);
        $this->invokePrivate($controller, 'syncUebungErgebnisse', [[], $gruppe, $teilnehmer, collect([$uebung->id => $uebung])]);
        $this->invokePrivate($controller, 'syncKompetenzbewertungen', ['selbst', [], $gruppe, $teilnehmer]);
        $this->invokePrivate($controller, 'syncKompetenzbewertungen', ['anleiter', [], $gruppe, $teilnehmer]);
        $this->invokePrivate($controller, 'syncBewertungen', [PotenzialanalyseBeurteilung::class, [], $gruppe, $teilnehmer, collect([$kriterium->id]), false]);
        $this->invokePrivate($controller, 'syncBewertungen', [PotenzialanalyseSelbsteinschaetzung::class, [], $gruppe, $teilnehmer, collect([$kriterium->id]), true]);
        $this->invokePrivate($controller, 'syncBericht', [[
            'status' => 'entwurf',
            'staerken' => '',
            'entwicklungsfelder' => '',
            'empfehlung' => '',
            'bericht_text' => '',
            'generator_stil' => 'staerkenorientiert',
            'generator_snapshot' => null,
        ], $gruppe, $teilnehmer]);

        $this->assertDatabaseMissing('potenzialanalyse_uebung_ergebnisse', ['gruppe_id' => $gruppe->id, 'personen_id' => $teilnehmer->id]);
        $this->assertDatabaseMissing('potenzialanalyse_kompetenzbewertungen', ['gruppe_id' => $gruppe->id, 'personen_id' => $teilnehmer->id]);
        $this->assertDatabaseMissing('potenzialanalyse_beurteilungen', ['gruppe_id' => $gruppe->id, 'personen_id' => $teilnehmer->id]);
        $this->assertDatabaseMissing('potenzialanalyse_selbsteinschaetzungen', ['gruppe_id' => $gruppe->id, 'personen_id' => $teilnehmer->id]);
        $this->assertDatabaseMissing('potenzialanalyse_berichte', ['gruppe_id' => $gruppe->id, 'personen_id' => $teilnehmer->id]);
    }

    private function paContext(): array
    {
        $betreuer = Personen::factory()->create();
        $teilnehmer = Personen::factory()->create(['typ' => 'teilnehmer']);
        $standort = Standort::factory()->create();
        $projekt = Projekt::factory()->create();
        $bereich = Bereich::query()->create(['name' => 'PA-Testbereich']);
        $raum = Raeume::query()->create([
            'name' => 'PA-Testraum',
            'standort_id' => $standort->id,
            'typ' => 'Seminarraum',
            'aktiv' => true,
        ]);
        $gruppe = Gruppe::query()->create([
            'personen_id' => $betreuer->id,
            'bereich_id' => $bereich->id,
            'projekt_id' => $projekt->id,
            'standort_id' => $standort->id,
            'raum_id' => $raum->id,
        ]);
        $uebung = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $projekt->id,
            'name' => 'Testübung',
            'aktiv' => true,
        ]);
        $kriterium = PotenzialanalyseKriterium::query()->create([
            'uebung_id' => $uebung->id,
            'name' => 'Testkriterium',
            'aktiv' => true,
        ]);

        return [$gruppe, $teilnehmer, $uebung, $kriterium];
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
