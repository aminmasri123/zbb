<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\MaterialanforderungGenehmigung;
use App\Models\Projekt;
use App\Notifications\CreateMaterialanforderungNotification;
use App\Notifications\UpdateMaterialanforderungNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaterialanforderungController extends Controller
{
    public function __construct(private readonly NotificationRecipientService $notificationRecipients)
    {
    }

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

    // Eigene Anforderungen sind immer sichtbar. Freigabeberechtigungen
    // erweitern die Liste um die jeweils zu bearbeitenden Anforderungen.
    $projekteIds = $user->projekte()->pluck('projekts.id');
    $query->where(function ($visibility) use ($user, $projekteIds) {
        $visibility->where('materialanforderungs.ersteller_id', $user->id);

        if ($user->can('materialanforderung.kaufmännische_freigabe.index')) {
            $visibility->orWhere(function ($approval) {
                $approval->whereNotIn('status', ['entwurf', 'eingereicht']);
            });
        }

        if ($user->can('materialanforderung.sachlische_freigabe.index')) {
            $visibility->orWhere(function ($approval) use ($projekteIds) {
                $approval->where('status', 'eingereicht')
                    ->whereHas('projekt', function ($project) use ($projekteIds) {
                        $project->whereIn('projekts.id', $projekteIds);
                    });
            });
        }
    });

    $anforderungen = $query
        ->orderByDesc('materialanforderungs.created_at')
        ->orderByDesc('materialanforderungs.id')
        ->get();

        return inertia('Bestellungen/Materialanforderung/Index', [
            'anforderungen' => $anforderungen,
            'user' => $user,
        ]);
    }

    public function create()
    {
        $user = Auth()->User()->person;

        $projekt = Projekt::where('id', Auth()->User()->current_team_id)->first();
        $kostenstellen = $projekt
            ? $projekt->kostenstellen()
                ->orderByPivot('gueltig_von', 'desc')
                ->orderByPivot('id', 'desc')
                ->limit(3)
                ->get(['kostenstelles.id', 'kostenstelles.kostenstelle'])
            : collect();

        return Inertia::render('Bestellungen/Materialanforderung/Create', [
            'user' => $user,
            'projekt' => $projekt,
            'kostenstellen' => $kostenstellen,
        ]);
    }

    public function store(Request $request)
    {
        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);
        $projektKostenstellenIds = $projekt->kostenstellen()->pluck('kostenstelles.id');

        $data = $request->validate([
            'kostenstelle' => [
                'required',
                'string',
                Rule::exists('kostenstelles', 'kostenstelle')
                    ->where(fn ($query) => $query->whereIn('id', $projektKostenstellenIds)),
            ],
            'bemerkungen' => 'nullable|string',
            'positionen' => 'required|array|min:1',

            'positionen.*.pos' => 'required|integer',
            'positionen.*.artikel' => 'required|string',
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
            'projekt_id' => $projekt->id,
            'kostenstelle' => $data['kostenstelle'],
            'ersteller_id' => auth()->id(),
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
        Notification::send(
            $this->notificationRecipients->forMaterialanforderung($anforderung, 'eingereicht', auth()->user()),
            new CreateMaterialanforderungNotification($anforderung)
        );


        return redirect()->route('materialanforderung.index');
    }

    public function update(Request $request)
    {
        $anforderung = Materialanforderung::with('artikeln')->findOrFail($request->id);

        // Berechtigung prüfen
        $this->authorize('materialanforderung.update');

        // Bis zur ersten Genehmigung darf die Anforderung vollständig bearbeitet werden.
        if (!in_array($anforderung->status, ['entwurf', 'eingereicht', 'zur_ueberarbeitung'], true)) {
            abort(403, 'Bereits genehmigte Materialanforderungen können nicht mehr bearbeitet werden.');
        }

        // Validierung
        $validator = $request->validate([
            'kostenstelle' => ['required', 'string', 'max:255'],
            'bemerkungen' => ['nullable', 'string'],
            'artikeln' => ['required', 'array', 'min:1'],
            'artikeln.*.id' => ['nullable', 'integer'],
            'artikeln.*.pos' => ['required', 'integer', 'min:1'],
            'artikeln.*.artikel' => ['required', 'string', 'max:255'],
            'artikeln.*.stueck' => ['required', 'integer', 'min:1'],
            'artikeln.*.einzelpreis' => ['required', 'numeric', 'min:0'],
            'artikeln.*.mwst' => ['required', 'numeric', 'between:0,100'],
            'artikeln.*.link' => ['nullable', 'string'],
            'artikeln.*.art_nr' => ['nullable', 'string', 'max:100'],
        ]);



        DB::transaction(function () use ($anforderung, $validator) {
            $artikelIds = collect($validator['artikeln'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id);

            // Im Formular entfernte Positionen auch in der Datenbank löschen.
            $anforderung->artikeln()->whereNotIn('id', $artikelIds)->delete();

            $gesamtsumme = 0;
            $endsumme = 0;

            foreach ($validator['artikeln'] as $a) {
                $gesamtpreis = $a['stueck'] * $a['einzelpreis'];
                $gesamtsumme += $gesamtpreis;
                $endsumme += $gesamtpreis * (1 + ($a['mwst'] / 100));

                $values = [
                    'pos' => $a['pos'],
                    'artikel' => $a['artikel'],
                    'stueck' => $a['stueck'],
                    'art_nr' => $a['art_nr'] ?? null,
                    'einzelpreis' => $a['einzelpreis'],
                    'mwst' => $a['mwst'],
                    'gesamtpreis' => $gesamtpreis,
                    'link' => $a['link'] ?? null,
                ];

                $artikel = isset($a['id'])
                    ? $anforderung->artikeln()->find($a['id'])
                    : null;

                $artikel ? $artikel->update($values) : $anforderung->artikeln()->create($values);
            }

            $anforderung->update([
                'kostenstelle' => $validator['kostenstelle'],
                'bemerkungen' => $validator['bemerkungen'] ?? null,
                'gesamtpreis' => $gesamtsumme,
                'endsumme' => $endsumme,
            ]);
        });

        return back()->with('success', 'Materialanforderung erfolgreich aktualisiert.');
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


        }elseif($query->ersteller_id != $user->id){
            return back()->with('error', 'Sie haben keine Berechtigung, diese Materialanforderung einzusehen.');
        }

        $notification = auth()->user()->notifications()->where('data->id', $id)->where('data->typ', 'Materialanforderung')->first();
        if ($notification) {
            $notification->markAsRead();
        }

        $kostenstellen = $query->projekt
            ? $query->projekt->kostenstellen()
                ->orderByPivot('gueltig_von', 'desc')
                ->orderByPivot('id', 'desc')
                ->limit(3)
                ->get(['kostenstelles.id', 'kostenstelles.kostenstelle'])
            : collect();

        return Inertia::render('Bestellungen/Materialanforderung/Show', [
            'anforderung' => $query,
            'kostenstellen' => $kostenstellen,
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
        Notification::send(
            $this->notificationRecipients->forMaterialanforderung($anforderung, $status, auth()->user()),
            new UpdateMaterialanforderungNotification($anforderung, $status)
        );

        return back()->with('success', 'Materialanforderung erfolgreich ' . $status . '.');
    }
}
