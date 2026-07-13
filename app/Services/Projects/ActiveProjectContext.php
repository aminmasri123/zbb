<?php

namespace App\Services\Projects;

use App\Models\Projekt;
use App\Models\User;

class ActiveProjectContext
{
    public function currentFor(User $user): ?Projekt
    {
        if ($user->current_team_id) {
            $project = $this->forUser($user, (int) $user->current_team_id);

            if ($project) {
                return $project;
            }
        }

        return $this->selectFallbackProject($user);
    }

    public function forUser(User $user, int $projectId): ?Projekt
    {
        return $user->projekte()
            ->where('projekts.id', $projectId)
            ->first();
    }

    public function currentAvailableFor(User $user): ?Projekt
    {
        $project = $this->currentFor($user);

        return $project;
    }

    public function payload(?Projekt $project): ?array
    {
        if (!$project) {
            return null;
        }

        return [
            'id' => $project->id,
            'name' => $project->name,
            'klassenbuch_aktiv' => (bool) ($project->klassenbuch_aktiv ?? false),
            'features' => $project->featureSettings(),
            'rules' => $project->ruleSettings(),
            'portal_features' => $project->portalFeatureSettings(),
        ];
    }

    private function selectFallbackProject(User $user): ?Projekt
    {
        $defaultProject = $user->default_projekt_id
            ? $this->forUser($user, (int) $user->default_projekt_id)
            : null;

        $project = $defaultProject ?: $user->projekte()
            ->orderBy('projekts.name')
            ->orderBy('projekts.id')
            ->first();

        if (! $project) {
            return null;
        }

        $updates = [];

        if ((int) $user->current_team_id !== (int) $project->id) {
            $updates['current_team_id'] = $project->id;
        }

        if (! $defaultProject && (int) $user->default_projekt_id !== (int) $project->id) {
            $updates['default_projekt_id'] = $project->id;
        }

        if ($updates) {
            $user->forceFill($updates)->saveQuietly();
        }

        return $project;
    }
}
