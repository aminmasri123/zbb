<?php

namespace App\Services\Bop;

use App\Models\BibbAttendanceListDraft;
use App\Models\Gruppe;
use App\Models\GroupAttendanceSignatureRemoval;
use App\Models\PaAttendanceListDraft;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class GroupAttendanceSignatures
{
    public function allowed($user, Gruppe $group): bool
    {
        // BO/BIBB collection remains available in BOP occupational groups; other projects use PA groups only.
        if ($group->isAptitudeTest() || (!$group->isPotentialAnalysisGroup()
            && !str_contains(mb_strtolower((string)$group->projekt?->name), 'bop'))) return false;
        // Group signature collection is currently reserved for department management.
        // Instructor assignment alone must not grant access, even with attendance permissions.
        if (!$user || !$user->hasAnyRole(['Administrator', 'Abteilungsleitung', 'Assistenz der Abt.-Leitung'])) return false;
        $project = app(ActiveProjectContext::class)->currentAvailableFor($user);
        return $user->can('anwesenheit.manage')
            && $project && (int) $project->id === (int) $group->projekt_id;
    }

    private function participants(Gruppe $group, $user)
    {
        return Personen::teilnehmer()->visibleForUser($user)
            ->whereIn('id', DB::table('gruppe_has_personens')->where('gruppe_id', $group->id)->select('personen_id'))
            ->whereHas('projekte', fn ($q) => $q->where('projekts.id', $group->projekt_id))
            ->orderBy('nachname')->orderBy('vorname')->get(['id', 'nachname', 'vorname']);
    }

    private function model(string $type): string
    {
        abort_unless(in_array($type, ['pa', 'bibb'], true), 422);
        return $type === 'pa' ? PaAttendanceListDraft::class : BibbAttendanceListDraft::class;
    }

    private function draft(Gruppe $group, string $type, int $id, bool $lock = false)
    {
        $query = $this->model($type)::where('projekt_id', $group->projekt_id)->whereKey($id);
        if ($lock) $query->lockForUpdate();
        if (DB::connection()->getDriverName() === 'mysql') {
            // Never load the school's full image collection into PHP.
            $query->select(['id', 'projekt_id', 'partner_id', 'schuljahr', 'teil', 'revision', 'updated_at'])
                ->selectRaw("JSON_REMOVE(payload, '$.signatures') AS payload")
                ->selectRaw("JSON_KEYS(payload, '$.signatures') AS signature_keys");
            if ($type === 'pa') $query->addSelect(['export_mode', 'klasse']);
        }
        $draft = $query->firstOrFail();
        $payload = $draft->payload ?? [];
        $keys = DB::connection()->getDriverName() === 'mysql'
            ? json_decode($draft->getAttribute('signature_keys') ?? '[]', true)
            : array_keys($payload['signatures'] ?? []);
        unset($payload['signatures']);
        return [$draft, $payload, $keys ?? []];
    }

    private function rows(Gruppe $group, $user, $draft, array $payload, array $keys, string $type): array
    {
        if ($type === 'pa') {
            // Keep a removed legacy PA key addressable even when the schedule uses a newer ID.
            $removedKeys = GroupAttendanceSignatureRemoval::where('gruppe_id', $group->id)
                ->where('projekt_id', $group->projekt_id)->where('list_type', $type)->where('draft_id', $draft->id)
                ->whereNull('restored_at')->orderByDesc('id')->pluck('signature_key')->all();
            $keys = array_values(array_unique(array_merge($keys, $removedKeys)));
        }
        $participants = $this->participants($group, $user)->keyBy('id');
        $students = PersonenIstSchueler::filterSchueler($draft->partner_id, $draft->schuljahr, $draft->teil)
            ->whereIn('person_id', $participants->keys())->get()->unique('person_id');
        $membership = DB::table('gruppe_has_personens')->join('tages', 'tages.id', '=', 'gruppe_has_personens.tage_id')
            ->where('gruppe_id', $group->id)->get(['personen_id', 'datum'])
            ->mapWithKeys(fn ($row) => [$row->personen_id.'|'.substr($row->datum, 0, 10) => true]);
        $rows = [];
        foreach ($students as $student) {
            if ($type === 'pa' && $draft->export_mode === 'klasse' && trim($draft->klasse ?? '') !== trim($student->klasse ?? '')) continue;
            $schedule = $type === 'pa' ? ($payload['classSchedules'][trim($student->klasse ?? '')] ?? $payload) : $payload;
            $days = $schedule['days'] ?? [];
            if ($type === 'bibb' && !empty($payload['form']['feedbackDate'])) {
                $date = $payload['form']['feedbackDate'];
                $days[] = ['id' => 'feedback-'.$date, 'date' => $date, 'type' => 'feedback'];
            }
            foreach ($days as $day) {
                $date = $day['date'] ?? '';
                if (empty($day['id']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                    || ($day['selected'] ?? true) === false
                    || $date < substr($group->anfangsdatum, 0, 10)
                    || $date > substr($group->enddatum ?: $group->anfangsdatum, 0, 10)
                    || !$membership->has($student->person_id.'|'.$date)) continue;
                if (!empty($day['eligible_classes']) && !in_array(trim($student->klasse ?? ''), $day['eligible_classes'], true)) continue;
                // BO workshop days may contain several separate groups.
                if ($type === 'bibb' && !in_array($day['type'] ?? '', ['rolltag', 'program_day', 'feedback'], true)
                    && !in_array($day['source'] ?? '', ['manual', 'auto'], true)) {
                    $expected = collect($day['groups'] ?? [])->flatMap(fn ($g) => $g['participants'] ?? [])
                        ->contains(fn ($p) => (int) ($p['person_id'] ?? $p['id'] ?? 0) === (int) $student->person_id);
                    if (!$expected) continue;
                }
                $dayId = $day['signature_ids_by_class'][trim($student->klasse ?? '')] ?? $day['id'];
                $key = $dayId.':'.$student->person_id;
                // Match the PA list's existing-date fallback without moving old keys/history.
                if ($type === 'pa' && !in_array($key, $keys, true)) {
                    foreach ($keys as $existing) {
                        $kind = str_contains($existing, 'feedback') ? 'feedback'
                            : ((str_contains($existing, 'preparation') || str_contains($existing, 'vorbereitung')) ? 'preparation' : 'pa_day');
                        if (str_ends_with($existing, ':'.$student->person_id) && str_contains($existing, $date)
                            && $kind === ($day['type'] ?? 'pa_day')) { $key = $existing; break; }
                    }
                }
                $person = $participants[$student->person_id];
                $rows[$key] = ['key' => $key, 'date' => $date, 'type' => $day['type'] ?? '',
                    'person_id' => $person->id, 'nachname' => $person->nachname, 'vorname' => $person->vorname, 'klasse' => $student->klasse];
            }
        }
        return array_values($rows);
    }

    public function lists(Gruppe $group, $user): array
    {
        abort_unless($this->allowed($user, $group), 403);
        $schools = PersonenIstSchueler::whereIn('person_id', $this->participants($group, $user)->pluck('id'))->pluck('schule_id');
        $result = [];
        foreach (['pa', 'bibb'] as $type) {
            $ids = $this->model($type)::where('projekt_id', $group->projekt_id)->whereIn('partner_id', $schools)->pluck('id');
            foreach ($ids as $id) {
                [$draft, $payload, $keys] = $this->draft($group, $type, $id);
                $rows = $this->rows($group, $user, $draft, $payload, $keys, $type);
                if (!$rows) continue;
                $dates = array_values(array_unique(array_column($rows, 'date')));
                sort($dates);
                $school = DB::table('partners')->where('id', $draft->partner_id)->value('name');
                $result[] = ['id' => $id, 'type' => $type, 'dates' => $dates,
                    'label' => ($type === 'pa' ? (($payload['form']['listType'] ?? '') === 'pa_preparation' ? 'Vorbereitung PA' : 'PA') : 'BO/BIBB').' · '.$school.' · '.$draft->schuljahr.' · '.$draft->teil];
            }
        }
        return $result;
    }

    private function values($draft, array $keys): array
    {
        if (!$keys) return [];
        if (DB::connection()->getDriverName() !== 'mysql') {
            return array_intersect_key($draft->payload['signatures'] ?? [], array_flip($keys));
        }
        $query = $draft->newQuery()->whereKey($draft->id);
        foreach (array_values($keys) as $i => $key) {
            $query->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) AS s'.$i, ['$.' . 'signatures.'.json_encode($key)]);
        }
        $record = $query->toBase()->first();
        $values = [];
        foreach (array_values($keys) as $i => $key) {
            $value = $record->{'s'.$i} ?? null;
            if (is_string($value) && $value !== '' && $value !== 'null') $values[$key] = $value;
        }
        return $values;
    }

    public function canRemove($user, Gruppe $group): bool
    {
        return $this->allowed($user, $group) && $user->can('anwesenheit.destroy');
    }

    private function decode(string $value): string
    {
        return str_starts_with($value, 'enc:v1:') ? Crypt::decryptString(substr($value, 7)) : $value;
    }

    private function hashes($draft, array $keys): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return array_map(fn ($value) => hash('sha256', $value), array_filter($this->values($draft, $keys)));
        }
        $hashes = [];
        // Only short hashes leave the database, even for an overview of many days.
        foreach (array_chunk($keys, 100) as $chunk) {
            $query = $draft->newQuery()->whereKey($draft->id);
            foreach ($chunk as $i => $key) {
                $query->selectRaw("SHA2(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, ?)), 'null'), ''), 256) AS s".$i,
                    ['$.signatures.'.json_encode($key)]);
            }
            $record = $query->toBase()->first();
            foreach ($chunk as $i => $key) {
                if ($hash = $record->{'s'.$i} ?? null) $hashes[$key] = $hash;
            }
        }
        return $hashes;
    }

    public function overview(Gruppe $group, $user, string $type, int $id): array
    {
        abort_unless($this->allowed($user, $group), 403);
        [$draft, $payload, $keys] = $this->draft($group, $type, $id);
        $rows = $this->rows($group, $user, $draft, $payload, $keys, $type);
        $hideWeekends = (bool) $group->projekt->rule('group_signatures_hide_weekends', true);
        $visibleDate = fn (string $date) => !$hideWeekends || (int) date('N', strtotime($date)) <= 5;
        $rows = array_values(array_filter($rows, fn ($row) => $visibleDate($row['date'])));
        $hashes = $this->hashes($draft, array_column($rows, 'key'));
        $canRemove = $this->canRemove($user, $group);
        $removals = $canRemove ? GroupAttendanceSignatureRemoval::where('gruppe_id', $group->id)
            ->where('projekt_id', $group->projekt_id)->where('list_type', $type)->where('draft_id', $id)
            ->whereNull('restored_at')->orderByDesc('id')->get(['id', 'signature_key', 'signed_for_date'])
            ->unique('signature_key')->keyBy('signature_key') : collect();
        foreach ($rows as &$row) {
            $row['signed'] = isset($hashes[$row['key']]);
            $row['expected_hash'] = $hashes[$row['key']] ?? null;
            $row['signature_url'] = $row['signed'] ? route('gruppe.signatures.image', [
                'gruppe' => $group->id, 'type' => $type, 'draft_id' => $id,
                'date' => $row['date'], 'key' => $row['key'], 'hash' => $row['expected_hash'],
            ], false) : null;
            $removal = $removals->get($row['key']);
            $row['removal_id'] = !$row['signed'] && $removal?->signed_for_date === $row['date'] ? $removal->id : null;
        }
        unset($row);
        $people = $this->participants($group, $user)->keyBy('id');
        $students = PersonenIstSchueler::filterSchueler($draft->partner_id, $draft->schuljahr, $draft->teil)
            ->whereIn('person_id', $people->keys())->get()->unique('person_id')
            ->filter(fn ($student) => $type !== 'pa' || $draft->export_mode !== 'klasse' || trim($draft->klasse ?? '') === trim($student->klasse ?? ''));
        $participants = $students->map(fn ($student) => [
            'person_id' => $student->person_id, 'vorname' => $people[$student->person_id]->vorname,
            'nachname' => $people[$student->person_id]->nachname, 'klasse' => $student->klasse,
        ])->values()->all();
        $dates = DB::table('gruppe_has_personens')->join('tages', 'tages.id', '=', 'gruppe_has_personens.tage_id')
            ->where('gruppe_id', $group->id)->whereIn('personen_id', $students->pluck('person_id'))
            ->whereBetween('datum', [substr($group->anfangsdatum, 0, 10), substr($group->enddatum ?: $group->anfangsdatum, 0, 10)])
            ->distinct()->orderBy('datum')->pluck('datum')->map(fn ($day) => substr($day, 0, 10))->all();
        $dates = array_values(array_filter($dates, $visibleDate));
        return ['rows' => $rows, 'participants' => $participants, 'dates' => $dates, 'can_remove' => $canRemove,
            'weekends_hidden' => $hideWeekends];
    }

    private function authorizedRow(Gruppe $group, $user, $draft, array $payload, array $keys, array $input): array
    {
        $row = collect($this->rows($group, $user, $draft, $payload, $keys, $input['type']))
            ->first(fn ($row) => $row['key'] === $input['key'] && $row['date'] === $input['date']);
        abort_unless($row, 403);
        return $row;
    }

    public function image(Gruppe $group, $user, array $input): string
    {
        abort_unless($this->allowed($user, $group), 403);
        [$draft, $payload, $keys] = $this->draft($group, $input['type'], $input['draft_id']);
        $this->authorizedRow($group, $user, $draft, $payload, $keys, $input);
        $value = $this->values($draft, [$input['key']])[$input['key']] ?? '';
        abort_unless($value && hash_equals(hash('sha256', $value), $input['hash']), 404);
        $signature = $this->decode($value);
        $bytes = str_starts_with($signature, 'data:image/png;base64,') ? base64_decode(substr($signature, 22), true) : false;
        abort_unless($bytes !== false, 404);
        return $bytes;
    }

    private function writeValue($draft, string $key, ?string $value, $user): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            if ($value === null) {
                DB::update('UPDATE '.$draft->getTable().' SET payload = JSON_REMOVE(payload, ?), revision = revision + 1, user_update = ?, updated_at = ? WHERE id = ?',
                    ['$.signatures.'.json_encode($key), $user->id, now(), $draft->id]);
            } else {
                DB::update('UPDATE '.$draft->getTable()." SET payload = JSON_SET(payload, '$.signatures', JSON_SET(CASE WHEN JSON_TYPE(JSON_EXTRACT(payload, '$.signatures')) = 'OBJECT' THEN JSON_EXTRACT(payload, '$.signatures') ELSE JSON_OBJECT() END, ?, ?)), revision = revision + 1, user_update = ?, updated_at = ? WHERE id = ?",
                    ['$.' . json_encode($key), $value, $user->id, now(), $draft->id]);
            }
            $draft->revision++;
        } else {
            $full = $draft->payload ?? [];
            if ($value === null) unset($full['signatures'][$key]);
            else $full['signatures'][$key] = $value;
            $draft->payload = $full;
            $draft->revision = ($draft->revision ?? 0) + 1;
            $draft->user_update = $user->id;
            $draft->save();
        }
    }

    private function historyScope($draft, array $payload, string $key): array
    {
        return ['projekt_id' => $draft->projekt_id, 'partner_id' => $draft->partner_id,
            'schuljahr' => $draft->schuljahr, 'teil' => $draft->teil,
            'list_type' => str_starts_with($key, 'pa-vorbereitung-') ? 'pa_preparation' : ($payload['form']['listType'] ?? 'pa')];
    }

    private function actorName($user): string
    {
        return mb_substr(trim(($user->person?->vorname ?? '').' '.($user->person?->nachname ?? '')) ?: $user->username ?: $user->email, 0, 255);
    }

    public function remove(Gruppe $group, Request $request, array $input): void
    {
        abort_unless($this->canRemove($request->user(), $group), 403);
        DB::transaction(function () use ($group, $request, $input) {
            [$draft, $payload, $keys] = $this->draft($group, $input['type'], $input['draft_id'], true);
            $row = $this->authorizedRow($group, $request->user(), $draft, $payload, $keys, $input);
            $value = $this->values($draft, [$input['key']])[$input['key']] ?? '';
            abort_unless($value && hash_equals(hash('sha256', $value), $input['expected_hash']), 409,
                'Die Unterschrift wurde inzwischen geändert. Bitte die Übersicht neu laden.');
            $plain = $this->decode($value);
            GroupAttendanceSignatureRemoval::create([
                'gruppe_id' => $group->id, 'projekt_id' => $group->projekt_id, 'list_type' => $input['type'],
                'draft_id' => $draft->id, 'person_id' => $row['person_id'], 'signature_key' => $input['key'],
                'signed_for_date' => $input['date'], 'signature_ciphertext' => 'enc:v1:'.Crypt::encryptString($plain),
                'removed_by' => $request->user()->id, 'removed_by_name' => $this->actorName($request->user()), 'removed_at' => now(),
            ]);
            if ($input['type'] === 'pa') {
                $history = app(PaAttendanceSignatureHistoryService::class);
                $scope = $this->historyScope($draft, $payload, $input['key']);
                $latest = $history->versions($scope, $input['key'])->first();
                // Legacy signatures may have been captured before version history existed.
                if ($latest?->signature_sha256 !== hash('sha256', $plain)) {
                    $history->append($draft, $scope, $payload, $input['key'], $plain, 'imported', $request);
                }
            }
            $this->writeValue($draft, $input['key'], null, $request->user());
            if ($input['type'] === 'pa') $history->append($draft, $scope, $payload, $input['key'], null, 'deleted', $request);
        });
    }

    public function restore(Gruppe $group, Request $request, array $input): void
    {
        abort_unless($this->canRemove($request->user(), $group), 403);
        DB::transaction(function () use ($group, $request, $input) {
            [$draft, $payload, $keys] = $this->draft($group, $input['type'], $input['draft_id'], true);
            $row = $this->authorizedRow($group, $request->user(), $draft, $payload, $keys, $input);
            $removal = GroupAttendanceSignatureRemoval::whereKey($input['removal_id'])->where('gruppe_id', $group->id)
                ->where('projekt_id', $group->projekt_id)->where('list_type', $input['type'])->where('draft_id', $draft->id)
                ->where('person_id', $row['person_id'])->where('signature_key', $input['key'])->where('signed_for_date', $input['date'])
                ->lockForUpdate()->firstOrFail();
            abort_if($removal->restored_at || !empty($this->values($draft, [$input['key']])[$input['key']]), 409,
                'Das Feld wurde inzwischen geändert. Eine vorhandene Unterschrift wird nicht überschrieben.');
            $plain = $this->decode($removal->signature_ciphertext);
            $this->writeValue($draft, $input['key'], 'enc:v1:'.Crypt::encryptString($plain), $request->user());
            $removal->update(['restored_at' => now(), 'restored_by' => $request->user()->id, 'restored_by_name' => $this->actorName($request->user())]);
            if ($input['type'] === 'pa') app(PaAttendanceSignatureHistoryService::class)->append($draft,
                $this->historyScope($draft, $payload, $input['key']), $payload, $input['key'], $plain, 'restored', $request);
        });
    }

    public function show(Gruppe $group, $user, string $type, int $id, string $date): array
    {
        abort_unless($this->allowed($user, $group), 403);
        [$draft, $payload, $keys] = $this->draft($group, $type, $id);
        $rows = array_values(array_filter($this->rows($group, $user, $draft, $payload, $keys, $type), fn ($row) => $row['date'] === $date));
        abort_unless($rows, 404);
        $values = $this->values($draft, array_column($rows, 'key'));
        foreach ($rows as &$row) {
            $value = $values[$row['key']] ?? '';
            $row['signed'] = $value !== '';
            $row['signature'] = str_starts_with($value, 'enc:v1:') ? Crypt::decryptString(substr($value, 7)) : $value;
        }
        return $rows;
    }

    public function store(Gruppe $group, Request $request, array $input): void
    {
        abort_unless($this->allowed($request->user(), $group), 403);
        $value = $input['signature'];
        $bytes = str_starts_with($value, 'data:image/png;base64,') ? base64_decode(substr($value, 22), true) : false;
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        abort_unless($size && $size[2] === IMAGETYPE_PNG && $size[0] <= 1400 && $size[1] <= 600, 422, 'Bitte eine gültige PNG-Unterschrift erfassen.');
        DB::transaction(function () use ($group, $request, $input, $value) {
            [$draft, $payload, $keys] = $this->draft($group, $input['type'], $input['draft_id'], true);
            $rows = $this->rows($group, $request->user(), $draft, $payload, $keys, $input['type']);
            abort_unless(collect($rows)->contains(fn ($row) => $row['key'] === $input['key'] && $row['date'] === $input['date']), 403);
            $existing = $this->values($draft, [$input['key']]);
            if (!empty($existing[$input['key']])) {
                $stored = $existing[$input['key']];
                if ((str_starts_with($stored, 'enc:v1:') ? Crypt::decryptString(substr($stored, 7)) : $stored) === $value) return;
            }
            abort_if(!empty($existing[$input['key']]), 409, 'Die Unterschrift ist bereits gespeichert und darf hier nicht überschrieben werden.');
            $encrypted = 'enc:v1:'.Crypt::encryptString($value);
            $this->writeValue($draft, $input['key'], $encrypted, $request->user());
            if ($input['type'] === 'pa') {
                app(PaAttendanceSignatureHistoryService::class)->recordSignatureChanges($draft, [
                    'projekt_id' => $draft->projekt_id, 'partner_id' => $draft->partner_id,
                    'schuljahr' => $draft->schuljahr, 'teil' => $draft->teil,
                    'list_type' => str_starts_with($input['key'], 'pa-vorbereitung-') ? 'pa_preparation' : ($payload['form']['listType'] ?? 'pa'),
                ], $payload, [$input['key'] => ['previous' => null, 'current' => $value]], $request);
            }
        });
    }
}
