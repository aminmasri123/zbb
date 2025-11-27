<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Tage;
use App\Models\Gruppe;

use App\Models\Projekt;
use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\GruppeHasPersonen;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExportExcelController extends Controller
{
   public function esfStammblatt($teilnehmerId, $projektId)
    {
        $teilnehmer = Personen::Teilnehmer()->with([
            'abschluesse',
            'sozialedaten',
            'adresses',
            'kontaktes.kontakttyp',
            'zielgruppen',
            'projekte',
        ])->findOrFail($teilnehmerId);

        if(!$teilnehmer){
            return back()->with('error', 'die Suche ist fehlgeschlagen.');
        }

        $projektHasTeilnehmer = ProjektHasPersonen::where('personen_id', $teilnehmerId)
            ->where('projekt_id', $projektId)
            ->with('austrittttypen', 'zeitraume', 'abschluss')
            ->first();

        if(!$projektHasTeilnehmer){
            return back()->with('error', 'die Suche ist fehlgeschlagen.');
        }

        $errors = [];

        if (!$teilnehmer->nachname) $errors[] = 'Nachname fehlt';
        if (!$teilnehmer->vorname) $errors[] = 'Vorname fehlt';

        if (!$projektHasTeilnehmer?->meta?->zielgruppe_id) $errors[] = 'Zielgruppe fehlt';
        $adresse = $teilnehmer->adresses->last();
        if (!$adresse) $errors[] = 'Adresse fehlt';
        else {
            if (!$adresse->strasse) $errors[] = 'Straße fehlt';
            if (!$adresse->hausnummer) $errors[] = 'Hausnummer fehlt';
            if (!$adresse->plz) $errors[] = 'PLZ fehlt';
            if (!$adresse->stadt) $errors[] = 'Stadt fehlt';
        }

            $email = $teilnehmer->kontaktes->where('kontakttyp.name', 'Email')->last();
            if (!$email) $errors[] = 'E-Mail fehlt';


            if (!$projektHasTeilnehmer->meta->austritt_id) $errors[] = 'Aaustritt fehlen';
            if (!$teilnehmer->sozialedaten) $errors[] = 'Sozialdaten fehlen';
            if (!$projektHasTeilnehmer->meta->projektabschluss_id) $errors[] = 'Projektabschlussdaten fehlen';
            if (!$projektHasTeilnehmer->meta->verbleib_id) $errors[] = 'Verbleib fehlen';
            if (!$projektHasTeilnehmer?->zeitraume->last()) $errors[] = 'Zeitraum (Start/Ende) fehlt';

            if (!empty($errors)) {
                return back()->with('error', 'Export abgebrochen. Fehlende Daten: ' . implode(', ', $errors));
            }

            // Pfad zur vorhandenen Excel-Datei
            $existingFile = storage_path('vorlage/esf/excel/ESF.xlsx');
            if(!file_exists($existingFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }

            // Excel-Datei öffnen
            $spreadsheet = IOFactory::load($existingFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Daten in den Zellen einfügen
            $sheet->setCellValue('B6', $teilnehmer?->nachname );
            $sheet->setCellValue('B7', $teilnehmer?->vorname );
            $sheet->setCellValue('B8', $teilnehmer?->adresses->last()?->strasse . ' Nr. ' . $teilnehmer?->adresses->last()?->hausnummer );
            $sheet->setCellValue('B9', $teilnehmer?->adresses->last()?->plz);
            $sheet->setCellValue('B10', $teilnehmer?->adresses->last()?->stadt );
            $sheet->setCellValue('B11', $teilnehmer?->kontaktes->where('kontakttyp.name', 'Email')->last()?->wert );


            $sheet->setCellValue('H13', $teilnehmer?->geschlecht === 'w' ? 'X' : null );
            $sheet->setCellValue('H14', $teilnehmer?->geschlecht === 'm' ? 'X' : null );


            $sheet->setCellValue('H19', $teilnehmer?->sozialedaten?->drittstaatsangehoerig === 1 ? 'X': null );
            $sheet->setCellValue('H20', $teilnehmer?->sozialedaten?->drittstaatsangehoerig === 0 ? 'X' : null );
            $sheet->setCellValue('H21', $teilnehmer?->sozialedaten?->drittstaatsangehoerig === null ? 'X' : null );

            $sheet->setCellValue('H23', $teilnehmer?->sozialedaten?->gefluechtet === 1 ? 'X': null );
            $sheet->setCellValue('H24', $teilnehmer?->sozialedaten?->gefluechtet === 0 ? 'X': null );
            $sheet->setCellValue('H25', $teilnehmer?->sozialedaten?->gefluechtet === null ? 'X': null );

            $sheet->setCellValue('H27', $teilnehmer?->sozialedaten?->migrationshintergrund === 1 ? 'X': null );
            $sheet->setCellValue('H28', $teilnehmer?->sozialedaten?->migrationshintergrund === 0 ? 'X': null );
            $sheet->setCellValue('H29', $teilnehmer?->sozialedaten?->migrationshintergrund === null ? 'X': null );

            $sheet->setCellValue('H31', $teilnehmer?->sozialedaten?->behinderung === 1 ? 'X': null );
            $sheet->setCellValue('H32', $teilnehmer?->sozialedaten?->behinderung === 0 ? 'X': null );
            $sheet->setCellValue('H33', $teilnehmer?->sozialedaten?->behinderung === null ? 'X': null );



            $sheet->setCellValue('H35', $teilnehmer?->sozialedaten?->leistungsbezug_id === 1 ? 'X': null );  //KEINE LEISTUNG
            $sheet->setCellValue('H36', $teilnehmer?->sozialedaten?->leistungsbezug_id === 10 ? 'X': null ); //SGB II UND SGB III
            $sheet->setCellValue('H37', $teilnehmer?->sozialedaten?->leistungsbezug_id === 3 ? 'X': null );  //SGB I
            $sheet->setCellValue('H38', $teilnehmer?->sozialedaten?->leistungsbezug_id === 2 ? 'X': null );  //SGB II
            $sheet->setCellValue('H39', $teilnehmer?->sozialedaten?->leistungsbezug_id === 4 ? 'X': null );  //SGB XII



            $ranking = [
                'ohne Hauptschulabschluss' => 1,
                'Hauptschulabschluss' => 2,
                'mittlere Reife' => 3,
                'Fachoberschule 1-jährig (nach vorheriger Berufsausbildung)' => 4,
                'Berufsfachschule, die zur Hochschulreife/Fachhochschulreife führt' => 5,
                'Fachhochschulreife' => 6,
                'Hochschulreife' => 7,
                'Berufsoberschule/Technische Oberschule' => 8,
            ];
            $hoechsterAbschluss = $teilnehmer->abschluesse
                ->where('typ', 'schule')
                ->sortBy(fn($a) => $ranking[$a->typ] ?? 0)   // nach Ranking sortieren
                ->last();
            $abschluss = $hoechsterAbschluss?->bezeichnung;

            // ✅ Wenn kein Abschluss existiert → „ohne Hauptschulabschluss“ als Default
            if (!$abschluss) {
                $abschluss = 'ohne Hauptschulabschluss';
            }
            $sheet->setCellValue('H41', $abschluss === 'ohne Hauptschulabschluss' ? 'X' : null);
            $sheet->setCellValue('H42', $abschluss === 'Hauptschulabschluss' ? 'X' : null);
            $sheet->setCellValue('H43', $abschluss === 'mittlere Reife' ? 'X' : null);
            $sheet->setCellValue('H44', $abschluss === 'Fachhochschulreife' ? 'X' : null);
            $sheet->setCellValue('H45', $abschluss === 'Hochschulreife' ? 'X' : null);
            $sheet->setCellValue('H46', $abschluss === 'Berufsfachschule, die zur Hochschulreife/Fachhochschulreife führt' ? 'X' : null);
            $sheet->setCellValue('H47', $abschluss === 'Fachoberschule 1-jährig (nach vorheriger Berufsausbildung)' ? 'X' : null);
            $sheet->setCellValue('H48', $abschluss === 'Berufsoberschule/Technische Oberschule' ? 'X' : null);

        $berufRanking = [
                'ohne Berufsabschluss' => 1,
                'Berufsvorbereitungsjahr' => 2,
                'berufliche Schulen, die zur mittleren Reife führen' => 3,
                'Berufsgrundbildungsjahr' => 4,
                'Berufsfachschule (duales System)' => 5,
                'Berufsfachschulen, die einen Berufsabschluss vermitteln (o. Gesundheits-/Sozialberufe, Erzieherausbildung)' => 6,
                'Einjährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe' => 7,
                'zwei-/dreijährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe' => 8,
                'Berufsfachschule (Zweitausbildung, nach Erwerb einer Studienberechtigung)' => 9,
                'berufliche Programme, die zwar einen Berufsabschluss, aber keine Studienberechtigung vermitteln' => 10,
                'Fachschulen (o. Gesundheits-/Sozialberufe, Erzieherausbildung), incl. Meisterausbildung, Technikerausbildung' => 11,
                'Ausbildungstätten/Schulen für Erzieher/-innen' => 12,
                'Bachelor an Universitäten, Fachhochschulen, Verwaltungsfachhochschulen, Berufsakademien' => 13,
                'zweiter Bachelorstudiengang' => 14,
                'Diplom (FH)-Studiengang' => 15,
                'Diplom (Universität)-Studiengang' => 16,
                'zweiter Diplom-Studiengang' => 17,
                'Masterstudiengang an Universitäten, Fachhochschulen, Verwaltungshochschulen, Berufsakademien' => 18,
                'zweiter Masterstudiengang' => 19,
                'Promotionsstudium' => 20,
            ];

            $hoechsterAbschluss = $teilnehmer->abschluesse
                ->whereIn('typ', ['beruf', 'hochschule'])
                ->sortBy(fn($a) => $berufRanking[$a->typ] ?? 0)   // nach Ranking sortieren
                ->last();


            $abschluss = $hoechsterAbschluss?->bezeichnung;

            // ✅ Wenn kein Abschluss existiert → „ohne Hauptschulabschluss“ als Default
            if (!$abschluss) {
                $abschluss = 'ohne Berufsabschluss';
            }

            $sheet->setCellValue('H50', $abschluss === 'ohne Berufsabschluss' ? 'X' : null);
            $sheet->setCellValue('H51', $abschluss === 'Berufsvorbereitungsjahr' ? 'X' : null);
            $sheet->setCellValue('H52', $abschluss === 'berufliche Schulen, die zur mittleren Reife führen' ? 'X' : null);
            $sheet->setCellValue('H53', $abschluss === 'Berufsgrundbildungsjahr' ? 'X' : null);
            $sheet->setCellValue('H54', $abschluss === 'Berufsschulen (duales System)' ? 'X' : null);
            $sheet->setCellValue('H55', $abschluss === 'Berufsfachschulen, die einen Berufsabschluss vermittelt (o. Gesundheits-Sozialberufe, Erzieherausbildung)' ? 'X' : null);
            $sheet->setCellValue('H56', $abschluss === 'Einjährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberuf' ? 'X' : null);
            $sheet->setCellValue('H57', $abschluss === 'ohne zwei-/dreijährige Programme an Ausbildungsstätten/Schulen für Gesundheits-/Sozialberufe' ? 'X' : null);
            $sheet->setCellValue('H58', $abschluss === 'Berufsschule (duales System, Zweitausbildung nach Erwerb einer Studienberechtigung)' ? 'X' : null);
            $sheet->setCellValue('H59', $abschluss === 'Berufsfachschule, die einen Berufsabschluss vermittelt (Zweitausbildung nach Erwerb einer Studienberechtigung)' ? 'X' : null);
            $sheet->setCellValue('H60', $abschluss === 'berufliche Programme, die sowohl einen Berufsabschluss wie auch eine Studienberechtigung vermittelt' ? 'X' : null);
            $sheet->setCellValue('H61', $abschluss === 'Fachschulen (o. Gesundheits-/Sozialberufe, Erzieherausbildung) einschl. Meisterausbildung, Technikerausbildung' ? 'X' : null);
            $sheet->setCellValue('H62', $abschluss === 'Ausbildungsstätten/Schulen für Erzieher/-innen' ? 'X' : null);
            $sheet->setCellValue('H63', $abschluss === 'Bachelor an Universitäten, Fachhochschulen, Verwaltungsfachhochschulen, Berufsakademien' ? 'X' : null);
            $sheet->setCellValue('H64', $abschluss === 'zweiter Bachelorstudiengang' ? 'X' : null);
            $sheet->setCellValue('H65', $abschluss === 'Diplom (FH)-Studiengang' ? 'X' : null);
            $sheet->setCellValue('H66', $abschluss === 'Diplomstudiengang (FH) einer Verwaltungsfachhochschule' ? 'X' : null);
            $sheet->setCellValue('H67', $abschluss === 'zweiter Diplom (FH)-Studiengang' ? 'X' : null);
            $sheet->setCellValue('H68', $abschluss === 'Masterstudiengang an Universitäten, Fachhochschulen, Verwaltungshochschulen, Berufsakademien' ? 'X' : null);
            $sheet->setCellValue('H69', $abschluss === 'zweiter Masterstudiengang' ? 'X' : null);

            $sheet->setCellValue('H70', $abschluss === 'Diplom-Studiengang' ? 'X' : null);
            $sheet->setCellValue('H71', $abschluss === 'zweiter Diplom-Studiengang' ? 'X' : null);
            $sheet->setCellValue('H72', $abschluss === 'Hochschulabschluss' ? 'X' : null);
            $sheet->setCellValue('H73', $abschluss === 'Promotionsstudium' ? 'X' : null);

            $sheet->setCellValue('H75', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Arbeitslos' ? 'X' : null);
            $sheet->setCellValue('H76', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Langzeitarbeitslos' ? 'X' : null);
            $sheet->setCellValue('H77', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Nichterwerbstätig' ? 'X' : null);
            $sheet->setCellValue('H78', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Nichterwerbstätige, die keine schriftliche oder berufliche Ausbildung absolvieren' ? 'X' : null);
            $sheet->setCellValue('H79', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Erwerbstätige' ? 'X' : null);
            $sheet->setCellValue('H80', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Selbstständige' ? 'X' : null);
            $sheet->setCellValue('H81', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Auszubildende' ? 'X' : null);
            $sheet->setCellValue('H82', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Berufsschüler' ? 'X' : null);
            $sheet->setCellValue('H83', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Schüler allgemeinbildender Schulen' ? 'X' : null);
            $sheet->setCellValue('H84', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Menschen mit Migrationshintergrund' ? 'X' : null);
            $sheet->setCellValue('H85', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'Flüchtinge' ? 'X' : null);
            $sheet->setCellValue('H86', $projektHasTeilnehmer?->meta?->zielgruppe->bezeichnung === 'KMU' ? 'X' : null);

            $sheet->setCellValue('H89', $teilnehmer->sozialedaten?->wohnsitz_stabil === 1 ? 'X': null );  //SGB XII
            $sheet->setCellValue('H90', $teilnehmer->sozialedaten?->wohnsitz_stabil === 0 ? 'X': null );  //SGB XII

            $sheet->setCellValue('B94', $projektHasTeilnehmer->zeitraume->last()->enddatum );



        //Austritt
        $sheet->setCellValue('H96', $projektHasTeilnehmer->meta->austritt->name === 'Kein Abbruch' ? 'X': null );
        $sheet->setCellValue('H97', $projektHasTeilnehmer->meta->austritt->name === 'Aufnahme sozialversicherungspflichtiger Beschäftigung' ? 'X': null );
        $sheet->setCellValue('H98', $projektHasTeilnehmer->meta->austritt->name === 'Aufnahme geringfügige Beschäftigung' ? 'X': null );
        $sheet->setCellValue('H99', $projektHasTeilnehmer->meta->austritt->name === 'Ausbildungsaufnahme' ? 'X': null );
        $sheet->setCellValue('H100', $projektHasTeilnehmer->meta->austritt->name === 'weiterer Schulbesuch' ? 'X': null );
        $sheet->setCellValue('H101', $projektHasTeilnehmer->meta->austritt->name === 'Aufnahme Studium' ? 'X': null );
        $sheet->setCellValue('H102', $projektHasTeilnehmer->meta->austritt->name === 'Selbstständigkeit' ? 'X': null );
        $sheet->setCellValue('H103', $projektHasTeilnehmer->meta->austritt->name === 'Kündigung Ausbildungsvertrag' ? 'X': null );
        $sheet->setCellValue('H104', $projektHasTeilnehmer->meta->austritt->name === 'vorgezogene Abschlussprüfung' ? 'X': null );
        $sheet->setCellValue('H105', $projektHasTeilnehmer->meta->austritt->name === 'sonstiger Abbruchgrund' ? 'X': null );



            $sheet->setCellValue('H107', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat eine Qualifizierung erhalten (qualifizierter TN-Nachweis)' ? 'X': null );
            $sheet->setCellValue('H108', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'einfacher Teilnahmenachweis' ? 'X': null );
            $sheet->setCellValue('H109', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat qualifizierte Beratung erhalten (Nachweis)' ? 'X': null );
            $sheet->setCellValue('H110', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'ist in ein Kompetenzfeststellungsverfahren gemündet' ? 'X': null );
            $sheet->setCellValue('H111', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat eine Qualifikationsanerkennung erreicht' ? 'X': null );
            $sheet->setCellValue('H112', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'absolviert eine schulische/berufliche Ausbildung' ? 'X': null );
            $sheet->setCellValue('H113', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat einen Arbeitsplatz' ? 'X': null );
            $sheet->setCellValue('H114', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat einen Ausbildungsabschluss erlangt' ? 'X': null );
            $sheet->setCellValue('H115', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat ein Studium aufgenommen' ? 'X': null );
            $sheet->setCellValue('H116', $projektHasTeilnehmer->meta->projektabschluss->bezeichnung === 'hat Teilnehme abgebrochen' ? 'X': null );


            $sheet->setCellValue('H118', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'auf Arbeitssuche' ? 'X': null );
            $sheet->setCellValue('H119', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'absolviert eine schulische/berufliche Ausbildung' ? 'X': null );
            $sheet->setCellValue('H120', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'erlangt eine Qualifizierung' ? 'X': null );
            $sheet->setCellValue('H121', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'hat einen Arbeitsplatz' ? 'X': null );
            $sheet->setCellValue('H122', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'hat eine Selbstständigkeit aufgenommen / selbstständig' ? 'X': null );
            $sheet->setCellValue('H123', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'sonstiger Verbleib' ? 'X': null );
            $sheet->setCellValue('H124', $projektHasTeilnehmer->meta->verbleib->bezeichnung === 'Wechsel in Folgemaßnahme' ? 'X': null );

            // Excel-Datei speichern
        $filename = 'ESF Stammdaten ' . $teilnehmer->nachname . ' ' . $teilnehmer->vorname . '-' . now()->format('Ymd_His') . '.xlsx';
            $path = storage_path('app/'.$filename);   // absoluter Pfad
            $writer = new Xlsx($spreadsheet);
            $writer->save($path);

            return response()->download($path)->deleteFileAfterSend(true);
    }

    public function anwesenheitslite_V1(Request $request, $id){

        $tag = Tage::where('datum', $request->query('tag'))->first();

        $gruppeHasTeilnehmer= GruppeHasPersonen::where('gruppe_id', $id)
        ->where('tage_id', $tag->id)
        ->with('teilnehmer')
        ->get();
        if(empty($gruppeHasTeilnehmer)){
            return back()->with('error', 'Export abgebrochen. Die Gruppe besitzt über keine Teilnehmer');
        }

        $gruppe = Gruppe::findOrFail($id)->with('bereich', 'teilnehmer', 'projekt', 'betreuer')->first();
        if(!$gruppe){
             return back()->with('error', 'Export abgebrochen. Die Gruppe konnte nich gefunden werden');
        }



        $existingFile = storage_path('vorlage/projekte/excel/anwesenheitsliste_V1.xls');
            if(!file_exists($existingFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }

            // Excel-Datei öffnen
            $spreadsheet = IOFactory::load($existingFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Daten in den Zellen einfügen
            $sheet->setCellValue('D3', $gruppe?->projekt?->name );
            $sheet->setCellValue('D4', $gruppe->betreuer->nachname . ', ' . $gruppe->betreuer->vorname);
            $sheet->setCellValue('D6', $request->query('tag') );
            $sheet->setCellValue('D7', $gruppe?->bereich?->name );

            // ============================
            //  TEILNEHMER EINTRAGEN
            // ============================
            $row = 17; // ← Startzeile in Excel (anpassen!)

            foreach ($gruppeHasTeilnehmer as $eintrag) {

                $vorname  = $eintrag->teilnehmer->vorname;
                $nachname = $eintrag->teilnehmer->nachname;

                // In Excel schreiben
                $sheet->setCellValue("B$row", $nachname . ', ' . $vorname);
                $row++;
            }

            // Excel-Datei speichern
            $filename = 'Anwesenheitsliste_V1 ' . $gruppe?->projekt?->name . ' ' . $gruppe?->bereich?->name . '-' . now()->format('Ymd_His') . '.xlsx';
                $path = storage_path('app/'.$filename);   // absoluter Pfad
                $writer = new Xlsx($spreadsheet);
                $writer->save($path);

            return response()->download($path)->deleteFileAfterSend(true);
    }




    public function anwesenheitliste_monat_projekt_gruppe(Request $request, $id)
    {
        // --- 1) Projekt prüfen ---
        if (!$projekt = Projekt::where('id', $id)->first()) {
            abort(404, 'Projekt existiert nicht.');
        }
        // --- 2) Monat + Jahr validieren ---
        $data = $request->validate([
            'monat' => 'required|integer|min:1|max:12',
            'jahr'  => 'required|integer|min:2000|max:2100',
        ]);

        $monat = $data['monat'];
        $jahr  = $data['jahr'];

        // --- 3) Start/Ende AUTOMATISCH berechnen ---
        $start = Carbon::create($jahr, $monat, 1)->format('Y-m-d');
        $ende  = Carbon::create($jahr, $monat, 1)->endOfMonth()->format('Y-m-d');

        // --- 4) Teilnehmer + Zeiträume + Anwesenheiten laden ---
        $projektHasTeilnehmer = ProjektHasPersonen::with([
                'teilnehmer',
                'zeitraume',
                'meta',
                'teilnehmer.sozialedaten',
                'teilnehmer.anwesenheiten.tag',
                'teilnehmer.anwesenheiten.status'
            ])
            ->where('projekt_id', $id)
            ->whereHas('zeitraume', function ($q) use ($start, $ende) {
                $q->whereDate('anfangsdatum', '<=', $ende)
                ->whereDate('enddatum', '>=', $start);
            })
            ->get();

        if ($projektHasTeilnehmer->isEmpty()) {
            abort(400, 'Keine Teilnehmer im ausgewählten Zeitraum.');
        }

        // --- 5) Excel-Vorlage laden ---
        $existingFile = storage_path('vorlage/projekte/excel/anwesenheitliste_monat_projekt_gruppe.xlsx');

        if (!file_exists($existingFile)) {
            abort(500, 'Excel-Vorlage nicht gefunden: ' . $existingFile);
        }

        $spreadsheet = IOFactory::load($existingFile);
        $sheet = $spreadsheet->getActiveSheet();

        // --- 6) Tage zwischen Start und Ende erstellen ---
        $tage = [];
        $tag = Carbon::parse($start);

        while ($tag->lte(Carbon::parse($ende))) {
            $tage[] = $tag->format('Y-m-d');
            $tag->addDay();
        }

        // --- 7) Spalten M → AQ zuordnen ---
        $columns = [];
        $colIndex = 13; // M

        foreach ($tage as $datum) {
            if ($colIndex > 43) break; // Spalte AQ = 42
            $columns[$datum] = Coordinate::stringFromColumnIndex($colIndex);
            $colIndex++;
        }
        // --- 8) Teilnehmer eintragen ---
        $sheet->setCellValue("S1", Carbon::create()->month($data['monat'])->locale('de')->monthName . ' ' . $jahr  ?? '');
        $sheet->setCellValue("AA1", 'ZBB gGmbH ' . $projekt->name ?? '');

        $row = 3;

        foreach ($projektHasTeilnehmer as $eintrag) {
            $person = $eintrag->teilnehmer;

            // Personendaten
            $sheet->setCellValue("B$row", $person->nachname . ', ' . $person->vorname);
            $sheet->setCellValue("H$row", $person->sozialedaten?->kundennummer ?? '');
            $sheet->setCellValue("I$row", $person->geburtsdatum ? Carbon::parse($person->geburtsdatum)->format('d.m.Y') : '' );
            $sheet->setCellValue("J$row", $eintrag?->zeitraume?->first()?->starttermin ? Carbon::parse($eintrag->zeitraume->first()->starttermin)->format('d.m.Y') : '' );
            $sheet->setCellValue("K$row", $eintrag?->zeitraume?->first()?->endtermin ?  Carbon::parse($eintrag->zeitraume->first()->endtermin)->format('d.m.Y') : '' );
            $sheet->setCellValue("L$row", $eintrag?->zeitraume?->first()?->enddatum ? Carbon::parse($eintrag->zeitraume->first()->enddatum)->format('d.m.Y') : '');
            $sheet->setCellValue("BC$row",
                ($eintrag->meta?->projektbegleiter
                    ? (($eintrag->meta?->projektbegleiter->geschlecht === 'w' ? 'Frau' : 'Herr')
                        . ' ' . $eintrag->meta?->projektbegleiter->vorname
                        . ' ' . $eintrag->meta?->projektbegleiter->nachname)
                    : '')
            );

            $sheet->setCellValue( "BD$row", $eintrag->meta?->betreuer ? trim( ((['m' => 'Herr', 'w' => 'Frau'][$eintrag->meta?->betreuer->geschlecht] ?? '') . ' ' . $eintrag->meta?->betreuer?->vorname . ' ' . $eintrag->meta?->betreuer?->nachname) ) : '');
            // Anwesenheiten
            $anwesenheiten = $person->anwesenheiten;

            foreach ($tage as $datum) {

                $found = $anwesenheiten->first(function ($a) use ($datum) {
                    return $a->tag->datum === $datum;
                });

                $kuerzel = $found ? ($found->status->abkuerzung ?? '') : '';

                $col = $columns[$datum];

                // NICHT Formelspalten überschreiben
                if ($col !== 'AR') {
                    $sheet->setCellValue($col . $row, $kuerzel);
                }
            }

            $row++;
        }

        // --- 9) Datei herunterladen ---
        $filename = 'Anwesenheitsliste_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename);
    }




}
