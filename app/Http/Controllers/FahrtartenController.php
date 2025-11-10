<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Fahrtarten;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class FahrtartenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fahrtarten = Fahrtarten::OrderBy('name')->get();

        return Inertia::render('Fahrten/Fahrtarten/Index', ['fahrtarten' => $fahrtarten]);

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

        $request->validate([
            'name' => 'required|string|max:255|unique:fahrtartens,name,',
            'beschreibung' => 'nullable|string',
        ]);

        $fahrtart = Fahrtarten::create([
            'name' => $request->name,
            'beschreibung' => $request->beschreibung,
        ]);

         return response()->json(['fahrtart' => $fahrtart]);
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
            $transportart = Fahrtarten::findOrFail($id);
            $transportart->delete();

            return response()->json(['message' => 'Transportart erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Transportart nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
