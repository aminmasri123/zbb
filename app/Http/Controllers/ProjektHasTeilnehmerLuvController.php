<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use App\Models\ProjektHasTeilnehmerLuv;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjektHasTeilnehmerLuvController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teilnehmer_id'      => ['required', 'integer', 'exists:personens,id'],
            'von'                => ['required', 'date'],
            'bis'                => ['required', 'date'],
            'typ'                => ['required', 'in:Start,Verlauf,Abschluss'],
            'ausgangssituation'  => ['nullable', 'string'],
            'zielvereinbarung'   => ['nullable', 'string'],
        ]);

        // ❌ Rückgabe bei Fehler – für axios (JSON!)
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Bitte alle Felder korrekt ausfüllen!',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // ✔ Vue ISO-Date → MySQL DATE
        $validated['von'] = date('Y-m-d', strtotime($validated['von']));
        $validated['bis'] = date('Y-m-d', strtotime($validated['bis']));

        $user = auth()->user();

        $projektHasTeilnehmer = ProjektHasPersonen::where('personen_id', $validated['teilnehmer_id'])
            ->where('projekt_id', $user->current_team_id)
            ->orderBy('created_at', 'desc')
            ->first();

        $luv = ProjektHasTeilnehmerLuv::create([
            'projekt_person_id' => $projektHasTeilnehmer->id,
            'typ'               => $validated['typ'],
            'von'               => $validated['von'],
            'bis'               => $validated['bis'],
            'ausgangssituation' => $validated['ausgangssituation'],
            'zielvereinbarung'  => $validated['zielvereinbarung'],
        ]);

        // ✔ JSON Antwort für axios + Modal schließen
        return response()->json([
            'success' => true,
            'message' => 'StandLuvort erfolgreich zugewiesen!',
            'luv'     => $luv,
        ]);
    }






    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $luv = ProjektHasTeilnehmerLuv::findOrFail($id);
            $luv->delete();

            return response()->json(['message' => 'der LuV wurde  erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function export(string $id)
    {
        $templateFile = storage_path('vorlage/projekte/word/luv.docx');

        if (!file_exists($templateFile)) {
            return redirect()->back()->with('error', 'Die LuV-Datei für den Export konnte nicht gefunden werden.');
        }

        // LuV + Beziehungen laden
        $luv = ProjektHasTeilnehmerLuv::where('id', $id)
            ->with(
                'projektHasTeilnehmer',
                'projektHasTeilnehmer.zeitraume',
                'projektHasTeilnehmer.projekt',
                'projektHasTeilnehmer.teilnehmer',
                'projektHasTeilnehmer.teilnehmer.gruppen',
                'projektHasTeilnehmer.teilnehmer.anwesenheiten',
                'projektHasTeilnehmer.meta',
                'projektHasTeilnehmer.teilnehmer.sozialedaten'
            )
        ->first();

        if (!$luv) {
            return redirect()->back()->with('error', 'Die LuV-Datei für den Export konnte nicht gefunden werden.');
        }

        $teilnehmer       = $luv->projektHasTeilnehmer->teilnehmer;
        $betreuer         = $luv->projektHasTeilnehmer->meta->betreuer;
        $projektbegleiter = $luv->projektHasTeilnehmer->meta->projektbegleiter;
        $projekt          = $luv->projektHasTeilnehmer->projekt;
        $zeitraum         = $luv->projektHasTeilnehmer->zeitraume()->orderBy('id', 'desc')->first();

        // Zeitraum (LuV)
        $von = $luv->von;
        $bis = $luv->bis;

        // Alle Anwesenheiten des Teilnehmers
        $alleAnwesenheiten = $teilnehmer->anwesenheiten;

        // Nur Anwesenheiten im Zeitraum (LuV)
        $anwesenheitenImZeitraum = $alleAnwesenheiten->filter(function ($tag) use ($von, $bis) {
            return Carbon::parse($tag->tag->datum)->between($von, $bis);
        });

        $alleGruppen = $teilnehmer->gruppen;

        // Anwesenheitsstatus zählen (A, U, F, E, K)
        $countA = $anwesenheitenImZeitraum->where('status.abkuerzung', 'A')->count();
        $countU = $anwesenheitenImZeitraum->where('status.abkuerzung', 'U')->count();
        $countF = $anwesenheitenImZeitraum->where('status.abkuerzung', 'F')->count();
        $countE = $anwesenheitenImZeitraum->where('status.abkuerzung', 'E')->count();
        $countK = $anwesenheitenImZeitraum->where('status.abkuerzung', 'K')->count();

        // Quoten berechnen
        $total = $anwesenheitenImZeitraum->count();

        $pAU = $total > 0 ? round((($countA + $countU) / $total) * 100, 1) : 0;
        $pKE = $total > 0 ? round((($countK + $countE) / $total) * 100, 1) : 0;
        $pF  = $total > 0 ? round(($countF / $total) * 100, 1) : 0;

        // Word-Template laden
        $templateProcessor = new TemplateProcessor($templateFile);

        // Stammdaten
        $templateProcessor->setValue('typ', $luv->typ);
        $templateProcessor->setValue('zeitraumStart', $luv->von->format('d.m.Y'));
        $templateProcessor->setValue('zeitraumBis', $luv->bis->format('d.m.Y'));
        $templateProcessor->setValue('geburtsdatum', $teilnehmer->geburtsdatum->format('d.m.Y'));
        $templateProcessor->setValue('kundennummer', $teilnehmer->sozialedaten->kundennummer);
        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('vermittler', $projektbegleiter->vorname . ' ' . $projektbegleiter->nachname);
        $templateProcessor->setValue('betreuer', $betreuer->vorname . ' ' . $betreuer->nachname);
        $templateProcessor->setValue('projekt', $projekt->name);
        $templateProcessor->setValue('zuweisungVon', $zeitraum->starttermin->format('d.m.Y'));
        $templateProcessor->setValue('zuweisungBis', $zeitraum->endtermin->format('d.m.Y'));
        $templateProcessor->setValue('ausgangssituation', $luv->ausgangssituation);
        $templateProcessor->setValue('zielvereinbarung', $luv->zielvereinbarung);

        // Liste aller LUVs — Textblock
        $luvs = ProjektHasTeilnehmerLuv::where(
            'projekt_person_id',
            $luv->projektHasTeilnehmer->id
        )->get();

        $liste = "";
        foreach ($luvs as $item) {
            $liste .= "- " . $item->typ . "-Luv vom " . $item->created_at->format('d.m.Y') . "\n";
        }
        $templateProcessor->setValue('listeErstellteLuvs', $liste);

        // Anwesenheit in Word setzen
        $templateProcessor->setValue('A', $countA);
        $templateProcessor->setValue('U', $countU);
        $templateProcessor->setValue('F', $countF);
        $templateProcessor->setValue('E', $countE);
        $templateProcessor->setValue('K', $countK);

        // Quoten
        $templateProcessor->setValue('PAU', $pAU);
        $templateProcessor->setValue('PKE', $pKE);
        $templateProcessor->setValue('PF', $pF);




        $listeGruppe = "";
        foreach ( $alleGruppen->pluck('bereich.name')->unique() as $item) {
            $listeGruppe .= $item . ", " ;
        }
        $templateProcessor->setValue('listeBereiche', $listeGruppe);





        // Dokument speichern
        $outputPath = storage_path("app/temp/luv{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

}
