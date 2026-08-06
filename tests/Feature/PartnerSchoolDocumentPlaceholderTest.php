<?php

namespace Tests\Feature;

use App\Http\Controllers\ExportWordController;
use App\Models\Gruppe;
use App\Models\EinteilungSetting;
use App\Models\PaAttendanceListDraft;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
