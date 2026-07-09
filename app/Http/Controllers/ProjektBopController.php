<?php

namespace App\Http\Controllers;

use App\Models\Bereich;
use App\Models\Bereichsauswahl;
use App\Models\BereichsauswahlSetting;
use App\Models\BibbAttendanceListDraft;
use App\Models\EinteilungBereiche;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\PaAttendanceListDraft;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\MyDatum;
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

    private function normalizeAuswahlAnzahl(int $value): int
    {
        return min(4, max(2, $value));
    }

    private function defaultAuswahlAnzahl(?Projekt $projekt): int
    {
        $bereicheCount = $projekt?->bereiche?->count() ?? 4;

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
        $setting = BereichsauswahlSetting::firstOrCreate(
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

    private function allowedBereichIds(Projekt $projekt): Collection
    {
        return $projekt->bereiche->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    private function validatedChoices(Request $request, BereichsauswahlSetting $setting, Collection $allowedBereichIds): array
    {
        $data = $request->validate([
            'choices' => ['required', 'array', 'size:' . $setting->auswahl_anzahl],
            'choices.*' => ['required', 'integer'],
        ]);

        $choices = collect($data['choices'])->map(fn ($id) => (int) $id)->values();

        if ($choices->unique()->count() !== $choices->count()) {
            throw ValidationException::withMessages([
                'choices' => 'Jeder Bereich darf nur einmal gewaehlt werden.',
            ]);
        }

        if ($choices->diff($allowedBereichIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'choices' => 'Mindestens ein Bereich ist fuer dieses Projekt nicht erlaubt.',
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
            ->where('schuljahr', $setting->schuljahr)
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
        $draft = BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();

        return response()->json([
            'exists' => (bool) $draft,
            'payload' => $draft ? $this->decryptBibbDraftPayloadSignatures($draft->payload ?? []) : null,
            'revision' => $draft?->revision ?? 0,
            'updated_at' => $draft?->updated_at?->toIso8601String(),
            'expires_at' => $draft?->expires_at?->toIso8601String(),
            'final_pdf_path' => $draft?->final_pdf_path,
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

            $existingPayload = $this->decryptBibbDraftPayloadSignatures($draft?->payload ?? []);
            $payload = $this->mergeBibbDraftPayload($existingPayload, $incomingPayload);
            $payload['saved_at'] = now()->toIso8601String();

            $draft->payload = $this->encryptBibbDraftPayloadSignatures($payload);
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = $userId;
            $draft->save();

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

        BibbAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->delete();

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

        $projektId = auth()->user()?->current_team_id;
        $partnerId = (int) $validated['schuleIdInputBibb'];
        $schuljahr = (string) $validated['schuljahrInputBibb'];
        $teil = (string) $validated['teilInputBibb'];

        return [
            'draft_hash' => hash('sha256', implode('|', [
                $projektId ?: 0,
                $partnerId,
                $schuljahr,
                $teil,
                'bibb-attendance-list',
            ])),
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

    private function bibbPreviewPayload(int $schulId, string $schuljahr, string $teil, ?string $rolltagDate, array $manualDays): array
    {
        $schule = Partner::findOrFail($schulId);
        $teilnehmer = $this->bibbTeilnehmer($schulId, $schuljahr, $teil);

        if ($teilnehmer->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die gewaehlte Schule weist zurzeit noch keine Schueler auf.',
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

    private function bibbDateListFromRequest(Request $request): array
    {
        $dates = collect($request->input('days', []))
            ->filter(fn ($day) => ($day['selected'] ?? true) && !empty($day['date']))
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('d.m.Y'))
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            $dates = collect(range(1, 10))
                ->map(fn ($index) => $request->input('termin' . $index))
                ->filter()
                ->map(fn ($date) => Carbon::parse($date)->format('d.m.Y'))
                ->values();
        }

        $feedbackDate = $request->filled('feedbackDate')
            ? Carbon::parse($request->input('feedbackDate'))->format('d.m.Y')
            : ($request->filled('termin11') ? Carbon::parse($request->input('termin11'))->format('d.m.Y') : '');

        return [
            $dates->take(10)->values()->all(),
            $feedbackDate,
        ];
    }

    public function anwesenheitslistePOBOExportWordBIBB(Request $request)
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
                return response()->json(['message' => 'Bitte mindestens einen Anwesenheitstag auswaehlen.'], 422);
            }

            $schuljahr = $request->schuljahrInputBibb;
            $schulId = $request->schuleIdInputBibb;
            $teil = $request->teilInputBibb;
            $format = $request->exportFormat;

            if($format == "A4")
            {
                $templateFile = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A4.docx');
            }elseif($format == "A3")
            {
                $templateFile = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A3.docx');
            }
            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }


            $alle_teilnehmer = PersonenIstSchueler::query()->filterSchueler( $schulId ?? null, $schuljahr ?? null, $teil ?? null)
            ->with('person')
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
            $templateProcessor = new TemplateProcessor($templateFile);

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
            while($i<=97){
                $templateProcessor->setValue('nachname' . $i, '');
                $templateProcessor->setValue('vorname' . $i, '');
                $templateProcessor->setValue('klasse' . $i, '');
                $i++;
            }
                $filename = 'Teilnehmendenliste_zum_Nachweis_der_praxisorientierten_Berufsorientierung_' . $schule->name . '_' . $schuljahr . '_' .  date('Ymd_His') . '.docx';
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePAPreviewDigital(Request $request)
    {
        $validated = $request->validate([
            'schuleId' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'exportMode' => ['nullable', 'in:alle,klasse'],
            'klasse' => ['nullable', 'required_if:exportMode,klasse', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'feedbackDate' => ['nullable', 'date'],
            'includeSaturday' => ['nullable', 'boolean'],
            'includeSunday' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*.date' => ['required_with:days', 'date'],
            'days.*.selected' => ['nullable', 'boolean'],
            'days.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $exportMode = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');

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
            (bool) ($validated['includeSaturday'] ?? false),
            (bool) ($validated['includeSunday'] ?? false)
        ));
    }

    public function anwesenheitslistePADraftShow(Request $request)
    {
        $this->purgeExpiredPaDrafts();
        $scope = $this->paDraftScope($request);
        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();

        return response()->json([
            'exists' => (bool) $draft,
            'payload' => $draft ? $this->decryptPaDraftPayloadSignatures($draft->payload ?? []) : null,
            'revision' => $draft?->revision ?? 0,
            'updated_at' => $draft?->updated_at?->toIso8601String(),
            'expires_at' => $draft?->expires_at?->toIso8601String(),
            'final_pdf_path' => $draft?->final_pdf_path,
        ]);
    }

    public function anwesenheitslistePADraftStore(Request $request)
    {
        $this->purgeExpiredPaDrafts();
        $scope = $this->paDraftScope($request);
        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'payload.form' => ['nullable', 'array'],
            'payload.days' => ['nullable', 'array'],
            'payload.selectedDayId' => ['nullable', 'string'],
            'payload.signatures' => ['nullable', 'array'],
            'payload.signatures.*' => ['nullable', 'string'],
        ]);

        $incomingPayload = $this->sanitizePaDraftPayload($validated['payload']);
        $userId = auth()->id();

        [$draft, $payload] = DB::transaction(function () use ($scope, $incomingPayload, $userId) {
            $now = now();

            PaAttendanceListDraft::query()->insertOrIgnore([
                'draft_hash' => $scope['draft_hash'],
                'projekt_id' => $scope['projekt_id'],
                'partner_id' => $scope['partner_id'],
                'schuljahr' => $scope['schuljahr'],
                'teil' => $scope['teil'],
                'export_mode' => $scope['export_mode'],
                'klasse' => $scope['klasse'],
                'payload' => json_encode([]),
                'revision' => 0,
                'user_create' => $userId,
                'user_update' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])
                ->lockForUpdate()
                ->first();

            $existingPayload = $this->decryptPaDraftPayloadSignatures($draft?->payload ?? []);
            $payload = $this->mergePaDraftPayload($existingPayload, $incomingPayload);
            $payload['saved_at'] = now()->toIso8601String();

            $draft->payload = $this->encryptPaDraftPayloadSignatures($payload);
            $draft->revision = ($draft->revision ?: 0) + 1;
            $draft->user_update = $userId;
            $draft->save();

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

    public function anwesenheitslistePADraftDestroy(Request $request)
    {
        $scope = $this->paDraftScope($request);

        PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->delete();

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
            $scope['klasse']
        );
        $path = $folder . DIRECTORY_SEPARATOR . $filename;

        if (File::exists($path)) {
            File::delete($path);
        }

        $request->file('pdf')->move($folder, $filename);

        $relativePath = $this->relativeStoragePath($path);
        $expiresAt = now()->addDays(self::PA_DRAFT_RETENTION_DAYS);

        $draft = PaAttendanceListDraft::where('draft_hash', $scope['draft_hash'])->first();
        if ($draft) {
            $payload = is_array($draft->payload) ? $draft->payload : [];
            $payload['final_pdf'] = [
                'path' => $relativePath,
                'saved_at' => now()->toIso8601String(),
                'draft_expires_at' => $expiresAt->toIso8601String(),
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
        ?string $klasse
    ): string {
        $baseName = $filename
            ? Str::beforeLast($filename, '.pdf')
            : 'Anwesenheitsliste_PA_' . $partner->name . '_' . $schuljahr . '_Teil_' . $teil;

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
            'exportMode' => ['nullable', 'in:alle,klasse'],
            'klasse' => ['nullable', 'required_if:exportMode,klasse', 'string'],
        ]);

        $exportMode = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');
        $klasse = $exportMode === 'klasse' ? (string) ($validated['klasse'] ?? '') : null;

        if ($exportMode === 'klasse' && $klasse === '') {
            throw ValidationException::withMessages([
                'klasse' => 'Bitte eine Klasse auswaehlen.',
            ]);
        }

        $projektId = auth()->user()?->current_team_id;
        $partnerId = (int) $validated['schuleId'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];

        return [
            'draft_hash' => hash('sha256', implode('|', [
                $projektId ?: 0,
                $partnerId,
                $schuljahr,
                $teil,
                $exportMode,
                $klasse ?: '',
                'pa-attendance-list',
            ])),
            'projekt_id' => $projektId,
            'partner_id' => $partnerId,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'export_mode' => $exportMode,
            'klasse' => $klasse,
        ];
    }

    private function sanitizePaDraftPayload(array $payload): array
    {
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $allowedFormKeys = [
            'exportFormat',
            'startDate',
            'endDate',
            'includeSaturday',
            'includeSunday',
            'feedbackDate',
            'exportMode',
            'klasse',
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

    private function mergePaDraftPayload(array $existingPayload, array $incomingPayload): array
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
        bool $includeSaturday = false,
        bool $includeSunday = false
    ): array {
        $schule = Partner::findOrFail($schuleId);
        $teilnehmer = $this->paTeilnehmer($schuleId, $schuljahr, $teil, $exportMode, $klasse);

        if ($teilnehmer->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die Schule hat keine Teilnehmer fuer diese PA-Auswahl.',
            ]);
        }

        $participants = $teilnehmer->map(fn ($item) => $this->bibbTeilnehmerPayload($item))->values();
        $klasseText = $exportMode === 'klasse' && $klasse
            ? $klasse
            : $teilnehmer->pluck('klasse')->unique()->implode(', ');
        $schulform = PersonenIstSchueler::query()->schulform($teilnehmer);
        $inputDays = collect($inputDays)
            ->filter(fn ($day) => is_array($day) && !empty($day['date']))
            ->reject(fn ($day) => ($day['type'] ?? null) === 'feedback'
                || ($day['source'] ?? null) === 'feedback'
                || Str::startsWith((string) ($day['id'] ?? ''), 'feedback-'))
            ->values()
            ->all();

        if (empty($inputDays) && $startDate && $endDate) {
            $inputDays = $this->paDaysFromRange($startDate, $endDate, $includeSaturday, $includeSunday);
        }

        if ($feedbackDate) {
            $date = Carbon::parse($feedbackDate)->toDateString();
            $inputDays[] = [
                'id' => 'feedback-' . $date,
                'date' => $date,
                'type' => 'feedback',
                'selected' => true,
                'source' => 'feedback',
                'note' => 'Feedbackgespraech',
            ];
        }

        $days = collect($inputDays)
            ->map(function ($day) use ($participants) {
                $date = Carbon::parse($day['date'])->toDateString();
                $type = ($day['type'] ?? null) === 'feedback' ? 'feedback' : 'pa_day';

                return [
                    'id' => $day['id'] ?? ($type === 'feedback' ? 'feedback-' : 'pa-') . $date,
                    'date' => $date,
                    'date_label' => Carbon::parse($date)->format('d.m.Y'),
                    'type' => $type,
                    'type_label' => $type === 'feedback' ? 'Feedbackgespraech' : 'PA-Tag',
                    'source' => $day['source'] ?? ($type === 'feedback' ? 'feedback' : 'manual'),
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
                'export_mode' => $exportMode,
                'teilnehmer_count' => $participants->count(),
            ],
            'participants' => $participants,
            'days' => $days,
        ];
    }

    private function paDaysFromRange(
        string $startDate,
        string $endDate,
        bool $includeSaturday,
        bool $includeSunday
    ): array {
        $days = [];
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isSaturday() && !$includeSaturday) {
                continue;
            }

            if ($date->isSunday() && !$includeSunday) {
                continue;
            }

            $dateValue = $date->toDateString();
            $days[] = [
                'id' => 'range-' . $dateValue,
                'date' => $dateValue,
                'selected' => true,
                'source' => 'range',
                'note' => null,
            ];
        }

        return $days;
    }

    private function paTeilnehmer(
        int $schuleId,
        string $schuljahr,
        string $teil,
        string $exportMode,
        ?string $klasse
    ): Collection {
        return PersonenIstSchueler::query()
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

        $templateFile = storage_path('vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx');

            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
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

            $tag1 = Carbon::parse($request->startDate)->format('d.m.Y');
            $tag2 = Carbon::parse($request->endDate)->format('d.m.Y');

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

                while($i<=30){
                    $templateProcessor->setValue('nachname' . $i, '');
                    $templateProcessor->setValue('vorname' . $i, '');
                    $i++;
                }

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
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
            while($i<=30){
                $templateProcessor->setValue('nachname' . $i, '');
                $templateProcessor->setValue('vorname' . $i, '');
                $i++;
            }
                $filename = 'Anwesenheitsliste_PA_' . $schule->name . '_' . $request->klasse . '_' . $tag1 . '_' . $tag2 . '_'  . date('Ymd_His') . '.docx';
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePOBOTag1($partnerID, $schuljahr, $teil, $klasse = 'exportAlleKlassen', Request $request)
    {
        // Query Parameter
        $anzahlBereiche = request()->query('anzahlBereiche', 6);
        $anzahlRaeumlichkeiten = request()->query('anzahlRaeumlichkeiten', $anzahlBereiche);
        $kapazitaeten = request()->query('kapazitaeten', []);
        $termin = request()->query('termin', date('Y-m-d')) ;
        $raumNamen = $request->input('raumNamen', []);

        // Prüfen
        if (!$partnerID || !$schuljahr || !$teil || !$termin ){
            return redirect()->route('partner.index')->with('error', 'Fehlende Daten.');
        }

        $schule = Partner::findOrFail($partnerID);

        // Teilnehmer laden
        $alleTeilnehmer = PersonenIstSchueler::where('schule_id', $schule->id)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->when($klasse !== 'exportAlleKlassen' && $klasse !== 'exportAlleKlassenZip' , fn($q) => $q->where('klasse', $klasse))
            ->with('person')
            ->get()
            ->sortBy(fn($t) => $t->person->nachname);

        if ($alleTeilnehmer->isEmpty()) {
            return back()->with('error', 'Keine Teilnehmer gefunden.');
        }

        // Template
        $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1.xlsx');
        if (!file_exists($templateFile)) {
            return back()->with('error', 'Template fehlt.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🟢 FALL 1: EINZELNE KLASSE
        |--------------------------------------------------------------------------
        */
        if ($klasse !== 'exportAlleKlassen' && $klasse !== 'exportAlleKlassenZip') {
          Log::info('fall 1');

            $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1-Klasse.xlsx');
            if (!file_exists($templateFile)) {
                return back()->with('error', 'Template fehlt.');
            }

            $spreadsheet = IOFactory::load($templateFile);
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

            $filePath = storage_path('Rolltag_' . $klasse . '.xlsx');
            (new Xlsx($spreadsheet))->save($filePath);

            return response()->download($filePath)->deleteFileAfterSend(true);
        }
        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 2: ALLE KLASSEN → ZIP mit Klassenlisten
        |--------------------------------------------------------------------------
        */
        if ($klasse === 'exportAlleKlassenZip') {
                      Log::info('fall 2');

            $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1-klasse.xlsx');
            if (!file_exists($templateFile)) {
                return back()->with('error', 'Template fehlt.');
            }

            // 👉 Teilnehmer nach Klassen gruppieren
            $gruppenNachKlassen = $alleTeilnehmer->groupBy('klasse');

            // 👉 Temp Ordner
            $tempDir = storage_path('temp_excel');
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

            array_map('unlink', glob($tempDir . '/*'));

            $dateien = [];

            foreach ($gruppenNachKlassen as $klassenName => $teilnehmerListe) {

                $spreadsheet = IOFactory::load($templateFile);
                $sheet = $spreadsheet->getActiveSheet();

                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                // Kopf
                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Rolltag - Klasse $klassenName - " . $schule->name);

                // Teilnehmer eintragen
                $row = 8;

                foreach ($teilnehmerListe as $t) {
                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                // Datei speichern
                $fileName = "Rolltag_{$klassenName}.xlsx";
                $filePath = $tempDir . '/' . $fileName;

                (new Xlsx($spreadsheet))->save($filePath);

                $dateien[] = $filePath;
            }

            // 👉 ZIP erstellen
            $zipFileName = storage_path('Rolltag_Klassen_' . date('Ymd_His') . '.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

                foreach ($dateien as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                // Temp löschen
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipFileName)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 3: Benutzerdefinierte Räume mit Namen und Kapazitäten
        |--------------------------------------------------------------------------
        */
        if ($request->has('anzahlRaeumlichkeiten') && $request->has('raumNamen')) {
            $anzahlRaeumlichkeiten = (int)$anzahlRaeumlichkeiten;
            $alleTeilnehmer = $alleTeilnehmer->values();
            $kapazitaeten = array_map(fn ($kapazitaet) => (int) $kapazitaet, $kapazitaeten ?? []);

            // Validierung
            if ($anzahlRaeumlichkeiten < 1 || count($raumNamen) != $anzahlRaeumlichkeiten) {
                return back()->with('error', 'Anzahl der Räume und Raumnamen stimmen nicht überein.');
            }

            $anzahlTeilnehmer = $alleTeilnehmer->count();

            // Kapazitäten automatisch berechnen, falls leer
            if (!$kapazitaeten || count($kapazitaeten) != $anzahlRaeumlichkeiten) {
                $grundzahl = intdiv($anzahlTeilnehmer, $anzahlRaeumlichkeiten);
                $rest = $anzahlTeilnehmer % $anzahlRaeumlichkeiten;

                $kapazitaeten = [];
                for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                    $kapazitaeten[$i] = $grundzahl + ($i < $rest ? 1 : 0);
                }
            }

            if (array_sum($kapazitaeten) < $anzahlTeilnehmer) {
                return response()->json([
                    'message' => 'Die eingegebenen Kapazitaeten reichen nicht fuer alle Schueler aus.',
                ], 422);
            }

            // Temp Ordner vorbereiten
            $tempDir = storage_path('temp_excel');
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            array_map('unlink', glob($tempDir . '/*'));

            $startIndex = 0;

            for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                $spreadsheet = IOFactory::load($templateFile);
                $sheet = $spreadsheet->getActiveSheet();

                // Raumname oder generisch
                $raumNameOriginal = $raumNamen[$i] ?? 'Raum ' . ($i + 1);
                $raumName = Str::slug($raumNameOriginal, '_');

                $anzahlAktuell = $kapazitaeten[$i];
                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Gruppe " . ($i + 1) . " - $raumName - " . $schule->name);

                $row = 8;

                for ($j = $startIndex; $j < $startIndex + $anzahlAktuell && $j < $anzahlTeilnehmer; $j++) {
                    $t = $alleTeilnehmer[$j];

                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                $startIndex += $anzahlAktuell;

                // Excel speichern
                (new Xlsx($spreadsheet))->save($tempDir . "/Rolltag_Raum_" . ($i + 1) . "_$raumName.xlsx");
            }

            // ZIP erstellen
            $zipPath = storage_path('Rolltag_Raeume_' . date('Ymd_His') . '.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                foreach (glob($tempDir . '/*.xlsx') as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();

                // Cleanup
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
        }

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
            $tempDir = storage_path('temp_excel');
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
                ->where('id', auth()->user()->current_team_id)
                ->first();

            // Als Array von Bereichsnamen
            $bereicheListe = $projekt->bereiche->pluck('name')->toArray();
            $bereiche = array_slice($bereicheListe, 0, $anzahlBereiche);

            $startIndex = 0;
            $gruppenListe = [];
            for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {

                $spreadsheet = IOFactory::load($templateFile);
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
            $zipPath = storage_path('Anwesenheitslisten.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {

                foreach (glob($tempDir . '/*.xlsx') as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                // Cleanup
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
    }


    public function hausordnungExportPdf($partnerID, $schuljahr, $teil, $sortBy, $termin)
    {
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
             $alle_teilnehmer = PersonenIstSchueler::where('schuljahr', $schuljahr)
            ->where('schule_id', $partnerID)
                ->where('teil', $teil)
                ->with('person')
                ->get()
                ->sortBy(fn($t) => strtolower($t->person->nachname), SORT_NATURAL);


       }elseif($sortBy == 'klasse'){
           $alle_teilnehmer = PersonenIstSchueler::where('schuljahr', $schuljahr)
            ->where('schule_id', $partnerID)
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
        $projekt = Projekt::with('bereiche', 'partners')->where('id', Auth()->user()->current_team_id)->firstOrFail();
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

        return Inertia::render('Bereichsauswahl/Index', [
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
            'zugang_aktiv' => ['nullable', 'boolean'],
        ]);

        $projekt = Projekt::with('bereiche')->findOrFail(auth()->user()->current_team_id);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $validated['partner_id'],
            $validated['schuljahr'],
            $validated['teil'],
            $projekt
        );

        $selectionCount = $this->normalizeAuswahlAnzahl((int) $validated['auswahl_anzahl']);

        $setting->update([
            'auswahl_anzahl' => $selectionCount,
            'zugang_aktiv' => $request->boolean('zugang_aktiv', true),
            'user_update' => auth()->id(),
        ]);

        $teilnehmerIds = PersonenIstSchueler::query()
            ->where('schule_id', $validated['partner_id'])
            ->where('schuljahr', $validated['schuljahr'])
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

        $projekt = Projekt::with('bereiche')->findOrFail(auth()->user()->current_team_id);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $teilnehmer->schule_id,
            $teilnehmer->schuljahr,
            $teilnehmer->teil,
            $projekt
        );
        $choices = $this->validatedChoices($request, $setting, $this->allowedBereichIds($projekt));

        $wahl = $teilnehmer->bereichsauswahl;

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
        $setting = BereichsauswahlSetting::with(['partner', 'projekt.bereiche'])
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

        return Inertia::render('Bereichsauswahl/Selbstwahl', [
            'context' => [
                'schule' => $setting->partner?->name,
                'schuljahr' => $setting->schuljahr,
                'teil' => $setting->teil,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
            ],
            'bereiche' => $setting->projekt?->bereiche?->values() ?? [],
            'token' => $token,
        ]);
    }

    public function bereichsauswahlSelfThanks(string $token)
    {
        $setting = BereichsauswahlSetting::with('partner')
            ->where('public_token', $token)
            ->firstOrFail();

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

        $setting = BereichsauswahlSetting::where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

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

        $setting = BereichsauswahlSetting::with('projekt.bereiche')
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

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
            $this->allowedBereichIds($setting->projekt)
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

    public function generatePdfAuswertungsbogenPaRolandSchule($partnerId, $schuljahr, $teil)
    {
        $schule = Partner::findOrFail($partnerId);

        $schueler = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
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
            return redirect()->back()->with('error', 'Die Schule: ' . $schule->name . ' verfuegt ueber keine Teilnehmer.');
        }

        $klasseCounter = [];
        $teilnehmer = $schueler->map(function (PersonenIstSchueler $schueler) use (&$klasseCounter, $schule, $schuljahr, $teil) {
            $klasse = trim((string) $schueler->klasse) ?: 'ohne Klasse';
            $klasseCounter[$klasse] = ($klasseCounter[$klasse] ?? 0) + 1;

            return [
                'vorname' => $schueler->person?->vorname ?? '',
                'nachname' => $schueler->person?->nachname ?? '',
                'name' => trim(($schueler->person?->nachname ?? '') . ', ' . ($schueler->person?->vorname ?? '')),
                'geburtsdatum' => $schueler->person?->geburtsdatum ? Carbon::parse($schueler->person->geburtsdatum)->format('d.m.Y') : '',
                'geschlecht' => $schueler->person?->geschlecht ?? '',
                'schule' => $schule->name,
                'klasse' => $klasse,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
                'footer_nummer' => $klasse . '-' . $klasseCounter[$klasse],
            ];
        })->values();

        $pdf = Pdf::loadView('pdf.auswertungsbogenPA-roland', [
            'teilnehmer' => $teilnehmer,
            'schulname' => $schule->name,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download(
            'Auswertungsbogen_PA_neu_Roland_' . $this->exportFilePart($schule->name) . '_' . $this->exportFilePart($schuljahr) . '_Teil_' . $this->exportFilePart($teil) . '.pdf'
        );
    }



    public function exportElterneinverstaendniserklaerungSchule($partnerId, $schuljahr, $teil)
    {
        $alle_teilnehmer = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->where('eee', '0')
            ->get()
             ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();
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
       $updatedFile = 'Ausstehende Elterneinverstaendniserklaerung-' . $partner->name .'-'. date('d-m-Y') . '.xlsx';
       $writer->save($updatedFile);

       // Aktualisierte Excel-Datei herunterladen
       return response()
        ->download($updatedFile)
        ->deleteFileAfterSend(true);

    }

    private function exportFilePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));

        return trim($value, '_') ?: 'export';
    }




}
