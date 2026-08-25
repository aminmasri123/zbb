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
use Spatie\Permission\PermissionRegistrar;

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
            'kontext' => ['required', 'string', 'in:teilnehmer,gruppe,partner'],
            'einsatzbereich' => ['required', 'string', 'in:partner,gruppe,teilnehmer'],
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
            'gruppen_export_modus' => ['nullable', 'string', 'in:kopf,eine_datei,einzelne_dateien'],
        ]);

        $this->validateContextForTarget($validated);

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
                'gruppen_export_modus' => $validated['gruppen_export_modus'] ?? $this->defaultGroupExportMode($validated['typ'], $validated['kontext']),
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

            $this->ensureDocumentExportPermission($dokument);
        });

        return redirect()->route('dokumente.index')->with('success', 'Export-Vorlage wurde angelegt.');
    }

    public function update(Request $request, Dokumente $dokument)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'typ' => ['required', 'string', 'in:word,excel,pdf'],
            'kontext' => ['required', 'string', 'in:teilnehmer,gruppe,partner'],
            'einsatzbereich' => ['required', 'string', 'in:partner,gruppe,teilnehmer'],
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
            'gruppen_export_modus' => ['nullable', 'string', 'in:kopf,eine_datei,einzelne_dateien'],
        ]);

        $this->validateContextForTarget($validated);

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
                'gruppen_export_modus' => $validated['gruppen_export_modus'] ?? $this->defaultGroupExportMode($validated['typ'], $validated['kontext']),
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

            $this->ensureDocumentExportPermission($dokument);
        });

        if ($storedPath && $this->isManagedUploadPath($oldPath)) {
            Storage::delete(ltrim(Str::after($oldPath, '/app/'), '/'));
        }

        return redirect()->route('dokumente.index')->with('success', 'Export-Vorlage wurde aktualisiert.');
    }

    public function download(Dokumente $dokument)
    {
        abort_unless(auth()->user()?->can('dokumente.download'), 403);

        $path = $this->resolvedStoragePath($dokument->dateipfad);
        if (!$path) {
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
                'gruppe' => 'Partner / Schule',
                'werte' => [
                    ['key' => 'partner', 'label' => 'Name des Hauptpartners / der Schule'],
                    ['key' => 'partner_name', 'label' => 'Name des Hauptpartners / der Schule'],
                    ['key' => 'partner_beschreibung', 'label' => 'Beschreibung des Hauptpartners'],
                    ['key' => 'partner_adresse', 'label' => 'Straße und Hausnummer des Hauptpartners'],
                    ['key' => 'partner_strasse', 'label' => 'Straße des Hauptpartners'],
                    ['key' => 'partner_hausnummer', 'label' => 'Hausnummer des Hauptpartners'],
                    ['key' => 'partner_plz', 'label' => 'PLZ des Hauptpartners'],
                    ['key' => 'partner_stadt', 'label' => 'Stadt des Hauptpartners'],
                    ['key' => 'partner_email', 'label' => 'E-Mail des Hauptpartners'],
                    ['key' => 'partner_telefon', 'label' => 'Telefon/Mobil des Hauptpartners'],
                    ['key' => 'partner_liste', 'label' => 'Alle Partner der Gruppe, kommagetrennt'],
                    ['key' => 'schulform', 'label' => 'Gemeinschaftsschule oder Förderschule'],
                    ['key' => 'schuljahr', 'label' => 'Schuljahr der Schülergruppe'],
                    ['key' => 'teil', 'label' => 'Teilabschnitt der Schülergruppe'],
                    ['key' => 'klassen', 'label' => 'Alle Klassen natürlich sortiert, getrennt mit +'],
                    ['key' => 'klassen_liste', 'label' => 'Alle Klassen natürlich sortiert, getrennt mit +'],
                    ['key' => 'zeitraum', 'label' => 'Erster bis letzter Termin'],
                    ['key' => 'zeitraum_von', 'label' => 'Erster Termin, bevorzugt Vorbereitung PA'],
                    ['key' => 'zeitraum_bis', 'label' => 'Letzter Termin, bevorzugt Feedback-/Auswertungsgespräch'],
                    ['key' => 'vorbereitung_pa_datum', 'label' => 'Erster Termin Vorbereitung PA'],
                    ['key' => 'pa_datum', 'label' => 'Termine der Potenzialanalyse'],
                    ['key' => 'pa_daten', 'label' => 'Termine der Potenzialanalyse'],
                    ['key' => 'feedbackgespraech_pa_datum', 'label' => 'Letzter Termin Feedbackgespräch PA'],
                    ['key' => 'rolltag_datum', 'label' => 'Termin oder Termine des Rolltags'],
                    ['key' => 'werkstatttage_daten', 'label' => 'Termine der Werkstatttage'],
                    ['key' => 'werkstatttage_gesamt_daten', 'label' => 'Alle Werkstatttage der gesamten Schule'],
                    ['key' => 'wt_daten', 'label' => 'Termine der Werkstatttage'],
                    ['key' => 'feedbackgespraech_wt_datum', 'label' => 'Letzter Termin Feedbackgespräch WT'],
                    ['key' => 'feedbackgespraech_datum', 'label' => 'Letzter Termin Feedbackgespräch'],
                    ['key' => 'auswertungsgespraech_datum', 'label' => 'Letzter Termin Auswertungs-/Feedbackgespräch'],
                    ['key' => 'pa_klassen_tabelle', 'label' => 'Excel: Musterblock für PA-Termine je Klasse dynamisch wiederholen'],
                    ['key' => 'pa_klasse', 'label' => 'Excel: Klasse innerhalb des dynamischen PA-Musterblocks'],
                ],
            ],
            [
                'gruppe' => 'Betreuung und Export',
                'werte' => [
                    ['key' => 'betreuer', 'label' => 'Betreuer/-in vollständig'],
                    ['key' => 'betreuer_name', 'label' => 'Betreuer/-in vollständig'],
                    ['key' => 'betreuer_anrede', 'label' => 'Frau/Herr für den Betreuer'],
                    ['key' => 'betreuer_anrede_dativ', 'label' => 'Frau/Herrn für Formulierungen mit „bei“'],
                    ['key' => 'betreuer_vorname', 'label' => 'Betreuer Vorname'],
                    ['key' => 'betreuer_nachname', 'label' => 'Betreuer Nachname'],
                    ['key' => 'termin_datum', 'label' => 'Datum des Starttermins/Erstgesprächs'],
                    ['key' => 'termin_uhrzeit', 'label' => 'Uhrzeit des Starttermins/Erstgesprächs'],
                    ['key' => 'termin', 'label' => 'Starttermin als Datum und Uhrzeit'],
                    ['key' => 'erstgespraech_datum', 'label' => 'Alias für das Datum des Erstgesprächs'],
                    ['key' => 'erstgespraech_uhrzeit', 'label' => 'Alias für die Uhrzeit des Erstgesprächs'],
                    ['key' => 'datum', 'label' => 'Heutiges Datum'],
                    ['key' => 'heute', 'label' => 'Heutiges Datum'],
                    ['key' => 'nr', 'label' => 'laufende Nummer'],
                    ['key' => 'nummer', 'label' => 'laufende Nummer'],
                ],
            ],
            [
                'gruppe' => 'Gruppenliste / Serienbrief',
                'werte' => [
                    ['key' => 'teilnehmer_tabelle', 'label' => 'Marker fuer eine Teilnehmerliste in Excel'],
                    ['key' => 'vorname', 'label' => 'Word-Tabellenzeile je Teilnehmer wiederholen'],
                    ['key' => 'nachname', 'label' => 'Word-Tabellenzeile je Teilnehmer wiederholen'],
                    ['key' => 'name', 'label' => 'Word-Tabellenzeile je Teilnehmer wiederholen'],
                    ['key' => 'vorname1', 'label' => 'Fester Platzhalter fuer Teilnehmer 1'],
                    ['key' => 'nachname1', 'label' => 'Fester Platzhalter fuer Teilnehmer 1'],
                    ['key' => 'vorname2', 'label' => 'Fester Platzhalter fuer Teilnehmer 2, danach 3, 4, ...'],
                    ['key' => 'nachname2', 'label' => 'Fester Platzhalter fuer Teilnehmer 2, danach 3, 4, ...'],
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

    private function ensureDocumentExportPermission(Dokumente $dokument): string
    {
        $permissionName = $dokument->export_permission ?: 'dokumente.export.' . $dokument->id;
        $categoryId = $this->documentExportCategoryId();

        $permissionId = DB::table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        $payload = [
            'berechtigungskategorie_id' => $categoryId,
            'beschreibung' => 'Erlaubt den Export der Dokumentvorlage "' . $dokument->name . '".',
            'updated_at' => now(),
        ];

        if ($permissionId) {
            DB::table('permissions')->where('id', $permissionId)->update($payload);
        } else {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $permissionName,
                'guard_name' => 'web',
                'created_at' => now(),
            ] + $payload);
        }

        if ($dokument->export_permission !== $permissionName) {
            $dokument->forceFill(['export_permission' => $permissionName])->save();
        }

        $administratorRoleId = DB::table('roles')->where('name', 'Administrator')->value('id');
        if ($administratorRoleId && $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionName;
    }

    private function documentExportCategoryId(): int
    {
        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Dokumentenexporte')
            ->value('id');

        if ($categoryId) {
            return (int) $categoryId;
        }

        return (int) DB::table('berechtigungskategories')->insertGetId([
            'name' => 'Dokumentenexporte',
            'beschreibung' => 'Einzelberechtigungen fuer den Export bestimmter Dokumentvorlagen.',
        ]);
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

    private function validateContextForTarget(array $validated): void
    {
        if (($validated['einsatzbereich'] ?? null) !== 'teilnehmer') {
            return;
        }

        if (($validated['kontext'] ?? null) !== 'teilnehmer') {
            throw ValidationException::withMessages([
                'kontext' => 'Für den Anzeigeort Teilnehmerseite muss der Datenbezug Teilnehmer gewählt sein.',
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

    private function defaultGroupExportMode(string $typ, string $kontext): string
    {
        if ($typ === 'word' && $kontext === 'gruppe') {
            return 'eine_datei';
        }

        return 'einzelne_dateien';
    }

    private function isManagedUploadPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, '/app/export-vorlagen/');
    }

    private function resolvedStoragePath(?string $path): ?string
    {
        if (!$path || str_contains($path, "\0")) {
            return null;
        }

        $storageRoot = realpath(storage_path());
        $resolvedPath = realpath(storage_path(ltrim($path, '/\\')));

        if (!$storageRoot || !$resolvedPath || !is_file($resolvedPath)) {
            return null;
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
        $normalisedPath = str_replace('\\', '/', $resolvedPath);

        if (PHP_OS_FAMILY === 'Windows') {
            $rootPrefix = strtolower($rootPrefix);
            $normalisedPath = strtolower($normalisedPath);
        }

        abort_unless(str_starts_with($normalisedPath, $rootPrefix), 404);

        return $resolvedPath;
    }
}
