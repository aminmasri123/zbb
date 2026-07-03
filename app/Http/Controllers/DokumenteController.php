<?php

namespace App\Http\Controllers;

use App\Models\DokumentKategorie;
use App\Models\Dokumente;
use App\Models\Projekt;
use App\Models\Bereich;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DokumenteController extends Controller
{
    public function index()
    {
        $this->authorizeManager();

        return Inertia::render('Dokumente/Index', [
            'dokumente' => Dokumente::query()
                ->with(['kategorien:id,name', 'projekte:id,name', 'bereiche:id,name'])
                ->orderBy('name')
                ->get(),
            'projekte' => Projekt::query()
                ->with('dokumentKategorien:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'aktiv']),
            'kategorien' => DokumentKategorie::query()
                ->orderBy('name')
                ->get(['id', 'name', 'beschreibung']),
            'bereiche' => Bereich::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'platzhalter' => self::platzhalterDefinitionen(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'typ' => ['required', 'string', 'in:word,excel,pdf'],
            'kontext' => ['required', 'string', 'in:teilnehmer,gruppe'],
            'einsatzbereich' => ['required', 'string', 'in:partner,gruppe'],
            'version' => ['nullable', 'string', 'max:50'],
            'beschreibung' => ['nullable', 'string'],
            'datei' => ['required', 'file', 'max:30720', 'mimes:docx,xlsx,pdf'],
            'ausgabeformate' => ['nullable', 'array'],
            'ausgabeformate.*' => ['string', 'in:docx,xlsx,pdf'],
            'projekt_ids' => ['nullable', 'array'],
            'projekt_ids.*' => ['integer', 'exists:projekts,id'],
            'kategorie_ids' => ['nullable', 'array'],
            'kategorie_ids.*' => ['integer', 'exists:dokument_kategories,id'],
            'bereich_ids' => ['nullable', 'array'],
            'bereich_ids.*' => ['integer', 'exists:bereiches,id'],
            'gruppen_export' => ['nullable', 'boolean'],
            'serienbrief' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('datei');
        $extension = strtolower($file->getClientOriginalExtension());
        $this->validateTypMatchesExtension($validated['typ'], $extension);

        $formats = $this->normaliseOutputFormats(
            $validated['typ'],
            $validated['ausgabeformate'] ?? null
        );

        $storedName = Str::uuid() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;
        $storedPath = $file->storeAs('export-vorlagen', $storedName);
        $gruppenExport = (bool) ($validated['gruppen_export'] ?? true);
        $serienbrief = (bool) ($validated['serienbrief'] ?? false);

        DB::transaction(function () use ($validated, $file, $storedPath, $formats, $gruppenExport, $serienbrief) {
            $dokument = Dokumente::create([
                'name' => $validated['name'],
                'typ' => $validated['typ'],
                'kontext' => $validated['kontext'],
                'einsatzbereich' => $validated['einsatzbereich'],
                'ausgabeformate' => $formats,
                'version' => $validated['version'] ?? null,
                'dateipfad' => '/app/' . str_replace('\\', '/', $storedPath),
                'dateipfadName' => $file->getClientOriginalName(),
                'beschreibung' => $validated['beschreibung'] ?? null,
                'aktiv' => true,
            ]);

            $projektSync = collect($validated['projekt_ids'] ?? [])
                ->unique()
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'gruppen_export' => $gruppenExport,
                    'serienbrief' => $serienbrief,
                    'sort_order' => 0,
                ]])
                ->all();

            $kategorieSync = collect($validated['kategorie_ids'] ?? [])
                ->unique()
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'gruppen_export' => $gruppenExport,
                    'serienbrief' => $serienbrief,
                ]])
                ->all();

            $dokument->projekte()->sync($projektSync);
            $dokument->kategorien()->sync($kategorieSync);
            $dokument->bereiche()->sync(
                $validated['einsatzbereich'] === 'gruppe'
                    ? collect($validated['bereich_ids'] ?? [])->unique()->values()->all()
                    : []
            );
        });

        return redirect()->route('dokumente.index')->with('success', 'Export-Vorlage wurde angelegt.');
    }

    public function update(Request $request, Dokumente $dokument)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'typ' => ['required', 'string', 'in:word,excel,pdf'],
            'kontext' => ['required', 'string', 'in:teilnehmer,gruppe'],
            'einsatzbereich' => ['required', 'string', 'in:partner,gruppe'],
            'version' => ['nullable', 'string', 'max:50'],
            'beschreibung' => ['nullable', 'string'],
            'datei' => ['nullable', 'file', 'max:30720', 'mimes:docx,xlsx,pdf'],
            'ausgabeformate' => ['nullable', 'array'],
            'ausgabeformate.*' => ['string', 'in:docx,xlsx,pdf'],
            'projekt_ids' => ['nullable', 'array'],
            'projekt_ids.*' => ['integer', 'exists:projekts,id'],
            'kategorie_ids' => ['nullable', 'array'],
            'kategorie_ids.*' => ['integer', 'exists:dokument_kategories,id'],
            'bereich_ids' => ['nullable', 'array'],
            'bereich_ids.*' => ['integer', 'exists:bereiches,id'],
            'gruppen_export' => ['nullable', 'boolean'],
            'serienbrief' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('datei');
        $storedPath = null;
        $originalName = null;

        if ($file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $this->validateTypMatchesExtension($validated['typ'], $extension);

            $storedName = Str::uuid() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;
            $storedPath = $file->storeAs('export-vorlagen', $storedName);
            $originalName = $file->getClientOriginalName();
        } else {
            $this->validateTypMatchesExtension($validated['typ'], strtolower(pathinfo($dokument->dateipfad ?? '', PATHINFO_EXTENSION)));
        }

        $formats = $this->normaliseOutputFormats(
            $validated['typ'],
            $validated['ausgabeformate'] ?? null
        );
        $gruppenExport = (bool) ($validated['gruppen_export'] ?? true);
        $serienbrief = (bool) ($validated['serienbrief'] ?? false);
        $oldPath = $dokument->dateipfad;

        DB::transaction(function () use ($dokument, $validated, $storedPath, $originalName, $formats, $gruppenExport, $serienbrief) {
            $payload = [
                'name' => $validated['name'],
                'typ' => $validated['typ'],
                'kontext' => $validated['kontext'],
                'einsatzbereich' => $validated['einsatzbereich'],
                'ausgabeformate' => $formats,
                'version' => $validated['version'] ?? null,
                'beschreibung' => $validated['beschreibung'] ?? null,
            ];

            if ($storedPath) {
                $payload['dateipfad'] = '/app/' . str_replace('\\', '/', $storedPath);
                $payload['dateipfadName'] = $originalName;
            }

            $dokument->update($payload);

            $projektSync = collect($validated['projekt_ids'] ?? [])
                ->unique()
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'gruppen_export' => $gruppenExport,
                    'serienbrief' => $serienbrief,
                    'sort_order' => 0,
                ]])
                ->all();

            $kategorieSync = collect($validated['kategorie_ids'] ?? [])
                ->unique()
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'gruppen_export' => $gruppenExport,
                    'serienbrief' => $serienbrief,
                ]])
                ->all();

            $dokument->projekte()->sync($projektSync);
            $dokument->kategorien()->sync($kategorieSync);
            $dokument->bereiche()->sync(
                $validated['einsatzbereich'] === 'gruppe'
                    ? collect($validated['bereich_ids'] ?? [])->unique()->values()->all()
                    : []
            );
        });

        if ($storedPath && $this->isManagedUploadPath($oldPath)) {
            Storage::delete(ltrim(Str::after($oldPath, '/app/'), '/'));
        }

        return redirect()->route('dokumente.index')->with('success', 'Export-Vorlage wurde aktualisiert.');
    }

    public function download(Dokumente $dokument)
    {
        $this->authorizeManager();

        $path = storage_path(ltrim($dokument->dateipfad ?? '', '/\\'));
        if (!$dokument->dateipfad || !file_exists($path)) {
            return back()->with('error', 'Die Vorlagendatei wurde nicht gefunden.');
        }

        return response()->download($path, $dokument->dateipfadName ?: basename($path));
    }

    public function storeKategorie(Request $request)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:dokument_kategories,name'],
            'beschreibung' => ['nullable', 'string'],
        ]);

        $kategorie = DokumentKategorie::create($validated);

        return response()->json([
            'message' => 'Kategorie wurde angelegt.',
            'kategorie' => $kategorie,
        ], 201);
    }

    public function updateProjektKategorien(Request $request, Projekt $projekt)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'kategorie_ids' => ['array'],
            'kategorie_ids.*' => ['integer', 'exists:dokument_kategories,id'],
        ]);

        $projekt->dokumentKategorien()->sync(collect($validated['kategorie_ids'] ?? [])->unique()->values()->all());

        return response()->json([
            'message' => 'Projekt-Kategorien wurden aktualisiert.',
            'projekt' => $projekt->fresh()->load('dokumentKategorien:id,name'),
        ]);
    }

    public static function platzhalterDefinitionen(): array
    {
        return [
            [
                'gruppe' => 'Teilnehmer',
                'werte' => [
                    ['key' => 'vorname', 'label' => 'Vorname'],
                    ['key' => 'nachname', 'label' => 'Nachname'],
                    ['key' => 'name', 'label' => 'Nachname, Vorname'],
                    ['key' => 'voller_name', 'label' => 'Vorname Nachname'],
                    ['key' => 'geburtsdatum', 'label' => 'Geburtsdatum'],
                    ['key' => 'geschlecht', 'label' => 'Geschlecht'],
                    ['key' => 'anrede', 'label' => 'Herr/Frau, soweit ableitbar'],
                    ['key' => 'kundennummer', 'label' => 'Kundennummer aus Sozialdaten'],
                ],
            ],
            [
                'gruppe' => 'Adresse und Kontakt',
                'werte' => [
                    ['key' => 'strasse', 'label' => 'Strasse'],
                    ['key' => 'hausnummer', 'label' => 'Hausnummer'],
                    ['key' => 'plz', 'label' => 'PLZ'],
                    ['key' => 'stadt', 'label' => 'Stadt'],
                    ['key' => 'ort', 'label' => 'Ort/Stadt'],
                    ['key' => 'adresse', 'label' => 'Strasse und Hausnummer'],
                    ['key' => 'email', 'label' => 'E-Mail'],
                    ['key' => 'telefon', 'label' => 'Telefon/Mobil'],
                ],
            ],
            [
                'gruppe' => 'Projekt und Gruppe',
                'werte' => [
                    ['key' => 'projekt', 'label' => 'Projektname'],
                    ['key' => 'projekt_name', 'label' => 'Projektname'],
                    ['key' => 'gruppe', 'label' => 'Gruppen-/Bereichsname'],
                    ['key' => 'gruppe_id', 'label' => 'Gruppen-ID'],
                    ['key' => 'bereich', 'label' => 'Bereich'],
                    ['key' => 'raum', 'label' => 'Raum oder externer Ort'],
                    ['key' => 'ort_typ', 'label' => 'raum oder extern'],
                    ['key' => 'startdatum', 'label' => 'Startdatum der Gruppe'],
                    ['key' => 'enddatum', 'label' => 'Enddatum der Gruppe'],
                    ['key' => 'von', 'label' => 'Startdatum'],
                    ['key' => 'bis', 'label' => 'Enddatum'],
                    ['key' => 'startzeit', 'label' => 'Startzeit'],
                    ['key' => 'endzeit', 'label' => 'Endzeit'],
                ],
            ],
            [
                'gruppe' => 'Betreuung und Export',
                'werte' => [
                    ['key' => 'betreuer', 'label' => 'Betreuer/-in vollständig'],
                    ['key' => 'betreuer_vorname', 'label' => 'Betreuer Vorname'],
                    ['key' => 'betreuer_nachname', 'label' => 'Betreuer Nachname'],
                    ['key' => 'datum', 'label' => 'Heutiges Datum'],
                    ['key' => 'heute', 'label' => 'Heutiges Datum'],
                    ['key' => 'nr', 'label' => 'laufende Nummer'],
                    ['key' => 'nummer', 'label' => 'laufende Nummer'],
                ],
            ],
        ];
    }

    private function authorizeManager(): void
    {
        $user = auth()->user();
        if (!$user?->can('projekt.update') && !$user?->can('projekt.store') && !$user?->can('projekt.index')) {
            abort(403);
        }
    }

    private function validateTypMatchesExtension(string $typ, string $extension): void
    {
        $expected = [
            'word' => ['docx'],
            'excel' => ['xlsx'],
            'pdf' => ['pdf'],
        ][$typ] ?? [];

        if (!in_array($extension, $expected, true)) {
            throw ValidationException::withMessages([
                'datei' => 'Dateityp und Vorlage passen nicht zusammen.',
            ]);
        }
    }

    private function normaliseOutputFormats(string $typ, ?array $requested): array
    {
        $allowed = match ($typ) {
            'word' => ['docx', 'pdf'],
            'excel' => ['xlsx', 'pdf'],
            default => ['pdf'],
        };

        $formats = collect($requested ?: $allowed)
            ->intersect($allowed)
            ->values()
            ->all();

        return $formats ?: $allowed;
    }

    private function isManagedUploadPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, '/app/export-vorlagen/');
    }
}
