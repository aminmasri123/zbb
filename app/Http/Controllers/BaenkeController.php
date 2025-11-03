<?php

namespace App\Http\Controllers;

use App\Models\Baenke;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BaenkeController extends Controller
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
       $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iban' => 'required|string|max:255',
            'blz' => 'nullable|string|max:20',
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        Baenke::create($validated);
        return back()->with('success', 'Bank erfolgreich hinzugefügt!');
    }

    /**
     * Display the specified resource.
     */
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

    public function destroy($id)
    {
        Baenke::findOrFail($id)->delete();
        return response()->json(['message' => 'Kontakt erfolgreich entfernt.'], 200);
    }
}
