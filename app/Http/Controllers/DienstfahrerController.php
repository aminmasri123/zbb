<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DienstfahrerController extends Controller
{
    //Privat und Dienstwagen Fahrer Controller
    public function index()
    {
        return Inertia::render('Fleet/Drivers/Index', [
            'drivers'   => Driver::with('location')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Fleet/Drivers/Create', [
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string',
            'department'  => 'nullable|string',
            'phone'       => 'nullable|string',
            'email'       => 'nullable|email',
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        Driver::create($data);

        return redirect()->route('fleet.drivers.index')
            ->with('success', 'Fahrer erfolgreich angelegt.');
    }

    public function edit(Driver $driver)
    {
        return Inertia::render('Fleet/Drivers/Edit', [
            'driver'    => $driver,
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'name'        => 'required|string',
            'department'  => 'nullable|string',
            'phone'       => 'nullable|string',
            'email'       => 'nullable|email',
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        $driver->update($data);

        return redirect()->route('fleet.drivers.index')
            ->with('success', 'Fahrer wurde aktualisiert.');
    }

    public function destroy(Driver $driver)
    {
        $driver->delete();

        return back()->with('success', 'Fahrer gelöscht.');
    }
}
