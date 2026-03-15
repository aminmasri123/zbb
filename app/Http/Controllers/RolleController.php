<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolleController extends Controller
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

        try {
            $adminRole = Role::create([
                'name' => $request->name,
                'guard_name' => 'web',
                'color' => '#000000',
                ]);

            return response()->json([
                'success' => true,
                'role' => $adminRole
            ]);
        } catch (Exception $e) {
            // Wenn ein Fehler auftritt, mache einen Rollback und gib den Fehler aus
            DB::rollback();
            return redirect()->back()->with('error', 'Ein Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }
    
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
            $rolle = Role::findOrFail($id);
            $rolle->delete();
            return response()->json(['message' => 'Rolle erfolgreich gelöscht'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Die Löschung der Rolle ist fehlgeschlagen.'], 500);
        }
    }
}
