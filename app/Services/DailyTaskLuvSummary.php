<?php

namespace App\Services;

use App\Models\GroupDailyTaskParticipant;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Str;

class DailyTaskLuvSummary
{
    public function entry(ProjektHasPersonen $p, string $from, string $until, string $type): ?array
    {
        if (! $p->projekt->featureEnabled('daily_documentation')) {
            return null;
        }
        $rows = GroupDailyTaskParticipant::with('task')->where('project_person_id', $p->id)
            ->whereHas('task', fn ($q) => $q->whereBetween('performed_on', [$from, $until])->whereHas('gruppe', fn ($g) => $g->where('projekt_id', $p->projekt_id)))->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $tasks = [];
        $notes = [];
        $days = [];
        foreach ($rows as $row) {
            $day = $row->task->performed_on->toDateString();
            $days[$day] = true;
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($row->task->description)));
            $tasks[$key]['label'] = $row->task->description;
            $tasks[$key]['days'][$day] = true;
            if (filled($row->observation)) {
                $notes[trim($row->observation)] = trim($row->observation);
            }
        }
        uasort($tasks, fn ($a, $b) => count($b['days']) <=> count($a['days']) ?: strcmp($a['label'], $b['label']));
        $labels = array_map(fn ($t) => Str::limit($t['label'], 90).' ('.count($t['days']).' '.(count($t['days']) === 1 ? 'Tag' : 'Tage').')', array_slice($tasks, 0, 5));
        $text = 'Im Berichtszeitraum wurden an '.count($days).' '.(count($days) === 1 ? 'Tag' : 'Tagen').' folgende Tätigkeiten dokumentiert: '.implode('; ', $labels).'.';
        if (count($tasks) > 5) {
            $text .= ' Hinzu kommen '.(count($tasks) - 5).' weitere unterschiedliche Tätigkeiten (siehe Tagesdokumentation).';
        }
        if ($notes) {
            $text .= ' Dokumentierte individuelle Beobachtungen: '.implode(' / ', array_map(fn ($s) => '„'.Str::limit($s, 160).'“', array_slice(array_values($notes), 0, 2))).'.';
            if (count($notes) > 2) {
                $text .= ' Weitere Beobachtungen sind in der Tagesdokumentation hinterlegt.';
            }
        }
        $key = match ($type) {
            'Verlauf','interim' => 'development.notes','Abschluss','final' => 'support.recommendations',default => 'competence.technical.assessment'
        };

        return ['source_id' => 'daily-tasks-'.$p->id.'-'.$from.'-'.$until, 'field_key' => $key, 'category_key' => 'technical', 'category' => 'Tagesdokumentation',
            'origin' => 'daily_documentation', 'decision' => 'assessment', 'observation' => $text, 'support_need' => null, 'from' => $from, 'until' => $until];
    }
}
