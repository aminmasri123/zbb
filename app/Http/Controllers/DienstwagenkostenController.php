<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use App\Models\Dienstwagenkostenaufzeichnungen;

class DienstwagenkostenController extends Controller
{
   public function index()
    {
        return Inertia::render('Dienstwagen/Kosten/Index', [
            'costs'    => Dienstwagenkostenaufzeichnungen::with('dienstwagen')->latest()->get(),
            'vehicles' => Dienstwagen::orderBy('kennzeichen')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dienstwagen_id' => 'required|exists:dienstwagens,id',
            'art'            => 'required|string|max:255',
            'datum'          => 'required|date',
            'betrag'         => 'required|numeric|min:0',
            'beschreibung'   => 'nullable|string',
        ]);

         if (!empty($data['datum'])) {
            try {
                $data['datum'] = Carbon::parse($data['datum'])->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Ungültiges Datumsformat übermittelt: " . $data['datum']);
            }
        }

        Dienstwagenkostenaufzeichnungen::create($data);

        return back()->with('success', 'Kosten erfasst.');
    }
    public function destroy(CostRecord $costRecord)
    {
        $costRecord->delete();

        return back()->with('success', 'Kosten gelöscht.');
    }
}
