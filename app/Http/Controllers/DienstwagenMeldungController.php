<?php

namespace App\Http\Controllers;

use App\Models\Dienstwagen;
use App\Models\DienstwagenMeldung;
use App\Services\DienstwagenVerlaufService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DienstwagenMeldungController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()?->loadMissing('person');

        return Inertia::render('Dienstwagen/Meldungen/Index', [
            'records' => DienstwagenMeldung::with(['dienstwagen', 'gemeldetVonPerson', 'verantwortlich'])
                ->latest()
                ->get(),
            'vehicles' => Dienstwagen::orderBy('kennzeichen')->get(),
            'currentPerson' => $user?->person,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['gemeldet_von_user_id'] = $request->user()?->id;
        $data['gemeldet_von_personen_id'] = $request->user()?->person_id;
        $data['verantwortlich_person_id'] = $request->user()?->person_id;

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('dienstwagen/meldungen', 'public');
        }

        unset($data['attachment']);

        $meldung = DienstwagenMeldung::create($data)->load(['dienstwagen', 'gemeldetVonPerson', 'verantwortlich']);
        $this->applyVehicleStatus($meldung);

        app(DienstwagenVerlaufService::class)->record(
            $meldung->dienstwagen,
            'meldung.created',
            'Meldung erfasst',
            $meldung->titel,
            ['status' => ['old' => null, 'new' => $meldung->status]],
            $meldung
        );

        return back()->with('success', 'Meldung wurde erfasst.');
    }

    public function update(Request $request, $id)
    {
        $meldung = DienstwagenMeldung::with('dienstwagen')->findOrFail($id);
        $data = $this->validated($request, true);

        if ($request->hasFile('attachment')) {
            if ($meldung->attachment_path) {
                Storage::disk('public')->delete($meldung->attachment_path);
            }

            $data['attachment_path'] = $request->file('attachment')->store('dienstwagen/meldungen', 'public');
        }

        if ($request->boolean('remove_attachment') && $meldung->attachment_path) {
            Storage::disk('public')->delete($meldung->attachment_path);
            $data['attachment_path'] = null;
        }

        unset($data['attachment'], $data['remove_attachment']);

        if (! $meldung->verantwortlich_person_id && $request->user()?->person_id) {
            $data['verantwortlich_person_id'] = $request->user()->person_id;
        }

        $original = $meldung->getOriginal();
        $meldung->fill($data);
        $dirty = $meldung->getDirty();
        $meldung->erledigt_am = $meldung->status === 'erledigt' ? now() : null;
        $dirty = array_merge($dirty, $meldung->getDirty());
        $meldung->save();
        $meldung->load(['dienstwagen', 'gemeldetVonPerson', 'verantwortlich']);
        $this->applyVehicleStatus($meldung);

        app(DienstwagenVerlaufService::class)->record(
            $meldung->dienstwagen,
            'meldung.updated',
            'Meldung aktualisiert',
            $meldung->titel,
            $this->formatChanges($original, $dirty),
            $meldung
        );

        return back()->with('success', 'Meldung wurde aktualisiert.');
    }

    public function destroy($id)
    {
        $meldung = DienstwagenMeldung::with('dienstwagen')->findOrFail($id);
        $vehicle = $meldung->dienstwagen;

        app(DienstwagenVerlaufService::class)->record(
            $vehicle,
            'meldung.deleted',
            'Meldung geloescht',
            $meldung->titel,
            [],
            $meldung
        );

        if ($meldung->attachment_path) {
            Storage::disk('public')->delete($meldung->attachment_path);
        }

        $meldung->delete();

        return response()->json(['message' => 'Meldung wurde geloescht.']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'dienstwagen_id' => ['required', 'integer', 'exists:dienstwagens,id'],
            'titel' => ['required', 'string', 'max:255'],
            'kategorie' => ['required', Rule::in(['reparatur', 'reifen', 'oel', 'unfall', 'reinigung', 'dokument', 'sonstiges'])],
            'prioritaet' => ['required', Rule::in(['niedrig', 'normal', 'hoch', 'kritisch'])],
            'status' => ['required', Rule::in(['offen', 'in_bearbeitung', 'erledigt'])],
            'beschreibung' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);
    }

    private function applyVehicleStatus(DienstwagenMeldung $meldung): void
    {
        if ($meldung->status === 'erledigt') {
            return;
        }

        if (in_array($meldung->prioritaet, ['hoch', 'kritisch'], true) || in_array($meldung->kategorie, ['reparatur', 'reifen', 'unfall'], true)) {
            $meldung->dienstwagen?->update(['status' => 'Werkstatt']);
        }
    }

    private function formatChanges(array $original, array $dirty): array
    {
        $changes = [];

        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
