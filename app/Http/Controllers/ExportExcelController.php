<?php

namespace App\Http\Controllers;

use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\Storage;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

if (!$teilnehmer->sozialedaten) $errors[] = 'Sozialdaten fehlen';

if (!$projektHasTeilnehmer?->abschluss) $errors[] = 'Abschlussdaten fehlen';
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

        $sheet->setCellValue('H75', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Arbeitslos' ? 'X' : null);
        $sheet->setCellValue('H76', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Langzeitarbeitslos' ? 'X' : null);
        $sheet->setCellValue('H77', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Nichterwerbstätig' ? 'X' : null);
        $sheet->setCellValue('H78', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Nichterwerbstätige, die keine schriftliche oder berufliche Ausbildung absolvieren' ? 'X' : null);
        $sheet->setCellValue('H79', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Erwerbstätige' ? 'X' : null);
        $sheet->setCellValue('H80', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Selbstständige' ? 'X' : null);
        $sheet->setCellValue('H81', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Auszubildende' ? 'X' : null);
        $sheet->setCellValue('H82', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Berufsschüler' ? 'X' : null);
        $sheet->setCellValue('H83', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Schüler allgemeinbildender Schulen' ? 'X' : null);
        $sheet->setCellValue('H84', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Menschen mit Migrationshintergrund' ? 'X' : null);
        $sheet->setCellValue('H85', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'Flüchtinge' ? 'X' : null);
        $sheet->setCellValue('H86', $teilnehmer->zielgruppen?->last()?->bezeichnung === 'KMU' ? 'X' : null);

        $sheet->setCellValue('H89', $teilnehmer->sozialedaten?->wohnsitz_stabil === 1 ? 'X': null );  //SGB XII
        $sheet->setCellValue('H90', $teilnehmer->sozialedaten?->wohnsitz_stabil === 0 ? 'X': null );  //SGB XII

        $sheet->setCellValue('B94', $projektHasTeilnehmer->zeitraume->last()->enddatum );




     //Austritt
        $sheet->setCellValue('H96', $projektHasTeilnehmer->abschluss->austritttypen_id === 1 ? 'X': null );
        $sheet->setCellValue('H97', $projektHasTeilnehmer->abschluss->austritttypen_id === 2 ? 'X': null );
        $sheet->setCellValue('H98', $projektHasTeilnehmer->abschluss->austritttypen_id === 3 ? 'X': null );
        $sheet->setCellValue('H99', $projektHasTeilnehmer->abschluss->austritttypen_id === 4 ? 'X': null );
        $sheet->setCellValue('H100', $projektHasTeilnehmer->abschluss->austritttypen_id === 5 ? 'X': null );
        $sheet->setCellValue('H101', $projektHasTeilnehmer->abschluss->austritttypen_id === 6 ? 'X': null );
        $sheet->setCellValue('H102', $projektHasTeilnehmer->abschluss->austritttypen_id === 7 ? 'X': null );
        $sheet->setCellValue('H103', $projektHasTeilnehmer->abschluss->austritttypen_id === 8 ? 'X': null );
        $sheet->setCellValue('H104', $projektHasTeilnehmer->abschluss->austritttypen_id === 9 ? 'X': null );
        $sheet->setCellValue('H105', $projektHasTeilnehmer->abschluss->austritttypen_id === 10 ? 'X': null );

        $sheet->setCellValue('H107', $projektHasTeilnehmer->abschluss->ergebnisse_id === 2 ? 'X': null );
        $sheet->setCellValue('H108', $projektHasTeilnehmer->abschluss->ergebnisse_id === 3 ? 'X': null );
        $sheet->setCellValue('H109', $projektHasTeilnehmer->abschluss->ergebnisse_id === 4 ? 'X': null );
        $sheet->setCellValue('H110', $projektHasTeilnehmer->abschluss->ergebnisse_id === 5 ? 'X': null );
        $sheet->setCellValue('H111', $projektHasTeilnehmer->abschluss->ergebnisse_id === 6 ? 'X': null );
        $sheet->setCellValue('H112', $projektHasTeilnehmer->abschluss->ergebnisse_id === 7 ? 'X': null );
        $sheet->setCellValue('H113', $projektHasTeilnehmer->abschluss->ergebnisse_id === 8 ? 'X': null );
        $sheet->setCellValue('H114', $projektHasTeilnehmer->abschluss->ergebnisse_id === 9 ? 'X': null );
        $sheet->setCellValue('H115', $projektHasTeilnehmer->abschluss->ergebnisse_id === 10 ? 'X': null );
        $sheet->setCellValue('H116', $projektHasTeilnehmer->abschluss->ergebnisse_id === 11 ? 'X': null );


        $sheet->setCellValue('H118', $projektHasTeilnehmer->abschluss->verbleib_id === 1 ? 'X': null );
        $sheet->setCellValue('H119', $projektHasTeilnehmer->abschluss->verbleib_id === 2 ? 'X': null );
        $sheet->setCellValue('H120', $projektHasTeilnehmer->abschluss->verbleib_id === 3 ? 'X': null );
        $sheet->setCellValue('H121', $projektHasTeilnehmer->abschluss->verbleib_id === 4 ? 'X': null );
        $sheet->setCellValue('H122', $projektHasTeilnehmer->abschluss->verbleib_id === 5 ? 'X': null );
        $sheet->setCellValue('H123', $projektHasTeilnehmer->abschluss->verbleib_id === 6 ? 'X': null );
        $sheet->setCellValue('H124', $projektHasTeilnehmer->abschluss->verbleib_id === 7 ? 'X': null );

        // Excel-Datei speichern
       $filename = 'ESF Stammdaten ' . $teilnehmer->nachname . ' ' . $teilnehmer->vorname . '-' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/'.$filename);   // absoluter Pfad
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
