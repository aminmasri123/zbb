<?php

namespace Tests\Feature;

use App\Models\BopRun;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolYearStartScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_scope_finds_short_and_full_school_year_by_start_year(): void
    {
        $partner = Partner::query()->create(['name' => 'Testschule']);

        $shortYear = $this->student($partner, '2026');
        $fullYear = $this->student($partner, '2026/2027');
        $hyphenatedYear = $this->student($partner, '2026-2027');
        $otherYear = $this->student($partner, '2027/2028');

        $ids = PersonenIstSchueler::query()
            ->forSchuljahr('2026')
            ->pluck('id');

        $this->assertEqualsCanonicalizing(
            [$shortYear->id, $fullYear->id, $hyphenatedYear->id],
            $ids->all()
        );
        $this->assertNotContains($otherYear->id, $ids->all());
    }

    public function test_bop_run_scope_finds_existing_full_year_with_start_year(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);

        $expected = BopRun::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
        ]);
        BopRun::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'schuljahr' => '2027/2028',
            'teil' => '_all',
        ]);

        $this->assertSame(
            $expected->id,
            BopRun::query()->forSchuljahr('2026')->sole()->id
        );
    }

    private function student(Partner $partner, string $schoolYear): PersonenIstSchueler
    {
        $person = Personen::factory()->create(['typ' => 'teilnehmer']);

        return PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => '8a',
            'schule_id' => $partner->id,
            'schuljahr' => $schoolYear,
            'teil' => '1',
        ]);
    }
}
