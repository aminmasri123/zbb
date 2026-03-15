<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\MaterialanforderungGenehmigung;
use App\Models\Projekt;
use App\Models\User;
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
    $query = Materialanforderung::with(['projekt', 'besteller', 'artikeln']);

    // Suche
    if ($search) {
        $query->where('materialanforderungen.id', 'like', "%{$search}%")
              ->orWhere('materialanforderungen.bemerkungen', 'like', "%{$search}%");
    }

    // Berechtigungen
    if ($user->can('materialanforderung.kaufmännische_freigabe.index')) {
        // Zeige alle, nichts filtern
    }
    elseif ($user->can('materialanforderung.sachlische_freigabe.index')) {
        // Alle Projekte, die dem User zugeordnet sind
        $projekteIds = $user->projekte()->pluck('projekts.id');
        $query->whereHas('projekt', function ($q) use ($projekteIds) {
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
                'stück' => $pos['stueck'],
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

    public function update(Request $request, Materialanforderung $materialanforderung)
    {
        $data = $request->validate([
            'projekt' => 'required|string',
            'kostenstelle' => 'required|string',
            'bemerkungen' => 'nullable|string',
        ]);

        $materialanforderung->update($data);

        return redirect()->route('materialanforderung.index');
    }

    public function destroy(Materialanforderung $materialanforderung)
    {
        $materialanforderung->delete();

        return redirect()->route('materialanforderung.index');
    }

    public function show($id)
    {
        $notification = auth()->user()->notifications()->where('data->id', $id)->where('data->typ', 'Materialanforderung')->first();
        if ($notification) {
            $notification->markAsRead();
        }
        $user = auth()->user();
        $materialanforderung = Materialanforderung::where('id', $id)
        ->with('artikeln', 'besteller')
        ->first();

        return Inertia::render('Bestellungen/Materialanforderung/Show', [
            'anforderung' => $materialanforderung,
            'canConfirmSachlich' => auth()->user()->can('materialanforderung.sachlische_freigabe.update'),
            'canConfirmKaufmaenisch' => auth()->user()->can('materialanforderung.kaufmännische_freigabe.update')
        ]);
    }

   public function genehmigenSachlich($id)
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

        return back()->with('success', 'Materialanforderung erfolgreich sachlich genehmigt.');
    }
}
