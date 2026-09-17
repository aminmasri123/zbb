<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\PotenzialanalyseBericht;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class PotenzialanalyseGroupExportMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_reports_are_available_without_a_school_export_context_and_match_individual_reports(): void
    {
        [$user, $group, $participants] = $this->paGroup();
        $this->grantTestPermission($user, 'gruppe.bop.export.berichte-pa');

        $items = $this->exportMenu($user, $group);
        $report = $items->firstWhere('id', 'bop-pa-berichte-gruppe');
        $this->assertNotNull($report);
        $this->assertSame('PDF', $report['format']);
        $this->assertSame(route('potenzialanalyse.gruppe.berichte', $group), $report['url']);
        $this->assertNull($items->firstWhere('id', 'bop-pa-auswertungsbogen'));
        $this->assertNull($items->firstWhere('id', 'bop-pa-berichte-ordner'));

        $response = $this->get($report['url'])->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $pages = (new Parser)->parseFile($path)->getPages();
            $this->assertCount(8, $pages);
            foreach ($participants->sortBy('nachname')->values() as $index => $participant) {
                $individual = $this->get(route('potenzialanalyse.gruppe.teilnehmer.bericht', [
                    'gruppe' => $group, 'personen' => $participant,
                ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
                $singlePages = (new Parser)->parseContent($individual->getContent())->getPages();
                $this->assertCount(4, $singlePages);
                $groupText = '';
                foreach ($singlePages as $pageIndex => $page) {
                    $groupPageText = $pages[$index * 4 + $pageIndex]->getText();
                    $this->assertSame($this->normalizeText($page->getText()), $this->normalizeText($groupPageText));
                    $groupText .= $groupPageText;
                }
                $this->assertStringContainsString($participant->nachname, $groupText);
                $this->assertStringContainsString('Bericht für '.$participant->vorname, $groupText);
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_school_exports_use_student_context_loaded_with_the_group_page(): void
    {
        [$user, $group, $participants] = $this->paGroup();
        $this->grantTestPermission($user, 'gruppe.bop.export.berichte-pa');
        $this->grantTestPermission($user, 'dokumente.schule.export');
        $school = Partner::query()->create(['name' => 'Testschule']);
        foreach ($participants as $participant) {
            PersonenIstSchueler::query()->create([
                'person_id' => $participant->id, 'schule_id' => $school->id,
                'schuljahr' => '2026', 'teil' => '1', 'klasse' => '7.1',
            ]);
        }

        $items = $this->exportMenu($user, $group);
        $this->assertNotNull($items->firstWhere('id', 'bop-pa-berichte-gruppe'));
        $schoolExport = $items->firstWhere('id', 'bop-pa-auswertungsbogen');
        $this->assertNotNull($schoolExport);
        $this->assertSame(route('export.auswertungsbogenPA.schule.pdf', [
            'partnerId' => $school->id, 'schuljahr' => '2026', 'teil' => '1',
        ]), $schoolExport['url']);
    }

    public function test_report_export_still_requires_its_permission(): void
    {
        [$user, $group] = $this->paGroup();
        $items = $this->exportMenu($user, $group);
        $this->assertNull($items->firstWhere('id', 'bop-pa-berichte-gruppe'));
        $this->get(route('potenzialanalyse.gruppe.berichte', $group))->assertForbidden();
    }

    private function exportMenu(User $user, Gruppe $group): \Illuminate\Support\Collection
    {
        $items = collect();
        $this->actingAs($user)->get(route('gruppeHasTeilnehmer.show', $group->id))
            ->assertOk()->assertInertia(function (Assert $page) use (&$items) {
                $page->component('Gruppe/GruppeHasTeilnehmer/Index')
                    ->where('bopLegacyExporte', function ($exports) use (&$items) {
                        $items = collect($exports);
                        return true;
                    });
            });
        return $items;
    }

    private function paGroup(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create([
            'name' => 'BOP', 'potenzialanalyse_aktiv' => true,
            'feature_settings' => [
                'group_management' => true, 'participant_management' => true, 'potential_analysis' => true,
            ],
        ]);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        foreach (['gruppeHasTeilnehmer.show', 'potenzialanalyse.index'] as $permission) {
            $this->grantTestPermission($user, $permission);
        }
        $location = Standort::factory()->create();
        $area = Bereich::query()->create(['name' => 'Potenzialanalyse']);
        $room = Raeume::query()->create([
            'name' => 'PA-Raum', 'standort_id' => $location->id, 'typ' => 'Seminarraum', 'aktiv' => true,
        ]);
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id, 'projekt_id' => $project->id,
            'bereich_id' => $area->id, 'standort_id' => $location->id, 'raum_id' => $room->id,
            'anfangsdatum' => '2026-06-17', 'enddatum' => '2026-06-18',
        ]);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend', 'farben' => '#22c55e', 'abkuerzung' => 'A',
        ]);
        $days = collect(['2026-06-17', '2026-06-18'])->map(fn ($date) => Tage::query()->create([
            'datum' => $date, 'wochentag' => 'Mittwoch',
        ]));
        $participants = collect();
        foreach ([['Ben', 'Zweite'], ['Anna', 'Erste']] as [$first, $last]) {
            $participant = Personen::factory()->create([
                'typ' => 'teilnehmer', 'aktiv' => true, 'vorname' => $first, 'nachname' => $last,
            ]);
            ProjektHasPersonen::query()->create([
                'projekt_id' => $project->id, 'personen_id' => $participant->id,
                'standort_id' => $location->id, 'status' => 'aktiv',
            ]);
            foreach ($days as $day) {
                GruppeHasPersonen::query()->create([
                    'personen_id' => $participant->id, 'user_id' => $user->person_id,
                    'gruppe_id' => $group->id, 'tage_id' => $day->id,
                    'zeitgeplant_id' => $time->id, 'zeittatsaechlich_id' => $time->id,
                    'anwesenheitsstatuten_id' => $status->id,
                ]);
            }
            PotenzialanalyseBericht::query()->create([
                'gruppe_id' => $group->id, 'personen_id' => $participant->id,
                'user_id' => $user->id, 'status' => 'fertig', 'bericht_text' => 'Bericht für '.$first,
            ]);
            $participants->push($participant);
        }
        return [$user, $group, $participants];
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
