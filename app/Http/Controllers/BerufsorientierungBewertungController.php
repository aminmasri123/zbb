<?php

namespace App\Http\Controllers;

use App\Models\BerufsorientierungBewertung;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\Projekt;
use App\Services\BerufsorientierungAuswertungService;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BerufsorientierungBewertungController extends Controller
{
    public function __construct(
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly BerufsorientierungAuswertungService $service,
    ) {}

    public function update(Request $request, Gruppe $gruppe, Personen $personen)
    {
        $this->authorizeGroup($request, $gruppe);
        abort_unless($gruppe->teilnehmer()->whereKey($personen->id)->exists(), 404);

        $config = $this->service->config($gruppe->projekt);
        abort_unless($config['enabled'] && $gruppe->bereich_id, 422, 'Für diese Gruppe ist keine Bereichsauswertung verfügbar.');
        $keys = collect($config['criteria'])->pluck('key')->all();
        $validated = $request->validate([
            'bewertungen' => ['required', 'array'],
            'bewertungen.*.kriterium' => ['required', 'string', Rule::in($keys)],
            'bewertungen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'bewertungen.*.bemerkung' => ['nullable', 'string', 'max:2000'],
        ]);
        $criteria = collect($config['criteria'])->keyBy('key');

        DB::transaction(function () use ($validated, $gruppe, $personen, $request, $criteria) {
            foreach ($validated['bewertungen'] as $entry) {
                $rating = $entry['bewertung'] ?? null;
                $note = trim((string) ($entry['bemerkung'] ?? '')) ?: null;
                $lookup = [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'kriterium' => $entry['kriterium'],
                ];
                if ($rating === null && $note === null) {
                    BerufsorientierungBewertung::where($lookup)->delete();

                    continue;
                }
                BerufsorientierungBewertung::updateOrCreate($lookup, [
                    'user_id' => $request->user()->id,
                    'kriterium_label' => $criteria[$entry['kriterium']]['label'],
                    'bewertung' => $rating,
                    'bemerkung' => $note,
                    'legacy_bewertungsbogen_id' => null,
                ]);
            }
        });

        return response()->json(['message' => 'Die Bereichsauswertung wurde gespeichert.']);
    }

    public function updateProjectConfig(Request $request, Projekt $projekt)
    {
        abort_unless($request->user()?->can('projekt.update'), 403);
        $active = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($active && (int) $active->id === (int) $projekt->id, 403);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'criteria' => ['required', 'array', 'min:1', 'max:50'],
            'criteria.*.key' => ['required', 'string', 'max:191', 'regex:/^[\pL\pN_-]+$/u', 'distinct'],
            'criteria.*.label' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string', 'max:2000'],
            'criteria.*.required' => ['required', 'boolean'],
        ]);
        $validated['criteria'] = collect($validated['criteria'])->values()
            ->map(fn ($item, $index) => array_merge($item, ['sort_order' => $index]))->all();
        $projekt->update(['berufsorientierung_auswertung_config' => $validated]);

        return response()->json($this->service->config($projekt->fresh()));
    }

    private function authorizeGroup(Request $request, Gruppe $gruppe): void
    {
        $active = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($active && (int) $active->id === (int) $gruppe->projekt_id, 403);
        abort_unless(
            $request->user()->can('gruppe.view.all')
            || $request->user()->can('projekt.mitarbeiter.view.all')
            || (int) $gruppe->personen_id === (int) $request->user()->person_id,
            403
        );
    }
}
