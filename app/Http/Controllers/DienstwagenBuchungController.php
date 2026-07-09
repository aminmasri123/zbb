<?php

namespace App\Http\Controllers;

use App\Models\Dienstwagen;
use App\Models\DienstwagenBuchung;
use App\Models\Personen;
use App\Services\DienstwagenVerlaufService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DienstwagenBuchungController extends Controller
{
    public function index()
    {
        return Inertia::render('Dienstwagen/Buchungen/Index', [
            'bookings' => DienstwagenBuchung::with(['dienstwagen', 'person', 'user'])
                ->orderBy('start_at')
                ->get(),
            'vehicles' => Dienstwagen::with('standort')->orderBy('kennzeichen')->get(),
            'drivers' => Personen::orderBy('nachname')->orderBy('vorname')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->ensureNoCollision($data);

        $data['user_id'] = $request->user()?->id;
        $booking = DienstwagenBuchung::create($data)->load(['dienstwagen', 'person', 'user']);

        app(DienstwagenVerlaufService::class)->record(
            $booking->dienstwagen,
            'buchung.created',
            'Buchung angelegt',
            $this->bookingSummary($booking),
            [],
            $booking
        );

        return back()->with('success', 'Buchung wurde angelegt.');
    }

    public function update(Request $request, $id)
    {
        $booking = DienstwagenBuchung::with('dienstwagen')->findOrFail($id);
        $data = $this->validated($request, $booking->id);
        $this->ensureNoCollision($data, $booking->id);

        $original = $booking->getOriginal();
        $booking->fill($data);
        $dirty = $booking->getDirty();
        $booking->save();
        $booking->load(['dienstwagen', 'person', 'user']);

        if ($booking->end_km && $booking->dienstwagen && $booking->end_km > $booking->dienstwagen->kilometerstand) {
            $booking->dienstwagen->update(['kilometerstand' => $booking->end_km]);
        }

        app(DienstwagenVerlaufService::class)->record(
            $booking->dienstwagen,
            'buchung.updated',
            'Buchung aktualisiert',
            $this->bookingSummary($booking),
            $this->formatChanges($original, $dirty),
            $booking
        );

        return back()->with('success', 'Buchung wurde aktualisiert.');
    }

    public function destroy($id)
    {
        $booking = DienstwagenBuchung::with('dienstwagen')->findOrFail($id);
        $vehicle = $booking->dienstwagen;

        app(DienstwagenVerlaufService::class)->record(
            $vehicle,
            'buchung.deleted',
            'Buchung geloescht',
            $this->bookingSummary($booking),
            [],
            $booking
        );

        $booking->delete();

        return response()->json(['message' => 'Buchung wurde geloescht.']);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'dienstwagen_id' => ['required', 'integer', 'exists:dienstwagens,id'],
            'person_id' => ['nullable', 'integer', 'exists:personens,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'ziel' => ['nullable', 'string', 'max:255'],
            'zweck' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['geplant', 'bestaetigt', 'abgelehnt', 'storniert', 'abgeschlossen'])],
            'start_km' => ['nullable', 'integer', 'min:0'],
            'end_km' => ['nullable', 'integer', 'gte:start_km'],
            'notizen' => ['nullable', 'string'],
        ]);

        $data['start_at'] = Carbon::parse($data['start_at'])->format('Y-m-d H:i:s');
        $data['end_at'] = Carbon::parse($data['end_at'])->format('Y-m-d H:i:s');

        return $data;
    }

    private function ensureNoCollision(array $data, ?int $ignoreId = null): void
    {
        if (in_array($data['status'], ['abgelehnt', 'storniert'], true)) {
            return;
        }

        $exists = DienstwagenBuchung::query()
            ->where('dienstwagen_id', $data['dienstwagen_id'])
            ->whereNotIn('status', ['abgelehnt', 'storniert'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($data) {
                $query->where('start_at', '<', $data['end_at'])
                    ->where('end_at', '>', $data['start_at']);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_at' => 'Der Dienstwagen ist in diesem Zeitraum bereits gebucht.',
            ]);
        }
    }

    private function bookingSummary(DienstwagenBuchung $booking): string
    {
        return trim(($booking->dienstwagen?->kennzeichen ?? 'Dienstwagen') . ' | ' .
            $booking->start_at?->format('d.m.Y H:i') . ' - ' .
            $booking->end_at?->format('d.m.Y H:i') . ' | ' .
            $booking->zweck);
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
