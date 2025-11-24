<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Raeume;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashbaordController extends Controller
{
    public function dashboard()
    {
        $projekte = Projekt::aktiv()->count();
        $dienstwagen = Dienstwagen::aktiv()->count();
        $raeume = Raeume::count();
        $teilnehmer = Personen::aktiv()->teilnehmer()->count();

        return Inertia::render('Dashboard', [
            'projekte' => $projekte,
            'dienstwagen' => $dienstwagen,
            'raeume' => $raeume,
            'teilnehmer' => $teilnehmer,
        ]);

    }
}
