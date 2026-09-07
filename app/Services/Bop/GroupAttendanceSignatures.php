<?php

namespace App\Services\Bop;

use App\Models\BibbAttendanceListDraft;
use App\Models\Gruppe;
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
        if (!$user) return false;
        $project = app(ActiveProjectContext::class)->currentAvailableFor($user);
        return $user && $user->can('anwesenheit.manage') && $user->person_id
            && (int) $group->personen_id === (int) $user->person_id
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
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::update('UPDATE '.$draft->getTable()." SET payload = JSON_SET(payload, '$.signatures', JSON_SET(CASE WHEN JSON_TYPE(JSON_EXTRACT(payload, '$.signatures')) = 'OBJECT' THEN JSON_EXTRACT(payload, '$.signatures') ELSE JSON_OBJECT() END, ?, ?)), revision = revision + 1, user_update = ?, updated_at = ? WHERE id = ?",
                    ['$.' . json_encode($input['key']), $encrypted, $request->user()->id, now(), $draft->id]);
                $draft->revision++;
            } else {
                $full = $draft->payload ?? [];
                $full['signatures'][$input['key']] = $encrypted;
                $draft->payload = $full;
                $draft->revision = ($draft->revision ?? 0) + 1;
                $draft->user_update = $request->user()->id;
                $draft->save();
            }
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
