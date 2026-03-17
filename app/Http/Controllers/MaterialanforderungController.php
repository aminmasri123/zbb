<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\MaterialanforderungGenehmigung;
use App\Models\Projekt;
use App\Models\User;
use App\Notifications\CreateMaterialanforderungGenehmigenKufmaenischNotification;
use App\Notifications\CreateMaterialanforderungNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MaterialanforderungController extends Controller
{
    /* public function index(Request $request)
    {
        $search = $request->get('search', '');
        $user = Auth()->User()->person;

        $anforderungen = Materialanforderung::with(['besteller', 'artikeln'])
            ->when($search, fn($q) => $q->where('projekt', 'like', "%$search%"))
            ->where('ersteller_id', $user->id)
            ->get();


        $projekt = Projekt::where('id', Auth()->User()->current_team_id)->first();

        return Inertia::render('Bestellungen/Materialanforderung/Index', [
            'anforderungen' => $anforderungen,
            'user' => $user,
            'projekt' => $projekt,
        ]);
    } */


    public function index(Request $request)
    {

    $user = auth()->user();
    $search = $request->input('search');
    // Grundquery
    $query = Materialanforderung::with(['projekt', 'besteller', 'artikeln'])->where('projekt_id', $user->current_team_id);

    // Suche
    if ($search) {
        $query->where('materialanforderungen.id', 'like', "%{$search}%")
              ->orWhere('materialanforderungen.bemerkungen', 'like', "%{$search}%");
    }

    // Berechtigungen
    if ($user->can('materialanforderung.kaufmännische_freigabe.index')) {
         $query->where('status', '!=', 'entwurf')->where('status', '!=', 'eingereicht');
    }
    elseif ($user->can('materialanforderung.sachlische_freigabe.index')) {
        // Alle Projekte, die dem User zugeordnet sind
        $projekteIds = $user->projekte()->pluck('projekts.id');
        $query->where('status', 'eingereicht')
        ->whereHas('projekt', function ($q) use ($projekteIds) {
            $q->whereIn('projekts.id', $projekteIds);
        });

    } else {
        // Nur eigene
        $query->where('materialanforderungs.ersteller_id', $user->id);
    }

    $anforderungen = $query->get();

        return inertia('Bestellungen/Materialanforderung/Index', [
            'anforderungen' => $anforderungen,
            'user' => $user,
        ]);
    }

     public function create()
    {
        $user = Auth()->User()->person;

        $projekt = Projekt::where('id', Auth()->User()->current_team_id)->first();
        return Inertia::render('Bestellungen/Materialanforderung/Create', [
            'user' => $user,
            'projekt' => $projekt,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'projekt' => 'required|string',
            'kostenstelle' => 'required|string',
            'ersteller_id' => 'required|exists:users,id',
            'bemerkungen' => 'nullable|string',
            'positionen' => 'required|array|min:1',

            'positionen.*.pos' => 'required|integer',
            'positionen.*.artikel' => 'nullable|string',
            'positionen.*.link' => 'nullable|string',
            'positionen.*.stueck' => 'required|integer|min:1',
            'positionen.*.art_nr' => 'nullable|string',
            'positionen.*.einzelpreis' => 'required|numeric',
            'positionen.*.mwst' => 'required|numeric',
            'positionen.*.gesamtpreis' => 'required|numeric',
        ]);

        $gesamtsumme = 0;
        $endsumme = 0;

        foreach ($data['positionen'] as $pos) {

            $netto = $pos['einzelpreis'] * $pos['stueck'];

            $mwst = $netto * ($pos['mwst'] / 100);

            $brutto = $netto + $mwst;

            $gesamtsumme += $netto;
            $endsumme += $brutto;
        }

        /*
        Materialanforderung erstellen
        */

        $anforderung = Materialanforderung::create([
            'projekt_id' => 5,
            'kostenstelle' => $data['kostenstelle'],
            'ersteller_id' => $data['ersteller_id'],
            'bemerkungen' => $data['bemerkungen'] ?? null,
            'gesamtpreis' => $gesamtsumme,
            'endsumme' => $endsumme
        ]);

        /*
        Positionen speichern
        */

        foreach ($data['positionen'] as $pos) {

            $anforderung->artikeln()->create([
                'pos' => $pos['pos'],
                'artikel' => $pos['artikel'],
                'link' => $pos['link'],
                'stueck' => $pos['stueck'],
                'art_nr' => $pos['art_nr'],
                'einzelpreis' => $pos['einzelpreis'],
                'mwst' => $pos['mwst'],
                'gesamtpreis' => $pos['gesamtpreis'],
            ]);
        }
        /*
        |-----------------------------
        | Notifications
        |-----------------------------
        */

        /*
            $roles = Role::whereIn('name', ['Administrator', 'IT-Administrator'])
            ->with('users')
            ->get();
        */


                $users = User::permission('materialanforderung.sachlische_freigabe.index')
                ->with('person', 'person.projekte')
                ->get(); // Returns only users with the permission 'edit articles' (inherited or directly)

                $meinProjekt = Auth()->User()->current_team_id;
                $users = $users->filter(function ($user) use ($meinProjekt) {
                    return $user->person && $user->person->projekte->contains('id', $meinProjekt);
                });

        foreach ($users as $user) {
            $user->notify(new CreateMaterialanforderungNotification($anforderung));
        }


        return redirect()->route('materialanforderung.index');
    }

    public function update(Request $request)
    {
        $anforderung = Materialanforderung::with('artikeln')->findOrFail($request->id);

        // Berechtigung prüfen
        $this->authorize('materialanforderung.update');

        // Nur Entwürfe dürfen bearbeitet werden
        if($anforderung->status !== 'entwurf'){
            abort(403, 'Nur Entwürfe können bearbeitet werden');
        }

        // Validierung
        $validator = $request->validate([
            'kostenstelle' => ['required', 'string', 'max:255'],
            'bemerkungen' => ['nullable', 'string'],
            'artikeln' => ['required', 'array', 'min:1'],
            'artikeln.*.artikel' => ['required', 'string', 'max:255'],
            'artikeln.*.stueck' => ['required', 'integer', 'min:1'],
            'artikeln.*.einzelpreis' => ['required', 'numeric', 'min:0'],
            'artikeln.*.mwst' => ['required', 'numeric', 'between:0,100'],
            'artikeln.*.link' => ['nullable', 'url'],
            'artikeln.*.art_nr' => ['nullable', 'string', 'max:100'],
        ]);



        // Materialanforderung aktualisieren
        $anforderung->update([
            'kostenstelle' => $request->kostenstelle,
            'bemerkungen' => $request->bemerkungen,
        ]);

        // Artikel aktualisieren / erstellen
        foreach ($request->artikeln as $a) {
            if (isset($a['id']) && $artikel = $anforderung->artikeln()->find($a['id'])) {
                $artikel->update([
                    'pos' => $a['pos'],
                    'artikel' => $a['artikel'],
                    'stueck' => $a['stueck'],
                    'art_nr' => $a['art_nr'] ?? null,
                    'einzelpreis' => $a['einzelpreis'],
                    'mwst' => $a['mwst'],
                    'gesamtpreis' => $a['stueck'] * $a['einzelpreis'],
                    'link' => $a['link'] ?? null,
                ]);
            } else {
                $anforderung->artikeln()->create([
                    'pos' => $a['pos'],
                    'artikel' => $a['artikel'],
                    'stueck' => $a['stueck'],
                    'art_nr' => $a['art_nr'] ?? null,
                    'einzelpreis' => $a['einzelpreis'],
                    'mwst' => $a['mwst'],
                    'gesamtpreis' => $a['stueck'] * $a['einzelpreis'],
                    'link' => $a['link'] ?? null,
                ]);
            }
        }
    }

    public function destroy(Materialanforderung $materialanforderung)
    {
        $materialanforderung->delete();

        return redirect()->route('materialanforderung.index');
    }

    public function show($id)
    {
        $user = auth()->user();
        $query = Materialanforderung::with(['projekt', 'besteller', 'artikeln'])
        ->where('id', $id)
        ->first();

        $verlauf = MaterialanforderungGenehmigung::with('genehmiger')->where('anforderung_id', $id)->orderBy('created_at', 'desc')->get();
        if(!$query) {
            return back()->with('error', 'Materialanforderung nicht gefunden.');
        }
        // Berechtigungen
        if ($user->can('materialanforderung.kaufmännische_freigabe.index') || $user->can('materialanforderung.sachlische_freigabe.index')) {


        }elseif($query->ersteller_id != $user->person->id){
            return back()->with('error', 'Sie haben keine Berechtigung, diese Materialanforderung einzusehen.');
        }

        $notification = auth()->user()->notifications()->where('data->id', $id)->where('data->typ', 'Materialanforderung')->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return Inertia::render('Bestellungen/Materialanforderung/Show', [
            'anforderung' => $query,
            'canConfirmSachlich' => auth()->user()->can('materialanforderung.sachlische_freigabe.update'),
            'canConfirmKaufmaenisch' => auth()->user()->can('materialanforderung.kaufmännische_freigabe.update'),
            'canEditMaterialanforderung' => auth()->user()->can('materialanforderung.update'),
            'canBestellen' => auth()->user()->can('materialanforderung.bestellwesen.update'),
            'verlauf' => $verlauf,

        ]);
    }

   /* public function genehmigenSachlich($id)
    {
        $user = auth()->user();

        // Materialanforderung laden
        $anforderung = Materialanforderung::findOrFail($id);



        if ($anforderung->status !== 'Entwurf') {
            return back()->with('error', 'Diese Genehmigung wurde bereits bearbeitet.');
        }


        // Genehmigung aktualisieren
        MaterialanforderungGenehmigung::create([
                'anforderung_id' => $anforderung->id,
                'genehmiger_id' => $user->id,
            ]);



            $anforderung->update([
                'status' => 'Freigegeben'
            ]);

              $users = User::permission('materialanforderung.kaufmännische_freigabe.update')->get();


            foreach ($users as $user) {
                $user->notify(new CreateMaterialanforderungGenehmigenKufmaenischNotification($anforderung));
            }

        return back()->with('success', 'Materialanforderung erfolgreich sachlich genehmigt.');
    } */
    public function genehmigen($id, $status)
    {


        if(!in_array($status, ['sachlich_genehmigt', 'eingereicht', 'kaufmaennisch_genehmigt', 'zur_ueberarbeitung', 'stornieren', 'geliefert', 'teilweise_geliefert', 'bestellt'])){
            return back()->with('error', 'Ungültiger Status.');
        }

        $user = auth()->user();
        $anforderung = Materialanforderung::findOrFail($id);

        if($status == 'eingereicht' && $anforderung->status != 'entwurf' && $anforderung->status != 'zur_ueberarbeitung'){
            return redirect()->back()->with('error', 'Ein fehler ist aufgetreten, bitte kontaktieren Sie den Administrator.');
        }

        if($status == 'sachlich_genehmigt' && $anforderung->status != 'eingereicht'){
            return redirect()->back()->with('error', 'die Materialanforderung soll zu erst Freigegeben werden.');
        }
        if($status == 'kaufmaennisch_genehmigt' && $anforderung->status != 'sachlich_genehmigt'){
            return redirect()->back()->with('error', 'Die Materialanforderung soll zuerst sachlich genehmigt werden.');
        }
        // Genehmigung aktualisieren

        MaterialanforderungGenehmigung::create([
                'anforderung_id' => $anforderung->id,
                'genehmiger_id' => $user->person->id,
                'status' => $status,
                'kommentar' => request('anmerkung'),
            ]);

            $anforderung->update([
                'status' => $status,
            ]);
        //noch zu bearbeiten
         //'geliefert', 'teilweise_geliefert',

            if($status == 'eingereicht')
            {
                $users = User::permission('materialanforderung.sachlische_freigabe.index')
                ->with('person', 'person.projekte')
                ->get(); // Returns only users with the permission 'edit articles' (inherited or directly)

                $meinProjekt = Auth()->User()->current_team_id;
                $users = $users->filter(function ($user) use ($meinProjekt) {
                    return $user->person && $user->person->projekte->contains('id', $meinProjekt);
                });

            }
            elseif($status == 'sachlich_genehmigt')
            {
                $users = User::permission('materialanforderung.kaufmännische_freigabe.update')->get();

            }
            elseif($status == 'kaufmaennisch_genehmigt')
            {
                $users = User::permission('materialanforderung.bestellwesen.update')->get();

            }
            elseif($status == 'zur_ueberarbeitung' || $status == 'stornieren' || $status == 'bestellt')
            {
                $user = User::find($anforderung->ersteller_id);
                $user->notify(new CreateMaterialanforderungGenehmigenKufmaenischNotification($anforderung, $status));
                return back()->with('success', 'Materialanforderung erfolgreich ' . $status . '.');
            }

        foreach ($users as $user) {
            $user->notify(new CreateMaterialanforderungGenehmigenKufmaenischNotification($anforderung, $status));
        }
        return back()->with('success', 'Materialanforderung erfolgreich ' . $status . '.');
    }
}
