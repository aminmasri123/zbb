<?php

namespace App\Services\Bop;

use App\Models\PaAttendanceListDraft;
use App\Models\PaAttendanceSignatureVersion;
use App\Models\PersonenIstSchueler;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaAttendanceSignatureHistoryService
{
    private const ENCRYPTION_PREFIX = 'enc:v1:';

    public function subjectHash(array $scope, string $signatureKey): string
    {
        return hash('sha256', implode('|', [
            $scope['projekt_id'] ?: 0,
            $scope['partner_id'],
            trim($scope['schuljahr']),
            $scope['teil'],
            $scope['list_type'],
            $signatureKey,
            'pa-signature-history',
        ]));
    }

    public function personIdFromSignatureKey(string $signatureKey): ?int
    {
        $separator = strrpos($signatureKey, ':');
        if ($separator === false) {
            return null;
        }

        $personId = substr($signatureKey, $separator + 1);

        return ctype_digit($personId) && (int) $personId > 0 ? (int) $personId : null;
    }

    public function dayKeyFromSignatureKey(string $signatureKey): ?string
    {
        $separator = strrpos($signatureKey, ':');

        return $separator === false ? null : substr($signatureKey, 0, $separator);
    }

    public function participantSnapshot(array $scope, string $signatureKey): ?array
    {
        $personId = $this->personIdFromSignatureKey($signatureKey);
        if (!$personId) {
            return null;
        }

        $student = PersonenIstSchueler::query()
            ->filterSchueler($scope['partner_id'], $scope['schuljahr'], $scope['teil'])
            ->where('person_id', $personId)
            ->with('person:id,vorname,nachname')
            ->first();

        if (!$student) {
            return null;
        }

        return [
            'person_id' => $personId,
            'name' => trim(implode(' ', array_filter([
                $student->person?->vorname,
                $student->person?->nachname,
            ]))) ?: 'Teilnehmer #' . $personId,
            'class_name' => $student->klasse,
        ];
    }

    public function recordChanges(
        PaAttendanceListDraft $draft,
        array $scope,
        array $existingPayload,
        array $incomingPayload,
        array $mergedPayload,
        Request $request
    ): void {
        $existingSignatures = is_array($existingPayload['signatures'] ?? null)
            ? $existingPayload['signatures']
            : [];
        $mergedSignatures = is_array($mergedPayload['signatures'] ?? null)
            ? $mergedPayload['signatures']
            : [];

        foreach (($incomingPayload['signatures'] ?? []) as $signatureKey => $incomingValue) {
            $previousValue = $existingSignatures[$signatureKey] ?? null;
            $currentValue = $mergedSignatures[$signatureKey] ?? null;

            if ($previousValue === $currentValue) {
                continue;
            }

            $this->recordSignatureChanges($draft, $scope, $mergedPayload, [
                (string) $signatureKey => [
                    'previous' => $previousValue,
                    'current' => $currentValue,
                ],
            ], $request);
        }
    }

    /**
     * @param array<string, array{previous:?string,current:?string}> $changes
     */
    public function recordSignatureChanges(
        PaAttendanceListDraft $draft,
        array $scope,
        array $schedulePayload,
        array $changes,
        Request $request
    ): void {
        foreach ($changes as $signatureKey => $change) {
            $previousValue = $change['previous'] ?? null;
            $currentValue = $change['current'] ?? null;

            if ($previousValue === $currentValue) {
                continue;
            }

            $action = $currentValue
                ? ($previousValue ? 'replaced' : 'captured')
                : 'deleted';

            $this->append(
                $draft,
                $scope,
                $schedulePayload,
                (string) $signatureKey,
                $currentValue,
                $action,
                $request
            );
        }
    }

    public function append(
        ?PaAttendanceListDraft $draft,
        array $scope,
        array $payload,
        string $signatureKey,
        ?string $signature,
        string $action,
        ?Request $request = null,
        ?int $restoredFromVersionId = null
    ): ?PaAttendanceSignatureVersion {
        $participant = $this->participantSnapshot($scope, $signatureKey);
        $dayKey = $this->dayKeyFromSignatureKey($signatureKey);

        if (!$participant || !$dayKey || !in_array($action, ['captured', 'replaced', 'restored', 'deleted', 'imported'], true)) {
            return null;
        }

        $subjectHash = $this->subjectHash($scope, $signatureKey);
        $latest = PaAttendanceSignatureVersion::query()
            ->where('subject_hash', $subjectHash)
            ->lockForUpdate()
            ->latest('version')
            ->first();
        $day = $this->daySnapshot($payload, $dayKey);
        $actor = $request?->user();
        $actor?->loadMissing('person:id,vorname,nachname');
        $actorName = $actor
            ? (trim(implode(' ', array_filter([
                $actor->person?->vorname,
                $actor->person?->nachname,
            ]))) ?: $actor->username ?: $actor->email)
            : null;

        return PaAttendanceSignatureVersion::query()->create([
            'subject_hash' => $subjectHash,
            'version' => ($latest?->version ?? 0) + 1,
            'draft_id' => $draft?->id,
            'projekt_id' => $scope['projekt_id'],
            'partner_id' => $scope['partner_id'],
            'person_id' => $participant['person_id'],
            'schuljahr' => $scope['schuljahr'],
            'teil' => $scope['teil'],
            'list_type' => $scope['list_type'],
            'signature_key' => $signatureKey,
            'day_key' => $dayKey,
            'signed_for_date' => $day['date'],
            'day_type' => $day['type'],
            'day_label' => $day['label'],
            'class_name' => $participant['class_name'],
            'action' => $action,
            'signature_ciphertext' => $signature ? $this->encrypt($signature) : null,
            'signature_sha256' => $signature ? hash('sha256', $signature) : null,
            'restored_from_version_id' => $restoredFromVersionId,
            'source_draft_revision' => $draft?->revision,
            'actor_user_id' => $actor?->id,
            'actor_name_snapshot' => $actorName,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    public function versions(array $scope, string $signatureKey)
    {
        return PaAttendanceSignatureVersion::query()
            ->where('subject_hash', $this->subjectHash($scope, $signatureKey))
            ->orderByDesc('version')
            ->get();
    }

    public function presentVersions(array $scope, string $signatureKey): array
    {
        $versions = $this->versions($scope, $signatureKey);
        $currentId = $versions->first()?->id;

        return $versions->map(function (PaAttendanceSignatureVersion $version) use ($currentId) {
            $signature = $version->signature_ciphertext
                ? $this->decrypt($version->signature_ciphertext)
                : null;

            return [
                'id' => $version->id,
                'version' => $version->version,
                'action' => $version->action,
                'is_current' => $version->id === $currentId,
                'can_restore' => $version->id !== $currentId && (bool) $signature,
                'signature' => $signature,
                'signature_available' => $version->signature_ciphertext === null || $signature !== null,
                'signature_sha256' => $version->signature_sha256,
                'signed_for_date' => $version->signed_for_date?->toDateString(),
                'day_type' => $version->day_type,
                'day_label' => $version->day_label,
                'class_name' => $version->class_name,
                'actor_name' => $version->actor_name_snapshot,
                'source_draft_revision' => $version->source_draft_revision,
                'restored_from_version_id' => $version->restored_from_version_id,
                'created_at' => $version->created_at?->toIso8601String(),
            ];
        })->all();
    }

    public function scopeSubjects(array $scope): array
    {
        return PaAttendanceSignatureVersion::query()
            ->where('projekt_id', $scope['projekt_id'])
            ->where('partner_id', $scope['partner_id'])
            ->where('schuljahr', $scope['schuljahr'])
            ->where('teil', $scope['teil'])
            ->where('list_type', $scope['list_type'])
            ->with('participant:id,vorname,nachname')
            ->orderByDesc('version')
            ->get()
            ->groupBy('subject_hash')
            ->map(function ($versions) {
                /** @var PaAttendanceSignatureVersion $latest */
                $latest = $versions->first();

                return [
                    'signature_key' => $latest->signature_key,
                    'person_id' => $latest->person_id,
                    'participant_name' => trim(implode(' ', array_filter([
                        $latest->participant?->vorname,
                        $latest->participant?->nachname,
                    ]))) ?: 'Teilnehmer #' . $latest->person_id,
                    'class_name' => $latest->class_name,
                    'signed_for_date' => $latest->signed_for_date?->toDateString(),
                    'day_type' => $latest->day_type,
                    'day_label' => $latest->day_label,
                    'current_action' => $latest->action,
                    'has_current_signature' => (bool) $latest->signature_ciphertext,
                    'version_count' => $versions->count(),
                    'updated_at' => $latest->created_at?->toIso8601String(),
                ];
            })
            ->sortBy(fn (array $subject) => implode('|', [
                (string) $subject['class_name'],
                (string) $subject['participant_name'],
                (string) $subject['signed_for_date'],
            ]), SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function importParticipantDrafts(int $projectId, int $personId, Request $request): void
    {
        DB::transaction(function () use ($projectId, $personId, $request) {
            $drafts = PaAttendanceListDraft::query()
                ->where('projekt_id', $projectId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortByDesc('updated_at');

            foreach ($drafts as $draft) {
                $payload = $this->decryptDraftPayloadSignatures($draft->payload ?? []);

                foreach (($payload['signatures'] ?? []) as $signatureKey => $signature) {
                    if (!is_string($signatureKey)
                        || !is_string($signature)
                        || $signature === ''
                        || $this->personIdFromSignatureKey($signatureKey) !== $personId) {
                        continue;
                    }

                    $listType = $this->listTypeForDraftSignature($payload, $signatureKey);
                    $scope = [
                        'projekt_id' => $draft->projekt_id,
                        'partner_id' => $draft->partner_id,
                        'schuljahr' => $draft->schuljahr,
                        'teil' => $draft->teil,
                        'list_type' => $listType,
                    ];

                    if ($this->versions($scope, $signatureKey)->isNotEmpty()) {
                        continue;
                    }

                    $this->append(
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
        });
    }

    public function participantSubjects(int $projectId, int $personId): array
    {
        return PaAttendanceSignatureVersion::query()
            ->where('projekt_id', $projectId)
            ->where('person_id', $personId)
            ->with([
                'draft:id,export_mode,klasse',
                'partner:id,name',
            ])
            ->orderByDesc('version')
            ->get()
            ->groupBy('subject_hash')
            ->map(function ($versions) {
                /** @var PaAttendanceSignatureVersion $latest */
                $latest = $versions->first();
                $exportMode = $latest->list_type === 'pa'
                    ? 'alle'
                    : ($latest->draft?->export_mode ?? ($latest->class_name ? 'klasse' : 'alle'));
                $klasse = $exportMode === 'klasse'
                    ? ($latest->draft?->klasse ?: $latest->class_name)
                    : null;

                return [
                    'signature_key' => $latest->signature_key,
                    'partner_id' => $latest->partner_id,
                    'partner_name' => $latest->partner?->name ?: 'Schule #' . $latest->partner_id,
                    'schuljahr' => $latest->schuljahr,
                    'teil' => $latest->teil,
                    'list_type' => $latest->list_type,
                    'export_mode' => $exportMode,
                    'klasse' => $klasse,
                    'class_name' => $latest->class_name,
                    'signed_for_date' => $latest->signed_for_date?->toDateString(),
                    'day_type' => $latest->day_type,
                    'day_label' => $latest->day_label,
                    'current_action' => $latest->action,
                    'has_current_signature' => (bool) $latest->signature_ciphertext,
                    'version_count' => $versions->count(),
                    'updated_at' => $latest->created_at?->toIso8601String(),
                ];
            })
            ->sortByDesc(fn (array $subject) => implode('|', [
                (string) $subject['signed_for_date'],
                (string) $subject['updated_at'],
            ]))
            ->values()
            ->all();
    }

    public function decryptVersion(PaAttendanceSignatureVersion $version): ?string
    {
        return $version->signature_ciphertext
            ? $this->decrypt($version->signature_ciphertext)
            : null;
    }

    public function currentVersionManifest(array $scope, array $payload): array
    {
        $manifest = [];

        foreach (($payload['signatures'] ?? []) as $signatureKey => $signature) {
            if (!is_string($signatureKey) || !is_string($signature) || $signature === '') {
                continue;
            }

            $latest = PaAttendanceSignatureVersion::query()
                ->where('subject_hash', $this->subjectHash($scope, $signatureKey))
                ->latest('version')
                ->first();

            if (!$latest || $latest->signature_sha256 !== hash('sha256', $signature)) {
                continue;
            }

            $manifest[$signatureKey] = [
                'version_id' => $latest->id,
                'version' => $latest->version,
                'sha256' => $latest->signature_sha256,
            ];
        }

        return $manifest;
    }

    private function daySnapshot(array $payload, string $dayKey): array
    {
        $schedules = [$payload];
        foreach (($payload['classSchedules'] ?? []) as $schedule) {
            if (is_array($schedule)) {
                $schedules[] = $schedule;
            }
        }

        foreach ($schedules as $schedule) {
            foreach (($schedule['days'] ?? []) as $day) {
                if (!is_array($day) || (string) ($day['id'] ?? '') !== $dayKey) {
                    continue;
                }

                return [
                    'date' => $this->validDate($day['date'] ?? null),
                    'type' => $day['type'] ?? null,
                    'label' => $day['note'] ?? $this->dayTypeLabel($day['type'] ?? null),
                ];
            }
        }

        preg_match('/\d{4}-\d{2}-\d{2}/', $dayKey, $matches);

        return [
            'date' => $this->validDate($matches[0] ?? null),
            'type' => Str::startsWith($dayKey, 'pa-vorbereitung-') ? 'preparation' : 'pa_day',
            'label' => Str::startsWith($dayKey, 'pa-vorbereitung-') ? 'Vorbereitung PA' : 'PA-Tag',
        ];
    }

    private function listTypeForDraftSignature(array $payload, string $signatureKey): string
    {
        if (Str::startsWith($signatureKey, 'pa-vorbereitung-')) {
            return 'pa_preparation';
        }

        $formListType = data_get($payload, 'form.listType');

        return $formListType === 'pa_preparation' ? 'pa_preparation' : 'pa';
    }

    private function decryptDraftPayloadSignatures(array $payload): array
    {
        if (!is_array($payload['signatures'] ?? null)) {
            return $payload;
        }

        $payload['signatures'] = collect($payload['signatures'])
            ->map(fn ($signature) => is_string($signature) ? $this->decrypt($signature) : null)
            ->filter(fn ($signature) => is_string($signature) && $signature !== '')
            ->all();

        return $payload;
    }

    private function validDate(mixed $date): ?string
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : null;
    }

    private function dayTypeLabel(?string $type): string
    {
        return match ($type) {
            'feedback' => 'Feedbackgespräch',
            'preparation' => 'Vorbereitung PA',
            default => 'PA-Tag',
        };
    }

    private function encrypt(string $signature): string
    {
        if (Str::startsWith($signature, self::ENCRYPTION_PREFIX)) {
            return $signature;
        }

        return self::ENCRYPTION_PREFIX . Crypt::encryptString($signature);
    }

    private function decrypt(string $signature): ?string
    {
        if (!Str::startsWith($signature, self::ENCRYPTION_PREFIX)) {
            return $signature;
        }

        try {
            return Crypt::decryptString(Str::after($signature, self::ENCRYPTION_PREFIX));
        } catch (DecryptException) {
            return null;
        }
    }
}
