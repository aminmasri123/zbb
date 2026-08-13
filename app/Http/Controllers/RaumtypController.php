<?php

namespace App\Http\Controllers;

use App\Models\Raeume;
use App\Models\Raumtyp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RaumtypController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeManagement();
        $validated = $request->validate($this->rules());
        $raumtyp = Raumtyp::create($this->normalized($validated));

        return response()->json([
            'message' => 'Raumtyp erfolgreich angelegt.',
            'raumtyp' => $raumtyp->loadCount('raeume'),
        ], 201);
    }

    public function update(Request $request, Raumtyp $raumtyp)
    {
        $this->authorizeManagement();
        $validated = $request->validate($this->rules($raumtyp));
        $data = $this->normalized($validated);

        DB::transaction(function () use ($raumtyp, $data) {
            $alterName = $raumtyp->name;

            if ($alterName !== $data['name']) {
                Raeume::query()->where('typ', $alterName)->update(['typ' => $data['name']]);
            }

            $raumtyp->update($data);
        });

        return response()->json([
            'message' => 'Raumtyp erfolgreich aktualisiert.',
            'raumtyp' => $raumtyp->fresh()->loadCount('raeume'),
        ]);
    }

    public function destroy(Raumtyp $raumtyp)
    {
        $this->authorizeManagement();

        if ($raumtyp->raeume()->exists()) {
            throw ValidationException::withMessages([
                'raumtyp' => 'Dieser Raumtyp wird noch verwendet. Deaktivieren Sie ihn stattdessen.',
            ]);
        }

        $raumtyp->delete();

        return response()->json(['message' => 'Raumtyp erfolgreich gelöscht.']);
    }

    private function rules(?Raumtyp $raumtyp = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('raumtypen', 'name')->ignore($raumtyp?->id),
            ],
            'beschreibung' => 'nullable|string|max:500',
            'aktiv' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ];
    }

    private function normalized(array $validated): array
    {
        return [
            'name' => trim($validated['name']),
            'beschreibung' => filled($validated['beschreibung'] ?? null)
                ? trim($validated['beschreibung'])
                : null,
            'aktiv' => $validated['aktiv'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('raeumlichkeiten.update'), 403);
    }
}
