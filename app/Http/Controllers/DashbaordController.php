<?php

namespace App\Http\Controllers;

use App\Models\AppCalendarEvent;
use App\Models\AppContact;
use App\Models\AppFile;
use App\Models\AppPopup;
use App\Models\AppTask;
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
            'apps' => [
                'events' => AppCalendarEvent::count(),
                'contacts' => AppContact::count(),
                'files' => AppFile::where('type', 'file')->count(),
                'tasks' => AppTask::where('status', '!=', 'done')->count(),
                'popups' => AppPopup::where('active', true)->count(),
                'participants' => $teilnehmer,
            ],
        ]);

    }
}
