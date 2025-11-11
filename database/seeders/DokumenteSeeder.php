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
            [ 'name' => 'Information für Teilnehmende', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/info_teilnehmende.docx', 'beschreibung' => null ],
            [ 'name' => 'Bildungsvertrag INTEQRA', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/projekte/inteqra/bildungsvertrag_inteqra.docx', 'beschreibung' => null ],
            [ 'name' => 'Datenschutzhinweis (Art. 13 DSGVO)', 'typ' => 'rtf', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/datenschutzhinweis_art13.rtf', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Datenschutz ESF', 'typ' => 'word', 'version' => '11.09.2024', 'dateipfad' => '/projekte/inteqra/einverstaendnis_datenschutz.docx', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Fotoverwendung', 'typ' => 'word', 'version' => '11.08.2025', 'dateipfad' => '/projekte/inteqra/einverstaendnis_foto.docx', 'beschreibung' => null ],
            [ 'name' => 'Einverständniserklärung Elternarbeit', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/einverstaendnis_elternarbeit.docx', 'beschreibung' => null ],
            [ 'name' => 'EDV-Nutzungsvereinbarung', 'typ' => 'word', 'version' => '11.08.2025', 'dateipfad' => '/projekte/inteqra/edv_nutzungsvereinbarung.docx', 'beschreibung' => null ],
            [ 'name' => 'Kursordnung INTEQRA', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/kursordnung_inteqra.docx', 'beschreibung' => null ],
            [ 'name' => 'Fragebogen Aufnahmegespräch', 'typ' => 'word', 'version' => '18.09.2025', 'dateipfad' => '/projekte/inteqra/fragebogen_aufnahme.docx', 'beschreibung' => null ],
            [ 'name' => 'Zielvereinbarung INTEQRA', 'typ' => 'xls', 'version' => '23.09.2025', 'dateipfad' => '/projekte/inteqra/zielvereinbarung_inteqra.xls', 'beschreibung' => null ],
            [ 'name' => 'Fallbesprechung im Team', 'typ' => 'word', 'version' => '12.08.2025', 'dateipfad' => '/projekte/inteqra/fallbesprechung_team.docx', 'beschreibung' => null ],
            [ 'name' => 'Förderplan INTEQRA', 'typ' => 'word', 'version' => '23.09.2025', 'dateipfad' => '/projekte/inteqra/foerderplan_inteqra.docx', 'beschreibung' => null ],
            [ 'name' => 'Tagesberichte', 'typ' => 'xls', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/tagesberichte.xls', 'beschreibung' => null ],
            [ 'name' => 'Beurteilungsbogen Fachpraxis', 'typ' => 'excel', 'version' => '08.11.2025', 'dateipfad' => '/projekte/inteqra/beurteilungsbogen_fachpraxis.xlsx', 'beschreibung' => null ],
            [ 'name' => 'Bewertung fachübergreifender Kompetenzen', 'typ' => 'excel', 'version' => '06.08.2025', 'dateipfad' => '/projekte/inteqra/bewertung_kompetenzen.xlsx', 'beschreibung' => null ],
            [ 'name' => 'Selbsteinschätzung Teilnehmende Inteqra', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/projekte/inteqra/selbsteinschaetzung_teilnehmende.docx', 'beschreibung' => null ],
            [ 'name' => 'Laufzettel Praktikumssuche', 'typ' => 'word', 'version' => '23.09.2025', 'dateipfad' => '/projekte/inteqra/laufzettel_praktikumssuche.docx', 'beschreibung' => null ],
            [ 'name' => 'Praktikumsvertrag Betrieb', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/projekte/inteqra/praktikumsvertrag_betrieb.docx', 'beschreibung' => null ],
            [ 'name' => 'Beurteilung Betriebspraktikum', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/beurteilung_praktikum_betrieb.docx', 'beschreibung' => null ],
            [ 'name' => 'Fragebogen Zufriedenheit Teilnehmende', 'typ' => 'word', 'version' => '06.08.2025', 'dateipfad' => '/projekte/inteqra/fragebogen_zufriedenheit.docx', 'beschreibung' => null ],
            [ 'name' => 'Muster Ermahnung', 'typ' => 'word', 'version' => '22.08.2025', 'dateipfad' => '/projekte/inteqra/muster_ermahnung.docx', 'beschreibung' => null ],
            [ 'name' => 'Bewerbungsübersicht', 'typ' => 'excel', 'version' => '23.09.2025', 'dateipfad' => '/projekte/inteqra/bewerbungsuebersicht_inteqra.xlsx', 'beschreibung' => null ],
            [ 'name' => 'Qualifizierte Teilnahmebescheinigung', 'typ' => 'rtf', 'version' => '23.09.2025', 'dateipfad' => '/projekte/inteqra/teilnahmebescheinigung_inteqra.rtf', 'beschreibung' => null ],



        /*
        [ 'name' => 'Inhaltsverzeichnis INTEQRA', 'typ' => 'word', 'version' => 1, 'dateipfad' => '/projekte/inteqra/inhaltsverzeichnis_inteqra.docx', 'beschreibung' => null ],
        [ 'name' => 'Teilnehmerkarten übersicht', 'typ' => 'word', 'version' => 1, 'dateipfad' => '/projekte/inteqra/teilnehmenden_uebersicht.docx', 'beschreibung' => null ],
        [ 'name' => 'Ganzheitliches Fehlzeitenkonzept', 'typ' => 'word', 'version' => 1, 'dateipfad' => '/projekte/inteqra/fehlzeitenkonzept_inteqra.docx', 'beschreibung' => null ],
        [ 'name' => 'Checkliste Dokumentenablage Förderplan', 'typ' => 'word', 'version' => 1, 'dateipfad' => '/projekte/inteqra/checkliste_dokumentenablage.docx', 'beschreibung' => null ],



        */
        ]);
    }
}
