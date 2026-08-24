<?php

namespace App\Services;

use App\Models\PotenzialanalyseUebung;
use Illuminate\Validation\ValidationException;

class PotenzialanalyseResultCalculator
{
    public function calculate(PotenzialanalyseUebung $exercise, array $input): array
    {
        $rule = $exercise->berechnungsregel ?: 'direkte_punkte';
        $points = $this->nullableNumber($input['punkte'] ?? null);
        $errors = $this->nullableInteger($input['fehler'] ?? null);
        $calculated = null;

        if ($rule === 'fehler_abzug' && $errors !== null) {
            if ($exercise->hoechstwert === null) {
                throw ValidationException::withMessages([
                    'fehler' => "Für {$exercise->name} müssen zunächst Maximalpunkte konfiguriert werden.",
                ]);
            }

            $deduction = max(0, (float) ($exercise->fehler_abzug ?? 1));
            $minimum = (float) ($exercise->mindestwert ?? 0);
            $calculated = max($minimum, (float) $exercise->hoechstwert - ($errors * $deduction));
            $points = null;
        } elseif ($rule === 'direkte_punkte' && $points !== null) {
            $calculated = $points;
        } elseif ($rule === 'zeit') {
            $duration = $this->durationInSeconds($input);
            $thresholds = $exercise->berechnungs_config['zeitgrenzen'] ?? [];

            if ($duration > 0 && $this->hasCompleteTimeThresholds($thresholds)) {
                $calculated = match (true) {
                    $duration <= (int) $thresholds['stufe_5_bis'] => 5,
                    $duration <= (int) $thresholds['stufe_4_bis'] => 4,
                    $duration <= (int) $thresholds['stufe_3_bis'] => 3,
                    $duration <= (int) $thresholds['stufe_2_bis'] => 2,
                    default => 1,
                };
            }
        }

        if ($calculated !== null && $exercise->hoechstwert !== null) {
            $calculated = min((float) $exercise->hoechstwert, $calculated);
        }

        return [
            'punkte' => $points,
            'fehler' => $errors,
            'berechnete_punkte' => $calculated !== null ? round($calculated, 2) : null,
            'maximalpunkte_snapshot' => $exercise->hoechstwert !== null ? (float) $exercise->hoechstwert : null,
            'fehler_abzug_snapshot' => $rule === 'fehler_abzug' ? (float) ($exercise->fehler_abzug ?? 1) : null,
            'berechnungs_snapshot' => [
                'regel' => $rule,
                'mindestwert' => (float) ($exercise->mindestwert ?? 0),
                'hoechstwert' => $exercise->hoechstwert !== null ? (float) $exercise->hoechstwert : null,
                'fehler_abzug' => $rule === 'fehler_abzug' ? (float) ($exercise->fehler_abzug ?? 1) : null,
                'config' => $exercise->berechnungs_config,
            ],
        ];
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function durationInSeconds(array $input): int
    {
        if (array_key_exists('zeit', $input) && $input['zeit'] !== null && $input['zeit'] !== '') {
            return max(0, (int) $input['zeit']);
        }

        $minutes = max(0, (int) ($input['zeit_min'] ?? 0));
        $seconds = max(0, min(59, (int) ($input['zeit_sec'] ?? 0)));

        return ($minutes * 60) + $seconds;
    }

    private function hasCompleteTimeThresholds(mixed $thresholds): bool
    {
        return is_array($thresholds)
            && collect(['stufe_5_bis', 'stufe_4_bis', 'stufe_3_bis', 'stufe_2_bis'])
                ->every(fn (string $key) => isset($thresholds[$key]) && is_numeric($thresholds[$key]));
    }
}
