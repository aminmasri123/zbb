<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DokumenteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('dokumentes')->insert([
            [ 'name' => 'Information für Teilnehmende', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/inteqra/info_teilnehmende.docx', 'dateipfadName' => 'info_teilnehmende', 'beschreibung' => null ],
            [ 'name' => 'Bildungsvertrag INTEQRA', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/vorlage/projekte/inteqra/bildungsvertrag_inteqra.docx', 'dateipfadName' => 'bildungsvertrag_inteqra.docx', 'beschreibung' => null ],
            [ 'name' => 'Datenschutzhinweis (Art. 13 DSGVO)', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/inteqra/datenschutzhinweis_art13.docx', 'dateipfadName' => 'datenschutzhinweis_art13', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Datenschutz ESF', 'typ' => 'word', 'version' => '11.09.2024', 'dateipfad' => '/vorlage/projekte/inteqra/einverstaendnis_datenschutz_esf.docx', 'dateipfadName' => 'einverstaendnis_datenschutz_esf', 'beschreibung' => null ],
            [ 'name' => 'Ganzheitliches Fehlzeitenkonzept', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/fehlzeitenkonzept.docx', 'dateipfadName' => 'fehlzeitenkonzept', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Elternarbeit', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/einverstaendnis_elternarbeit.docx', 'dateipfadName' => 'einverstaendnis_elternarbeit', 'beschreibung' => null ],
            [ 'name' => 'EDV-Nutzungsvereinbarung', 'typ' => 'word', 'version' => '11.08.2025', 'dateipfad' => '/vorlage/projekte/edv_nutzungsvereinbarung.docx', 'dateipfadName' => 'edv_nutzungsvereinbarung', 'beschreibung' => null ],
            [ 'name' => 'Hausordnung V1', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/hausordnung_v1.docx', 'dateipfadName' => 'hausordnung_v1', 'beschreibung' => null ],
            [ 'name' => 'Fragebogen Aufnahmegespräch', 'typ' => 'word', 'version' => '18.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/fragebogen_aufnahme.docx', 'dateipfadName' => 'fragebogen_aufnahme', 'beschreibung' => null ],
            [ 'name' => 'Zielvereinbarung INTEQRA', 'typ' => 'excel', 'version' => '23.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/zielvereinbarung_inteqra.xls', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Fallbesprechung im Team', 'typ' => 'word', 'version' => '12.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/fallbesprechung_team.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Förderplan INTEQRA', 'typ' => 'word', 'version' => '23.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/foerderplan_inteqra.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Tagesberichte', 'typ' => 'excel', 'version' => '22.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/tagesberichte.xls', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Beurteilungsbogen Fachpraxis', 'typ' => 'excel', 'version' => '08.11.2025', 'dateipfad' => '/storage/dokumente/Inteqra/beurteilungsbogen_fachpraxis.xlsx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Bewertung fachübergreifender Kompetenzen', 'typ' => 'excel', 'version' => '06.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/bewertung_kompetenzen.xlsx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Selbsteinschätzung Teilnehmende Inteqra', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/selbsteinschaetzung_teilnehmende.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Laufzettel Praktikumssuche', 'typ' => 'word', 'version' => '23.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/laufzettel_praktikumssuche.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Praktikumsvertrag Betrieb', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/praktikumsvertrag_betrieb.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Beurteilung Betriebspraktikum', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/beurteilung_praktikum_betrieb.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Fragebogen Zufriedenheit Teilnehmende', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/fragebogen_zufriedenheit.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Muster Ermahnung', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/storage/dokumente/Inteqra/muster_ermahnung.docx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Bewerbungsübersicht', 'typ' => 'excel', 'version' => '23.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/bewerbungsuebersicht_inteqra.xlsx', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Qualifizierte Teilnahmebescheinigung', 'typ' => 'rtf', 'version' => '23.09.2025', 'dateipfad' => '/storage/dokumente/Inteqra/teilnahmebescheinigung_inteqra.rtf', 'dateipfadName' => '', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Fotoverwendung', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/vorlage/projekte/einverstaendnis_foto.docx', 'dateipfadName' => '', 'beschreibung' => null ],
        ]);

    }
}
