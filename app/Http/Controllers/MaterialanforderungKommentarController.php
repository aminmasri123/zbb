<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\MaterialanforderungKommentar;
use App\Models\MaterialanforderungKommentarAnhang;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaterialanforderungKommentarController extends Controller
{
    public const REASONS = [
        'allgemein' => 'Allgemeiner Hinweis',
        'nicht_gefunden' => 'Produkt nicht gefunden',
        'preis_geaendert' => 'Preis hat sich geändert',
        'qualitaet' => 'Qualität entspricht nicht den Anforderungen',
        'menge_nicht_verfuegbar' => 'Gewünschte Menge nicht verfügbar',
        'alternative' => 'Alternative vorgeschlagen',
        'entscheidung' => 'Entscheidung / Bestätigung',
        'sonstiges' => 'Sonstiger Grund',
    ];

    public function store(Request $request, Materialanforderung $materialanforderung)
    {
        $this->ensureVisible($request, $materialanforderung);

        $validated = $request->validate([
            'artikel_id' => [
                'nullable', 'integer',
                Rule::exists('materialanforderung_artikels', 'id')
                    ->where(fn ($query) => $query->where('anforderung_id', $materialanforderung->id)),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('materialanforderung_kommentare', 'id')
                    ->where(fn ($query) => $query->where('anforderung_id', $materialanforderung->id)),
            ],
            'grund' => ['required', Rule::in(array_keys(self::REASONS))],
            'body' => ['required', 'string', 'max:5000'],
            'vorgeschlagener_preis' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'vorgeschlagener_link' => ['nullable', 'url', 'max:2000'],
            'antwort_erforderlich' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', config('internal_communication.allowed_attachment_mimes')),
                'max:'.(int) config('internal_communication.max_attachment_kilobytes', 10240),
            ],
        ]);

        $kommentar = DB::transaction(function () use ($request, $materialanforderung, $validated) {
            $kommentar = $materialanforderung->kommentare()->create([
                'artikel_id' => $validated['artikel_id'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'user_id' => $request->user()->id,
                'grund' => $validated['grund'],
                'body' => trim($validated['body']),
                'vorgeschlagener_preis' => $validated['vorgeschlagener_preis'] ?? null,
                'vorgeschlagener_link' => $validated['vorgeschlagener_link'] ?? null,
                'antwort_erforderlich' => (bool) ($validated['antwort_erforderlich'] ?? false),
            ]);

            foreach ($validated['attachments'] ?? [] as $file) {
                $storedName = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                $path = $file->storeAs("materialanforderungen/{$materialanforderung->id}/kommentare/{$kommentar->id}", $storedName, 'local');
                $kommentar->attachments()->create([
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $kommentar;
        });

        $recipients = $this->recipients($materialanforderung, $kommentar, $request->user());
        Notification::send($recipients, new ConfiguredEventNotification([
            'event_key' => 'materialanforderung.kommentar',
            'message' => $kommentar->antwort_erforderlich
                ? "Neue Rückfrage zur Materialanforderung #{$materialanforderung->id}."
                : "Neuer Kommentar zur Materialanforderung #{$materialanforderung->id}.",
            'link' => route('materialanforderung.show', $materialanforderung).'#kommunikation',
            'id' => $materialanforderung->id,
            'typ' => 'Materialanforderung',
        ]));

        return back()->with('success', $kommentar->antwort_erforderlich
            ? 'Rückfrage wurde gespeichert und die Beteiligten wurden informiert.'
            : 'Kommentar wurde gespeichert.');
    }

    public function resolve(Request $request, MaterialanforderungKommentar $kommentar)
    {
        $kommentar->load('anforderung');
        $this->ensureVisible($request, $kommentar->anforderung);
        abort_unless(
            (int) $kommentar->user_id === (int) $request->user()->id
            || (int) $kommentar->anforderung->ersteller_id === (int) $request->user()->id
            || $request->user()->can('materialanforderung.bestellwesen.update'),
            403
        );
        abort_unless($kommentar->antwort_erforderlich, 422, 'Dieser Kommentar ist keine offene Rückfrage.');

        $kommentar->update([
            'geklaert_am' => now(),
            'geklaert_von_id' => $request->user()->id,
        ]);

        $recipients = User::query()
            ->whereIn('id', collect([$kommentar->user_id, $kommentar->anforderung->ersteller_id])->filter()->unique())
            ->whereKeyNot($request->user()->id)
            ->get();
        Notification::send($recipients, new ConfiguredEventNotification([
            'event_key' => 'materialanforderung.rueckfrage.geklaert',
            'message' => "Eine Rückfrage zur Materialanforderung #{$kommentar->anforderung_id} wurde als geklärt markiert.",
            'link' => route('materialanforderung.show', $kommentar->anforderung).'#kommunikation',
            'id' => $kommentar->anforderung_id,
            'typ' => 'Materialanforderung',
        ]));

        return back()->with('success', 'Rückfrage wurde als geklärt markiert.');
    }

    public function downloadAttachment(Request $request, MaterialanforderungKommentarAnhang $attachment)
    {
        $attachment->load('kommentar.anforderung');
        $this->ensureVisible($request, $attachment->kommentar->anforderung);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    private function ensureVisible(Request $request, Materialanforderung $anforderung): void
    {
        $user = $request->user();
        $visible = (int) $anforderung->ersteller_id === (int) $user->id
            || $anforderung->genehmigungen()->where('genehmiger_id', $user->id)->exists()
            || ($user->can('materialanforderung.sachlische_freigabe.index')
                && $anforderung->status === 'eingereicht'
                && $user->projekte()->whereKey($anforderung->projekt_id)->exists())
            || (($user->can('materialanforderung.kaufmännische_freigabe.index')
                    || $user->can('materialanforderung.kaufmännische_freigabe.update')
                    || $user->can('materialanforderung.bestellwesen.update'))
                && ! in_array($anforderung->status, ['entwurf', 'eingereicht'], true));

        abort_unless($visible, 403);
    }

    private function recipients(Materialanforderung $anforderung, MaterialanforderungKommentar $kommentar, User $actor)
    {
        $ids = collect([$anforderung->ersteller_id, $kommentar->parent?->user_id])
            ->merge($anforderung->genehmigungen()->pluck('genehmiger_id'))
            ->merge($anforderung->kommentare()->pluck('user_id'))
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $actor->id);

        if (in_array($anforderung->status, ['kaufmaennisch_genehmigt', 'bestellt', 'teilweise_geliefert'], true)) {
            try {
                $ids = $ids->merge(User::permission('materialanforderung.bestellwesen.update')->pluck('id'));
            } catch (\Throwable) {
                // No recipient is preferable to exposing the request to an unintended user.
            }
        }

        return User::query()->whereIn('id', $ids->unique())->whereKeyNot($actor->id)->get();
    }
}
