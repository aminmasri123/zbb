<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Partnerschaftstypen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PartnerDetailsPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_and_refresh_hide_details_but_keep_school_year_and_part(): void
    {
        [$user, $school] = $this->context();
        $this->actingAs($user)->get(route('partner.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Partner/Index')->where('partners.data.0.name', 'Testschule')
            ->where('partners.data.0.schueler.0.schuljahr', '2026')
            ->where('partners.data.0.schueler.0.teil', '1')
            ->where('partners.data.0.partnerschaftstypens.0.bezeichnung', 'Kooperationsschule')
            ->missing('partners.data.0.beschreibung')->missing('partners.data.0.kontaktes')
            ->missing('partners.data.0.adresses')->missing('partners.data.0.ansprechpartners')
            ->missing('partners.data.0.partnerschaftstypens.0.pivot')->etc());
        $response = $this->getJson(route('partner.indexAjaxFresh'))->assertOk();
        $data = $response->json('partners.data.0');
        $this->assertSame('Testschule', $data['name']);
        foreach (['beschreibung', 'adresses', 'kontaktes', 'ansprechpartners'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
        $this->getJson(route('partner.indexAjaxFresh', ['search' => 'VERTRAULICH']))
            ->assertOk()->assertJsonCount(0, 'partners.data');
        $this->getJson(route('partner.indexAjaxFresh', ['search' => 'Testschule']))
            ->assertOk()->assertJsonCount(1, 'partners.data');
        $this->grantTestPermission($user, 'kooperationspartner.update');
        $this->putJson(route('partner.update', $school), [])->assertForbidden();
    }

    public function test_explicit_permission_reveals_details_and_allows_detail_search(): void
    {
        [$user] = $this->context();
        $this->grantTestPermission($user, 'kooperationspartner.details.view');
        $this->actingAs($user)->getJson(route('partner.indexAjaxFresh', ['search' => 'VERTRAULICH']))
            ->assertOk()->assertJsonPath('partners.data.0.beschreibung', 'VERTRAULICH')
            ->assertJsonPath('partners.data.0.adresses.0.strasse', 'Geheimweg');
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $this->grantTestPermission($user, 'kooperationspartner.index');
        $school = Partner::create(['name' => 'Testschule', 'beschreibung' => 'VERTRAULICH']);
        $project->partners()->attach($school->id);
        $school->adresses()->create(['strasse' => 'Geheimweg', 'hausnummer' => '1', 'plz' => '66111', 'stadt' => 'Saarbrücken', 'land' => 'DE']);
        $type = Partnerschaftstypen::firstOrCreate(['bezeichnung' => 'Kooperationsschule']);
        $school->partnerschaftstypens()->attach($type->id);
        PersonenIstSchueler::create(['person_id' => Personen::factory()->create()->id, 'schule_id' => $school->id,
            'schuljahr' => '2026', 'teil' => '1', 'klasse' => '8']);
        return [$user, $school];
    }
}
