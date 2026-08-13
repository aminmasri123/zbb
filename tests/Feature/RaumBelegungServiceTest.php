<?php

namespace Tests\Feature;

use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\RaumBuchung;
use App\Models\Standort;
use App\Services\RaumBelegungService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaumBelegungServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_conflict_contains_person_area_room_and_exact_overlap(): void
    {
        [$room, $project, $area, $supervisor] = $this->roomContext();

        Gruppe::create([
            'personen_id' => $supervisor->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'standort_id' => $room->standort_id,
            'ort_typ' => 'raum',
            'raum_id' => $room->id,
            'anfangsdatum' => '2026-08-12',
            'enddatum' => '2026-08-13',
            'startzeit' => '08:00',
            'endzeit' => '12:30',
        ]);

        $conflicts = app(RaumBelegungService::class)->conflictsForGroup(
            $room->id,
            Carbon::parse('2026-08-12 09:00'),
            Carbon::parse('2026-08-13 11:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('group', $conflicts[0]['type']);
        $this->assertSame('Verkauf (BOP - EG55)', $conflicts[0]['room']['name']);
        $this->assertSame('Ernst-Abbe-9', $conflicts[0]['room']['location']);
        $this->assertSame('Dieter Schneider', $conflicts[0]['occupied_by']['supervisor']);
        $this->assertSame('Metalltechnik', $conflicts[0]['occupied_by']['area']);
        $this->assertSame('12.08.2026 bis 13.08.2026, 09:00–11:00 Uhr', $conflicts[0]['overlap']['label']);
    }

    public function test_multiday_group_does_not_conflict_when_daily_times_only_touch(): void
    {
        [$room, $project, $area, $supervisor] = $this->roomContext();

        Gruppe::create([
            'personen_id' => $supervisor->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'standort_id' => $room->standort_id,
            'ort_typ' => 'raum',
            'raum_id' => $room->id,
            'anfangsdatum' => '2026-08-12',
            'enddatum' => '2026-08-13',
            'startzeit' => '08:00',
            'endzeit' => '12:30',
        ]);

        $conflicts = app(RaumBelegungService::class)->conflictsForGroup(
            $room->id,
            Carbon::parse('2026-08-12 12:30'),
            Carbon::parse('2026-08-13 15:00'),
        );

        $this->assertSame([], $conflicts);
    }

    public function test_room_booking_conflict_uses_the_exact_daily_overlap(): void
    {
        [$room, $project, $area, $supervisor] = $this->roomContext();

        RaumBuchung::create([
            'raum_id' => $room->id,
            'projekt_id' => $project->id,
            'gebucht_von_personen_id' => $supervisor->id,
            'titel' => 'Zusatzgruppe Schneider',
            'typ' => 'projekt',
            'start_at' => '2026-08-12 10:30:00',
            'end_at' => '2026-08-12 14:00:00',
            'teilnehmerzahl' => 6,
            'status' => 'bestaetigt',
        ]);

        $conflicts = app(RaumBelegungService::class)->conflictsForGroup(
            $room->id,
            Carbon::parse('2026-08-12 08:00'),
            Carbon::parse('2026-08-13 12:30'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('booking', $conflicts[0]['type']);
        $this->assertSame('12.08.2026, 10:30–12:30 Uhr', $conflicts[0]['overlap']['label']);
        $this->assertSame('Dieter Schneider', $conflicts[0]['occupied_by']['supervisor']);
        $this->assertSame(6, $conflicts[0]['occupied_by']['participant_count']);
    }

    private function roomContext(): array
    {
        $location = Standort::factory()->create(['name' => 'Ernst-Abbe-9']);
        $room = Raeume::create([
            'standort_id' => $location->id,
            'name' => 'Verkauf (BOP - EG55)',
            'typ' => 'Unterrichtsraum',
            'kapazitaet' => 34,
        ]);
        $project = Projekt::factory()->create();
        $area = Bereich::create(['name' => 'Metalltechnik']);
        $supervisor = Personen::factory()->create([
            'vorname' => 'Dieter',
            'nachname' => 'Schneider',
        ]);

        return [$room, $project, $area, $supervisor];
    }
}
