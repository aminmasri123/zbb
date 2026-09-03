<?php

namespace App\Services\Ai;

use App\Services\Ai\Tools\GetParticipantPotentialAnalysisSupportNeedsTool;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ApprovedPaSupportNeedMerger
{
    /**
     * Fachlich freigegebene PA-Angaben sind verbindliche LuV-Quellen. Das
     * Sprachmodell darf sie professionell formulieren, aber nicht auslassen.
     *
     * @param  array<string, mixed>  $report
     * @param  list<array{role: string, tool_name: string, content: array<string, mixed>}>  $toolResults
     * @return array<string, mixed>
     */
    public function merge(array $report, array $toolResults): array
    {
        $report = $this->withoutInsufficientDataSections($report);

        $toolResult = collect($toolResults)
            ->firstWhere('tool_name', GetParticipantPotentialAnalysisSupportNeedsTool::NAME);
        $entries = is_array($toolResult)
            ? data_get($toolResult, 'content.entries', [])
            : [];

        $entries = collect(is_array($entries) ? $entries : [])
            ->filter(fn ($entry) => is_array($entry)
                && filled($entry['source_id'] ?? null)
                && filled($entry['field_key'] ?? null)
                && in_array($entry['decision'] ?? null, ['support_need', 'no_support_need'], true))
            ->values();

        if ($entries->isEmpty()) {
            return $report;
        }

        $sections = collect(is_array($report['sections'] ?? null) ? $report['sections'] : []);
        $requiredFieldKeys = $entries->pluck('field_key')->unique()->values();

        foreach ($entries->groupBy('field_key') as $fieldKey => $fieldEntries) {
            $matchingSections = $sections->filter(
                fn ($section) => is_array($section) && $this->fieldKey((string) ($section['heading'] ?? '')) === $fieldKey
            );
            $claims = $matchingSections
                ->flatMap(fn (array $section) => is_array($section['claims'] ?? null) ? $section['claims'] : [])
                ->filter(fn ($claim) => is_array($claim))
                ->values();

            foreach ($fieldEntries as $entry) {
                $sourceId = (string) $entry['source_id'];
                $alreadyCovered = $claims->contains(fn (array $claim) => ($claim['status'] ?? null) === 'supported'
                    && in_array($sourceId, Arr::wrap($claim['source_ids'] ?? []), true)
                );

                if (! $alreadyCovered) {
                    $claims->push([
                        'claim_id' => $this->claimId($entry),
                        'text' => $this->fallbackText($entry),
                        'status' => 'supported',
                        'source_ids' => [$sourceId],
                    ]);
                }
            }

            $requiredSourceIds = $fieldEntries->pluck('source_id')->map(fn ($id) => (string) $id);
            $paClaims = $claims->filter(fn (array $claim) => collect(Arr::wrap($claim['source_ids'] ?? []))->intersect($requiredSourceIds)->isNotEmpty());
            $otherClaims = $claims->reject(fn (array $claim) => collect(Arr::wrap($claim['source_ids'] ?? []))->intersect($requiredSourceIds)->isNotEmpty());
            $claims = $otherClaims
                ->take(max(0, 100 - $paClaims->count()))
                ->concat($paClaims)
                ->unique('claim_id')
                ->values();

            // Mehrere Modellabschnitte für dasselbe Formularfeld würden sich
            // beim Übernehmen gegenseitig überschreiben. Deshalb immer genau
            // einen konsolidierten Abschnitt je Feld zurückgeben.
            $sections = $sections->reject(
                fn ($section) => is_array($section) && $this->fieldKey((string) ($section['heading'] ?? '')) === $fieldKey
            );
            $sections->push([
                'heading' => "[{$fieldKey}] ".$this->fieldLabel((string) $fieldKey, $fieldEntries->all()),
                'claims' => $claims->all(),
            ]);
        }

        $paSections = $sections->filter(fn (array $section) => $requiredFieldKeys->contains($this->fieldKey((string) ($section['heading'] ?? ''))));
        $otherSections = $sections->reject(fn (array $section) => $requiredFieldKeys->contains($this->fieldKey((string) ($section['heading'] ?? ''))));
        $report['sections'] = $otherSections
            ->take(max(0, 60 - $paSections->count()))
            ->concat($paSections)
            ->values()
            ->all();
        $report['warnings'] = collect(is_array($report['warnings'] ?? null) ? $report['warnings'] : [])
            ->push('Fachlich freigegebene Angaben aus der Potenzialanalyse wurden den passenden LuV-Feldern automatisch zugeordnet. Bitte den Gesamtentwurf weiterhin fachlich prüfen.')
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return $report;
    }

    /** @param array<string, mixed> $report */
    private function withoutInsufficientDataSections(array $report): array
    {
        $report['sections'] = collect(is_array($report['sections'] ?? null) ? $report['sections'] : [])
            ->filter(fn ($section) => is_array($section))
            ->map(function (array $section): array {
                $section['claims'] = collect(is_array($section['claims'] ?? null) ? $section['claims'] : [])
                    ->filter(fn ($claim) => is_array($claim) && ($claim['status'] ?? null) === 'supported')
                    ->unique(fn (array $claim) => Str::lower(trim((string) ($claim['text'] ?? ''))))
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => $section['claims'] !== [])
            ->take(60)
            ->values()
            ->all();

        return $report;
    }

    private function fieldKey(string $heading): ?string
    {
        return preg_match('/^\[([a-z][a-z0-9_.-]{0,119})\]/i', $heading, $match)
            ? $match[1]
            : null;
    }

    /** @param array<string, mixed> $entry */
    private function claimId(array $entry): string
    {
        $category = Str::slug((string) ($entry['category_key'] ?? $entry['category'] ?? 'support'), '-');

        return Str::limit('pa-'.$category.'-'.substr(hash('sha256', (string) $entry['source_id']), 0, 12), 80, '');
    }

    /** @param array<string, mixed> $entry */
    private function fallbackText(array $entry): string
    {
        $category = trim((string) ($entry['category'] ?? 'diesem Kompetenzbereich'));
        $observation = $this->sentence((string) ($entry['observation'] ?? ''));
        $supportNeed = $this->sentence((string) ($entry['support_need'] ?? ''));
        $sentences = [];

        if ($observation !== '') {
            $sentences[] = "Die Potenzialanalyse dokumentiert im Bereich {$category}: {$observation}";
        }

        if (($entry['decision'] ?? null) === 'no_support_need') {
            $sentences[] = 'Ein zusätzlicher Förderbedarf wurde fachlich nicht festgestellt.';
        } elseif ($supportNeed !== '') {
            $sentences[] = "Daraus wurde folgender Förderbedarf fachlich abgeleitet: {$supportNeed}";
        }

        return implode(' ', $sentences) ?: "Für den Bereich {$category} liegt eine fachlich freigegebene Angabe aus der Potenzialanalyse vor.";
    }

    private function sentence(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return rtrim($value, " \t\n\r\0\x0B.!?").'.';
    }

    /** @param list<array<string, mixed>> $entries */
    private function fieldLabel(string $fieldKey, array $entries): string
    {
        if ($fieldKey === 'support.description') {
            return 'Beschreibung des Unterstützungsbedarfs';
        }

        $category = trim((string) ($entries[0]['category'] ?? 'Kompetenz'));
        $suffix = str_ends_with($fieldKey, '.current_need') ? 'aktueller Förderbedarf' : 'Förderbedarf';

        return "{$category} – {$suffix}";
    }
}
