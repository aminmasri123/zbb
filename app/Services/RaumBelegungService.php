<?php

namespace App\Services;

use App\Models\Gruppe;
use App\Models\RaumBuchung;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class RaumBelegungService
{
    public function assertAvailable(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreBuchungId = null,
        ?int $ignoreGruppeId = null
    ): void {
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_at' => 'Das Ende muss nach dem Beginn liegen.',
            ]);
        }

        $buchung = $this->conflictingBuchung($raumId, $start, $end, $ignoreBuchungId);

        if ($buchung) {
            throw ValidationException::withMessages([
                'raum_id' => 'Der Raum ist in diesem Zeitraum bereits durch "' . $buchung->titel . '" belegt.',
            ]);
        }

        $gruppe = $this->conflictingGruppe($raumId, $start, $end, $ignoreGruppeId);

        if ($gruppe) {
            $label = $gruppe->bereich?->name ?? ('Gruppe #' . $gruppe->id);

            throw ValidationException::withMessages([
                'raum_id' => 'Der Raum ist in diesem Zeitraum bereits durch "' . $label . '" belegt.',
            ]);
        }
    }

    private function conflictingBuchung(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreBuchungId
    ): ?RaumBuchung {
        return RaumBuchung::query()
            ->where('raum_id', $raumId)
            ->whereIn('status', ['reserviert', 'bestaetigt'])
            ->when($ignoreBuchungId, fn ($query) => $query->whereKeyNot($ignoreBuchungId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->first();
    }

    private function conflictingGruppe(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreGruppeId
    ): ?Gruppe {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        return Gruppe::query()
            ->with('bereich')
            ->where('raum_id', $raumId)
            ->where('ort_typ', 'raum')
            ->whereNotNull('anfangsdatum')
            ->whereNotNull('startzeit')
            ->whereNotNull('endzeit')
            ->when($ignoreGruppeId, fn ($query) => $query->whereKeyNot($ignoreGruppeId))
            ->whereDate('anfangsdatum', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereDate('enddatum', '>=', $startDate)
                    ->orWhere(function ($query) use ($startDate) {
                        $query->whereNull('enddatum')
                            ->whereDate('anfangsdatum', '>=', $startDate);
                    });
            })
            ->when($startDate === $endDate, function ($query) use ($startTime, $endTime) {
                $query->where('startzeit', '<', $endTime)
                    ->where('endzeit', '>', $startTime);
            })
            ->orderBy('anfangsdatum')
            ->orderBy('startzeit')
            ->first();
    }
}
