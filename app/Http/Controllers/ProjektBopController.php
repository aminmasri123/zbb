<?php

namespace App\Http\Controllers;

use App\Models\Bereich;
use App\Models\Bereichsauswahl;
use App\Models\BereichsauswahlSetting;
use App\Models\BibbAttendanceListDraft;
use App\Models\BopRun;
use App\Models\Dokumente;
use App\Models\EinteilungBereiche;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\PaAttendanceListDraft;
use App\Models\PaAttendanceSignatureVersion;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\Bop\AttendanceFooterService;
use App\Services\Bop\PackagePlanning;
use App\Services\Bop\PaAttendanceSignatureHistoryService;
use App\Services\Bop\PaPreparationAttendanceWordExportService;
use App\Services\Bop\PublicAreaSelectionAccess;
use App\Services\Bop\RolandEvaluationPdfService;
use App\Services\Bop\RolltagAttendanceTemplateResolver;
use App\Services\MyDatum;
use App\Services\Projects\ActiveProjectContext;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class ProjektBopController extends Controller
{
    private const BIBB_DRAFT_RETENTION_DAYS = 90;
    private const BIBB_SIGNATURE_ENCRYPTION_PREFIX = 'enc:v1:';
    private const PA_DRAFT_RETENTION_DAYS = 90;
    private const PA_SIGNATURE_ENCRYPTION_PREFIX = 'enc:v1:';

    private const ACCESS_CODE_PARTS = [
        'BA', 'BE', 'BI', 'BO',
        'DA', 'DE', 'DI', 'DO',
        'FA', 'FE', 'FI', 'FO',
        'KA', 'KE', 'KI', 'KO',
        'LA', 'LE', 'LI', 'LO',
        'MA', 'ME', 'MI', 'MO',
        'NA', 'NE', 'NI', 'NO',
        'PA', 'PE', 'PI', 'PO',
        'RA', 'RE', 'RI', 'RO',
        'SA', 'SE', 'SI', 'SO',
        'TA', 'TE', 'TI', 'TO',
    ];

    public function __construct(
        private readonly PublicAreaSelectionAccess $publicAreaSelection,
        private readonly AttendanceFooterService $attendanceFooter,
        private readonly PaAttendanceSignatureHistoryService $paSignatureHistory,
        private readonly PaPreparationAttendanceWordExportService $paPreparationWordExport,
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly RolandEvaluationPdfService $rolandEvaluationPdf,
    ) {
    }

    private function ensurePartnerInActiveProject(int $partnerId): int
    {
        $user = auth()->user();
        $project = $user ? $this->activeProjectContext->currentAvailableFor($user) : null;
        abort_unless($project, 409, 'Bitte waehlen Sie zuerst ein aktives Projekt aus.');

        abort_unless(
            DB::table('projekt_has_partners')
                ->where('projekt_id', $project->id)
                ->where('partner_id', $partnerId)
                ->exists(),
            404
        );

        return (int) $project->id;
    }

    private function normalizeAuswahlAnzahl(int $value): int
    {
        return min(4, max(2, $value));
    }

    private function defaultAuswahlAnzahl(?Projekt $projekt): int
    {
        $bereicheCount = $projekt ? app(\App\Services\Bop\SelectableAreas::class)->eligible($projekt)->count() : 4;

        return $this->normalizeAuswahlAnzahl($bereicheCount > 0 ? $bereicheCount : 4);
    }

    private function publicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (BereichsauswahlSetting::where('public_token', $token)->exists());

        return $token;
    }

    private function settingFor(int $projektId, int $partnerId, string $schuljahr, string $teil, ?Projekt $projekt = null): BereichsauswahlSetting
    {
        $setting = BereichsauswahlSetting::query()
            ->forContext($projektId, $partnerId, $schuljahr, $teil)
            ->preferConfigured()
            ->first() ?? BereichsauswahlSetting::firstOrCreate(
            [
                'projekt_id' => $projektId,
                'partner_id' => $partnerId,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
            ],
            [
                'auswahl_anzahl' => $this->defaultAuswahlAnzahl($projekt),
                'public_token' => $this->publicToken(),
                'zugang_aktiv' => true,
                'user_create' => auth()->id(),
            ]
        );

        if (!$setting->public_token) {
            $setting->update(['public_token' => $this->publicToken()]);
        }

        return $setting;
    }

    private function accessCode(): string
    {
        do {
            $parts = self::ACCESS_CODE_PARTS;
            $code = $parts[random_int(0, count($parts) - 1)]
                . '-' . $parts[random_int(0, count($parts) - 1)]
                . '-' . random_int(20, 98)
                . '-' . $parts[random_int(0, count($parts) - 1)];
        } while (Bereichsauswahl::where('access_code', $code)->exists());

        return $code;
    }

    private function normalizeAccessCodeInput(string $value): string
    {
        $normalized = Str::upper(preg_replace('/\s+/', '', trim($value)));
        $plain = str_replace('-', '', $normalized);

        if (preg_match('/^[A-Z]{4}[0-9]{2}[A-Z]{2}$/', $plain)) {
            return substr($plain, 0, 2)
                . '-' . substr($plain, 2, 2)
                . '-' . substr($plain, 4, 2)
                . '-' . substr($plain, 6, 2);
        }

        return $normalized;
    }

    private function ensureAccessCodes(Collection $teilnehmer, ?int $userId): void
    {
        foreach ($teilnehmer as $schueler) {
            $wahl = $schueler->bereichsauswahl;

            if (!$wahl) {
                $wahl = Bereichsauswahl::create([
                    'teilnehmer_id' => $schueler->id,
                    'access_code' => $this->accessCode(),
                    'user_create' => $userId,
                ]);

                $schueler->setRelation('bereichsauswahl', $wahl);
                continue;
            }

            if (!$wahl->access_code) {
                $wahl->update(['access_code' => $this->accessCode()]);
            }
        }
    }

    private function qrSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(128),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($url);
    }

    private function allowedBereichIds(Projekt $projekt, BereichsauswahlSetting $setting): Collection
    {
        return app(\App\Services\Bop\SelectableAreas::class)->forSetting($projekt, $setting)
            ->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    private function validatedChoices(Request $request, BereichsauswahlSetting $setting, Collection $allowedBereichIds): array
    {
        $data = $request->validate([
            'choices' => ['required', 'array', 'size:' . $setting->auswahl_anzahl],
            'choices.*' => ['required', 'integer'],
        ], [
            'choices.required' => 'Bitte wählen Sie die vorgegebenen Bereiche aus.',
            'choices.array' => 'Bitte wählen Sie die vorgegebenen Bereiche aus.',
            'choices.size' => 'Bitte wählen Sie genau ' . $setting->auswahl_anzahl . ' Bereiche aus.',
            'choices.*.required' => 'Bitte wählen Sie in jedem Wahlfeld einen Bereich aus.',
            'choices.*.integer' => 'Die Bereichsauswahl ist ungültig.',
        ]);

        $choices = collect($data['choices'])->map(fn ($id) => (int) $id)->values();

        if ($choices->unique()->count() !== $choices->count()) {
            throw ValidationException::withMessages([
                'choices' => 'Jeder Bereich darf nur einmal gewählt werden.',
            ]);
        }

        if ($choices->diff($allowedBereichIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'choices' => 'Mindestens ein Bereich ist für dieses Projekt nicht erlaubt.',
            ]);
        }

        return $choices->all();
    }

    private function persistChoices(Bereichsauswahl $wahl, array $choices, ?int $userId = null, bool $submitted = false): void
    {
        $payload = [
            'bereich_id1' => $choices[0] ?? null,
            'bereich_id2' => $choices[1] ?? null,
            'bereich_id3' => $choices[2] ?? null,
            'bereich_id4' => $choices[3] ?? null,
        ];

        if ($userId) {
            $payload['user_update'] = $userId;
        }

        if ($submitted) {
            $payload['submitted_at'] = now();
        }

        $wahl->update($payload);
    }

    private function teilnehmerForCode(BereichsauswahlSetting $setting, string $code): ?PersonenIstSchueler
    {
        return PersonenIstSchueler::with(['person', 'bereichsauswahl'])
            ->where('schule_id', $setting->partner_id)
            ->forSchuljahr($setting->schuljahr)
            ->where('teil', $setting->teil)
            ->whereHas('bereichsauswahl', fn ($query) => $query->where('access_code', $code))
            ->first();
    }

    private function formatSelfTeilnehmer(PersonenIstSchueler $teilnehmer, BereichsauswahlSetting $setting): array
    {
        $wahl = $teilnehmer->bereichsauswahl;

        return [
            'id' => $teilnehmer->id,
            'vorname' => $teilnehmer->person?->vorname,
            'nachname' => $teilnehmer->person?->nachname,
            'klasse' => $teilnehmer->klasse,
            'auswahl_anzahl' => $setting->auswahl_anzahl,
            'choices' => array_slice([
                $wahl?->bereich_id1,
                $wahl?->bereich_id2,
                $wahl?->bereich_id3,
                $wahl?->bereich_id4,
            ], 0, $setting->auswahl_anzahl),
            'submitted_at' => $wahl?->submitted_at?->toIso8601String(),
        ];
    }

    public function anwesenheitslistePOBOPreviewBIBB(Request $request)
    {
        $validated = $request->validate([
            'schuleIdInputBibb' => ['required', 'integer', 'exists:partners,id'],
            'schuljahrInputBibb' => ['required', 'string'],
            'teilInputBibb' => ['required', 'string'],
            'rolltagDate' => ['nullable', 'date'],
            'manualDays' => ['nullable', 'array'],
            'manualDays.*.date' => ['required_with:manualDays', 'date'],
            'manualDays.*.type' => ['nullable', 'string'],
            'manualDays.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensurePartnerInActiveProject((int) $validated['schuleIdInputBibb']);

        return response()->json($this->bibbPreviewPayload(
            (int) $validated['schuleIdInputBibb'],
            (string) $validated['schuljahrInputBibb'],
            (string) $validated['teilInputBibb'],
            $validated['rolltagDate'] ?? null,
            $validated['manualDays'] ?? []
        ));
    }

    public function anwesenheitslistePOBODraftShowBIBB(Request $request)
    {
        $this->purgeExpiredBibbDrafts();
        $scope = $this->bibbDraftScope($request);
        // Poll only metadata before loading/decrypting the large signature JSON.
        // Legacy drafts still require merging, so never skip their full response.
        if ($request->has('known_revision') && $request->filled('known_updated_at')) {
            $metadata = BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])
                ->first(['id', 'revision', 'updated_at']);
            if ($metadata
                && (string) $metadata->revision === (string) $request->input('known_revision')
                && $metadata->updated_at?->toIso8601String() === $request->input('known_updated_at')
                && !BibbAttendanceListDraft::whereIn('draft_hash', $scope['legacy_draft_hashes'] ?? [])->exists()) {
                return response()->json(['exists' => true, 'unchanged' => true]);
            }
        }
        $draft = BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        $legacyDraft = $this->legacyBibbDraft($scope);
        $sourceDraft = $draft ?: $legacyDraft;
        $payload = $sourceDraft
            ? $this->decryptBibbDraftPayloadSignatures($sourceDraft->payload ?? [])
            : null;

        if ($draft && $legacyDraft && is_array($payload)) {
            $payload = $this->restoreLegacySignatures(
                $payload,
                $this->decryptBibbDraftPayloadSignatures($legacyDraft->payload ?? [])
            );
        }

        return response()->json([
            'exists' => (bool) $sourceDraft,
            'payload' => $payload,
            'revision' => $sourceDraft?->revision ?? 0,
            'updated_at' => $sourceDraft?->updated_at?->toIso8601String(),
            'expires_at' => $sourceDraft?->expires_at?->toIso8601String(),
            'final_pdf_path' => $sourceDraft?->final_pdf_path,
            'restored_from_schuljahr' => $legacyDraft?->schuljahr,
        ]);
    }

    public function anwesenheitslistePOBODraftStoreBIBB(Request $request)
    {
        $this->purgeExpiredBibbDrafts();
        $scope = $this->bibbDraftScope($request);
        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'payload.form' => ['nullable', 'array'],
            'payload.days' => ['nullable', 'array'],
            'payload.selectedDayId' => ['nullable', 'string'],
            'payload.signatures' => ['nullable', 'array'],
            'payload.signatures.*' => ['nullable', 'string'],
        ]);

        $incomingPayload = $this->sanitizeBibbDraftPayload($validated['payload']);
        $userId = auth()->id();

        [$draft, $payload] = DB::transaction(function () use ($scope, $incomingPayload, $userId) {
            $now = now();

            BibbAttendanceListDraft::query()->insertOrIgnore([
                'draft_hash' => $scope['draft_hash'],
                'projekt_id' => $scope['projekt_id'],
                'partner_id' => $scope['partner_id'],
                'schuljahr' => $scope['schuljahr'],
                'teil' => $scope['teil'],
                'payload' => json_encode([]),
                'revision' => 0,
                'user_create' => $userId,
                'user_update' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $draft = BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])
                ->lockForUpdate()
                ->first();

            $storedPayload = $this->decryptBibbDraftPayloadSignatures($draft?->payload ?? []);
            $existingPayload = $storedPayload;
            $legacyDraft = $this->legacyBibbDraft($scope);
            if ($legacyDraft) {
                $existingPayload = $this->restoreLegacySignatures(
                    $existingPayload,
                    $this->decryptBibbDraftPayloadSignatures($legacyDraft->payload ?? [])
                );
            }
            $payload = $this->mergeBibbDraftPayload($existingPayload, $incomingPayload);
            $restoredLegacySignatures = $storedPayload !== $existingPayload;

            if (! $restoredLegacySignatures && ! $this->draftPayloadHasChanges($existingPayload, $payload)) {
                return [$draft, $existingPayload];
            }

            $payload['saved_at'] = now()->toIso8601String();

            $draft->payload = $this->encryptBibbDraftPayloadSignatures($payload);
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = $userId;
            $draft->save();

            if ($restoredLegacySignatures) {
                BibbAttendanceListDraft::whereIn('draft_hash', $scope['legacy_draft_hashes'])->delete();
            }

            return [$draft, $payload];
        });

        return response()->json([
            'success' => true,
            'payload' => $payload,
            'revision' => $draft->revision,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'expires_at' => $draft->expires_at?->toIso8601String(),
            'final_pdf_path' => $draft->final_pdf_path,
        ]);
    }

    public function anwesenheitslistePOBODraftDestroyBIBB(Request $request)
    {
        $scope = $this->bibbDraftScope($request);

        BibbAttendanceListDraft::whereIn('draft_hash', [
            $scope['draft_hash'],
            ...$scope['legacy_draft_hashes'],
        ])->delete();

        return response()->json(['success' => true]);
    }

    public function anwesenheitslistePOBOArchiveFolderBIBB(Request $request)
    {
        $scope = $this->bibbDraftScope($request);
        $partner = Partner::findOrFail($scope['partner_id']);
        $folder = $this->bibbBaseFolder($partner, $scope['schuljahr'], $scope['teil']);
        $subfolders = $this->ensureBibbArchiveSubfolders($folder);

        return response()->json([
            'success' => true,
            'folder' => $this->relativeStoragePath($folder),
            'subfolders' => array_map(fn ($path) => $this->relativeStoragePath($path), $subfolders),
        ]);
    }

    public function anwesenheitslistePOBOSignedPdfStoreBIBB(Request $request)
    {
        $this->purgeExpiredBibbDrafts();
        $scope = $this->bibbDraftScope($request);

        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
            'filename' => ['nullable', 'string', 'max:180'],
        ]);

        $partner = Partner::findOrFail($scope['partner_id']);
        $folder = $this->bibbBaseFolder($partner, $scope['schuljahr'], $scope['teil'])
            . DIRECTORY_SEPARATOR
            . 'Anwesenheit';

        File::ensureDirectoryExists($folder);

        $filename = $this->bibbSignedPdfFilename(
            $validated['filename'] ?? null,
            $partner,
            $scope['schuljahr'],
            $scope['teil']
        );
        $path = $folder . DIRECTORY_SEPARATOR . $filename;

        if (File::exists($path)) {
            File::delete($path);
        }

        $request->file('pdf')->move($folder, $filename);

        $relativePath = $this->relativeStoragePath($path);
        $expiresAt = now()->addDays(self::BIBB_DRAFT_RETENTION_DAYS);

        $draft = BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        if ($draft) {
            $payload = is_array($draft->payload) ? $draft->payload : [];
            $payload['final_pdf'] = [
                'path' => $relativePath,
                'saved_at' => now()->toIso8601String(),
                'draft_expires_at' => $expiresAt->toIso8601String(),
            ];

            $draft->payload = $this->encryptBibbDraftPayloadSignatures($payload);
            $draft->final_pdf_path = $relativePath;
            $draft->finalized_at = now();
            $draft->expires_at = $expiresAt;
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = auth()->id();
            $draft->save();
        }

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'path' => $relativePath,
            'folder' => $this->relativeStoragePath($folder),
            'revision' => $draft?->revision ?? 0,
            'updated_at' => $draft?->updated_at?->toIso8601String(),
            'expires_at' => $draft?->expires_at?->toIso8601String() ?? $expiresAt->toIso8601String(),
        ]);
    }

    private function purgeExpiredBibbDrafts(): int
    {
        return BibbAttendanceListDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function bibbBaseFolder(Partner $partner, string $schuljahr, string $teil): string
    {
        $folder = storage_path('app/bop/'
            . $this->bibbSafeName($partner->name)
            . '/'
            . $this->bibbSafeName($schuljahr)
            . '/Teil_'
            . $this->bibbSafeName($teil));

        File::ensureDirectoryExists($folder);

        return $folder;
    }

    private function ensureBibbArchiveSubfolders(string $folder): array
    {
        $subfolders = [
            'Anwesenheit',
            'Teilnehmerliste',
            'Zertifikate_POBO',
            'Auswertung_POBO',
            'Auswertung_PA',
        ];

        return collect($subfolders)
            ->map(function (string $subfolder) use ($folder) {
                $path = $folder . DIRECTORY_SEPARATOR . $subfolder;
                File::ensureDirectoryExists($path);

                return $path;
            })
            ->all();
    }

    private function bibbSignedPdfFilename(?string $filename, Partner $partner, string $schuljahr, string $teil): string
    {
        $baseName = $filename
            ? Str::beforeLast($filename, '.pdf')
            : 'Anwesenheitsliste_BIBB_' . $partner->name . '_' . $schuljahr . '_Teil_' . $teil;

        return $this->bibbSafeName($baseName) . '.pdf';
    }

    private function bibbSafeName(string $value): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));

        return $safeName ?: 'Datei';
    }

    private function relativeStoragePath(string $path): string
    {
        $storageRoot = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, storage_path('app')), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        if (Str::startsWith($normalizedPath, $storageRoot)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', Str::after($normalizedPath, $storageRoot));
        }

        return str_replace('\\', '/', $path);
    }

    private function encryptBibbDraftPayloadSignatures(array $payload): array
    {
        if (!is_array($payload['signatures'] ?? null)) {
            return $payload;
        }

        $payload['signatures'] = collect($payload['signatures'])
            ->mapWithKeys(function ($value, $key) {
                if (!is_string($key) || !is_string($value) || $value === '') {
                    return [$key => $value];
                }

                return [$key => $this->encryptBibbSignature($value)];
            })
            ->all();

        return $payload;
    }

    private function decryptBibbDraftPayloadSignatures(array $payload): array
    {
        if (!is_array($payload['signatures'] ?? null)) {
            return $payload;
        }

        $signatures = [];
        foreach ($payload['signatures'] as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $decrypted = $this->decryptBibbSignature($value);
            if ($decrypted) {
                $signatures[$key] = $decrypted;
            }
        }

        $payload['signatures'] = $signatures;

        return $payload;
    }

    private function encryptBibbSignature(string $value): string
    {
        if ($this->isEncryptedBibbSignature($value)) {
            return $value;
        }

        return self::BIBB_SIGNATURE_ENCRYPTION_PREFIX . Crypt::encryptString($value);
    }

    private function decryptBibbSignature(string $value): ?string
    {
        if (!$this->isEncryptedBibbSignature($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(Str::after($value, self::BIBB_SIGNATURE_ENCRYPTION_PREFIX));
        } catch (DecryptException) {
            return null;
        }
    }

    private function isEncryptedBibbSignature(string $value): bool
    {
        return Str::startsWith($value, self::BIBB_SIGNATURE_ENCRYPTION_PREFIX);
    }

    private function bibbDraftScope(Request $request): array
    {
        $validated = $request->validate([
            'schuleIdInputBibb' => ['required', 'integer', 'exists:partners,id'],
            'schuljahrInputBibb' => ['required', 'string'],
            'teilInputBibb' => ['required', 'string'],
        ]);

        $partnerId = (int) $validated['schuleIdInputBibb'];
        $projektId = $this->ensurePartnerInActiveProject($partnerId);
        $schuljahr = (string) $validated['schuljahrInputBibb'];
        $teil = (string) $validated['teilInputBibb'];
        $draftHash = $this->bibbDraftHash($projektId, $partnerId, $schuljahr, $teil);
        $legacyDraftHashes = collect($this->schoolYearAliases($schuljahr))
            ->reject(fn (string $alias) => $alias === trim($schuljahr))
            ->map(fn (string $alias) => $this->bibbDraftHash($projektId, $partnerId, $alias, $teil))
            ->values()
            ->all();

        return [
            'draft_hash' => $draftHash,
            'legacy_draft_hashes' => $legacyDraftHashes,
            'projekt_id' => $projektId,
            'partner_id' => $partnerId,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
        ];
    }

    private function sanitizeBibbDraftPayload(array $payload): array
    {
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $allowedFormKeys = [
            'exportFormat',
            'rolltagDate',
            'startDate',
            'endDate',
            'includeSaturday',
            'includeSunday',
            'includeHolidays',
            'feedbackDate',
        ];

        $signatures = [];
        foreach (($payload['signatures'] ?? []) as $key => $value) {
            if (!is_string($key) || (!is_null($value) && !is_string($value))) {
                continue;
            }

            if ($value === null || $value === '') {
                $signatures[$key] = '';
                continue;
            }

            if (!Str::startsWith($value, 'data:image/png;base64,')) {
                continue;
            }

            $signatures[$key] = $value;
        }

        return [
            'version' => 1,
            'form' => array_intersect_key($form, array_flip($allowedFormKeys)),
            'days' => is_array($payload['days'] ?? null) ? array_values($payload['days']) : [],
            'selectedDayId' => is_string($payload['selectedDayId'] ?? null) ? $payload['selectedDayId'] : null,
            'signatures' => $signatures,
        ];
    }

    private function mergeBibbDraftPayload(array $existingPayload, array $incomingPayload): array
    {
        $payload = $existingPayload;

        foreach (['version', 'form', 'days', 'selectedDayId'] as $key) {
            if (array_key_exists($key, $incomingPayload)) {
                $payload[$key] = $incomingPayload[$key];
            }
        }

        $signatures = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
        foreach (($incomingPayload['signatures'] ?? []) as $key => $value) {
            if ($value === '' || $value === null) {
                unset($signatures[$key]);
                continue;
            }

            $signatures[$key] = $value;
        }

        $payload['signatures'] = $signatures;

        return $payload;
    }

    /**
     * Liefert alle historisch verwendeten Schreibweisen desselben Schuljahres.
     * So bleibt ein unter "2026/2027" gespeicherter Entwurf auch bei der neuen
     * Eingabe "2026" auffindbar.
     */
    private function schoolYearAliases(string $schuljahr): array
    {
        $value = trim($schuljahr);
        preg_match('/\d{4}/', $value, $matches);

        if (empty($matches[0])) {
            return [$value];
        }

        $startYear = (int) $matches[0];
        $endYear = $startYear + 1;

        return array_values(array_unique([
            $value,
            (string) $startYear,
            $startYear . '/' . $endYear,
            $startYear . '-' . $endYear,
            $startYear . '/' . substr((string) $endYear, -2),
            $startYear . '-' . substr((string) $endYear, -2),
        ]));
    }

    /**
     * Übernimmt nur fehlende alte Unterschriften. Aktuelle Werte gewinnen und
     * Termine/Formulardaten des neuen Entwurfs werden nicht überschrieben.
     */
    private function restoreLegacySignatures(array $currentPayload, array $legacyPayload): array
    {
        $currentSignatures = is_array($currentPayload['signatures'] ?? null)
            ? $currentPayload['signatures']
            : [];
        $legacySignatures = is_array($legacyPayload['signatures'] ?? null)
            ? $legacyPayload['signatures']
            : [];

        $currentPayload['signatures'] = array_replace($legacySignatures, $currentSignatures);

        return $currentPayload;
    }

    private function draftPayloadHasChanges(array $existingPayload, array $nextPayload): bool
    {
        unset($existingPayload['saved_at'], $nextPayload['saved_at']);

        return $existingPayload != $nextPayload;
    }

    private function bibbPreviewPayload(int $schulId, string $schuljahr, string $teil, ?string $rolltagDate, array $manualDays): array
    {
        $schule = Partner::findOrFail($schulId);
        $teilnehmer = $this->bibbTeilnehmer($schulId, $schuljahr, $teil);

        if ($teilnehmer->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die gewählte Schule weist zurzeit noch keine Schüler auf.',
            ]);
        }

        $alleTeilnehmer = $teilnehmer->map(fn ($item) => $this->bibbTeilnehmerPayload($item))->values();
        $projekt = auth()->user()?->current_team_id
            ? Projekt::with('bereiche')->find(auth()->user()->current_team_id)
            : null;
        $bereiche = $projekt?->bereiche
            ->pluck('code')
            ->unique()
            ->implode('/ ');
        $klassen = $teilnehmer->pluck('klasse')->unique()->implode(', ');
        $schulform = PersonenIstSchueler::query()->schulform($teilnehmer);
        $days = collect();

        if ($rolltagDate) {
            $date = Carbon::parse($rolltagDate)->toDateString();
            $days->push([
                'id' => 'rolltag-' . $date,
                'date' => $date,
                'date_label' => Carbon::parse($date)->format('d.m.Y'),
                'type' => 'rolltag',
                'type_label' => 'Rolltag',
                'source' => 'rolltag',
                'note' => null,
                'groups' => [[
                    'id' => 'rolltag-all',
                    'label' => 'Alle Rolltag-Gruppen',
                    'bereich' => null,
                    'runde' => null,
                    'participants' => $alleTeilnehmer,
                    'participants_count' => $alleTeilnehmer->count(),
                ]],
                'participants_count' => $alleTeilnehmer->count(),
            ]);
        }

        $days = $days->merge($this->bibbGeneratedGroupDays($schulId, $schuljahr, $teil, $teilnehmer));

        foreach ($manualDays as $manualDay) {
            if (empty($manualDay['date'])) {
                continue;
            }

            $date = Carbon::parse($manualDay['date'])->toDateString();
            if ($days->contains(fn ($day) => $day['date'] === $date)) {
                continue;
            }

            $type = $manualDay['type'] ?? 'manual';
            $days->push([
                'id' => 'manual-' . $date,
                'date' => $date,
                'date_label' => Carbon::parse($date)->format('d.m.Y'),
                'type' => $type,
                'type_label' => $type === 'rolltag' ? 'Rolltag' : 'Manueller Tag',
                'source' => 'manual',
                'note' => $manualDay['note'] ?? null,
                'groups' => [[
                    'id' => 'manual-all-' . $date,
                    'label' => 'Alle Teilnehmer',
                    'bereich' => null,
                    'runde' => null,
                    'participants' => $alleTeilnehmer,
                    'participants_count' => $alleTeilnehmer->count(),
                ]],
                'participants_count' => $alleTeilnehmer->count(),
            ]);
        }

        $days = $days
            ->sortBy(fn ($day) => $day['date'] . '|' . ($day['type'] === 'rolltag' ? '0' : '1'))
            ->values();

        return [
            'context' => [
                'schule' => [
                    'id' => $schule->id,
                    'name' => $schule->name,
                ],
                'schulform' => $schulform,
                'klasse' => $klassen,
                'bereiche' => $bereiche,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
                'teilnehmer_count' => $alleTeilnehmer->count(),
            ],
            'participants' => $alleTeilnehmer,
            'days' => $days,
        ];
    }

    private function bibbTeilnehmer(int $schulId, string $schuljahr, string $teil): Collection
    {
        return PersonenIstSchueler::query()
            ->filterSchueler($schulId, $schuljahr, $teil)
            ->with('person')
            ->get()
            ->sort(function ($a, $b) {
                $klasseCompare = strnatcasecmp((string) $a->klasse, (string) $b->klasse);
                if ($klasseCompare !== 0) {
                    return $klasseCompare;
                }

                $nachnameCompare = strnatcasecmp((string) ($a->person?->nachname ?? ''), (string) ($b->person?->nachname ?? ''));
                if ($nachnameCompare !== 0) {
                    return $nachnameCompare;
                }

                return strnatcasecmp((string) ($a->person?->vorname ?? ''), (string) ($b->person?->vorname ?? ''));
            })
            ->values();
    }

    private function bibbTeilnehmerPayload(PersonenIstSchueler $schueler): array
    {
        $vorname = (string) ($schueler->person?->vorname ?? '');
        $nachname = (string) ($schueler->person?->nachname ?? '');

        return [
            'id' => $schueler->id,
            'person_id' => $schueler->person_id,
            'vorname' => $vorname,
            'nachname' => $nachname,
            'name' => trim($nachname . ', ' . $vorname, ' ,') ?: 'Teilnehmer #' . $schueler->id,
            'klasse' => $schueler->klasse,
            'geschlecht' => $schueler->person?->geschlecht,
        ];
    }

    private function bibbGeneratedGroupDays(int $schulId, string $schuljahr, string $teil, Collection $teilnehmer): Collection
    {
        $projektId = auth()->user()?->current_team_id;
        if (!$projektId) {
            return collect();
        }

        $gruppen = Gruppe::query()
            ->with('bereich')
            ->where('projekt_id', $projektId)
            ->where('bemerkung', 'like', $this->bibbGeneratedGroupLike($schulId, $schuljahr, $teil))
            ->get();

        if ($gruppen->isEmpty()) {
            return collect();
        }

        $schuelerNachPerson = $teilnehmer
            ->filter(fn ($item) => $item->person_id)
            ->keyBy('person_id');

        $entries = GruppeHasPersonen::query()
            ->with(['tag', 'gruppe.bereich'])
            ->whereIn('gruppe_id', $gruppen->pluck('id'))
            ->whereIn('personen_id', $schuelerNachPerson->keys())
            ->get()
            ->filter(fn ($entry) => $entry->tag?->datum && $entry->gruppe);

        return $entries
            ->groupBy(fn ($entry) => $entry->tag->datum)
            ->map(function ($dateEntries, $date) use ($schuelerNachPerson) {
                $groupIndex = 0;
                $groups = $dateEntries
                    ->groupBy('gruppe_id')
                    ->sortKeys()
                    ->map(function ($groupEntries) use (&$groupIndex, $schuelerNachPerson) {
                        $gruppe = $groupEntries->first()->gruppe;
                        $runde = $this->bibbRundeFromBemerkung((string) $gruppe->bemerkung);
                        $letter = chr(65 + ($groupIndex % 26));
                        $groupIndex++;

                        $participants = $groupEntries
                            ->unique('personen_id')
                            ->map(fn ($entry) => $schuelerNachPerson->get($entry->personen_id))
                            ->filter()
                            ->sort(function ($a, $b) {
                                $klasseCompare = strnatcasecmp((string) $a->klasse, (string) $b->klasse);
                                if ($klasseCompare !== 0) {
                                    return $klasseCompare;
                                }

                                return strnatcasecmp(
                                    (string) ($a->person?->nachname ?? ''),
                                    (string) ($b->person?->nachname ?? '')
                                );
                            })
                            ->map(fn ($item) => $this->bibbTeilnehmerPayload($item))
                            ->values();

                        return [
                            'id' => $gruppe->id,
                            'label' => 'Gruppe ' . $letter,
                            'bereich' => $gruppe->bereich?->name,
                            'runde' => $runde,
                            'participants' => $participants,
                            'participants_count' => $participants->count(),
                        ];
                    })
                    ->values();

                return [
                    'id' => 'gruppen-' . $date,
                    'date' => Carbon::parse($date)->toDateString(),
                    'date_label' => Carbon::parse($date)->format('d.m.Y'),
                    'type' => 'group_day',
                    'type_label' => 'Gruppentag',
                    'source' => 'generated_groups',
                    'note' => null,
                    'groups' => $groups,
                    'participants_count' => $groups->sum('participants_count'),
                ];
            })
            ->sortKeys()
            ->values();
    }

    private function bibbGeneratedGroupLike(int $schulId, string $schuljahr, string $teil): string
    {
        return "BOP Einteilung Schule {$schulId} Schuljahr {$schuljahr} Teil {$teil} Runde %";
    }

    private function bibbRundeFromBemerkung(string $bemerkung): ?int
    {
        if (preg_match('/Runde\s+(\d+)/', $bemerkung, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function attendanceDateAllowed($date, array $options = []): bool
    {
        return filled($date) && app(\App\Services\SaarlandWorkdayService::class)->isAttendanceDay($date, $options);
    }

    private function bibbDateListFromRequest(Request $request): array
    {
        $options = $request->only(['includeSaturday', 'includeSunday', 'includeHolidays']);
        $dates = collect($request->input('days', []))
            ->filter(fn ($day) => ($day['selected'] ?? true) && !empty($day['date']))
            ->pluck('date')
            ->filter(fn ($date) => $this->attendanceDateAllowed($date, $options))
            ->sort()
            ->map(fn ($date) => Carbon::parse($date)->format('d.m.Y'))
            ->unique()
            ->values();

        if (!$request->has('days')) {
            $dates = collect(range(1, 10))
                ->map(fn ($index) => $request->input('termin' . $index))
                ->filter(fn ($date) => $this->attendanceDateAllowed($date, $options))
                ->map(fn ($date) => Carbon::parse($date)->format('d.m.Y'))
                ->values();
        }

        $feedbackDate = $request->filled('feedbackDate')
            ? Carbon::parse($request->input('feedbackDate'))->format('d.m.Y')
            : ($request->filled('termin11') ? Carbon::parse($request->input('termin11'))->format('d.m.Y') : '');

        return [
            $dates->take(10)->values()->all(),
            $this->attendanceDateAllowed($feedbackDate, $options) ? $feedbackDate : '',
        ];
    }

    public function anwesenheitslistePOBOExportWordBIBB(Request $request, ?int $exportProjectId = null)
    {
            $request->validate([
                'exportFormat' => ['required', 'in:A3,A4'],
                'schuleIdInputBibb' => ['required', 'integer', 'exists:partners,id'],
                'schuljahrInputBibb' => ['required', 'string'],
                'teilInputBibb' => ['required', 'string'],
                'days' => ['nullable', 'array'],
                'days.*.date' => ['required_with:days', 'date'],
                'days.*.selected' => ['nullable', 'boolean'],
                'feedbackDate' => ['nullable', 'date'],
            ]);

            [$programmTage, $tag11] = $this->bibbDateListFromRequest($request);

            if (empty($programmTage)) {
                return response()->json(['message' => 'Bitte mindestens einen Anwesenheitstag auswählen.'], 422);
            }

            $schuljahr = $request->schuljahrInputBibb;
            $schulId = $request->schuleIdInputBibb;
            $teil = $request->teilInputBibb;
            $format = $request->exportFormat;
            $projectId = $this->ensurePartnerInActiveProject((int) $schulId);

            if($format == "A4")
            {
                $templateFile = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A4.docx');
            }elseif($format == "A3")
            {
                $templateFile = app(\App\Services\Bop\BibbAttendanceWordTemplate::class)
                    ->a3TemplatePath($exportProjectId ?? $projectId);
            }
            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }


            $alle_teilnehmer = PersonenIstSchueler::forExportProject($exportProjectId ?? $projectId)
            ->filterSchueler( $schulId ?? null, $schuljahr ?? null, $teil ?? null)
            ->when($request->attributes->get('bop_package_student_ids') !== null,
                fn ($query) => $query->whereIn('id', $request->attributes->get('bop_package_student_ids')))
            ->with('person')
            ->reorder()
            ->orderBy(Personen::select('nachname')->whereColumn('personens.id', (new PersonenIstSchueler)->qualifyColumn('person_id')))
            ->orderBy('id')
            ->get();


            $klassen = $alle_teilnehmer->pluck('klasse')->unique()->toArray();

            $klassen = implode(', ', $klassen);
            $schule = Partner::findOrFail($schulId);


            $projekt_id = Auth()->user()->current_team_id;
            $projekt = Projekt::with('bereiche')->find($projekt_id);
            $bereiche = $projekt?->bereiche
                ->pluck('code')
                ->unique()
                ->implode('/ ');


            if(!$schule){
                return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.');
            }
            if($alle_teilnehmer->isEmpty()){
                return redirect()->back()->with('error', 'Die gewählte Schule weist zurzeit noch keine Schüler auf.');
            }

            $tagWerte = array_pad($programmTage, 10, '');
            $i = 1;
            $templateProcessor = app(\App\Services\Bop\BibbAttendanceWordTemplate::class)->processor($templateFile, $alle_teilnehmer->count());

            // Einfügen der Daten in die Textfelder


            $templateProcessor->setValue('schule', $schule->name);
            $schulform = PersonenIstSchueler::query()->schulform($alle_teilnehmer);
            $templateProcessor->setValue('bereiche', $bereiche);

            $templateProcessor->setValue('schulform', $schulform);
            $templateProcessor->setValue('klasse', $klassen);
            foreach (range(1, 10) as $tagIndex) {
                $templateProcessor->setValue('tag' . $tagIndex, $tagWerte[$tagIndex - 1] ?? '');
            }
            $templateProcessor->setValue('tag11', $tag11);

            $templateProcessor->setValue('anfangsdatum', $tagWerte[0] ?? '');
            $templateProcessor->setValue('enddatum', $tag11 ?: (collect($tagWerte)->filter()->last() ?: ''));

            foreach ($alle_teilnehmer as $teilnehmer)
            {
                // Initialisieren Sie den TemplateProcessor für jede Schleifeniteration
                $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);
                $templateProcessor->setValue('klasse' . $i, $teilnehmer->klasse);
                $i++;
            }
                $filename = 'Teilnehmendenliste_zum_Nachweis_der_praxisorientierten_Berufsorientierung_' . $schule->name . '_' . $schuljahr . '_' .  date('Ymd_His') . '.docx';
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                $this->attendanceFooter->applyToWordDocument($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePAPreviewDigital(Request $request)
    {
        $validated = $request->validate([
            'schuleId' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'listType' => ['nullable', 'in:pa,pa_preparation'],
            'exportMode' => ['nullable', 'in:alle,klasse'],
            'klasse' => ['nullable', 'required_if:exportMode,klasse', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'feedbackDate' => ['nullable', 'date'],
            'days' => ['nullable', 'array'],
            'days.*.id' => ['nullable', 'string', 'max:100'],
            'days.*.date' => ['required_with:days', 'date'],
            'days.*.type' => ['nullable', 'in:pa_day,feedback,preparation'],
            'days.*.source' => ['nullable', 'string', 'max:50'],
            'days.*.selected' => ['nullable', 'boolean'],
            'days.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensurePartnerInActiveProject((int) $validated['schuleId']);

        $exportMode = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');
        $listType = $validated['listType'] ?? 'pa';

        return response()->json($this->paPreviewPayload(
            (int) $validated['schuleId'],
            (string) $validated['schuljahr'],
            (string) $validated['teil'],
            $exportMode,
            $validated['klasse'] ?? null,
            $validated['days'] ?? [],
            $validated['startDate'] ?? null,
            $validated['endDate'] ?? null,
            $validated['feedbackDate'] ?? null,
            $listType
        ));
    }

    public function anwesenheitslistePADraftShow(Request $request)
    {
        // Ein zentraler PA-Entwurf kann mehrere hundert verschlüsselte
        // PNG-Signaturen enthalten. Eloquent, Entschlüsselung und JSON-Antwort
        // benötigen zeitweise mehrere Kopien davon. Die Anhebung gilt nur für
        // diesen Request und verändert keine gespeicherten Signaturdaten.
        $this->ensureMemoryLimit(512 * 1024 * 1024);

        $this->purgeExpiredPaDrafts();
        $scope = $this->paDraftScope($request);
        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        $legacyDraft = $this->legacyPaDraft($scope);
        $sourceDraft = $draft ?: $legacyDraft;
        $payload = $sourceDraft
            ? $this->decryptPaDraftPayloadSignatures($sourceDraft->payload ?? [])
            : null;

        if ($draft && $legacyDraft && is_array($payload)) {
            $payload = $this->restoreLegacySignatures(
                $payload,
                $this->decryptPaDraftPayloadSignatures($legacyDraft->payload ?? [])
            );
        }

        return response()->json([
            'exists' => (bool) $sourceDraft,
            'payload' => $payload,
            'revision' => $sourceDraft?->revision ?? 0,
            'updated_at' => $sourceDraft?->updated_at?->toIso8601String(),
            'expires_at' => $sourceDraft?->expires_at?->toIso8601String(),
            'final_pdf_path' => $sourceDraft?->final_pdf_path,
            'restored_from_schuljahr' => $legacyDraft?->schuljahr,
        ]);
    }

    public function anwesenheitslistePADraftStore(Request $request)
    {
        // Das höhere Limit bleibt für Datenbanktreiber ohne JSON-Teilupdates als
        // sichere Rückfallebene bestehen. MariaDB speichert weiter unten nur das
        // geänderte Feld und lädt den Gesamtbestand nicht mehr nach PHP.
        $this->ensureMemoryLimit(512 * 1024 * 1024);

        $this->purgeExpiredPaDrafts();
        $scope = $this->paDraftScope($request);
        $request->validate([
            'payload' => ['required', 'array'],
            'payload.version' => ['nullable', 'integer', 'min:1', 'max:2'],
            'payload.form' => ['nullable', 'array'],
            'payload.days' => ['nullable', 'array'],
            'payload.selectedDayId' => ['nullable', 'string'],
            'payload.classSchedules' => ['nullable', 'array'],
            'payload.classSchedules.*' => ['array'],
            'payload.classSchedules.*.form' => ['nullable', 'array'],
            'payload.classSchedules.*.days' => ['nullable', 'array'],
            'payload.classSchedules.*.selectedDayId' => ['nullable', 'string'],
            'payload.signatures' => ['nullable', 'array'],
            'payload.signatures.*' => ['nullable', 'string'],
        ]);

        // Die Klassenbezeichnungen können Punkte enthalten (z. B. "7.1").
        // Nach erfolgreicher Validierung wird deshalb die rohe Payload sanitisiert,
        // damit Laravel diese Array-Schlüssel nicht als Dot-Notation behandelt.
        $incomingPayload = $this->sanitizePaDraftPayload((array) $request->input('payload', []));
        $userId = auth()->id();

        [$draft, $responsePayload] = DB::transaction(function () use ($scope, $incomingPayload, $userId, $request) {
            $now = now();

            PaAttendanceListDraft::query()->insertOrIgnore([
                'draft_hash' => $scope['draft_hash'],
                'projekt_id' => $scope['projekt_id'],
                'partner_id' => $scope['partner_id'],
                'schuljahr' => $scope['schuljahr'],
                'teil' => $scope['teil'],
                'export_mode' => $scope['draft_export_mode'],
                'klasse' => $scope['draft_klasse'],
                'payload' => json_encode((object) []),
                'revision' => 0,
                'user_create' => $userId,
                'user_update' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // MariaDB kann einzelne JSON-Werte direkt in der Datenbank ändern.
            // So muss PHP bei großen Entwürfen nicht für jede neue Unterschrift
            // alle bereits gespeicherten PNGs laden und mehrfach kopieren.
            // Auch alte Schuljahr-Entwürfe werden dabei direkt in MariaDB
            // zusammengeführt, ohne den großen Bildbestand nach PHP zu laden.
            if (DB::connection()->getDriverName() === 'mysql') {
                return $this->storePaDraftDeltaWithDatabaseJson(
                    $scope,
                    $incomingPayload,
                    $userId,
                    $request
                );
            }

            $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])
                ->lockForUpdate()
                ->first();

            // Signaturen bleiben beim normalen Speichern verschlüsselt. Dadurch
            // müssen hunderte Base64-Bilder nicht gleichzeitig entschlüsselt,
            // kopiert, erneut verschlüsselt und als Antwort serialisiert werden.
            $draftPayload = $draft?->payload;
            $storedPayload = is_array($draftPayload) ? $draftPayload : [];
            unset($draftPayload);
            $existingPayload = $storedPayload;
            $legacyDraft = $this->legacyPaDraft($scope);
            if ($legacyDraft) {
                $legacyPayload = $legacyDraft->payload;
                $existingPayload = $this->restoreLegacySignatures(
                    $existingPayload,
                    is_array($legacyPayload) ? $legacyPayload : []
                );
                unset($legacyPayload);
            }
            $restoredLegacySignatures = $storedPayload !== $existingPayload;
            [$payload, $signatureChanges] = $this->mergeEncryptedPaDraftPayload(
                $existingPayload,
                $incomingPayload
            );

            if ($restoredLegacySignatures) {
                $payload = $this->encryptPaDraftPayloadSignatures($payload);
            }

            if (! $restoredLegacySignatures && ! $this->draftPayloadHasChanges($existingPayload, $payload)) {
                return [$draft, null];
            }

            $payload['saved_at'] = now()->toIso8601String();

            $draft->payload = $payload;
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = $userId;
            $historyPayload = $payload;
            unset($historyPayload['signatures']);
            $this->paSignatureHistory->recordSignatureChanges(
                $draft,
                $scope,
                $historyPayload,
                $signatureChanges,
                $request
            );
            $draft->save();

            if ($restoredLegacySignatures) {
                PaAttendanceListDraft::whereIn('draft_hash', $scope['legacy_draft_hashes'])->delete();
            }

            // Nur eine seltene Altdatenübernahme benötigt den vollständigen
            // Rückgabestand. Normale Delta-Saves behalten den Browserzustand und
            // vermeiden eine weitere große Klartextkopie aller Unterschriften.
            return [
                $draft,
                $restoredLegacySignatures
                    ? $this->decryptPaDraftPayloadSignatures($payload)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'payload' => $responsePayload,
            'revision' => $draft->revision,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'expires_at' => $draft->expires_at?->toIso8601String(),
            'final_pdf_path' => $draft->final_pdf_path,
        ]);
    }

    public function anwesenheitslistePASignatureHistory(Request $request)
    {
        $scope = $this->paDraftScope($request);
        $validated = $request->validate([
            'signatureKey' => ['required', 'string', 'max:255'],
        ]);
        $signatureKey = (string) $validated['signatureKey'];
        $participant = $this->paSignatureHistory->participantSnapshot($scope, $signatureKey);

        abort_unless($participant, 404);

        if ($this->paSignatureHistory->versions($scope, $signatureKey)->isEmpty()) {
            DB::transaction(function () use ($scope, $signatureKey, $request) {
                $draftHashes = [$scope['draft_hash'], ...$scope['legacy_draft_hashes']];
                $drafts = PaAttendanceListDraft::query()
                    ->whereIn('draft_hash', $draftHashes)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->sortByDesc('updated_at');

                foreach ($drafts as $draft) {
                    $payload = $this->decryptPaDraftPayloadSignatures($draft->payload ?? []);
                    $signature = $payload['signatures'][$signatureKey] ?? null;

                    if (is_string($signature) && $signature !== '') {
                        $this->paSignatureHistory->append(
                            $draft,
                            $scope,
                            $payload,
                            $signatureKey,
                            $signature,
                            'imported',
                            $request
                        );
                        break;
                    }
                }
            });
        }

        return response()->json([
            'participant' => $participant,
            'signature_key' => $signatureKey,
            'versions' => $this->paSignatureHistory->presentVersions($scope, $signatureKey),
        ]);
    }

    public function anwesenheitslistePASignatureHistoryIndex(Request $request)
    {
        $scope = $this->paDraftScope($request);

        DB::transaction(function () use ($scope, $request) {
            $drafts = PaAttendanceListDraft::query()
                ->whereIn('draft_hash', [$scope['draft_hash'], ...$scope['legacy_draft_hashes']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortByDesc('updated_at');

            foreach ($drafts as $draft) {
                $payload = $this->decryptPaDraftPayloadSignatures($draft->payload ?? []);
                foreach (($payload['signatures'] ?? []) as $signatureKey => $signature) {
                    if (!is_string($signatureKey) || !is_string($signature) || $signature === '') {
                        continue;
                    }

                    if ($this->paSignatureHistory->versions($scope, $signatureKey)->isEmpty()) {
                        $this->paSignatureHistory->append(
                            $draft,
                            $scope,
                            $payload,
                            $signatureKey,
                            $signature,
                            'imported',
                            $request
                        );
                    }
                }
            }
        });

        return response()->json([
            'subjects' => $this->paSignatureHistory->scopeSubjects($scope),
        ]);
    }

    public function anwesenheitslistePASignatureRestore(Request $request)
    {
        $scope = $this->paDraftScope($request);
        $validated = $request->validate([
            'signatureKey' => ['required', 'string', 'max:255'],
            'versionId' => ['required', 'integer'],
        ]);
        $signatureKey = (string) $validated['signatureKey'];
        $participant = $this->paSignatureHistory->participantSnapshot($scope, $signatureKey);

        abort_unless($participant, 404);

        $subjectHash = $this->paSignatureHistory->subjectHash($scope, $signatureKey);
        $sourceVersion = PaAttendanceSignatureVersion::query()
            ->where('subject_hash', $subjectHash)
            ->whereKey($validated['versionId'])
            ->firstOrFail();
        $latestVersion = PaAttendanceSignatureVersion::query()
            ->where('subject_hash', $subjectHash)
            ->latest('version')
            ->first();

        abort_if($latestVersion?->is($sourceVersion), 422, 'Diese Unterschriftsversion ist bereits aktuell.');

        $signature = $this->paSignatureHistory->decryptVersion($sourceVersion);
        abort_unless($signature, 422, 'Diese Unterschriftsversion kann nicht wiederhergestellt werden.');

        $userId = auth()->id();
        $draft = DB::transaction(function () use ($scope, $signatureKey, $signature, $sourceVersion, $request, $userId) {
            $now = now();

            PaAttendanceListDraft::query()->insertOrIgnore([
                'draft_hash' => $scope['draft_hash'],
                'projekt_id' => $scope['projekt_id'],
                'partner_id' => $scope['partner_id'],
                'schuljahr' => $scope['schuljahr'],
                'teil' => $scope['teil'],
                'export_mode' => $scope['draft_export_mode'],
                'klasse' => $scope['draft_klasse'],
                'payload' => json_encode((object) []),
                'revision' => 0,
                'user_create' => $userId,
                'user_update' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $draft = PaAttendanceListDraft::query()
                ->where('draft_hash', $scope['draft_hash'])
                ->lockForUpdate()
                ->firstOrFail();
            $payload = $this->decryptPaDraftPayloadSignatures($draft->payload ?? []);
            $payload['version'] = max(2, (int) ($payload['version'] ?? 1));
            $payload['signatures'] = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
            $payload['signatures'][$signatureKey] = $signature;
            $payload['saved_at'] = now()->toIso8601String();

            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = $userId;
            $draft->payload = $this->encryptPaDraftPayloadSignatures($payload);
            $this->paSignatureHistory->append(
                $draft,
                $scope,
                $payload,
                $signatureKey,
                $signature,
                'restored',
                $request,
                $sourceVersion->id
            );
            $draft->save();

            return $draft;
        });

        return response()->json([
            'success' => true,
            'signature' => $signature,
            'revision' => $draft->revision,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'participant' => $participant,
            'versions' => $this->paSignatureHistory->presentVersions($scope, $signatureKey),
        ]);
    }

    public function anwesenheitslistePADraftDestroy(Request $request)
    {
        $scope = $this->paDraftScope($request);

        DB::transaction(function () use ($scope, $request) {
            $drafts = PaAttendanceListDraft::query()
                ->whereIn('draft_hash', [$scope['draft_hash'], ...$scope['legacy_draft_hashes']])
                ->lockForUpdate()
                ->get();
            $recordedKeys = [];

            foreach ($drafts->sortByDesc('updated_at') as $draft) {
                $payload = $this->decryptPaDraftPayloadSignatures($draft->payload ?? []);

                foreach (($payload['signatures'] ?? []) as $signatureKey => $signature) {
                    if (!is_string($signatureKey) || !is_string($signature) || $signature === '' || isset($recordedKeys[$signatureKey])) {
                        continue;
                    }

                    $this->paSignatureHistory->append(
                        $draft,
                        $scope,
                        $payload,
                        $signatureKey,
                        null,
                        'deleted',
                        $request
                    );
                    $recordedKeys[$signatureKey] = true;
                }
            }

            PaAttendanceListDraft::query()
                ->whereKey($drafts->modelKeys())
                ->delete();
        });

        return response()->json(['success' => true]);
    }

    public function anwesenheitslistePAArchiveFolder(Request $request)
    {
        $scope = $this->paDraftScope($request);
        $partner = Partner::findOrFail($scope['partner_id']);
        $folder = $this->bibbBaseFolder($partner, $scope['schuljahr'], $scope['teil']);
        $subfolders = $this->ensureBibbArchiveSubfolders($folder);

        return response()->json([
            'success' => true,
            'folder' => $this->relativeStoragePath($folder),
            'subfolders' => array_map(fn ($path) => $this->relativeStoragePath($path), $subfolders),
        ]);
    }

    public function anwesenheitslistePASignedPdfStore(Request $request)
    {
        $this->purgeExpiredPaDrafts();
        $scope = $this->paDraftScope($request);

        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
            'filename' => ['nullable', 'string', 'max:180'],
        ]);

        $partner = Partner::findOrFail($scope['partner_id']);
        $folder = $this->bibbBaseFolder($partner, $scope['schuljahr'], $scope['teil'])
            . DIRECTORY_SEPARATOR
            . 'Anwesenheit';

        File::ensureDirectoryExists($folder);

        $filename = $this->paSignedPdfFilename(
            $validated['filename'] ?? null,
            $partner,
            $scope['schuljahr'],
            $scope['teil'],
            $scope['export_mode'],
            $scope['klasse'],
            $scope['list_type']
        );
        [$filename, $path] = $this->availablePaSignedPdfPath($folder, $filename);

        $request->file('pdf')->move($folder, $filename);

        $relativePath = $this->relativeStoragePath($path);
        $expiresAt = now()->addDays(self::PA_DRAFT_RETENTION_DAYS);

        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        if ($draft) {
            $payload = $this->decryptPaDraftPayloadSignatures($draft->payload ?? []);
            $legacyDraft = $this->legacyPaDraft($scope);
            if ($legacyDraft) {
                $payload = $this->restoreLegacySignatures(
                    $payload,
                    $this->decryptPaDraftPayloadSignatures($legacyDraft->payload ?? [])
                );
            }
            foreach (($payload['signatures'] ?? []) as $signatureKey => $signature) {
                if (!is_string($signatureKey) || !is_string($signature) || $signature === '') {
                    continue;
                }

                if ($this->paSignatureHistory->versions($scope, $signatureKey)->isEmpty()) {
                    $this->paSignatureHistory->append(
                        $draft,
                        $scope,
                        $payload,
                        $signatureKey,
                        $signature,
                        'imported',
                        $request
                    );
                }
            }
            $payload['final_pdf'] = [
                'path' => $relativePath,
                'saved_at' => now()->toIso8601String(),
                'draft_expires_at' => $expiresAt->toIso8601String(),
                'signature_versions' => $this->paSignatureHistory->currentVersionManifest($scope, $payload),
            ];

            $draft->payload = $this->encryptPaDraftPayloadSignatures($payload);
            $draft->final_pdf_path = $relativePath;
            $draft->finalized_at = now();
            $draft->expires_at = $expiresAt;
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = auth()->id();
            $draft->save();
        }

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'path' => $relativePath,
            'folder' => $this->relativeStoragePath($folder),
            'revision' => $draft?->revision ?? 0,
            'updated_at' => $draft?->updated_at?->toIso8601String(),
            'expires_at' => $draft?->expires_at?->toIso8601String() ?? $expiresAt->toIso8601String(),
        ]);
    }

    public function anwesenheitslistePAPreparationExportWord(Request $request, ?int $exportProjectId = null, string $sort = 'klasse', bool $asExcel = false)
    {
        $this->ensureMemoryLimit(512 * 1024 * 1024);
        $scope = $this->paDraftScope($request);
        abort_unless($scope['list_type'] === 'pa_preparation', 422, 'Dieser Export ist nur für die Vorbereitung PA verfügbar.');

        $validated = $request->validate([
            'exportFormat' => ['nullable', 'in:A4,A3'],
        ]);
        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        $legacyDraft = $this->legacyPaDraft($scope);
        $sourceDraft = $draft ?: $legacyDraft;
        if ($request->boolean('usePlanning')) {
            return $this->plannedPaPreparationExport($scope, $validated['exportFormat'] ?? 'A4', $exportProjectId, $sort, $asExcel, $request->input('termin'));
        }
        if (!$sourceDraft) {
            return $this->plannedPaPreparationExport($scope, $validated['exportFormat'] ?? 'A4', $exportProjectId, $sort, $asExcel);
        }

        $payload = $this->decryptPaDraftPayloadSignatures($sourceDraft->payload ?? []);
        if ($draft && $legacyDraft) {
            $payload = $this->restoreLegacySignatures(
                $payload,
                $this->decryptPaDraftPayloadSignatures($legacyDraft->payload ?? [])
            );
        }

        $day = collect($payload['days'] ?? [])
            ->first(fn ($item) => is_array($item)
                && ($item['selected'] ?? true)
                && ($item['type'] ?? 'preparation') === 'preparation'
                && $this->attendanceDateAllowed($item['date'] ?? null, $payload['form'] ?? []));
        abort_unless($day, 422, 'Bitte übernehmen Sie zuerst den Termin Vorbereitung PA.');

        $participants = $this->paTeilnehmer(
            $scope['partner_id'],
            $scope['schuljahr'],
            $scope['teil'],
            $scope['export_mode'],
            $scope['klasse'],
            $scope['list_type'],
            $exportProjectId
        );
        abort_if($participants->isEmpty(), 422, 'Die Schule hat keine Teilnehmer für diese Auswahl.');

        $school = Partner::findOrFail($scope['partner_id']);
        $exportFormat = $validated['exportFormat'] ?? ($payload['form']['exportFormat'] ?? 'A4');
        $classPart = $scope['export_mode'] === 'klasse' && $scope['klasse']
            ? '_Klasse_' . $scope['klasse']
            : '';
        $filename = $this->bibbSafeName(
            'Anwesenheitsliste_Vorbereitung_PA_' . $school->name . '_' . $scope['schuljahr']
            . '_Teil_' . $scope['teil'] . $classPart
        ) . '.docx';
        $path = storage_path('exports/' . Str::uuid() . '_' . $filename);

        if ($asExcel) {
            $filename = preg_replace('/\.docx$/', '.xlsx', $filename);
            $path = preg_replace('/\.docx$/', '.xlsx', $path);
            app(\App\Services\Bop\PaPreparationAttendanceTemplateExportService::class)->create(
                $school, PersonenIstSchueler::sortForPackage($participants, $sort), $day,
                is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [],
                $scope['export_mode'], $scope['klasse'], $exportFormat, $path, false, $scope['projekt_id']
            );

            return response()->download($path, $filename)->deleteFileAfterSend(true);
        }

        $this->paPreparationWordExport->create(
            $school,
            PersonenIstSchueler::sortForPackage($participants, $sort),
            $day,
            is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [],
            $scope['schuljahr'],
            $scope['teil'],
            $scope['export_mode'],
            $scope['klasse'],
            $exportFormat,
            $path
        );

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function plannedPaPreparationExport(array $scope, string $format, ?int $projectId, string $sort, bool $asExcel = false, ?string $fallbackDate = null)
    {
        $planning = app(\App\Services\Bop\PackagePlanning::class);
        $phase = $planning->phase($scope['projekt_id'], $scope['partner_id'], $scope['schuljahr'], $scope['teil'], 'pa_preparation');
        $participants = $this->paTeilnehmer($scope['partner_id'], $scope['schuljahr'], $scope['teil'],
            $scope['export_mode'], $scope['klasse'], 'pa_preparation', $projectId);
        $participants = PersonenIstSchueler::sortForPackage($participants, $sort);
        $dates = collect($planning->dates($phase, $scope['projekt_id'], $scope['partner_id'], $scope['schuljahr'], $scope['teil']));
        if ($dates->isEmpty() && $fallbackDate) {
            $dates = collect([$fallbackDate]);
            if ($phase) {
                $phase = clone $phase;
                $phase->class_date_assignments = [];
            }
        }
        abort_if($dates->isEmpty(), 422, 'Für diesen Teil fehlt der Termin Vorbereitung PA in der BOP-Planung.');
        $school = Partner::findOrFail($scope['partner_id']);

        // Each planned date receives its own document, preserving its participant assignment.
        $directory = storage_path('exports/pa-plan-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $paths = [];
        try {
            foreach ($dates as $date) {
                abort_unless($this->attendanceDateAllowed($date), 422, 'Der geplante PA-Vorbereitungstermin liegt an einem ausgeschlossenen Tag.');
                $selected = $participants->filter(function ($student) use ($phase, $date) {
                    if (!$phase) return true;
                    if ($phase->scope_type === 'participants' && !$phase->participants->contains('personen_ist_schueler_id', $student->id)) return false;
                    if ($phase->scope_type === 'classes' && !in_array($student->klasse, $phase->selected_classes ?? [], true)) return false;
                    if (!empty($phase->class_date_assignments)) {
                        return in_array($date, $phase->class_date_assignments[$student->klasse] ?? [], true);
                    }

                    return true;
                })->values();
                if ($selected->isEmpty()) continue;
                $path = $directory.'/Vorbereitung_PA_'.$date.($asExcel ? '.xlsx' : '.docx');
                if ($asExcel) {
                    app(\App\Services\Bop\PaPreparationAttendanceTemplateExportService::class)->create(
                        $school, $selected, $this->paPreparationDayFromDate($date)[0], [],
                        $scope['export_mode'], $scope['klasse'], $format, $path, false, $scope['projekt_id']
                    );
                    $paths[] = $path;
                    continue;
                }
                $this->paPreparationWordExport->create($school, $selected, $this->paPreparationDayFromDate($date)[0],
                    [], $scope['schuljahr'], $scope['teil'], $scope['export_mode'], $scope['klasse'], $format, $path);
                $paths[] = $path;
            }
            abort_if(empty($paths), 422, 'Für die geplanten Vorbereitungstermine sind dieser Auswahl keine Teilnehmenden zugeordnet.');
            if ($asExcel) {
                $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $book->removeSheetByIndex(0);
                foreach ($paths as $file) {
                    $source = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                    $sheet = $source->getActiveSheet();
                    $sheet->setTitle(substr(basename($file, '.xlsx'), -10));
                    $book->addExternalSheet($sheet);
                }
                $book->setActiveSheetIndex(0);
                $output = storage_path('exports/'.Str::uuid().'.xlsx');
                try {
                    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($output);
                } finally {
                    $book->disconnectWorksheets();
                }

                return response()->download($output, 'Anwesenheitsliste_Vorbereitung_PA.xlsx')->deleteFileAfterSend(true);
            }
            $output = storage_path('exports/'.Str::uuid().'.docx');
            app(BopGruppeExportController::class)->mergeDocxFiles($paths, $output);

            return response()->download($output, 'Anwesenheitsliste_Vorbereitung_PA.docx')->deleteFileAfterSend(true);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function anwesenheitslistePAPreparationExportTemplate(
        Request $request,
        \App\Services\Bop\PaPreparationAttendanceTemplateExportService $exporter
    ) {
        $this->ensureMemoryLimit(512 * 1024 * 1024);
        $scope = $this->paDraftScope($request);
        abort_unless($scope['list_type'] === 'pa_preparation', 422, 'Dieser Export ist nur für die Vorbereitung PA verfügbar.');
        $data = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
            'exportFormat' => ['nullable', 'in:A4,A3'],
        ]);
        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        $legacyDraft = $this->legacyPaDraft($scope);
        $sourceDraft = $draft ?: $legacyDraft;
        abort_unless($sourceDraft, 422, 'Bitte speichern Sie zuerst den Entwurf der Anwesenheitsliste.');
        $payload = $this->decryptPaDraftPayloadSignatures($sourceDraft->payload ?? []);
        if ($draft && $legacyDraft) {
            $payload = $this->restoreLegacySignatures($payload, $this->decryptPaDraftPayloadSignatures($legacyDraft->payload ?? []));
        }
        $selectedDays = collect($payload['days'] ?? [])->filter(fn ($item) => is_array($item)
            && ($item['selected'] ?? true) && ($item['type'] ?? 'preparation') === 'preparation'
            && $this->attendanceDateAllowed($item['date'] ?? null, $payload['form'] ?? []));
        abort_unless($selectedDays->count() === 1, 422, 'Bitte wählen Sie genau einen Termin Vorbereitung PA für die Vorlage aus.');
        $day = $selectedDays->first();
        $participants = $this->paTeilnehmer($scope['partner_id'], $scope['schuljahr'], $scope['teil'], $scope['export_mode'], $scope['klasse'], $scope['list_type']);
        abort_if($participants->isEmpty(), 422, 'Die Schule hat keine Teilnehmer für diese Auswahl.');
        $school = Partner::findOrFail($scope['partner_id']);
        $classPart = $scope['export_mode'] === 'klasse' && $scope['klasse'] ? '_Klasse_'.$scope['klasse'] : '';
        $baseName = $this->bibbSafeName('Anwesenheitsliste_Vorbereitung_PA_'.$school->name.'_'.$scope['schuljahr'].'_Teil_'.$scope['teil'].$classPart);
        $xlsxPath = storage_path('exports/'.Str::uuid().'_'.$baseName.'.xlsx');
        $outputPath = $xlsxPath;
        try {
            $exporter->create($school, $participants, $day, is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [],
                $scope['export_mode'], $scope['klasse'], $data['exportFormat'] ?? ($payload['form']['exportFormat'] ?? 'A4'),
                $xlsxPath, $data['format'] === 'pdf', $scope['projekt_id']);
            if ($data['format'] === 'pdf') {
                $outputPath = $exporter->createPdf($xlsxPath);
                File::delete($xlsxPath);
            }
        } catch (\Throwable $exception) {
            File::delete($xlsxPath);
            if ($outputPath !== $xlsxPath) File::delete($outputPath);
            report($exception);
            throw ValidationException::withMessages(['export' => 'Die Anwesenheitsliste konnte nicht erstellt werden. Bitte erneut versuchen. Falls der Fehler bleibt, muss die Vorlagendatei und das Serverprotokoll geprüft werden.']);
        }

        return response()->download($outputPath, $baseName.'.'.$data['format'])->deleteFileAfterSend(true);
    }

    private function availablePaSignedPdfPath(string $folder, string $filename): array
    {
        $path = $folder . DIRECTORY_SEPARATOR . $filename;
        if (!File::exists($path)) {
            return [$filename, $path];
        }

        $baseName = Str::beforeLast($filename, '.pdf');
        $version = 2;

        do {
            $versionedFilename = $baseName . '_v' . $version . '.pdf';
            $versionedPath = $folder . DIRECTORY_SEPARATOR . $versionedFilename;
            $version++;
        } while (File::exists($versionedPath));

        return [$versionedFilename, $versionedPath];
    }

    private function purgeExpiredPaDrafts(): int
    {
        return PaAttendanceListDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function paSignedPdfFilename(
        ?string $filename,
        Partner $partner,
        string $schuljahr,
        string $teil,
        string $exportMode,
        ?string $klasse,
        string $listType = 'pa'
    ): string {
        $prefix = $listType === 'pa_preparation'
            ? 'Anwesenheitsliste_Vorbereitung_PA_'
            : 'Anwesenheitsliste_PA_';
        $baseName = $filename
            ? Str::beforeLast($filename, '.pdf')
            : $prefix . $partner->name . '_' . $schuljahr . '_Teil_' . $teil;

        if (!$filename && $exportMode === 'klasse' && $klasse) {
            $baseName .= '_Klasse_' . $klasse;
        }

        return $this->bibbSafeName($baseName) . '.pdf';
    }

    private function encryptPaDraftPayloadSignatures(array $payload): array
    {
        if (!is_array($payload['signatures'] ?? null)) {
            return $payload;
        }

        $payload['signatures'] = collect($payload['signatures'])
            ->mapWithKeys(function ($value, $key) {
                if (!is_string($key) || !is_string($value) || $value === '') {
                    return [$key => $value];
                }

                return [$key => $this->encryptPaSignature($value)];
            })
            ->all();

        return $payload;
    }

    /**
     * Speichert eine PA-Änderung direkt im JSON-Dokument von MariaDB. Die
     * Payload kann hunderte verschlüsselte PNGs enthalten; würde Eloquent sie
     * als PHP-Array casten, entstünden beim Zusammenführen mehrere vollständige
     * Kopien und das PHP-Speicherlimit wäre schnell erreicht.
     *
     * @return array{0:PaAttendanceListDraft,1:null}
     */
    private function storePaDraftDeltaWithDatabaseJson(
        array $scope,
        array $incomingPayload,
        ?int $userId,
        Request $request
    ): array {
        $draftRow = DB::table('pa_attendance_list_drafts')
            ->select(['id', 'revision'])
            ->where('draft_hash', $scope['draft_hash'])
            ->lockForUpdate()
            ->first();

        abort_unless($draftRow, 404);

        $draftId = (int) $draftRow->id;
        $savedAt = now();
        $savedAtIso = $savedAt->toIso8601String();

        // Frühere Datensätze konnten als leeres JSON-Array angelegt werden.
        // Für benannte Eigenschaften wird einmalig ein Objekt sichergestellt.
        DB::update(
            "UPDATE pa_attendance_list_drafts
             SET payload = IF(JSON_TYPE(payload) = 'OBJECT', payload, JSON_OBJECT())
             WHERE id = ?",
            [$draftId]
        );

        $legacyDraftId = null;
        if (!empty($scope['legacy_draft_hashes'])) {
            $legacyDraftId = DB::table('pa_attendance_list_drafts')
                ->whereIn('draft_hash', $scope['legacy_draft_hashes'])
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->value('id');
        }

        if ($legacyDraftId) {
            DB::update(
                "UPDATE pa_attendance_list_drafts AS current_draft
                 INNER JOIN pa_attendance_list_drafts AS legacy_draft
                     ON legacy_draft.id = ?
                 SET current_draft.payload = JSON_SET(
                     current_draft.payload,
                     '$.signatures', JSON_MERGE_PATCH(
                         IFNULL(JSON_EXTRACT(legacy_draft.payload, '$.signatures'), JSON_OBJECT()),
                         IFNULL(JSON_EXTRACT(current_draft.payload, '$.signatures'), JSON_OBJECT())
                     )
                 )
                 WHERE current_draft.id = ?",
                [(int) $legacyDraftId, $draftId]
            );
        }

        DB::update(
            "UPDATE pa_attendance_list_drafts
             SET payload = JSON_SET(
                 payload,
                 '$.version', GREATEST(
                     IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.version')) AS UNSIGNED), 1),
                     ?
                 ),
                 '$.form', JSON_EXTRACT(?, '$'),
                 '$.days', JSON_EXTRACT(?, '$'),
                 '$.selectedDayId', JSON_EXTRACT(?, '$'),
                 '$.saved_at', ?
             )
             WHERE id = ?",
            [
                (int) ($incomingPayload['version'] ?? 1),
                json_encode($incomingPayload['form'] ?? [], JSON_THROW_ON_ERROR),
                json_encode($incomingPayload['days'] ?? [], JSON_THROW_ON_ERROR),
                json_encode($incomingPayload['selectedDayId'] ?? null, JSON_THROW_ON_ERROR),
                $savedAtIso,
                $draftId,
            ]
        );

        if (array_key_exists('classSchedules', $incomingPayload)) {
            DB::update(
                "UPDATE pa_attendance_list_drafts
                 SET payload = JSON_SET(
                     payload,
                     '$.classSchedules', JSON_MERGE_PATCH(
                         IFNULL(JSON_EXTRACT(payload, '$.classSchedules'), JSON_OBJECT()),
                         JSON_EXTRACT(?, '$')
                     )
                 )
                 WHERE id = ?",
                [
                    json_encode($incomingPayload['classSchedules'] ?? [], JSON_THROW_ON_ERROR),
                    $draftId,
                ]
            );
        }

        $signatureType = DB::selectOne(
            "SELECT JSON_TYPE(JSON_EXTRACT(payload, '$.signatures')) AS signature_type
             FROM pa_attendance_list_drafts
             WHERE id = ?",
            [$draftId]
        )?->signature_type;

        if ($signatureType !== 'OBJECT') {
            DB::update(
                "UPDATE pa_attendance_list_drafts
                 SET payload = JSON_SET(payload, '$.signatures', JSON_EXTRACT(?, '$'))
                 WHERE id = ?",
                ['{}', $draftId]
            );
        }

        $signatureChanges = [];
        foreach (($incomingPayload['signatures'] ?? []) as $signatureKey => $incomingValue) {
            if (!is_string($signatureKey)) {
                continue;
            }

            $jsonPath = '$.signatures."'
                . str_replace(['\\', '"'], ['\\\\', '\\"'], $signatureKey)
                . '"';
            $storedRow = DB::selectOne(
                'SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) AS signature_value
                 FROM pa_attendance_list_drafts
                 WHERE id = ?',
                [$jsonPath, $draftId]
            );
            $storedValue = is_string($storedRow?->signature_value)
                ? $storedRow->signature_value
                : null;
            $previousValue = $storedValue !== null
                ? $this->decryptPaSignature($storedValue)
                : null;
            $currentValue = is_string($incomingValue) && $incomingValue !== ''
                ? $incomingValue
                : null;

            if ($previousValue === $currentValue) {
                continue;
            }

            $signatureChanges[$signatureKey] = [
                'previous' => $previousValue,
                'current' => $currentValue,
            ];

            if ($currentValue === null) {
                DB::update(
                    'UPDATE pa_attendance_list_drafts SET payload = JSON_REMOVE(payload, ?) WHERE id = ?',
                    [$jsonPath, $draftId]
                );
            } else {
                DB::update(
                    'UPDATE pa_attendance_list_drafts SET payload = JSON_SET(payload, ?, ?) WHERE id = ?',
                    [$jsonPath, $this->encryptPaSignature($currentValue), $draftId]
                );
            }
        }

        $revision = ((int) $draftRow->revision) + 1;
        DB::table('pa_attendance_list_drafts')
            ->where('id', $draftId)
            ->update([
                'revision' => $revision,
                'user_update' => $userId,
                'updated_at' => $savedAt,
            ]);

        $draft = PaAttendanceListDraft::query()
            ->select([
                'id',
                'draft_hash',
                'projekt_id',
                'partner_id',
                'schuljahr',
                'teil',
                'export_mode',
                'klasse',
                'final_pdf_path',
                'finalized_at',
                'expires_at',
                'revision',
                'user_create',
                'user_update',
                'created_at',
                'updated_at',
            ])
            ->findOrFail($draftId);

        $historyPayload = $incomingPayload;
        unset($historyPayload['signatures']);
        $this->paSignatureHistory->recordSignatureChanges(
            $draft,
            $scope,
            $historyPayload,
            $signatureChanges,
            $request
        );

        if ($legacyDraftId) {
            PaAttendanceListDraft::query()
                ->whereIn('draft_hash', $scope['legacy_draft_hashes'])
                ->delete();
        }

        return [$draft, null];
    }

    private function decryptPaDraftPayloadSignatures(array $payload): array
    {
        if (!is_array($payload['signatures'] ?? null)) {
            return $payload;
        }

        $signatures = [];
        foreach ($payload['signatures'] as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $decrypted = $this->decryptPaSignature($value);
            if ($decrypted) {
                $signatures[$key] = $decrypted;
            }
        }

        $payload['signatures'] = $signatures;

        return $payload;
    }

    private function encryptPaSignature(string $value): string
    {
        if ($this->isEncryptedPaSignature($value)) {
            return $value;
        }

        return self::PA_SIGNATURE_ENCRYPTION_PREFIX . Crypt::encryptString($value);
    }

    private function decryptPaSignature(string $value): ?string
    {
        if (!$this->isEncryptedPaSignature($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(Str::after($value, self::PA_SIGNATURE_ENCRYPTION_PREFIX));
        } catch (DecryptException) {
            return null;
        }
    }

    private function isEncryptedPaSignature(string $value): bool
    {
        return Str::startsWith($value, self::PA_SIGNATURE_ENCRYPTION_PREFIX);
    }

    private function paDraftScope(Request $request): array
    {
        $validated = $request->validate([
            'schuleId' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'listType' => ['nullable', 'in:pa,pa_preparation'],
            'exportMode' => ['nullable', 'in:alle,klasse'],
            'klasse' => ['nullable', 'required_if:exportMode,klasse', 'string'],
        ]);

        $exportMode = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');
        $klasse = $exportMode === 'klasse' ? (string) ($validated['klasse'] ?? '') : null;
        $listType = $validated['listType'] ?? 'pa';

        if ($listType === 'pa_preparation' && $exportMode === 'klasse' && $klasse === '') {
            throw ValidationException::withMessages([
                'klasse' => 'Bitte eine Klasse für die Anwesenheitsliste Vorbereitung PA auswählen.',
            ]);
        }

        if ($exportMode === 'klasse' && $klasse === '') {
            throw ValidationException::withMessages([
                'klasse' => 'Bitte eine Klasse auswählen.',
            ]);
        }

        $partnerId = (int) $validated['schuleId'];
        $projektId = $this->ensurePartnerInActiveProject($partnerId);
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $draftExportMode = $listType === 'pa' ? 'alle' : $exportMode;
        $draftKlasse = $listType === 'pa' ? null : $klasse;
        $draftHash = $this->paDraftHash(
            $projektId,
            $partnerId,
            $schuljahr,
            $teil,
            $draftExportMode,
            $draftKlasse,
            $listType
        );
        $legacyDraftHashes = collect($this->schoolYearAliases($schuljahr))
            ->reject(fn (string $alias) => $alias === trim($schuljahr))
            ->map(fn (string $alias) => $this->paDraftHash(
                $projektId,
                $partnerId,
                $alias,
                $teil,
                $draftExportMode,
                $draftKlasse,
                $listType
            ))
            ->values()
            ->all();

        return [
            'draft_hash' => $draftHash,
            'legacy_draft_hashes' => $legacyDraftHashes,
            'projekt_id' => $projektId,
            'partner_id' => $partnerId,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'list_type' => $listType,
            'export_mode' => $exportMode,
            'klasse' => $klasse,
            'draft_export_mode' => $draftExportMode,
            'draft_klasse' => $draftKlasse,
        ];
    }

    private function paDraftHash(
        ?int $projektId,
        int $partnerId,
        string $schuljahr,
        string $teil,
        string $exportMode,
        ?string $klasse,
        string $listType
    ): string {
        return hash('sha256', implode('|', [
            $projektId ?: 0,
            $partnerId,
            trim($schuljahr),
            $teil,
            $exportMode,
            $klasse ?: '',
            $listType,
            'pa-attendance-list',
        ]));
    }

    private function legacyPaDraft(array $scope): ?PaAttendanceListDraft
    {
        if (empty($scope['legacy_draft_hashes'])) {
            return null;
        }

        return PaAttendanceListDraft::query()
            ->whereIn('draft_hash', $scope['legacy_draft_hashes'])
            ->latest('updated_at')
            ->first();
    }

    private function sanitizePaDraftPayload(array $payload): array
    {
        $signatures = [];
        foreach (($payload['signatures'] ?? []) as $key => $value) {
            if (!is_string($key) || (!is_null($value) && !is_string($value))) {
                continue;
            }

            if ($value === null || $value === '') {
                $signatures[$key] = '';
                continue;
            }

            if (!Str::startsWith($value, 'data:image/png;base64,')) {
                continue;
            }

            $signatures[$key] = $value;
        }

        $baseSchedule = $this->sanitizePaDraftSchedule($payload);
        $sanitized = [
            'version' => (int) ($payload['version'] ?? 1) >= 2 ? 2 : 1,
            ...$baseSchedule,
            'signatures' => $signatures,
        ];

        if (array_key_exists('classSchedules', $payload)) {
            $classSchedules = [];

            foreach (($payload['classSchedules'] ?? []) as $className => $schedule) {
                $className = trim((string) $className);

                if ($className === '' || mb_strlen($className) > 100 || !is_array($schedule)) {
                    continue;
                }

                $classSchedules[$className] = $this->sanitizePaDraftSchedule($schedule);
            }

            $sanitized['classSchedules'] = $classSchedules;
        }

        return $sanitized;
    }

    private function sanitizePaDraftSchedule(array $schedule): array
    {
        $form = is_array($schedule['form'] ?? null) ? $schedule['form'] : [];
        $allowedFormKeys = [
            'exportFormat',
            'startDate',
            'endDate',
            'feedbackDate',
            'exportMode',
            'klasse',
            'listType',
            'includeSaturday',
            'includeSunday',
            'includeHolidays',
        ];

        return [
            'form' => array_intersect_key($form, array_flip($allowedFormKeys)),
            'days' => is_array($schedule['days'] ?? null) ? array_values($schedule['days']) : [],
            'selectedDayId' => is_string($schedule['selectedDayId'] ?? null)
                ? $schedule['selectedDayId']
                : null,
        ];
    }

    private function bibbDraftHash(?int $projektId, int $partnerId, string $schuljahr, string $teil): string
    {
        return hash('sha256', implode('|', [
            $projektId ?: 0,
            $partnerId,
            trim($schuljahr),
            $teil,
            'bibb-attendance-list',
        ]));
    }

    private function legacyBibbDraft(array $scope): ?BibbAttendanceListDraft
    {
        if (empty($scope['legacy_draft_hashes'])) {
            return null;
        }

        return BibbAttendanceListDraft::query()
            ->whereIn('draft_hash', $scope['legacy_draft_hashes'])
            ->latest('updated_at')
            ->first();
    }

    private function mergePaDraftPayload(array $existingPayload, array $incomingPayload): array
    {
        $payload = $existingPayload;

        foreach (['form', 'days', 'selectedDayId'] as $key) {
            if (array_key_exists($key, $incomingPayload)) {
                $payload[$key] = $incomingPayload[$key];
            }
        }

        $payload['version'] = max(
            (int) ($payload['version'] ?? 1),
            (int) ($incomingPayload['version'] ?? 1)
        );

        if (array_key_exists('classSchedules', $incomingPayload)) {
            $classSchedules = is_array($payload['classSchedules'] ?? null)
                ? $payload['classSchedules']
                : [];

            foreach ($incomingPayload['classSchedules'] as $className => $schedule) {
                $classSchedules[$className] = $schedule;
            }

            $payload['classSchedules'] = $classSchedules;
        }

        $signatures = is_array($payload['signatures'] ?? null) ? $payload['signatures'] : [];
        foreach (($incomingPayload['signatures'] ?? []) as $key => $value) {
            if ($value === '' || $value === null) {
                unset($signatures[$key]);
                continue;
            }

            $signatures[$key] = $value;
        }

        $payload['signatures'] = $signatures;

        return $payload;
    }

    /**
     * Verarbeitet ausschließlich die übermittelten Signaturänderungen. Bereits
     * gespeicherte Ciphertexte bleiben unverändert, damit große Entwürfe weder
     * unnötig kopiert noch versehentlich durch einen Teil-Request entfernt werden.
     *
     * @return array{0:array,1:array<string,array{previous:?string,current:?string}>}
     */
    private function mergeEncryptedPaDraftPayload(array $existingPayload, array $incomingPayload): array
    {
        $incomingSignatures = is_array($incomingPayload['signatures'] ?? null)
            ? $incomingPayload['signatures']
            : [];
        $incomingPayload['signatures'] = [];
        $payload = $this->mergePaDraftPayload($existingPayload, $incomingPayload);
        $signatures = is_array($existingPayload['signatures'] ?? null)
            ? $existingPayload['signatures']
            : [];
        $changes = [];

        foreach ($incomingSignatures as $key => $incomingValue) {
            if (!is_string($key)) {
                continue;
            }

            $storedValue = is_string($signatures[$key] ?? null)
                ? $signatures[$key]
                : null;
            $previousValue = $storedValue !== null
                ? $this->decryptPaSignature($storedValue)
                : null;
            $currentValue = is_string($incomingValue) && $incomingValue !== ''
                ? $incomingValue
                : null;

            if ($previousValue === $currentValue) {
                continue;
            }

            $changes[$key] = [
                'previous' => $previousValue,
                'current' => $currentValue,
            ];

            if ($currentValue === null) {
                unset($signatures[$key]);
            } else {
                $signatures[$key] = $this->encryptPaSignature($currentValue);
            }
        }

        $payload['signatures'] = $signatures;

        return [$payload, $changes];
    }

    private function paPreviewPayload(
        int $schuleId,
        string $schuljahr,
        string $teil,
        string $exportMode,
        ?string $klasse,
        array $inputDays,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $feedbackDate = null,
        string $listType = 'pa'
    ): array {
        $schule = Partner::findOrFail($schuleId);
        $teilnehmer = $this->paTeilnehmer($schuleId, $schuljahr, $teil, $exportMode, $klasse, $listType);
        $listType = $listType === 'pa_preparation' ? 'pa_preparation' : 'pa';

        if ($teilnehmer->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die Schule hat keine Teilnehmer für diese PA-Auswahl.',
            ]);
        }

        $participants = $teilnehmer->map(fn ($item) => $this->bibbTeilnehmerPayload($item))->values();
        $klasseText = $exportMode === 'klasse' && $klasse
            ? $klasse
            : $teilnehmer->pluck('klasse')->unique()->implode(', ');
        $schulform = PersonenIstSchueler::query()->schulform($teilnehmer);
        $inputDays = collect($inputDays)
            ->filter(fn ($day) => is_array($day) && !empty($day['date']))
            ->when($listType === 'pa_preparation', function ($days) {
                return $days
                    ->reject(fn ($day) => ($day['type'] ?? null) === 'feedback'
                        || ($day['source'] ?? null) === 'feedback'
                        || Str::startsWith((string) ($day['id'] ?? ''), 'feedback-'))
                    ->map(function ($day) {
                        $day['type'] = 'preparation';
                        $day['source'] = $day['source'] ?? 'pa-preparation';
                        $day['note'] = $day['note'] ?? 'Vorbereitung PA';

                        return $day;
                    });
            }, function ($days) {
                return $days->reject(fn ($day) => ($day['type'] ?? null) === 'feedback'
                    || ($day['source'] ?? null) === 'feedback'
                    || Str::startsWith((string) ($day['id'] ?? ''), 'feedback-'));
            })
            ->values()
            ->all();

        if (empty($inputDays) && $listType === 'pa_preparation' && $startDate) {
            $inputDays = $this->paPreparationDayFromDate($startDate);
        } elseif (empty($inputDays) && ($startDate || $endDate)) {
            $inputDays = $this->paDaysFromFixedDates($startDate, $endDate);
        }

        if ($listType !== 'pa_preparation' && $feedbackDate) {
            $date = Carbon::parse($feedbackDate)->toDateString();
            $inputDays[] = [
                'id' => 'feedback-' . $date,
                'date' => $date,
                'type' => 'feedback',
                'selected' => true,
                'source' => 'feedback',
                'note' => 'Feedbackgespräch',
            ];
        }

        $days = collect($inputDays)
            ->map(function ($day) use ($participants) {
                $date = Carbon::parse($day['date'])->toDateString();
                $type = match ($day['type'] ?? null) {
                    'feedback' => 'feedback',
                    'preparation' => 'preparation',
                    default => 'pa_day',
                };
                $typeLabel = match ($type) {
                    'feedback' => 'Feedbackgespräch',
                    'preparation' => 'Vorbereitung PA',
                    default => 'PA-Tag',
                };
                $idPrefix = match ($type) {
                    'feedback' => 'feedback-',
                    'preparation' => 'pa-vorbereitung-',
                    default => 'pa-',
                };

                return [
                    'id' => $day['id'] ?? $idPrefix . $date,
                    'date' => $date,
                    'date_label' => Carbon::parse($date)->format('d.m.Y'),
                    'type' => $type,
                    'type_label' => $typeLabel,
                    'source' => $day['source'] ?? ($type === 'feedback' ? 'feedback' : ($type === 'preparation' ? 'pa-preparation' : 'manual')),
                    'selected' => $day['selected'] ?? true,
                    'note' => $day['note'] ?? null,
                    'groups' => [[
                        'id' => 'pa-all-' . $date,
                        'label' => 'Alle Teilnehmer',
                        'bereich' => null,
                        'runde' => null,
                        'participants' => $participants,
                        'participants_count' => $participants->count(),
                    ]],
                    'participants_count' => $participants->count(),
                ];
            })
            ->unique(fn ($day) => $day['date'] . '|' . $day['type'])
            ->sortBy(fn ($day) => ($day['type'] === 'feedback' ? '9999-99-99' : $day['date']) . '|' . $day['date'])
            ->values();

        return [
            'context' => [
                'schule' => [
                    'id' => $schule->id,
                    'name' => $schule->name,
                ],
                'schulform' => $schulform,
                'klasse' => $klasseText,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
                'list_type' => $listType,
                'export_mode' => $exportMode,
                'teilnehmer_count' => $participants->count(),
            ],
            'participants' => $participants,
            'days' => $days,
        ];
    }

    private function paDaysFromFixedDates(
        ?string $startDate,
        ?string $endDate
    ): array {
        return collect([
            ['id' => 'pa-tag-1', 'date' => $startDate, 'note' => 'PA-Tag 1'],
            ['id' => 'pa-tag-2', 'date' => $endDate, 'note' => 'PA-Tag 2'],
        ])
            ->filter(fn ($day) => filled($day['date']))
            ->map(function ($day) {
                $date = Carbon::parse($day['date'])->toDateString();

                return [
                    'id' => $day['id'] . '-' . $date,
                    'date' => $date,
                    'type' => 'pa_day',
                    'selected' => true,
                    'source' => 'pa-term',
                    'note' => $day['note'],
                ];
            })
            ->values()
            ->all();
    }

    private function paPreparationDayFromDate(?string $date): array
    {
        if (!filled($date)) {
            return [];
        }

        $date = Carbon::parse($date)->toDateString();

        return [[
            'id' => 'pa-vorbereitung-' . $date,
            'date' => $date,
            'type' => 'preparation',
            'selected' => true,
            'source' => 'pa-preparation',
            'note' => 'Vorbereitung PA',
        ]];
    }

    private function paTeilnehmer(
        int $schuleId,
        string $schuljahr,
        string $teil,
        string $exportMode,
        ?string $klasse,
        string $listType = 'pa',
        ?int $exportProjectId = null
    ): Collection {
        return PersonenIstSchueler::query()
            ->forExportProject($exportProjectId)
            ->filterSchueler($schuleId, $schuljahr, $teil)
            ->when($exportMode === 'klasse', fn ($query) => $query->where('klasse', $klasse))
            ->with('person')
            ->get()
            ->sort(function ($a, $b) {
                $klasseCompare = strnatcasecmp((string) $a->klasse, (string) $b->klasse);
                if ($klasseCompare !== 0) {
                    return $klasseCompare;
                }

                $nachnameCompare = strnatcasecmp((string) ($a->person?->nachname ?? ''), (string) ($b->person?->nachname ?? ''));
                if ($nachnameCompare !== 0) {
                    return $nachnameCompare;
                }

                return strnatcasecmp((string) ($a->person?->vorname ?? ''), (string) ($b->person?->vorname ?? ''));
            })
            ->values();
    }

    public function anwesenheitslistePAexportWord(Request $request)
    {

          $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'schuleId' => 'required|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'exportMode' => 'nullable|in:alle,klasse',
            'klasse' => 'nullable|required_if:exportMode,klasse|string',
        ]);

        $validated['exportMode'] = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');
        $projectId = $this->ensurePartnerInActiveProject((int) $validated['schuleId']);
        $template = Dokumente::query()
            ->where('export_permission', 'anwesenheit.abrechnung')
            ->where('typ', 'word')
            ->where(function ($query) use ($projectId) {
                $query->whereHas('projekte', fn ($project) => $project->whereKey($projectId))
                    ->orWhereHas('kategorien.projekte', fn ($project) => $project->whereKey($projectId));
            })
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'vorlage' => 'Dem aktiven BOP-Projekt ist keine Vorlage „Anwesenheitsliste PA“ zugeordnet.',
            ]);
        }
        if (! $template->aktiv) {
            throw ValidationException::withMessages([
                'vorlage' => 'Die Vorlage „Anwesenheitsliste PA“ ist deaktiviert.',
            ]);
        }
        if (! in_array('docx', $template->ausgabeformate ?? [], true) || ! $template->dateipfad) {
            throw ValidationException::withMessages([
                'vorlage' => 'Die Vorlage „Anwesenheitsliste PA“ muss eine Word-Vorlage mit DOCX-Ausgabe sein.',
            ]);
        }

        $templateFile = storage_path(ltrim($template->dateipfad, '/\\'));
        if (! is_file($templateFile)) {
            throw ValidationException::withMessages([
                'vorlage' => 'Die Datei der Vorlage „Anwesenheitsliste PA“ wurde nicht gefunden.',
            ]);
        }


            $alle_teilnehmer = PersonenIstSchueler::query()
            ->filterSchueler($validated['schuleId'], $validated['schuljahr'], $validated['teil'])
            ->when(($validated['exportMode'] ?? 'klasse') === 'klasse', fn ($query) => $query->where('klasse', $validated['klasse']))
            ->with('person')
            ->get()
            ->sortBy(fn($item) => $item->person->nachname);


            $schule = Partner::findOrFail($request->schuleId);


            if(!$schule){
                return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.');
            }
            if($alle_teilnehmer->isEmpty()){
                return redirect()->back()->with('error', 'Die Schule hat keine Teilnehmer.');
            }

            $zuGrosseKlasse = $alle_teilnehmer
                ->groupBy(fn ($teilnehmer) => (string) ($teilnehmer->klasse ?: 'ohne Klasse'))
                ->first(fn ($teilnehmerListe) => $teilnehmerListe->count() > 34);

            if ($zuGrosseKlasse) {
                return redirect()->back()->with('error', 'Die PA-Anwesenheitsliste unterstützt maximal 34 Teilnehmer/-innen pro Klasse.');
            }

            $options = $request->only(['includeSaturday', 'includeSunday', 'includeHolidays']);
            $tag1 = $this->attendanceDateAllowed($request->startDate, $options) ? Carbon::parse($request->startDate)->format('d.m.Y') : '';
            $tag2 = $this->attendanceDateAllowed($request->endDate, $options) ? Carbon::parse($request->endDate)->format('d.m.Y') : '';
            abort_if($tag1 === '' && $tag2 === '', 422, 'Keine Arbeitstage ausgewählt. Wochenenden und Feiertage sind standardmäßig ausgeschlossen.');

            $createDocument = function ($teilnehmerListe, string $klasseName, string $exportPath) use ($templateFile, $schule, $tag1, $tag2) {
                $i = 1;
                $templateProcessor = new TemplateProcessor($templateFile);

                $templateProcessor->setValue('schule', $schule->name);
                $templateProcessor->setValue('schulform', PersonenIstSchueler::query()->schulform($teilnehmerListe));
                $templateProcessor->setValue('klasse', $klasseName);
                $templateProcessor->setValue('tag1', $tag1);
                $templateProcessor->setValue('tag2', $tag2);

                foreach ($teilnehmerListe as $teilnehmer) {
                    $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                    $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);
                    $i++;
                }

                while($i<=34){
                    $templateProcessor->setValue('nachname' . $i, '');
                    $templateProcessor->setValue('vorname' . $i, '');
                    $i++;
                }

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                $this->attendanceFooter->applyToWordDocument($exportPath);
            };

            if (($validated['exportMode'] ?? 'klasse') === 'alle') {
                $exportDir = storage_path('exports/pa_' . Str::uuid());
                File::ensureDirectoryExists($exportDir);

                $zipPath = storage_path('exports/Anwesenheitslisten_PA_' . $schule->name . '_' . $tag1 . '_' . $tag2 . '_' . date('Ymd_His') . '.zip');
                File::ensureDirectoryExists(dirname($zipPath));
                $zip = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['message' => 'ZIP-Datei konnte nicht erstellt werden.'], 500);
                }

                foreach ($alle_teilnehmer->groupBy('klasse') as $klasseName => $teilnehmerListe) {
                    $klasseName = (string) ($klasseName ?: 'ohne_Klasse');
                    $filename = 'Anwesenheitsliste_PA_' . Str::slug($schule->name, '_') . '_' . Str::slug($klasseName, '_') . '_' . date('Ymd_His') . '.docx';
                    $exportPath = $exportDir . DIRECTORY_SEPARATOR . $filename;

                    $createDocument($teilnehmerListe->sortBy(fn($item) => $item->person->nachname), $klasseName, $exportPath);
                    $zip->addFile($exportPath, $filename);
                }

                $zip->close();
                File::deleteDirectory($exportDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            $i = 1;
            $templateProcessor = new TemplateProcessor($templateFile);

            // Einfügen der Daten in die Textfelder

            $templateProcessor->setValue('schule', $schule->name);

            $schulform = PersonenIstSchueler::query()->schulform($alle_teilnehmer);

            $templateProcessor->setValue('schulform', $schulform);

            $templateProcessor->setValue('klasse', $request->klasse);
            $templateProcessor->setValue('tag1', $tag1);
            $templateProcessor->setValue('tag2', $tag2);

            foreach ($alle_teilnehmer as $teilnehmer)
            {
                // Initialisieren Sie den TemplateProcessor für jede Schleifeniteration

                $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);

                $i++;

                // Speichern der individuellen Briefe


            }
            while($i<=34){
                $templateProcessor->setValue('nachname' . $i, '');
                $templateProcessor->setValue('vorname' . $i, '');
                $i++;
            }
                $filename = 'Anwesenheitsliste_PA_' . $schule->name . '_' . $request->klasse . '_' . $tag1 . '_' . $tag2 . '_'  . date('Ymd_His') . '.docx';
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                $this->attendanceFooter->applyToWordDocument($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePOBOTag1(Request $request, $partnerID, $schuljahr, $teil, $klasse = 'exportAlleKlassen', ?int $exportProjectId = null)
    {
        $projectId = $this->ensurePartnerInActiveProject((int) $partnerID);
        $dialog = $exportProjectId === null && $request->boolean('dialog');
        $usePlanning = $dialog || $request->boolean('usePlanning');
        $preview = $dialog && $request->boolean('preview');

        // Query Parameter
        $anzahlBereiche = $request->query('anzahlBereiche', 6);
        $anzahlRaeumlichkeiten = $request->query('anzahlRaeumlichkeiten', $anzahlBereiche);
        $kapazitaeten = $request->query('kapazitaeten', []);
        $termin = $request->query('termin', date('Y-m-d')) ;
        if (! $preview) {
            $request->validate(['termin' => [$dialog ? 'required' : 'sometimes', 'date_format:Y-m-d']]);
            abort_unless($this->attendanceDateAllowed($termin), 422, 'Wochenenden und Feiertage sind für diese Anwesenheitsliste ausgeschlossen.');
        }

        // Prüfen
        if (!$partnerID || !$schuljahr || !$teil || !$termin ){
            abort(422, 'Für den Rolltag-Export fehlen Angaben.');
        }

        $schule = Partner::findOrFail($partnerID);
        $classExport = ! in_array($klasse, ['exportAlleKlassen', 'exportAlleKlassenZip'], true);
        $planning = app(PackagePlanning::class);
        $phase = $usePlanning ? $planning->phase($projectId, $schule->id, $schuljahr, $teil, 'roll_day') : null;
        $plannedDates = collect($planning->dates($phase, $projectId, $schule->id, $schuljahr, $teil));
        $classDates = collect($phase?->class_date_assignments ?? []);
        $participants = $phase?->participants ?? collect();
        $plannedGroups = $participants->filter(fn ($participant) => filled($participant->group_key))
            ->pluck('group_key', 'personen_ist_schueler_id');

        // Teilnehmer laden
        $alleTeilnehmer = PersonenIstSchueler::forExportProject($exportProjectId ?? $projectId)->where('schule_id', $schule->id)
            ->when($request->attributes->get('bop_package_student_ids') !== null, fn ($query) => $query->whereIn('id', $request->attributes->get('bop_package_student_ids')))
            ->forSchuljahr($schuljahr)
            ->where('teil', $teil)
            ->when($classExport, fn ($query) => $query->where('klasse', $klasse))
            ->when($participants->isNotEmpty() || $phase?->scope_type === 'participants',
                fn ($query) => $query->whereIn('id', $participants->pluck('personen_ist_schueler_id')))
            ->when($phase?->scope_type === 'classes', fn ($query) => $query->whereIn('klasse', $phase->selected_classes ?? []))
            ->whereHas('person')
            ->with('person')
            ->orderBy('id')
            ->get();

        $datesForStudent = fn ($student) => $classDates->isEmpty() ? $plannedDates->all() : ($classDates->get($student->klasse) ?? []);
        if ($preview) {
            $classes = $alleTeilnehmer->pluck('klasse')->filter()->unique()->sort(SORT_NATURAL)->values();
            return response()->json([
                'classes' => $classes,
                'count' => $alleTeilnehmer->unique('person_id')->count(),
                'counts_by_date' => $plannedDates->mapWithKeys(fn ($date) => [
                    $date => $alleTeilnehmer->filter(fn ($student) => in_array($date, $datesForStudent($student), true))
                        ->unique('person_id')->count(),
                ]),
                'dates' => $plannedDates,
                'has_schedule' => ! empty($phase?->dates),
                'class_dates' => $classes->mapWithKeys(fn ($class) => [
                    $class => $classDates->isEmpty() ? $plannedDates->all() : ($classDates->get($class) ?? []),
                ]),
                'has_groups' => $alleTeilnehmer->contains(fn ($student) => $plannedGroups->has($student->id)),
            ]);
        }
        if (! empty($phase?->dates)) {
            abort_unless($plannedDates->contains($termin), 422, 'Bitte wählen Sie einen geplanten Rolltag-Termin.');
            $alleTeilnehmer = $alleTeilnehmer->filter(fn ($student) => in_array($termin, $datesForStudent($student), true));
        }
        $alleTeilnehmer = PersonenIstSchueler::sortForPackage(
            $alleTeilnehmer->unique('person_id'),
            $request->query('sortierung', 'klasse')
        );

        if ($alleTeilnehmer->isEmpty()) {
            abort(422, 'Für diese Auswahl wurden keine Teilnehmer gefunden.');
        }

        $usePlannedGroups = $usePlanning && $plannedGroups->isNotEmpty();
        $templates = app(RolltagAttendanceTemplateResolver::class);
        $templateFile = ($classExport || $klasse === 'exportAlleKlassenZip')
            ? $templates->classTemplate($projectId)
            : $templates->groupTemplate($projectId);

        /*
        |--------------------------------------------------------------------------
        | 🟢 FALL 1: EINZELNE KLASSE
        |--------------------------------------------------------------------------
        */
        if ($classExport) {

            $spreadsheet = IOFactory::load($templateFile);
            $this->attendanceFooter->applyToSpreadsheet($spreadsheet);
            $sheet = $spreadsheet->getActiveSheet();

            $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

            $sheet->setCellValue('H5', $terminDatum);
            $sheet->setCellValue('C2', "Rolltag - Klasse $klasse - " . $schule->name);

            $row = 8;

            foreach ($alleTeilnehmer as $t) {
                $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                $sheet->setCellValue('D' . $row, $t->klasse);
                $sheet->setCellValue('E' . $row, $t->geschlecht);
                $row++;
            }

            $filename = 'Rolltag_'.(Str::slug($klasse, '.') ?: 'Klasse').'.xlsx';
            $filePath = storage_path(Str::uuid().'_'.$filename);
            (new Xlsx($spreadsheet))->save($filePath);

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);
        }
        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 2: ALLE KLASSEN → ZIP mit Klassenlisten
        |--------------------------------------------------------------------------
        */
        if ($klasse === 'exportAlleKlassenZip') {

            // 👉 Teilnehmer nach Klassen gruppieren
            $gruppenNachKlassen = $alleTeilnehmer->groupBy('klasse')->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

            // 👉 Temp Ordner
            $tempDir = storage_path('temp_excel_'.Str::uuid());
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

            array_map('unlink', glob($tempDir . '/*'));

            $dateien = [];

            foreach ($gruppenNachKlassen as $klassenName => $teilnehmerListe) {

                $spreadsheet = IOFactory::load($templateFile);
                $this->attendanceFooter->applyToSpreadsheet($spreadsheet);
                $sheet = $spreadsheet->getActiveSheet();

                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                // Kopf
                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Rolltag - Klasse $klassenName - ".$schule->name);

                // Teilnehmer eintragen
                $row = 8;

                foreach ($teilnehmerListe as $t) {
                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                // Datei speichern
                $fileName = 'Rolltag_'.Str::slug((string) $klassenName, '_').'_'.(count($dateien) + 1).'.xlsx';
                $filePath = $tempDir . '/' . $fileName;

                (new Xlsx($spreadsheet))->save($filePath);

                $dateien[] = $filePath;
            }

            // 👉 ZIP erstellen
            $zipFileName = storage_path('Rolltag_Klassen_'.Str::uuid().'.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

                foreach ($dateien as $file) {
                    $zip->addFile($file, basename($file));
                }

                $closed = $zip->close();

                // Temp löschen
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                abort_unless($closed, 500, 'Die ZIP-Datei konnte nicht gespeichert werden.');
                return response()->download($zipFileName)->deleteFileAfterSend(true);
            }

            File::deleteDirectory($tempDir);
            abort(500, 'Die ZIP-Datei konnte nicht erstellt werden.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 3: Benutzerdefinierte Räume mit Namen und Kapazitäten
        |--------------------------------------------------------------------------
        */
        if ($usePlannedGroups || ($request->has('anzahlRaeumlichkeiten') && $request->has('raumNamen'))) {
            $groups = collect();
            if ($usePlannedGroups) {
                $groups = $alleTeilnehmer
                    ->groupBy(fn ($student) => $plannedGroups->get($student->id, 'Ohne Einteilung'))
                    ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
            } else {
                $data = $request->validate([
                    'anzahlRaeumlichkeiten' => ['required', 'integer', 'min:1', 'max:50'],
                    'raumNamen' => ['required', 'array', 'list', 'size:'.$anzahlRaeumlichkeiten],
                    'raumNamen.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
                    'kapazitaeten' => ['required', 'array', 'list', 'size:'.$anzahlRaeumlichkeiten],
                    'kapazitaeten.*' => ['required', 'integer', 'min:1'],
                ]);
                abort_if(array_sum($data['kapazitaeten']) < $alleTeilnehmer->count(), 422,
                    'Die eingegebenen Kapazitäten reichen nicht für alle Schüler aus.');

                $offset = 0;
                foreach ($data['raumNamen'] as $index => $name) {
                    $capacity = (int) $data['kapazitaeten'][$index];
                    $groups->put($name, $alleTeilnehmer->slice($offset, $capacity));
                    $offset += $capacity;
                }
            }
            return $this->rolltagRoomZip($groups, $templateFile, $schule->name, $termin);
        }

        abort_if($dialog, 422, 'Bitte geben Sie Räume und Kapazitäten an.');

         /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 4: ALLE KLASSEN gemischt sortiert nach nachname für alle Bereiche
        |--------------------------------------------------------------------------
        */
            $anzahlTeilnehmer = $alleTeilnehmer->count();
            $alleTeilnehmer = $alleTeilnehmer
            ->sortBy(fn($t) => strtolower($t->person->nachname), SORT_NATURAL)
            ->values();
            // Temp Ordner
            abort_if($anzahlRaeumlichkeiten < 1, 422, 'Bitte geben Sie mindestens einen Raum an.');
            $tempDir = storage_path('temp_excel_'.Str::uuid());
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            array_map('unlink', glob($tempDir . '/*'));

            // Kapazitäten berechnen (wenn leer)
            if (!$kapazitaeten || count($kapazitaeten) != $anzahlRaeumlichkeiten) {
                $grundzahl = intdiv($anzahlTeilnehmer, $anzahlRaeumlichkeiten);
                $rest = $anzahlTeilnehmer % $anzahlRaeumlichkeiten;

                $kapazitaeten = [];
                for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                    $kapazitaeten[$i] = $grundzahl + ($i < $rest ? 1 : 0);
                }
            }

            // Bereiche laden
            $projekt = Projekt::with('bereiche')
                ->where('id', $projectId)
                ->first();

            // Als Array von Bereichsnamen
            $bereicheListe = $projekt->bereiche->pluck('name')->toArray();
            $bereiche = array_slice($bereicheListe, 0, $anzahlBereiche) ?: ['Rolltag'];

            $startIndex = 0;
            $gruppenListe = [];
            for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {

                $spreadsheet = IOFactory::load($templateFile);
                $this->attendanceFooter->applyToSpreadsheet($spreadsheet);
                $sheet = $spreadsheet->getActiveSheet();

                $bereichNameOriginal = $bereiche[$i % count($bereiche)];
                $anzahlAktuell = $kapazitaeten[$i];

                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                // Dateisicheren Bereichsnamen erstellen
                $bereich = Str::slug($bereichNameOriginal, '_');

                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Gruppe " . ($i + 1) . " - $bereich - " . $schule->name);

                $row = 8;

                for ($j = $startIndex; $j < $startIndex + $anzahlAktuell && $j < $anzahlTeilnehmer; $j++) {
                    $t = $alleTeilnehmer[$j];

                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;

                    $gruppenListe[] = [
                        'gruppe' => $i + 1,
                        'bereich' => $bereich,
                        'name' => $t->person->nachname . ', ' . $t->person->vorname
                    ];
                }

                $startIndex += $anzahlAktuell;

                // Excel speichern
                (new Xlsx($spreadsheet))->save($tempDir . "/Liste_" . ($i + 1) . ".xlsx");
            }

            // ZIP erstellen
            $zipPath = storage_path('Anwesenheitslisten_'.Str::uuid().'.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                foreach (glob($tempDir . '/*.xlsx') as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                // Cleanup
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipPath, 'Anwesenheitslisten.zip')->deleteFileAfterSend(true);
            }

            File::deleteDirectory($tempDir);
            abort(500, 'Die ZIP-Datei konnte nicht erstellt werden.');
    }

    private function rolltagRoomZip(Collection $groups, string $template, string $school, string $date)
    {
        $path = storage_path('Rolltag_Gruppen_'.Str::uuid().'.zip');
        $zip = new ZipArchive();
        abort_unless($zip->open($path, ZipArchive::CREATE) === true, 500, 'Die ZIP-Datei konnte nicht erstellt werden.');

        try {
            $overview = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $overviewSheet = $overview->getActiveSheet();
            $overviewSheet->setTitle('Einteilung');
            $formattedDate = Carbon::parse($date)->format('d.m.Y');
            $overviewSheet->fromArray(['Rolltag', $formattedDate]);
            $overviewSheet->setCellValueExplicit('C1', $school, DataType::TYPE_STRING);
            $overviewSheet->fromArray(['Gruppe / Raum', 'Klasse', 'Name'], null, 'A3');
            $overviewRow = 4;
            $index = 0;

            foreach ($groups as $name => $students) {
                $list = IOFactory::load($template);
                $this->attendanceFooter->applyToSpreadsheet($list);
                $sheet = $list->getActiveSheet();
                $sheet->setCellValue('H5', $formattedDate);
                $sheet->setCellValue('C2', "Rolltag - $name - $school");
                $row = 8;

                foreach ($students as $student) {
                    $fullName = $student->person->nachname.', '.$student->person->vorname;
                    $sheet->setCellValueExplicit('B'.$row, $fullName, DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D'.$row, (string) $student->klasse, DataType::TYPE_STRING);
                    $sheet->setCellValue('E'.$row, $student->geschlecht);
                    $row++;
                    $overviewSheet->setCellValueExplicit('A'.$overviewRow, (string) $name, DataType::TYPE_STRING);
                    $overviewSheet->setCellValueExplicit('B'.$overviewRow, (string) $student->klasse, DataType::TYPE_STRING);
                    $overviewSheet->setCellValueExplicit('C'.$overviewRow, $fullName, DataType::TYPE_STRING);
                    $overviewRow++;
                }

                $filename = 'Anwesenheitsliste_'.(++$index).'_'.Str::slug((string) $name, '_').'.xlsx';
                $this->addRolltagWorkbook($zip, $filename, $list);
            }

            foreach (['A', 'B', 'C'] as $column) {
                $overviewSheet->getColumnDimension($column)->setAutoSize(true);
            }
            $this->addRolltagWorkbook($zip, 'Einteilung.xlsx', $overview);
            abort_unless($zip->close(), 500, 'Die ZIP-Datei konnte nicht gespeichert werden.');
        } catch (\Throwable $error) {
            if (isset($zip->filename)) {
                $zip->close();
            }
            File::delete($path);
            throw $error;
        }

        return response()->download($path, 'Rolltag_Gruppen.zip')->deleteFileAfterSend(true);
    }

    private function addRolltagWorkbook(ZipArchive $zip, string $filename, \PhpOffice\PhpSpreadsheet\Spreadsheet $workbook): void
    {
        $file = tempnam(storage_path(), 'rolltag_');
        abort_if($file === false, 500, 'Die Excel-Datei konnte nicht erstellt werden.');

        try {
            (new Xlsx($workbook))->save($file);
            $contents = file_get_contents($file);
            abort_unless($contents !== false && $zip->addFromString($filename, $contents), 500,
                'Die Excel-Datei konnte nicht zum ZIP hinzugefügt werden.');
        } finally {
            File::delete($file);
            $workbook->disconnectWorksheets();
        }
    }


    public function hausordnungExportPdf($partnerID, $schuljahr, $teil, $sortBy, $termin, ?int $exportProjectId = null, ?array $plannedStudentIds = null)
    {
        $this->ensurePartnerInActiveProject((int) $partnerID);
        $schule = Partner::findOrFail($partnerID);
            if (!$schule)
            {
                return redirect()->route('partner.index')->with('error', 'Die gewählte Schule konnte nicht gefunden werden.');
            }
            if($sortBy != 'nachname' && $sortBy != 'klasse' ){
                return redirect()->route('partner.index')->with('error', 'Bitte wählen Sie einen Sortierungstyp vor dem Export aus.');
            }
        // Daten aus der Tabelle abrufen
       if($sortBy == 'nachname'){
             $alle_teilnehmer = PersonenIstSchueler::forExportProject($exportProjectId)->where('schule_id', $partnerID)
            ->forSchuljahr($schuljahr)
                ->where('teil', $teil)
                ->with('person')
                ->get()
                ->sortBy(fn($t) => strtolower($t->person->nachname), SORT_NATURAL);


       }elseif($sortBy == 'klasse'){
           $alle_teilnehmer = PersonenIstSchueler::forExportProject($exportProjectId)->where('schule_id', $partnerID)
            ->forSchuljahr($schuljahr)
            ->where('teil', $teil)
            ->with('person')
            ->get()
            ->sort(function($a, $b) {
                // Zuerst Klasse, natürlich sortiert
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) {
                    return $klasseCompare;
                }

                // Dann Nachname, natürlich sortiert
                return strnatcasecmp($a->person->nachname, $b->person->nachname);
            });
        }
        if ($plannedStudentIds !== null) $alle_teilnehmer = $alle_teilnehmer->whereIn('id', $plannedStudentIds);
        if($alle_teilnehmer->isEmpty()){
            return redirect()->back()->with('error', 'Die Schule verfügt derzeit keine Teilnehmer.');
        }

        $data = [
            'alle_teilnehmer' => $alle_teilnehmer,
            'datum' => $termin,
        ];

       $pdf = Pdf::loadView('pdf.hausordnung',  $data);
        return $pdf->stream('invoice.pdf');
    }


    public function bereichsauswahl($partnerId, $schuljahr, $teil)
    {
        $projektId = $this->ensurePartnerInActiveProject((int) $partnerId);
        $projekt = Projekt::with('bereiche', 'partners')->findOrFail($projektId);
        $partner = Partner::findOrFail($partnerId);
        $setting = $this->settingFor($projekt->id, (int) $partnerId, $schuljahr, $teil, $projekt);

        $alle_teilnehmer = PersonenIstSchueler::with(['bereichsauswahl', 'person'])
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->get()
            ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();

        $this->ensureAccessCodes($alle_teilnehmer, auth()->id());
        $alle_teilnehmer->load('bereichsauswahl');

        $publicUrl = route('bereichsauswahl.self.show', $setting->public_token);
        $areaService = app(\App\Services\Bop\SelectableAreas::class);
        $availableAreas = $areaService->forSetting($projekt, $setting);

        return Inertia::render('Bereichsauswahl/Index', [
            'verfuegbare_bereiche' => $availableAreas,
            'waehlbare_projektbereiche' => $areaService->eligible($projekt),
            'bereich_warnungen' => $areaService->selectionWarnings($alle_teilnehmer, $availableAreas),
            'projekt' => $projekt,
            'alle_teilnehmer' => $alle_teilnehmer,
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
            ],
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'setting' => [
                'id' => $setting->id,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
                'zugang_aktiv' => $setting->zugang_aktiv,
                'public_url' => $publicUrl,
                'qr_svg' => $this->qrSvg($publicUrl),
            ],
        ]);
    }

    public function bereichsauswahlSettingUpdate(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'auswahl_anzahl' => ['required', 'integer', 'min:2', 'max:4'],
            'bereich_ids' => ['sometimes', 'array', 'min:2'],
            'bereich_ids.*' => ['required', 'integer', 'distinct'],
            'zugang_aktiv' => ['nullable', 'boolean'],
        ]);

        $projektId = $this->ensurePartnerInActiveProject((int) $validated['partner_id']);
        $projekt = Projekt::with('bereiche')->findOrFail($projektId);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $validated['partner_id'],
            $validated['schuljahr'],
            $validated['teil'],
            $projekt
        );

        $selectionCount = $this->normalizeAuswahlAnzahl((int) $validated['auswahl_anzahl']);
        $areaService = app(\App\Services\Bop\SelectableAreas::class);
        $areaIds = $request->has('bereich_ids')
            ? collect($validated['bereich_ids'])->map(fn ($id) => (int) $id)->values()
            : $areaService->forSetting($projekt, $setting)->pluck('id');
        if ($areaIds->diff($areaService->eligible($projekt)->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'bereich_ids' => 'Bitte nur wählbare Berufsbereiche dieses Projekts auswählen. PA und Rolltag sind nicht wählbar.',
            ]);
        }
        if ($areaIds->count() < $selectionCount) {
            throw ValidationException::withMessages([
                'bereich_ids' => 'Es müssen mindestens so viele Berufsbereiche verfügbar sein wie Wahlfelder.',
            ]);
        }

        $setting->update([
            ...($request->has('bereich_ids') ? ['bereich_ids' => $areaIds->all()] : []),
            'auswahl_anzahl' => $selectionCount,
            'zugang_aktiv' => $request->boolean('zugang_aktiv', true),
            'user_update' => auth()->id(),
        ]);

        $teilnehmerIds = PersonenIstSchueler::query()
            ->where('schule_id', $validated['partner_id'])
            ->forSchuljahr($validated['schuljahr'])
            ->where('teil', $validated['teil'])
            ->pluck('id');

        $clearFields = collect([3, 4])
            ->filter(fn ($field) => $field > $selectionCount)
            ->mapWithKeys(fn ($field) => ['bereich_id' . $field => null])
            ->all();

        if ($clearFields) {
            Bereichsauswahl::whereIn('teilnehmer_id', $teilnehmerIds)->update($clearFields);
        }

        return response()->json([
            'success' => true,
            'setting' => [
                'id' => $setting->id,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
                'zugang_aktiv' => $setting->zugang_aktiv,
            ],
        ]);
    }

    public function waehlen(Request $request)
    {
        $request->validate([
            'teilnehmer_id' => ['required', 'integer', 'exists:personen_ist_schuelers,id'],
        ]);

        $teilnehmer = PersonenIstSchueler::where('id', $request->teilnehmer_id)
            ->with(['bereichsauswahl', 'person'])
            ->firstOrFail();

        $projektId = $this->ensurePartnerInActiveProject((int) $teilnehmer->schule_id);
        $projekt = Projekt::with('bereiche')->findOrFail($projektId);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $teilnehmer->schule_id,
            $teilnehmer->schuljahr,
            $teilnehmer->teil,
            $projekt
        );
        $choices = $this->validatedChoices($request, $setting, $this->allowedBereichIds($projekt, $setting));

        $wahl = $teilnehmer->bereichsauswahl;
        $hasSavedChoices = collect([1, 2, 3, 4])->contains(
            fn (int $field) => $wahl?->{'bereich_id' . $field} !== null
        );
        $requiredPermission = $hasSavedChoices ? 'bereichsauswahl.update' : 'bereichsauswahl.store';
        abort_unless(auth()->user()?->can($requiredPermission), 403);

        if (!$wahl) {
            $wahl = Bereichsauswahl::create([
                'teilnehmer_id' => $request->teilnehmer_id,
                'access_code' => $this->accessCode(),
                'user_create' => auth()->user()->id,
            ]);
        }

        $this->persistChoices($wahl, $choices, auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Bereichsauswahl aktualisiert.',
            'choices' => $choices,
            'access_code' => $wahl->access_code,
        ]);
    }

    public function bereichsauswahlSelfShow(string $token)
    {
        $setting = $this->publicAreaSelection->activeSetting($token, ['partner', 'projekt.bereiche']);

        return Inertia::render('Bereichsauswahl/Selbstwahl', [
            'context' => [
                'schule' => $setting->partner?->name,
                'schuljahr' => $setting->schuljahr,
                'teil' => $setting->teil,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
            ],
            'bereiche' => app(\App\Services\Bop\SelectableAreas::class)->forSetting($setting->projekt, $setting),
            'token' => $token,
        ]);
    }

    public function bereichsauswahlSelfThanks(string $token)
    {
        $setting = $this->publicAreaSelection->activeSetting($token, ['partner']);

        return Inertia::render('Bereichsauswahl/Danke', [
            'context' => [
                'schule' => $setting->partner?->name,
                'schuljahr' => $setting->schuljahr,
                'teil' => $setting->teil,
            ],
        ]);
    }

    public function bereichsauswahlSelfVerify(Request $request, string $token)
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:20'],
        ]);

        $setting = $this->publicAreaSelection->activeSetting($token);

        $code = $this->normalizeAccessCodeInput($request->input('access_code'));
        $teilnehmer = $this->teilnehmerForCode($setting, $code);

        if (!$teilnehmer) {
            throw ValidationException::withMessages([
                'access_code' => 'Der Code wurde nicht gefunden.',
            ]);
        }

        return response()->json([
            'success' => true,
            'teilnehmer' => $this->formatSelfTeilnehmer($teilnehmer, $setting),
        ]);
    }

    public function bereichsauswahlSelfStore(Request $request, string $token)
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:20'],
        ]);

        $setting = $this->publicAreaSelection->activeSetting($token, ['projekt.bereiche']);

        $code = $this->normalizeAccessCodeInput($request->input('access_code'));
        $teilnehmer = $this->teilnehmerForCode($setting, $code);

        if (!$teilnehmer || !$teilnehmer->bereichsauswahl) {
            throw ValidationException::withMessages([
                'access_code' => 'Der Code wurde nicht gefunden.',
            ]);
        }

        $choices = $this->validatedChoices(
            $request,
            $setting,
            $this->allowedBereichIds($setting->projekt, $setting)
        );

        $this->persistChoices($teilnehmer->bereichsauswahl, $choices, null, true);
        $teilnehmer->load('bereichsauswahl');

        return response()->json([
            'success' => true,
            'message' => 'Deine Bereichsauswahl wurde gespeichert.',
            'teilnehmer' => $this->formatSelfTeilnehmer($teilnehmer, $setting),
            'redirect_url' => route('bereichsauswahl.self.thanks', $token),
        ]);
    }


    public function generatePdfauswertungsbogenPASchule($partnerId, $schuljahr, $teil)
    {
            $this->ensurePartnerInActiveProject((int) $partnerId);
            $schule = Partner::findOrFail($partnerId);
            if (!$schule)
            {
                return redirect()->route('schule.index')->with('error', 'Die gewählte Schule konnte nicht gefunden werden.');
            }

            // Daten aus der Tabelle abrufen
            $alle_teilnehmer = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->get()
            ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();

            if ($alle_teilnehmer->isEmpty())
            {
                return redirect()->back()->with('error', 'Die Schule: ' . $schule->name . ' verfügt über keine Teilnehmer.');
            }

            $data = [
                'alle_teilnehmer' => $alle_teilnehmer,
                'schulname' => $schule->name,
            ];


            $pdf = Pdf::loadView('pdf.auswertungsbogenPA',  $data);
            $pdf->setPaper('A4', 'landscape');
            return $pdf->stream('Auswertungbogen_PA_' . $schule->name. '_' . $schuljahr  . '_Teil_' . $teil .'.pdf');
    }

    public function generatePdfAuswertungsbogenPaRolandSchule($partnerId, $schuljahr, $teil, $klasse = null)
    {
        // Der Export wird in kleinen Bloecken gerendert und danach zusammengefuegt.
        // So bleibt der Speicherverbrauch auch bei vielen Teilnehmenden begrenzt.
        $pdfPath = null;

        try {
            $this->prepareLargePdfExport();
            $this->ensurePartnerInActiveProject((int) $partnerId);
            $schuljahr = (string) $schuljahr;
            $teil = (string) $teil;

            $schule = Partner::findOrFail($partnerId);

            $schueler = PersonenIstSchueler::with('person')
                ->filterSchueler((int) $partnerId, $schuljahr, $teil)
                ->when($klasse !== null && trim((string) $klasse) !== '', fn ($query) => $query->where('klasse', (string) $klasse))
                ->get()
                ->sort(function ($a, $b) {
                    $klasseCompare = strnatcasecmp((string) $a->klasse, (string) $b->klasse);
                    if ($klasseCompare !== 0) {
                        return $klasseCompare;
                    }

                    $nachnameCompare = strnatcasecmp((string) ($a->person?->nachname ?? ''), (string) ($b->person?->nachname ?? ''));
                    if ($nachnameCompare !== 0) {
                        return $nachnameCompare;
                    }

                    return strnatcasecmp((string) ($a->person?->vorname ?? ''), (string) ($b->person?->vorname ?? ''));
                })
                ->values();

            if ($schueler->isEmpty()) {
                return redirect()->back()->with('error', 'Die Schule: ' . $schule->name . ' verfügt über keine Teilnehmer.');
            }

            $klasseCounter = [];
            $teilnehmer = $schueler->map(function (PersonenIstSchueler $schueler) use (&$klasseCounter, $schule, $schuljahr, $teil) {
                $klasse = trim((string) $schueler->klasse) ?: 'ohne Klasse';
                $klasseCounter[$klasse] = ($klasseCounter[$klasse] ?? 0) + 1;

                $geburtsdatum = $this->safeGermanDate($schueler->person?->geburtsdatum ?? null);

                return [
                    'vorname' => $schueler->person?->vorname ?? '',
                    'nachname' => $schueler->person?->nachname ?? '',
                    'name' => trim(($schueler->person?->nachname ?? '') . ', ' . ($schueler->person?->vorname ?? '')),
                    'geburtsdatum' => $geburtsdatum,
                    'geschlecht' => $schueler->person?->geschlecht ?? '',
                    'schule' => $schule->name,
                    'klasse' => $klasse,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                    'footer_nummer' => $klasse . '-' . $klasseCounter[$klasse],
                ];
            })->values();

            $pdfPath = $this->rolandEvaluationPdf->create($teilnehmer);

            $classFilePart = $klasse !== null && trim((string) $klasse) !== ''
                ? '_Klasse_' . $this->exportFilePart((string) $klasse)
                : '';

            return response()->download(
                $pdfPath,
                'Auswertungsbogen_PA_neu_Roland_' . $this->exportFilePart($schule->name) . '_' . $this->exportFilePart($schuljahr) . '_Teil_' . $this->exportFilePart($teil) . $classFilePart . '.pdf',
                ['Content-Type' => 'application/pdf']
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            Log::error('Auswertungsbogen PA neu Roland konnte nicht erstellt werden.', [
                'route' => 'export.auswertungsbogenPA.roland.schule.pdf',
                'partner_id' => (int) $partnerId,
                'schuljahr' => (string) $schuljahr,
                'teil' => (string) $teil,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Auswertungsbogen PA neu Roland konnte nicht erstellt werden. Bitte probieren Sie es erneut.');
        }
    }



    public function exportElterneinverstaendniserklaerungSchule($partnerId, $schuljahr, $teil, ?int $exportProjectId = null, string $sort = 'klasse')
    {
        $this->ensurePartnerInActiveProject((int) $partnerId);
        $alle_teilnehmer = PersonenIstSchueler::with('person')
            ->forExportProject($exportProjectId)
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->where('eee', '0')
            ->get();
        $alle_teilnehmer = PersonenIstSchueler::sortForPackage($alle_teilnehmer, $sort);
        $partner = Partner::findOrFail($partnerId);
        if(!$partner){
            return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.' );
        }
        if($alle_teilnehmer->isEmpty()){
            return redirect()->back()->with('success', 'Alle Elterneinverständniserklärung der Schule sind erfolgreich eingegangen.' );
        }

        // Pfad zur vorhandenen Excel-Datei
        $existingFile = storage_path('vorlage/projekte/bop/excel/Liste-Elterneinverstaendniserklaerung.xlsx');
        if(!file_exists($existingFile)){
            return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
        }
        // Excel-Datei öffnen
        $spreadsheet = IOFactory::load($existingFile);
        $sheet = $spreadsheet->getActiveSheet();


        $sheet->setCellValue('B2', 'Schule: ' . $partner->name);


        $row = 5; // Startzeile für Daten
        foreach ($alle_teilnehmer as $teilnehmer)
        {
                $sheet->setCellValue('B'.$row, $teilnehmer->person->vorname);
                $sheet->setCellValue('C'.$row, $teilnehmer->person->nachname);
                $sheet->setCellValue('D'.$row, $teilnehmer->geschlecht);
                $sheet->setCellValue('E'.$row, $teilnehmer->klasse);
                $row++;
        }
        $row++;

        $sheet->setCellValue('D'.$row, 'Anzahl:');
        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);

        $sheet->setCellValue('E'.$row, $alle_teilnehmer->count());
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('left');


        // Excel-Datei speichern

        $writer = new Xlsx($spreadsheet);
       $filename = 'Ausstehende Elterneinverstaendniserklaerung-' . $partner->name . '-' . date('d-m-Y') . '.xlsx';
       File::ensureDirectoryExists(storage_path('exports'));
       $updatedFile = storage_path('exports/'.Str::uuid().'.xlsx');
       $writer->save($updatedFile);

       // Aktualisierte Excel-Datei herunterladen
       return response()
        ->download($updatedFile, $filename)
        ->deleteFileAfterSend(true);

    }

    private function exportFilePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));

        return trim($value, '_') ?: 'export';
    }

    private function safeGermanDate(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function prepareLargePdfExport(): void
    {
        $this->ensureMemoryLimit(512 * 1024 * 1024);

        @set_time_limit(180);
    }

    private function ensureMemoryLimit(int $minimumBytes): void
    {
        $currentLimit = trim((string) ini_get('memory_limit'));

        if ($currentLimit === '-1' || $this->phpIniSizeToBytes($currentLimit) >= $minimumBytes) {
            return;
        }

        @ini_set('memory_limit', (string) max(1, (int) ceil($minimumBytes / 1024 / 1024)).'M');
    }

    private function phpIniSizeToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }




}
