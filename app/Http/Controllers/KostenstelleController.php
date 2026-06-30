<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Kostenstelle;
use Illuminate\Http\Request;

class KostenstelleController extends Controller
{
    public function index()
    {
        $kostenstelle = Kostenstelle::all();
        dd($kostenstelle);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kostenstelle' => 'required|string|max:10',
        ]);

        $kostenstelle = Kostenstelle::firstOrCreate([
            'kostenstelle' => $validated['kostenstelle'],
        ]);

        return response()->json([
            'message' => 'Kostenstelle erfolgreich angelegt.',
            'kostenstelle' => $kostenstelle,
        ], $kostenstelle->wasRecentlyCreated ? 201 : 200);
    }
}
