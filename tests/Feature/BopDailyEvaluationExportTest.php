<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\BerufsorientierungBewertung;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class BopDailyEvaluationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_selected_bo_day_is_exported_for_every_group_participant(): void
    {
        [$user, $group] = $this->context();
        $this->assertSame('BOP', $group->projekt?->name);

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.tagesauswertung', [
            'gruppe' => $group->id,
            'bo_tag' => 2,
        ]))->assertOk();

        $this->assertStringContainsString('BO_Tag_2.pdf', (string) $response->headers->get('content-disposition'));
        $pdf = new Fpdi;
        $this->assertSame(4, $pdf->setSourceFile($response->getFile()->getPathname()));
    }

    public function test_all_three_bo_days_are_exported_for_every_group_participant(): void
    {
        [$user, $group] = $this->context();

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.tagesauswertung', [
            'gruppe' => $group->id,
            'bo_tag' => 'alle',
        ]))->assertOk();

        $pdf = new Fpdi;
        $this->assertSame(8, $pdf->setSourceFile($response->getFile()->getPathname()));
    }

    public function test_participant_number_matches_the_alphabetical_order_within_the_class(): void
    {
        [$user, $group, $project, $partner] = $this->context();
        $earlierClassmate = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Anna',
            'nachname' => 'Adler',
        ]);
        PersonenIstSchueler::query()->create([
            'person_id' => $earlierClassmate->id,
            'schule_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'klasse' => '7.1',
        ]);
        $project->teilnehmer()->attach($earlierClassmate->id);

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.tagesauswertung', [
            'gruppe' => $group->id,
            'bo_tag' => 1,
        ]))->assertOk();

        $text = (new Parser)->parseFile($response->getFile()->getPathname())->getText();

        $this->assertStringContainsString('TN-NR.: 7.1-2', $text);
        $this->assertStringContainsString('TN-NR.: 7.2-1', $text);
    }

    public function test_single_workshop_evaluation_uses_the_bop_form_and_only_contains_the_selected_participant(): void
    {
        [$user, $group] = $this->context();
        $participants = $group->fresh()->teilnehmer->unique('id')->values();
        $selected = $participants->firstWhere('nachname', 'Muster');
        $other = $participants->firstWhere('nachname', 'Beispiel');
        BerufsorientierungBewertung::query()->create([
            'gruppe_id' => $group->id,
            'personen_id' => $selected->id,
            'user_id' => $user->id,
            'kriterium' => 'einhaltung_der_regeln',
            'kriterium_label' => 'Einhaltung der Arbeitszeitregeln',
            'bewertung' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.teilnehmer-auswertungsbogen-bop', [
            'gruppe' => $group->id,
            'personen' => $selected->id,
        ]))->assertOk();
        $document = (new Parser)->parseContent($response->getContent());
        $text = $document->getText();

        $this->assertSame(1, (int) $document->getDetails()['Pages']);
        $this->assertStringContainsString('Einschätzungen der Kompetenzen', $text);
        $this->assertStringContainsString($selected->nachname, $text);
        $this->assertStringNotContainsString($other->nachname, $text);
        $this->assertStringContainsString('Einhaltung der Arbeitszeitregeln', $text);
    }

    public function test_group_evaluation_contains_one_bop_form_per_participant_in_class_order(): void
    {
        [$user, $group] = $this->context();
        foreach ($group->fresh()->teilnehmer->unique('id') as $participant) {
            BerufsorientierungBewertung::query()->create([
                'gruppe_id' => $group->id,
                'personen_id' => $participant->id,
                'user_id' => $user->id,
                'kriterium' => 'einhaltung_der_regeln',
                'kriterium_label' => 'Einhaltung der Arbeitszeitregeln',
                'bewertung' => 4,
            ]);
        }

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.auswertungsbogen-bop', $group->id))->assertOk();
        $document = (new Parser)->parseContent($response->getContent());
        $text = $document->getText();

        $this->assertSame(2, (int) $document->getDetails()['Pages']);
        $this->assertLessThan(strpos($text, 'Beispiel'), strpos($text, 'Muster'));
    }

    public function test_school_evaluation_excludes_potential_analysis_and_is_sorted_by_class_then_last_name(): void
    {
        [$user, $group, $project, $partner] = $this->context();
        $this->grantTestPermission($user, 'dokumente.schule.export');
        $participants = $group->fresh()->teilnehmer->unique('id')->values();
        $metalArea = Bereich::query()->create(['name' => 'Metall']);
        $paArea = Bereich::query()->create(['name' => 'Potenzialanalyse']);

        foreach ($participants as $participant) {
            BerufsorientierungBewertung::query()->create([
                'gruppe_id' => $group->id,
                'personen_id' => $participant->id,
                'user_id' => $user->id,
                'kriterium' => 'einhaltung_der_regeln',
                'kriterium_label' => 'Einhaltung der Arbeitszeitregeln',
                'bewertung' => 4,
            ]);
        }

        foreach ([$metalArea, $paArea] as $area) {
            $additionalGroup = Gruppe::query()->create([
                'personen_id' => $group->personen_id,
                'bereich_id' => $area->id,
                'projekt_id' => $project->id,
                'partner_id' => $partner->id,
                'standort_id' => $group->standort_id,
                'raum_id' => $group->raum_id,
                'anfangsdatum' => '2026-09-08',
                'enddatum' => '2026-09-10',
                'bemerkung' => 'BOP Einteilung Schule '.$partner->id.' Schuljahr 2026/2027 Teil Teil 1 Runde 2',
            ]);

            foreach ($participants as $participant) {
                $source = GruppeHasPersonen::query()->where('gruppe_id', $group->id)->where('personen_id', $participant->id)->firstOrFail();
                GruppeHasPersonen::query()->create([
                    'personen_id' => $participant->id,
                    'user_id' => $user->id,
                    'gruppe_id' => $additionalGroup->id,
                    'tage_id' => $source->tage_id,
                    'zeitgeplant_id' => $source->zeitgeplant_id,
                    'zeittatsaechlich_id' => $source->zeittatsaechlich_id,
                    'anwesenheitsstatuten_id' => $source->anwesenheitsstatuten_id,
                ]);
                BerufsorientierungBewertung::query()->create([
                    'gruppe_id' => $additionalGroup->id,
                    'personen_id' => $participant->id,
                    'user_id' => $user->id,
                    'kriterium' => 'einhaltung_der_regeln',
                    'kriterium_label' => 'Einhaltung der Arbeitszeitregeln',
                    'bewertung' => 4,
                ]);
            }
        }

        $response = $this->actingAs($user)->get(route('export.auswertungBO.schule.pdf', [
            'schulId' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
        ]))->assertOk();
        $document = (new Parser)->parseContent($response->getContent());
        $text = $document->getText();

        $this->assertSame(4, (int) $document->getDetails()['Pages']);
        $this->assertStringNotContainsString('Potenzialanalyse', $text);
        $this->assertLessThan(strpos($text, 'Beispiel'), strpos($text, 'Muster'));
    }

    public static function deniedIndividualExports(): array
    {
        return [['permission', 403], ['group', 403], ['project', 403], ['participant', 404], ['scope', 403]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deniedIndividualExports')]
    public function test_individual_export_checks_permissions_and_scope(string $case, int $status): void
    {
        [$user, $group] = $this->context();
        $person = $group->teilnehmer->first();
        if ($case === 'permission') $user->revokePermissionTo('gruppe.bop.export.auswertungsbogen-bop');
        if ($case === 'group') $group->update(['personen_id' => User::factory()->create()->person_id]);
        if ($case === 'project') {
            $otherProject = Projekt::factory()->create();
            $user->projekte()->attach($otherProject->id);
            $user->update(['current_team_id' => $otherProject->id]);
        }
        if ($case === 'participant') $person = Personen::factory()->create(['typ' => 'teilnehmer']);
        if ($case === 'scope') \App\Models\RoleDataAccessSetting::where('role_id', $user->roles->first()->id)->update(['participant_scope' => 'none']);
        $this->actingAs($user)->get(route('gruppe.bop.export.teilnehmer-auswertungsbogen-bop', ['gruppe' => $group->id, 'personen' => $person->id]))->assertStatus($status);
        if (in_array($case, ['scope', 'permission', 'group', 'project'], true)) {
            $this->get(route('gruppe.bop.export.auswertungsbogen-bop', $group->id))->assertForbidden();
        }
    }

    public function test_school_export_includes_unrated_students_but_excludes_groups_from_other_years_and_parts(): void
    {
        [$user, $group, $project, $partner] = $this->context();
        $this->grantTestPermission($user, 'dokumente.schule.export');
        foreach ([['2025/2026', 'Teil 1'], ['2026/2027', 'Teil 2']] as [$year, $part]) {
            $other = $group->replicate();
            $other->bemerkung = 'BOP Einteilung Schule '.$partner->id.' Schuljahr '.$year.' Teil '.$part.' Runde 1';
            $other->save();
            foreach (GruppeHasPersonen::where('gruppe_id', $group->id)->get() as $membership) {
                $copy = $membership->replicate();
                $copy->gruppe_id = $other->id;
                $copy->save();
            }
        }
        $url = route('export.auswertungBO.schule.pdf', ['schulId' => $partner->id, 'schuljahr' => '2026-2027', 'teil' => 'Teil 1']);
        $response = $this->actingAs($user)->get($url)->assertOk();
        $pages = (new Parser)->parseContent($response->getContent())->getPages();
        $this->assertCount(2, $pages);
        foreach ($pages as $page) {
            $this->assertStringNotContainsString('${', $page->getText());
            $this->assertDoesNotMatchRegularExpression('/\bX\b/', $page->getText());
        }
        \App\Models\RoleDataAccessSetting::where('role_id', $user->roles->first()->id)->update(['participant_scope' => 'none']);
        $this->get($url)->assertForbidden();
    }

    public function test_school_export_requires_the_school_export_permission(): void
    {
        [$user, $group, $project, $partner] = $this->context();
        $this->actingAs($user)->get(route('export.auswertungBO.schule.pdf', [
            'schulId' => $partner->id, 'schuljahr' => '2026-2027', 'teil' => 'Teil 1',
        ]))->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Gemeinschaftsschule Test']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'BOP-Exporttest', 'guard_name' => 'web'], ['color' => '#123456']);
        \App\Models\RoleDataAccessSetting::updateOrCreate(['role_id' => $role->id], ['team_scope' => 'own_projects', 'participant_scope' => 'own_projects']);
        $user->assignRole($role);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'gruppe.bop.export.auswertungsbogen-bop');

        $area = Bereich::query()->create(['name' => 'IT- und Mediengestaltung']);
        $staff = $user->person;
        $staff->update(['typ' => 'mitarbeiter']);
        $location = Standort::factory()->create();
        $room = Raeume::query()->create(['name' => 'IT-Raum', 'standort_id' => $location->id, 'typ' => 'Werkstatt']);
        $group = Gruppe::query()->create([
            'personen_id' => $staff->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
            'anfangsdatum' => '2026-09-01',
            'enddatum' => '2026-09-03',
            'bemerkung' => 'BOP Einteilung Schule '.$partner->id.' Schuljahr 2026/2027 Teil Teil 1 Runde 1',
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create(['status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e']);

        foreach ([['Mia', 'Muster', '7.1'], ['Noah', 'Beispiel', '7.2']] as [$firstName, $lastName, $class]) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => $firstName, 'nachname' => $lastName]);
            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'schule_id' => $partner->id,
                'schuljahr' => '2026/2027',
                'teil' => 'Teil 1',
                'klasse' => $class,
            ]);
            $project->teilnehmer()->attach($person->id);
            GruppeHasPersonen::query()->create([
                'personen_id' => $person->id,
                'user_id' => $user->id,
                'gruppe_id' => $group->id,
                'tage_id' => $day->id,
                'zeitgeplant_id' => $time->id,
                'zeittatsaechlich_id' => $time->id,
                'anwesenheitsstatuten_id' => $status->id,
            ]);
        }

        return [$user, $group, $project, $partner];
    }
}
