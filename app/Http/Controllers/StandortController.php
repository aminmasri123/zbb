<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Standort;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StandortController extends Controller
{
    public function index()
    {
        $standorte = Standort::with('personen', 'personen.projekte')->orderBy('name')->get();

        return Inertia::render('Standort/Index', ['standorte' => $standorte]);
    }
    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'beschreibung' => 'nullable|string',
        ]);

        $standort = Standort::create([
            'name' => $request->name,
            'beschreibung' => $request->beschreibung,
        ]);

        return response()->json(['message' => 'Standort erfolgreich angelegt', 'standort' => $standort]);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        $standort = Standort::find($id);
        if (!$standort) {
            return response()->json(['message' => 'Standort nicht gefunden'], 404);
        }

        $standort->delete();
        return response()->json(['message' => 'Standort erfolgreich gelöscht'], 200);
    }
}
