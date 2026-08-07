<?php

namespace Tests\Feature;

use App\Http\Controllers\ExportWordController;
use App\Models\Gruppe;
use App\Models\EinteilungSetting;
use App\Models\PaAttendanceListDraft;
use App\Models\BopRun;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use ReflectionMethod;
use Tests\TestCase;

class PartnerSchoolDocumentPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_form_classes_and_pa_period_are_resolved_for_partner_documents(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::query()->create(['name' => 'Testschule']);
        $participants = collect([
            $this->student($school, '7.10', true),
            $this->student($school, '7.2', true),
            $this->student($school, '7.1', false),
            $this->student($school, '7.2', true),
        ]);

        PaAttendanceListDraft::query()->create([
            'draft_hash' => hash('sha256', 'preparation'),
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'export_mode' => 'klasse',
            'klasse' => '7.1',
            'payload' => [
                'form' => [
                    'listType' => 'pa_preparation',
                    'startDate' => '2026-09-01',
                ],
                'days' => [[
                    'date' => '2026-09-01',
                    'type' => 'preparation',
                    'selected' => true,
                    'note' => 'Vorbereitung PA',
                ]],
            ],
        ]);
        PaAttendanceListDraft::query()->create([
            'draft_hash' => hash('sha256', 'feedback'),
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'export_mode' => 'alle',
            'payload' => [
                'form' => [
                    'listType' => 'pa',
                    'startDate' => '2026-09-10',
                    'endDate' => '2026-09-11',
                    'feedbackDate' => '2026-09-30',
                ],
                'days' => [[
                    'date' => '2026-09-30',
                    'type' => 'feedback',
                    'selected' => true,
                    'note' => 'Auswertungsgespräch',
                ]],
            ],
        ]);

        $group = new Gruppe();
        $group->id = 99;
        $group->setRelation('partner', $school);
        $group->setRelation('partners', new Collection([$school]));
        $group->setRelation('teilnehmer', new Collection($participants->all()));
        $group->setRelation('betreuer', null);
        $group->setRelation('raum', null);
        $group->setRelation('bereich', null);

        $values = $this->placeholderValues($group, $project);

        $this->assertSame('Förderschule', $values['schulform']);
        $this->assertSame('2026/2027', $values['schuljahr']);
        $this->assertSame('1', $values['teil']);
        $this->assertSame('7.1 + 7.2 + 7.10', $values['klassen']);
        $this->assertSame('01.09.2026 – 30.09.2026', $values['zeitraum']);
        $this->assertSame('01.09.2026', $values['vorbereitung_pa_datum']);
        $this->assertSame('30.09.2026', $values['feedbackgespraech_datum']);
        $this->assertSame('30.09.2026', $values['auswertungsgespraech_datum']);
    }

    public function test_configured_round_dates_are_the_period_fallback_without_pa_dates(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::query()->create(['name' => 'Testschule']);
        $participant = $this->student($school, '8.1', false);
        $setting = EinteilungSetting::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'runden_anzahl' => 2,
            'standard_kapazitaet' => 15,
        ]);
        $setting->rundentermine()->createMany([
            [
                'runde' => 1,
                'anfangsdatum' => '2026-10-05',
                'enddatum' => '2026-10-06',
                'startzeit' => '08:00',
                'endzeit' => '15:00',
            ],
            [
                'runde' => 2,
                'anfangsdatum' => '2026-10-12',
                'enddatum' => '2026-10-13',
                'startzeit' => '08:00',
                'endzeit' => '15:00',
            ],
        ]);

        $group = new Gruppe();
        $group->id = 100;
        $group->setRelation('partner', $school);
        $group->setRelation('partners', new Collection([$school]));
        $group->setRelation('teilnehmer', new Collection([$participant]));
        $group->setRelation('betreuer', null);
        $group->setRelation('raum', null);
        $group->setRelation('bereich', null);

        $values = $this->placeholderValues($group, $project);

        $this->assertSame('05.10.2026 – 13.10.2026', $values['zeitraum']);
        $this->assertSame('05.10.2026', $values['zeitraum_von']);
        $this->assertSame('13.10.2026', $values['zeitraum_bis']);
        $this->assertSame('', $values['vorbereitung_pa_datum']);
        $this->assertSame('', $values['feedbackgespraech_datum']);
    }

    public function test_saved_bop_plan_is_the_primary_period_source_and_respects_the_part(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::query()->create(['name' => 'Testschule']);
        $participant = $this->student($school, '7.1', false);

        $run = BopRun::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
            'school_type' => 'Gemeinschaftsschule',
            'parts' => ['1', '2'],
            'planned_classes' => [
                ['name' => '7.1', 'expected_participants' => 20, 'part' => '1'],
                ['name' => '8.1', 'expected_participants' => 20, 'part' => '2'],
            ],
            'status' => 'confirmed',
        ]);
        $run->phases()->create([
            'phase_type' => 'pa_preparation',
            'dates' => ['2026-08-25', '2026-09-01'],
            'scope_type' => 'classes',
            'selected_classes' => ['7.1', '8.1'],
            'class_date_assignments' => [
                '7.1' => ['2026-09-01'],
                '8.1' => ['2026-08-25'],
            ],
            'group_mode' => 'class',
        ]);
        $run->phases()->create([
            'phase_type' => 'workshop_days',
            'dates' => ['2026-10-01', '2026-10-08'],
            'scope_type' => 'school',
            'part_date_assignments' => [
                '1' => ['2026-10-01'],
                '2' => ['2026-10-08'],
            ],
            'group_mode' => 'existing_assignment',
        ]);
        $run->phases()->create([
            'phase_type' => 'wt_feedback',
            'dates' => ['2026-10-20'],
            'scope_type' => 'school',
            'group_mode' => 'none',
        ]);

        $group = new Gruppe();
        $group->id = 101;
        $group->setRelation('partner', $school);
        $group->setRelation('partners', new Collection([$school]));
        $group->setRelation('teilnehmer', new Collection([$participant]));
        $group->setRelation('betreuer', null);
        $group->setRelation('raum', null);
        $group->setRelation('bereich', null);

        $values = $this->placeholderValues($group, $project);

        $this->assertSame('01.09.2026 – 20.10.2026', $values['zeitraum']);
        $this->assertSame('01.09.2026', $values['zeitraum_von']);
        $this->assertSame('20.10.2026', $values['zeitraum_bis']);
        $this->assertSame('01.09.2026', $values['vorbereitung_pa_datum']);
        $this->assertSame('20.10.2026', $values['feedbackgespraech_datum']);
        $this->assertSame('01.10.2026', $values['werkstatttage_daten']);
        $this->assertSame('01.10.2026, 08.10.2026', $values['werkstatttage_gesamt_daten']);
    }

    public function test_excel_pa_class_marker_repeats_the_formatted_block_for_every_planned_class(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::query()->create(['name' => 'Testschule']);
        $run = BopRun::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
            'school_type' => 'Gemeinschaftsschule',
            'parts' => ['1'],
            'planned_classes' => [
                ['name' => '7.1', 'expected_participants' => 20, 'part' => '1'],
                ['name' => '7.2', 'expected_participants' => 20, 'part' => '1'],
            ],
            'status' => 'confirmed',
        ]);
        $run->phases()->create([
            'phase_type' => 'pa',
            'dates' => ['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11'],
            'scope_type' => 'classes',
            'selected_classes' => ['7.1', '7.2'],
            'class_date_assignments' => [
                '7.1' => ['2026-06-08', '2026-06-09'],
                '7.2' => ['2026-06-10', '2026-06-11'],
            ],
            'group_mode' => 'class',
        ]);
        $run->phases()->create([
            'phase_type' => 'pa_feedback',
            'dates' => ['2026-06-15'],
            'scope_type' => 'school',
            'group_mode' => 'none',
        ]);
        $run->phases()->create([
            'phase_type' => 'roll_day',
            'dates' => ['2026-06-22'],
            'scope_type' => 'school',
            'group_mode' => 'class',
        ]);
        $run->phases()->create([
            'phase_type' => 'workshop_days',
            'dates' => ['2026-06-23', '2026-06-24'],
            'scope_type' => 'school',
            'part_date_assignments' => ['1' => ['2026-06-23', '2026-06-24']],
            'group_mode' => 'existing_assignment',
        ]);
        $run->phases()->create([
            'phase_type' => 'wt_feedback',
            'dates' => ['2026-07-01'],
            'scope_type' => 'school',
            'group_mode' => 'none',
        ]);

        $group = new Gruppe();
        $group->setAttribute('export_schuljahr', '2026/2027');
        $group->setAttribute('export_teil', '1');
        $group->setRelation('partner', $school);
        $group->setRelation('partners', new Collection([$school]));
        $group->setRelation('teilnehmer', new Collection());
        $group->setRelation('betreuer', null);
        $group->setRelation('raum', null);
        $group->setRelation('bereich', null);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Datum', 'Klasse', 'Name', 'Unterschrift'], null, 'A3');
        $sheet->setCellValue('A4', '${pa_klassen_tabelle}');
        $sheet->setCellValue('B4', '${pa_klasse}');
        $sheet->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('A3:D4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension(4)->setRowHeight(36);

        $method = new ReflectionMethod(ExportWordController::class, 'fillSpreadsheetTemplate');
        $method->setAccessible(true);
        $method->invoke(new ExportWordController(), $spreadsheet, $group, $project, collect());

        $values = $this->placeholderValues($group, $project);
        $this->assertSame('08.06.–11.06.2026', $values['pa_daten']);
        $this->assertSame('15.06.2026', $values['feedbackgespraech_pa_datum']);
        $this->assertSame('22.06.2026', $values['rolltag_datum']);
        $this->assertSame('23.06.–24.06.2026', $values['werkstatttage_daten']);
        $this->assertSame('01.07.2026', $values['feedbackgespraech_wt_datum']);

        $this->assertSame('08.06.–09.06.2026', $sheet->getCell('A4')->getValue());
        $this->assertSame('7.1', $sheet->getCell('B4')->getValue());
        $this->assertSame('Datum', $sheet->getCell('A5')->getValue());
        $this->assertSame('10.06.–11.06.2026', $sheet->getCell('A6')->getValue());
        $this->assertSame('7.2', $sheet->getCell('B6')->getValue());
        $this->assertSame(36.0, $sheet->getRowDimension(6)->getRowHeight());
        $this->assertSame('FFFFFF00', $sheet->getStyle('A5')->getFill()->getStartColor()->getARGB());
        $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('D6')->getBorders()->getBottom()->getBorderStyle());
    }

    private function student(Partner $school, string $class, bool $specialNeeds): Personen
    {
        $person = Personen::factory()->create(['typ' => 'teilnehmer']);
        PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => $class,
            'foerderschueler' => $specialNeeds,
            'eee' => false,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'schule_id' => $school->id,
        ]);

        return $person;
    }

    private function placeholderValues(Gruppe $group, Projekt $project): array
    {
        $method = new ReflectionMethod(ExportWordController::class, 'placeholderValues');
        $method->setAccessible(true);

        return $method->invoke(new ExportWordController(), $group, $project);
    }
}
