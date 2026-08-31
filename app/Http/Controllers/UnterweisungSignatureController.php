<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UnterweisungSignatureController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'unterschrift' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:min_width=120,min_height=40,max_width=2400,max_height=1200'],
        ]);

        $file = $validated['unterschrift'];
        $request->user()->forceFill([
            'unterweisung_unterschrift' => [
                'mime' => $file->getMimeType(),
                'data' => base64_encode($file->get()),
            ],
            'unterweisung_unterschrift_updated_at' => now(),
        ])->save();

        return back()->with('success', 'Ihre Unterschrift für Dokumentplatzhalter wurde verschlüsselt gespeichert.');
    }

    public function destroy(Request $request)
    {
        $request->user()->forceFill([
            'unterweisung_unterschrift' => null,
            'unterweisung_unterschrift_updated_at' => null,
        ])->save();

        return back()->with('success', 'Ihre Unterschrift für Dokumentplatzhalter wurde gelöscht.');
    }
}
