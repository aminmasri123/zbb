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
}
