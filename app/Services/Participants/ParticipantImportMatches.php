<?php

namespace App\Services\Participants;

use App\Models\ParticipantCvEntry;
use App\Models\ParticipantCvVersion;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\User;

class ParticipantImportMatches
{
    public function inspect(array $person, User $user, Projekt $project): array
    {
        $query = Personen::query()->whereRaw('LOWER(TRIM(vorname)) = ?', [mb_strtolower(trim($person['vorname']))])
            ->whereRaw('LOWER(TRIM(nachname)) = ?', [mb_strtolower(trim($person['nachname']))]);
        if ($person['geburtsdatum']) {
            $query->where(fn ($q) => $q->whereDate('geburtsdatum', $person['geburtsdatum'])->orWhereNull('geburtsdatum'));
        }
        $exists = (clone $query)->exists();
        if (! $exists) {
            return ['status' => 'new', 'candidates' => []];
        }
        // The import permission alone never exposes an existing participant or their projects.
        if (! $user->can('teilnehmer.import') || ! $user->can('teilnehmer.update')) {
            return ['status' => 'restricted', 'candidates' => []];
        }
        $allowedProjects = $user->projekte()->pluck('projekts.id');
        $visible = (clone $query)->teilnehmer()->visibleForUser($user)
            ->whereHas('projekte', fn ($q) => $q->whereIn('projekts.id', $allowedProjects))
            ->with(['projekte' => fn ($q) => $q->whereIn('projekts.id', $allowedProjects)->select('projekts.id', 'projekts.name')])->limit(10)->get();
        $candidates = $visible->map(function ($candidate) use ($project, $person) {
            $already = $candidate->projekte->contains('id', $project->id);
            // These legacy records have no project owner. Do not widen their audience by attaching a new project.
            $legacyData = ! $already && ($candidate->sozialedaten()->exists() || $candidate->baenke()->exists()
                || $candidate->fahrtabrechnungen()->exists() || $candidate->abschluesse()->exists()
                || $candidate->portalProfile()->exists()
                || ParticipantCvEntry::where('person_id', $candidate->id)->exists()
                || ParticipantCvVersion::where('person_id', $candidate->id)->exists());
            $inactive = ! $candidate->aktiv;
            $reason = match (true) {
                $already => 'Bereits im Zielprojekt. Ein Wiedereintritt muss separat geprüft werden.',
                $inactive => 'Dieser Personendatensatz ist deaktiviert. Eine Reaktivierung muss separat geprüft werden.',
                $legacyData => 'Projektübergreifende Bestandsdaten müssen vor der Zuordnung fachlich und datenschutzrechtlich geprüft werden.',
                default => null,
            };

            return [
                'id' => $candidate->id, 'name' => trim($candidate->vorname.' '.$candidate->nachname),
                'birthdate' => $candidate->geburtsdatum?->format('Y-m-d'),
                'exact_birthdate' => $person['geburtsdatum'] && $candidate->geburtsdatum?->format('Y-m-d') === $person['geburtsdatum'],
                'projects' => $candidate->projekte->pluck('name')->values(), 'already_in_project' => $already,
                'can_reuse' => ! $already && ! $legacyData && ! $inactive,
                'reason' => $reason,
            ];
        })->values()->all();

        return ['status' => $candidates ? 'match' : 'restricted', 'candidates' => $candidates];
    }
}
