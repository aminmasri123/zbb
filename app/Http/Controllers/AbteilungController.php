<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Abteilung;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AbteilungController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $search = Request::input('search');

        $abteilungen = Abteilung::query()
        ->when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })->with('user:id,first_name,last_name')
        ->with('abteilungsassistente.user')
        ->orderBy('name') // Optional: Sortierung nach dem Namen
        ->paginate(10)    // Paginierung anwenden, bevor die Abfrage ausgeführt wird
        ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

    return Inertia::render('Abteilung/Index', [
        'abteilungen' => $abteilungen,
    ]);


/*

        $abteilungen = Abteilung::with('user')
            ->when(Request::input('search'), function ($query, $search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
            ->when(Request::input('trashed') === 'with', function ($query) {
                return $query->withTrashed();
            })
            ->when(Request::input('trashed') === 'only', function ($query) {
                return $query->onlyTrashed();
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Abteilung/Index', [
            'filters' => Request::all('search', 'trashed'),
            'abteilungen' => $abteilungen->through(fn ($abteilung) => [
                'id' => $abteilung->id,
                'name' => $abteilung->name,
                'abteilungsleiter' => $abteilung->user ? $abteilung->user->only('name') : null,
            ]),
        ]);

*/
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
