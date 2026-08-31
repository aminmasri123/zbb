<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class UnterweisungsnachweisExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_only_an_encrypted_signature_payload_on_own_account(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'user.profil');

        $this->actingAs($user)->post(route('profile.unterweisung-signature.update'), [
            'unterschrift' => UploadedFile::fake()->image('unterschrift.png', 400, 120),
        ])->assertRedirect();

        $raw = (string) DB::table('users')->where('id', $user->id)->value('unterweisung_unterschrift');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('image/png', $raw);
        $this->assertTrue($user->fresh()->has_unterweisung_unterschrift);
    }

    public function test_assigned_instructor_exports_pdf_with_area_defaults_and_participant_name(): void
    {
        Log::spy();
        [$user, $gruppe, $teilnehmer] = $this->context();
        $user->update([
            'unterweisung_unterschrift' => [
                'mime' => 'image/png',
                'data' => base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL3WQAAAABJRU5ErkJggg==')),
            ],
            'unterweisung_unterschrift_updated_at' => now(),
        ]);

        $response = $this->actingAs($user->fresh())
            ->get(route('gruppe.bop.export.unterweisungsnachweis', $gruppe));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $parser = new Parser;
        $text = $parser->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Metallbearbeitung', $text);
        $this->assertStringContainsString($teilnehmer->vorname, $text);
        $this->assertStringContainsString('Elektrische Geräte', $text);
        $this->assertStringContainsString('X', $text);
    }

    public function test_another_logged_in_user_cannot_sign_the_group_pdf(): void
    {
        [, $gruppe] = $this->context();
        $other = User::factory()->create([
            'unterweisung_unterschrift' => ['mime' => 'image/png', 'data' => 'abc'],
        ]);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $gruppe->projekt_id,
            'personen_id' => $other->person_id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $other->update(['current_team_id' => $gruppe->projekt_id]);

        $this->actingAs($other)->get(route('gruppe.bop.export.unterweisungsnachweis', $gruppe))->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $user->person->update(['vorname' => 'Anna', 'nachname' => 'Anleiterin', 'typ' => 'mitarbeiter']);
        $projekt = Projekt::factory()->create(['name' => 'BOP Test']);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $projekt->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $projekt->id]);
        $standort = Standort::query()->create(['name' => 'Saarbrücken']);
        $raum = Raeume::query()->create([
            'name' => 'Werkstatt 1',
            'standort_id' => $standort->id,
            'typ' => 'Arbeitsbereich',
            'aktiv' => true,
        ]);
        $bereich = Bereich::query()->create([
            'name' => 'Metallbearbeitung',
            'unterweisung_themen' => ['elektrische_geraete', 'pers_schutzausruestung'],
        ]);
        $gruppe = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $bereich->id,
            'projekt_id' => $projekt->id,
            'standort_id' => $standort->id,
            'raum_id' => $raum->id,
            'anfangsdatum' => '2026-09-01',
            'enddatum' => '2026-09-03',
        ]);
        $teilnehmer = Personen::factory()->create([
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'typ' => 'teilnehmer',
            'aktiv' => true,
        ]);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $projekt->id,
            'personen_id' => $teilnehmer->id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tag = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $zeit = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create(['status' => 'anwesend', 'farben' => '#00ff00', 'abkuerzung' => 'A']);
        GruppeHasPersonen::query()->create([
            'personen_id' => $teilnehmer->id,
            'user_id' => $user->id,
            'gruppe_id' => $gruppe->id,
            'tage_id' => $tag->id,
            'zeitgeplant_id' => $zeit->id,
            'anwesenheitsstatuten_id' => $status->id,
        ]);

        return [$user, $gruppe, $teilnehmer];
    }
}
