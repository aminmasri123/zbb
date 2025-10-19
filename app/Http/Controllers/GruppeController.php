<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Bereich;
use App\Models\Projekt;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BereichHasTeilnehmer;

class GruppeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     public function index(Request $request)
    {
        // Optional: Filter (z. B. aktiv/inaktiv)
        $status = $request->get('status');

        $query = Bereich::with('projekte')
            ->withCount('bereichHasTeilnehmer'); // zählt Einträge in TeilnehmerBereich

        if ($status === 'aktiv') {
            $query->where('aktiv', true);
        } elseif ($status === 'inaktiv') {
            $query->where('aktiv', false);
        }

        $bereiche = $query->get();

        return Inertia::render('Projekt/Index', [
            'projekte' => $bereiche->toArray(),
            ],
        );
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
        //
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
