<?php

namespace App\Services\Aptitude;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AptitudeScoring
{
    public function validateDefinition(array $definition): array
    {
        Validator::make($definition, [
            'sections' => 'required|array|min:1|max:20',
            'sections.*.key' => 'required|alpha_dash|distinct|max:60',
            'sections.*.label' => 'required|string|max:150',
            'sections.*.category' => 'required|in:school,personal,methodical,social,technical',
            'sections.*.threshold' => 'nullable|numeric|min:0|max:100',
            'sections.*.grades' => 'present|array|max:20',
            'sections.*.grades.*.min' => 'required|numeric|min:0|max:100',
            'sections.*.grades.*.label' => 'required|string|max:20',
            'sections.*.items' => 'required|array|min:1|max:100',
        ])->validate();
        foreach ($definition['sections'] as $section) {
            $keys = [];
            $this->validateItems($section['items'], $keys, 0);
            $mins = array_column($section['grades'], 'min');
            if (count($mins) !== count(array_unique($mins))) $this->fail('Notengrenzen dürfen nicht doppelt vorkommen.');
        }
        return $definition;
    }

    private function validateItems(array $items, array &$keys, int $depth): void
    {
        if ($depth > 2) $this->fail('Höchstens drei Aufgabenebenen sind möglich.');
        foreach ($items as $item) {
            Validator::make($item, [
                'key' => 'required|alpha_dash|max:60', 'label' => 'required|string|max:180',
                'max' => 'required|numeric|gt:0|max:10000',
                'children' => 'sometimes|array|min:1|max:50',
                'choices' => 'sometimes|array|min:1|max:20',
                'choices.*' => 'required|numeric|min:0|lte:max',
            ])->validate();
            if (in_array($item['key'], $keys, true)) $this->fail('Aufgabenschlüssel müssen innerhalb eines Testbereichs eindeutig sein.');
            $keys[] = $item['key'];
            if (isset($item['children'])) {
                if (isset($item['choices'])) $this->fail('Eine Aufgabe kann Unterkriterien oder Bewertungsstufen haben.');
                $this->validateItems($item['children'], $keys, $depth + 1);
                if (abs(array_sum(array_column($item['children'], 'max')) - $item['max']) > 0.001) $this->fail('Die Maximalpunkte der Unterkriterien müssen zur übergeordneten Aufgabe passen.');
            }
        }
    }

    public function calculate(array $definition, array $scores): array
    {
        $allowedSections = array_column($definition['sections'], 'key');
        if (array_diff(array_keys($scores), $allowedSections)) $this->fail('Unbekannter Testbereich.');
        $results = [];
        foreach ($definition['sections'] as $section) {
            $input = $scores[$section['key']] ?? [];
            if (! is_array($input)) $this->fail('Ungültige Punkteangaben.');
            $used = [];
            $items = [];
            foreach ($section['items'] as $item) $items[] = $this->item($item, $input, $used);
            if (array_diff(array_keys($input), $used)) $this->fail('Punkte dürfen nur für die vorhandenen Einzelkriterien eingetragen werden.');
            $complete = ! in_array(false, array_column($items, 'complete'), true);
            $max = array_sum(array_column($items, 'max'));
            $points = array_sum(array_column($items, 'points'));
            $percent = $complete ? round($points / $max * 100, 2) : null;
            $grade = $complete ? collect($section['grades'])->sortByDesc('min')->first(fn ($g) => $percent >= $g['min']) : null;
            $results[$section['key']] = [
                'label' => $section['label'], 'category' => $section['category'], 'items' => $items,
                'points' => $points, 'max' => $max, 'complete' => $complete,
                'percent' => $percent, 'grade' => $grade['label'] ?? null,
                'below_threshold' => $percent !== null && isset($section['threshold']) ? $percent < $section['threshold'] : null,
            ];
        }
        return $results;
    }

    private function item(array $item, array $input, array &$used): array
    {
        if (isset($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) $children[] = $this->item($child, $input, $used);
            return ['key' => $item['key'], 'label' => $item['label'], 'max' => $item['max'], 'points' => array_sum(array_column($children, 'points')), 'complete' => ! in_array(false, array_column($children, 'complete'), true), 'children' => $children];
        }
        $used[] = $item['key'];
        $value = $input[$item['key']] ?? null;
        if ($value === '') $value = null;
        if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value) || $value < 0 || $value > $item['max'])) $this->fail('Ungültige Punktzahl für '.$item['label'].'.');
        if ($value !== null && isset($item['choices']) && ! in_array((float) $value, array_map('floatval', $item['choices']), true)) $this->fail('Bitte eine Bewertungsstufe für '.$item['label'].' auswählen.');
        return ['key' => $item['key'], 'label' => $item['label'], 'max' => $item['max'], 'points' => $value === null ? null : (float) $value, 'complete' => $value !== null];
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['scores' => $message]); }
}
