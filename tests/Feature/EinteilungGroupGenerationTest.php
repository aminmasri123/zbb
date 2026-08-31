<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\EinteilungBereiche;
use App\Models\EinteilungSetting;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EinteilungGroupGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_use_one_supervisor_per_area_and_can_be_created_without_rooms(): void
    {
        $user = User::factory()->create();
        $projekt = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($projekt->id);
        $user->update(['current_team_id' => $projekt->id]);
        $projekt->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'einteilung.index');
        $this->grantTestPermission($user, 'einteilung.planning');

        $holz = Bereich::query()->create(['name' => 'Holz']);
        $metall = Bereich::query()->create(['name' => 'Metall']);
        $projekt->bereiche()->attach([$holz->id, $metall->id]);

        $holzAnleiter = Personen::factory()->create(['vorname' => 'Hanna', 'nachname' => 'Holz']);
        $metallAnleiter = Personen::factory()->create(['vorname' => 'Max', 'nachname' => 'Metall']);
        $projekt->mitarbeiter()->attach([$holzAnleiter->id, $metallAnleiter->id]);

        $teilnehmerPerson = Personen::factory()->create(['typ' => 'teilnehmer']);
        $schueler = PersonenIstSchueler::query()->create([
            'person_id' => $teilnehmerPerson->id,
            'klasse' => '8a',
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'schule_id' => $partner->id,
        ]);
        $projekt->teilnehmer()->attach($teilnehmerPerson->id);
        EinteilungBereiche::query()->create([
            'teilnehmende_id' => $schueler->id,
            'teilnehmende_type' => PersonenIstSchueler::class,
            'bereich_id' => $holz->id,
            'runde' => 1,
        ]);
        EinteilungBereiche::query()->create([
            'teilnehmende_id' => $schueler->id,
            'teilnehmende_type' => PersonenIstSchueler::class,
            'bereich_id' => $metall->id,
            'runde' => 2,
        ]);

        Anwesenheitsstatuten::query()->create([
            'status' => 'unentschuldigt',
            'abkuerzung' => 'U',
            'farben' => '#ef4444',
        ]);

        $setting = EinteilungSetting::query()->create([
            'projekt_id' => $projekt->id,
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'runden_anzahl' => 2,
            'standard_kapazitaet' => 15,
            'user_create' => $user->id,
        ]);
        $setting->rundentermine()->createMany([
            ['runde' => 1, 'anfangsdatum' => '2026-09-01', 'enddatum' => '2026-09-01', 'startzeit' => '08:00', 'endzeit' => '15:00'],
            ['runde' => 2, 'anfangsdatum' => '2026-09-02', 'enddatum' => '2026-09-02', 'startzeit' => '08:00', 'endzeit' => '15:00'],
        ]);

        $standort = Standort::factory()->create();
        $holzRaum = Raeume::query()->create([
            'standort_id' => $standort->id,
            'name' => 'Holzwerkstatt',
            'typ' => 'Werkstatt',
        ]);
        $metallRaum = Raeume::query()->create([
            'standort_id' => $standort->id,
            'name' => 'Metallwerkstatt',
            'typ' => 'Werkstatt',
        ]);
        DB::table('projekt_has_personens')
            ->where('projekt_id', $projekt->id)
            ->whereIn('personen_id', [$holzAnleiter->id, $metallAnleiter->id])
            ->update(['standort_id' => $standort->id]);

        $this->actingAs($user)->get(route('einteilung.show', [$partner->id, '2026-2027', 'Teil 1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teilnehmer/Einteilung/Index')
                ->has('betreuer', 3)
                ->has('raeume', 2)
                ->where('betreuer', fn ($items) => collect($items)->pluck('id')->contains($holzAnleiter->id)
                    && collect($items)->pluck('id')->contains($metallAnleiter->id)));

        $payload = [
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'bereiche' => [$holz->id, $metall->id],
            'bereich_betreuer' => [
                $holz->id => $holzAnleiter->id,
                $metall->id => $metallAnleiter->id,
            ],
            'bereich_raeume' => [
                $holz->id => $holzRaum->id,
                $metall->id => null,
            ],
        ];

        $this->actingAs($user)->postJson(route('gruppen.generieren'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Gruppen wurden generiert (4 Gruppen, 2 neue Anwesenheitseintraege).');

        $this->assertSame(4, Gruppe::query()->count());
        $this->assertSame(2, Gruppe::query()->where('bereich_id', $holz->id)->where('personen_id', $holzAnleiter->id)->count());
        $this->assertSame(2, Gruppe::query()->where('bereich_id', $metall->id)->where('personen_id', $metallAnleiter->id)->count());
        $this->assertSame(2, Gruppe::query()->where('bereich_id', $holz->id)->where('raum_id', $holzRaum->id)->count());
        $this->assertSame(2, Gruppe::query()->where('bereich_id', $metall->id)->whereNull('raum_id')->count());
        $this->assertSame(2, GruppeHasPersonen::query()->where('user_id', $user->id)->count());

        $gruppe = Gruppe::query()->where('bereich_id', $metall->id)->firstOrFail();
        $anleiterUser = User::factory()->create([
            'person_id' => $metallAnleiter->id,
            'current_team_id' => $projekt->id,
        ]);
        $this->grantTestPermission($anleiterUser, 'gruppe.index');
        $this->grantTestPermission($anleiterUser, 'gruppe.update');

        $this->actingAs($anleiterUser)->putJson(route('gruppe.update', $gruppe->id), [
            'bereich' => $metall->id,
            'betreuer' => $metallAnleiter->id,
            'partner_ids' => [],
            'ort_typ' => 'raum',
            'raum_id' => $metallRaum->id,
            'anfangsdatum' => $gruppe->anfangsdatum,
            'enddatum' => $gruppe->enddatum,
            'startzeit' => substr((string) $gruppe->startzeit, 0, 5),
            'endzeit' => substr((string) $gruppe->endzeit, 0, 5),
            'bemerkung' => $gruppe->bemerkung,
        ])->assertOk();

        $this->assertSame($metallRaum->id, $gruppe->fresh()->raum_id);

        $this->actingAs($user)->postJson(route('gruppen.generieren'), $payload)->assertOk();

        $this->assertSame($metallRaum->id, $gruppe->fresh()->raum_id);
        $this->assertSame($standort->id, $gruppe->fresh()->standort_id);
    }
}
