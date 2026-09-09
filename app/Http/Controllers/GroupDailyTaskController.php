<?php

namespace App\Http\Controllers;

use App\Models\GroupDailyTask;
use App\Models\Gruppe;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GroupDailyTaskController extends Controller
{
    private function access(Request $r, Gruppe $g, bool $write = false): array
    {
        $u = $r->user();
        $p = app(ActiveProjectContext::class)->currentAvailableFor($u);
        abort_unless($p && (int) $p->id === (int) $g->projekt_id && $p->featureEnabled('daily_documentation'), 403);
        abort_unless($u->hasStoredPermission('teilnehmer.update') || (! $write && $u->hasStoredPermission('teilnehmer.index')), 403);
        abort_unless((int) $g->personen_id === (int) $u->person_id || $u->hasStoredPermission('gruppe.view.all') || $u->hasStoredPermission('projekt.mitarbeiter.view.all'), 403);
        $people = $g->teilnehmer()->visibleForUser($u)->where('typ', 'teilnehmer')->get(['personens.id', 'vorname', 'nachname'])->unique('id')->values();
        $participations = ProjektHasPersonen::where('projekt_id', $p->id)->whereIn('personen_id', $people->pluck('id'))->get(['id', 'personen_id']);

        return [$people, $participations];
    }

    public function index(Request $r, Gruppe $gruppe)
    {
        [$people,$participations] = $this->access($r, $gruppe);
        $data = $r->validate([
            'date' => 'nullable|required_without:from|date',
            'from' => 'nullable|required_without:date|date',
            'until' => 'nullable|required_with:from|date|after_or_equal:from',
        ]);
        $from = Carbon::parse($data['date'] ?? $data['from'])->startOfDay();
        $until = Carbon::parse($data['date'] ?? ($data['until'] ?? $data['from']))->startOfDay();
        abort_if($from->diffInDays($until) > 6, 422, 'Die Wochenübersicht darf höchstens sieben Tage umfassen.');
        $ids = $participations->pluck('id');
        $canWrite = $r->user()->hasStoredPermission('teilnehmer.update');
        $tasks = GroupDailyTask::with('assignments')->where('gruppe_id', $gruppe->id)->whereBetween('performed_on', [$from->toDateString(), $until->toDateString()])
            ->whereHas('assignments', fn ($q) => $q->whereIn('project_person_id', $ids))->orderBy('id')->get()->map(function ($t) use ($ids, $canWrite) {
                $editable = $canWrite && $t->assignments->every(fn ($a) => $ids->contains($a->project_person_id));

                return [...$t->only(['id', 'description', 'performed_on', 'revision']), 'can_edit' => $editable,
                    'assignments' => $t->assignments->filter(fn ($a) => $ids->contains($a->project_person_id))->values()->map->only(['project_person_id', 'observation'])];
            });
        $library = GroupDailyTask::where('created_by', $r->user()->id)->whereHas('gruppe', fn ($q) => $q->where('projekt_id', $gruppe->projekt_id))
            ->select('description')->selectRaw('MAX(performed_on) as last_used')->groupBy('description')->orderByDesc('last_used')->limit(100)->get();

        return response()->json(['participants' => $people, 'participations' => $participations, 'tasks' => $tasks, 'library' => $library, 'can_write' => $canWrite])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $r, Gruppe $gruppe)
    {
        [, $participations] = $this->access($r, $gruppe, true);
        $ids = $participations->pluck('id')->all();
        $data = $r->validate(['id' => 'nullable|integer', 'revision' => 'required|integer|min:0', 'performed_on' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:500', 'assignments' => 'required|array|min:1|max:500',
            'assignments.*.project_person_id' => ['required', 'integer', 'distinct', Rule::in($ids)], 'assignments.*.observation' => 'nullable|string|max:1000']);
        $date = Carbon::parse($data['performed_on'])->toDateString();
        abort_unless($date >= substr($gruppe->anfangsdatum, 0, 10) && $date <= substr($gruppe->enddatum ?: $gruppe->anfangsdatum, 0, 10), 422, 'Das Datum muss innerhalb des Gruppenzeitraums liegen.');
        abort_if(trim($data['description']) === '', 422, 'Bitte eine Aufgabe beschreiben.');
        $task = DB::transaction(function () use ($r, $gruppe, $ids, $data, $date) {
            Gruppe::whereKey($gruppe->id)->lockForUpdate()->firstOrFail();
            $t = ! empty($data['id']) ? GroupDailyTask::where('gruppe_id', $gruppe->id)->lockForUpdate()->findOrFail($data['id']) : new GroupDailyTask(['gruppe_id' => $gruppe->id, 'created_by' => $r->user()->id]);
            abort_if($t->exists && $t->assignments()->whereNotIn('project_person_id', $ids)->exists(), 403);
            abort_if((int) ($t->revision ?? 0) !== (int) $data['revision'], 409, 'Die Aufgabe wurde inzwischen geändert. Bitte neu laden.');
            $t->fill(['performed_on' => $date, 'description' => trim($data['description']), 'updated_by' => $r->user()->id, 'revision' => $data['revision'] + 1])->save();
            $t->assignments()->delete();
            foreach ($data['assignments'] as $a) {
                $t->assignments()->create(['project_person_id' => $a['project_person_id'], 'observation' => trim($a['observation'] ?? '') ?: null]);
            }

            return $t;
        });

        return response()->json(['id' => $task->id]);
    }

    public function destroy(Request $r, Gruppe $gruppe, GroupDailyTask $task)
    {
        [, $participations] = $this->access($r, $gruppe, true);
        abort_unless((int) $task->gruppe_id === (int) $gruppe->id, 404);
        $data = $r->validate(['revision' => 'required|integer']);
        DB::transaction(function () use ($task, $participations, $data) {
            $locked = GroupDailyTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->assignments()->whereNotIn('project_person_id', $participations->pluck('id'))->exists(), 403);
            abort_if((int) $locked->revision !== (int) $data['revision'], 409, 'Die Aufgabe wurde inzwischen geändert. Bitte neu laden.');
            $locked->delete();
        });

        return response()->json(['success' => true]);
    }
}
