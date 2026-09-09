<?php

namespace App\Services\Aptitude;

use App\Models\AptitudeAttempt;

class AptitudeLuvSources
{
    public function entries(int $participationId, string $from, string $until, string $reportType): array
    {
        $entries = [];
        $attempts = AptitudeAttempt::with('profile')->where('project_person_id', $participationId)
            ->where('status', 'approved')->whereBetween('tested_on', [$from, $until])
            ->where('approved_at', '<=', $until.' 23:59:59')->orderByDesc('tested_on')->orderByDesc('id')->get();
        $seen = [];
        foreach ($attempts as $attempt) {
            foreach ($attempt->results as $key => $result) {
                // Use the latest approved result of each named area, keeping older attempts in the participant history.
                $identity = $result['category'].'|'.$result['label'];
                if (isset($seen[$identity])) continue;
                $seen[$identity] = true;
                $report = $attempt->reports[$key] ?? [];
                foreach (['assessment', 'support_need'] as $field) {
                    if (blank($report[$field] ?? null)) continue;
                    $category = $result['category'];
                    $fieldKey = 'competence.'.$category.'.'.$field;
                    if ($reportType === 'interim') $fieldKey = $field === 'support_need' ? 'competence.'.$category.'.current_need' : 'development.notes';
                    if ($reportType === 'final') $fieldKey = 'support.description';
                    $entries[] = [
                        'source_id' => "aptitude-{$attempt->id}-{$key}-{$field}", 'field_key' => $fieldKey,
                        'category_key' => $category, 'category' => $result['label'], 'origin' => 'aptitude',
                        'decision' => $field === 'assessment' ? 'assessment' : 'support_need',
                        'observation' => $field === 'assessment' ? $report[$field] : null,
                        'support_need' => $field === 'support_need' ? $report[$field] : null,
                        'tested_on' => $attempt->tested_on->toDateString(), 'approved_at' => $attempt->approved_at->toIso8601String(),
                        'test_profile' => $attempt->profile->name, 'profile_version' => $attempt->profile->version,
                        'points' => $result['points'], 'max' => $result['max'],
                    ];
                }
            }
        }
        return $entries;
    }
}
