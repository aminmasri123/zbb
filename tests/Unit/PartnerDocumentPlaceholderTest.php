<?php

namespace Tests\Unit;

use App\Http\Controllers\DokumenteController;
use App\Http\Controllers\ExportWordController;
use App\Models\Adresse;
use App\Models\Gruppe;
use App\Models\Kontakte;
use App\Models\Kontakttypen;
use App\Models\Partner;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class PartnerDocumentPlaceholderTest extends TestCase
{
    public function test_partner_placeholders_are_listed_in_the_data_manager(): void
    {
        $category = collect(DokumenteController::platzhalterDefinitionen())
            ->firstWhere('gruppe', 'Partner / Schule');

        $this->assertNotNull($category);
        $this->assertSame([
            'partner',
            'partner_name',
            'partner_beschreibung',
            'partner_adresse',
            'partner_strasse',
            'partner_hausnummer',
            'partner_plz',
            'partner_stadt',
            'partner_email',
            'partner_telefon',
            'partner_liste',
            'schulform',
            'schuljahr',
            'teil',
            'klassen',
            'klassen_liste',
            'zeitraum',
            'zeitraum_von',
            'zeitraum_bis',
            'vorbereitung_pa_datum',
            'pa_datum',
            'pa_daten',
            'feedbackgespraech_pa_datum',
            'rolltag_datum',
            'werkstatttage_daten',
            'werkstatttage_gesamt_daten',
            'wt_daten',
            'feedbackgespraech_wt_datum',
            'feedbackgespraech_datum',
            'auswertungsgespraech_datum',
            'pa_klassen_tabelle',
            'pa_klasse',
        ], collect($category['werte'])->pluck('key')->all());
    }

    public function test_group_export_resolves_primary_partner_and_all_partner_fields(): void
    {
        $emailType = new Kontakttypen(['name' => 'E-Mail']);
        $phoneType = new Kontakttypen(['name' => 'Telefon']);

        $email = new Kontakte(['wert' => 'schule@example.test']);
        $email->setRelation('kontakttyp', $emailType);
        $phone = new Kontakte(['wert' => '0681 123456']);
        $phone->setRelation('kontakttyp', $phoneType);

        $primary = new Partner([
            'name' => 'Zentrale Schule',
            'beschreibung' => 'Kooperationsschule',
        ]);
        $primary->id = 20;
        $primary->setRelation('adresses', new Collection([
            new Adresse([
                'strasse' => 'Schulstraße',
                'hausnummer' => '12a',
                'plz' => '66111',
                'stadt' => 'Saarbrücken',
            ]),
        ]));
        $primary->setRelation('kontaktes', new Collection([$email, $phone]));

        $additional = new Partner(['name' => 'Alpha Bildungspartner']);
        $additional->id = 10;
        $additional->setRelation('adresses', new Collection());
        $additional->setRelation('kontaktes', new Collection());

        $group = $this->groupWithPartners($primary, [$additional, $primary]);
        $values = $this->placeholderValues($group);

        $this->assertSame('Zentrale Schule', $values['partner']);
        $this->assertSame('Zentrale Schule', $values['partner_name']);
        $this->assertSame('Kooperationsschule', $values['partner_beschreibung']);
        $this->assertSame('Schulstraße 12a', $values['partner_adresse']);
        $this->assertSame('Schulstraße', $values['partner_strasse']);
        $this->assertSame('12a', $values['partner_hausnummer']);
        $this->assertSame('66111', $values['partner_plz']);
        $this->assertSame('Saarbrücken', $values['partner_stadt']);
        $this->assertSame('schule@example.test', $values['partner_email']);
        $this->assertSame('0681 123456', $values['partner_telefon']);
        $this->assertSame('Alpha Bildungspartner, Zentrale Schule', $values['partner_liste']);
    }

    public function test_first_partner_is_used_when_no_primary_partner_is_set(): void
    {
        $alpha = $this->emptyPartner(10, 'Alpha Schule');
        $beta = $this->emptyPartner(20, 'Beta Schule');
        $group = $this->groupWithPartners(null, [$beta, $alpha]);

        $values = $this->placeholderValues($group);

        $this->assertSame('Alpha Schule', $values['partner_name']);
        $this->assertSame('Alpha Schule, Beta Schule', $values['partner_liste']);
    }

    private function placeholderValues(Gruppe $group): array
    {
        $project = new Projekt(['name' => 'Testprojekt']);
        $method = new ReflectionMethod(ExportWordController::class, 'placeholderValues');
        $method->setAccessible(true);

        return $method->invoke(new ExportWordController(), $group, $project);
    }

    private function groupWithPartners(?Partner $primary, array $partners): Gruppe
    {
        $group = new Gruppe([
            'partner_id' => $primary?->id,
            'ort_typ' => 'extern',
            'externer_ort' => 'Testort',
        ]);
        $group->id = 99;
        $group->setRelation('partner', $primary);
        $group->setRelation('partners', new Collection($partners));
        $group->setRelation('betreuer', null);
        $group->setRelation('raum', null);
        $group->setRelation('bereich', null);

        return $group;
    }

    private function emptyPartner(int $id, string $name): Partner
    {
        $partner = new Partner(['name' => $name]);
        $partner->id = $id;
        $partner->setRelation('adresses', new Collection());
        $partner->setRelation('kontaktes', new Collection());

        return $partner;
    }
}
