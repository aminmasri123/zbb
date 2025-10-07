<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $partners = Partner::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->with('partnerschaftstypens')
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

            return Inertia::render('Partner/Index', [
            'partners' => $partners,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

   public function store(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string',
        'typ' => 'nullable|string',
        'beschreibung' => 'nullable|string',
        'ansprechpartner' => 'array',
        'ansprechpartner.*.vorname' => 'required|string',
        'ansprechpartner.*.nachname' => 'required|string',
        'ansprechpartner.*.geschlecht' => 'nullable|string',
        'ansprechpartner.*.typ' => 'nullable|string',
    ]);

    // Partner anlegen
    $partner = Partner::create($data);

    // Ansprechpartner mit Kurzform speichern
    foreach ($data['ansprechpartner'] as $person) {
        // Geschlecht-Umwandlung
        $geschlecht = strtolower($person['geschlecht'] ?? '');

        switch ($geschlecht) {
            case 'männlich':
                $person['geschlecht'] = 'm';
                break;
            case 'weiblich':
                $person['geschlecht'] = 'w';
                break;
            case 'divers':
                $person['geschlecht'] = 'd';
                break;
            default:
                $person['geschlecht'] = null; // falls nichts angegeben
        }

        $partner->ansprechpartner()->create($person);
    }

    return response()->json([
        'success' => true,
        'partner' => $partner->load('ansprechpartner')
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
        //
    }
}
